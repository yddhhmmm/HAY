"""Integration tests, standard library only. PHP_BIN must point to PHP 8.1+."""
import concurrent.futures
import http.cookiejar
import json
import os
import queue
import re
from pathlib import Path
import socket
import subprocess
import tempfile
import threading
import time
import unittest
import urllib.error
import urllib.request
import urllib.parse
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer

ROOT = Path(__file__).resolve().parents[1]
PHP = os.environ.get('PHP_BIN', 'php')

def free_port():
    with socket.socket() as sock:
        sock.bind(('127.0.0.1', 0))
        return sock.getsockname()[1]

class DashboardTest(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.temp = tempfile.TemporaryDirectory(prefix='hay-test-')
        cls.env = dict(os.environ, HAY_STORAGE=cls.temp.name)
        cls.password = 'Integration-test-password-42'
        cls.run_php('bin/admin.php', 'init', 'admin', input=cls.password)
        cls.port = free_port()
        cls.log = open(Path(cls.temp.name)/'server.log','w')
        cls.server = subprocess.Popen([PHP,'-n','-d','session.save_path='+cls.temp.name,'-S',f'127.0.0.1:{cls.port}','-t',str(ROOT/'public')],env=cls.env,stdout=cls.log,stderr=cls.log)
        cls.base=f'http://127.0.0.1:{cls.port}/'
        for _ in range(100):
            try:
                urllib.request.urlopen(cls.base, timeout=.5).close()
                break
            except (OSError, urllib.error.URLError):
                time.sleep(.05)
        else:
            raise RuntimeError('PHP server did not start')

    @classmethod
    def run_php(cls,*args,input=None):
        return subprocess.run([PHP,'-n',*args],cwd=ROOT,env=cls.env,text=True,input=input,capture_output=True,check=True)

    @classmethod
    def tearDownClass(cls):
        cls.server.terminate();cls.server.wait(timeout=5);cls.log.close();cls.temp.cleanup()

    def client(self):
        return urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))

    def request(self,client,action,body=None,token=None,raw=None):
        headers={}
        data=None
        if body is not None or raw is not None:
            data=raw if raw is not None else json.dumps(body).encode()
            headers={'Content-Type':'application/json','X-CSRF-Token':token or ''}
        req=urllib.request.Request(self.base+'api.php?action='+action,data=data,headers=headers)
        try: response=client.open(req)
        except urllib.error.HTTPError as e: response=e
        return response.status,json.load(response)

    def login(self):
        client=self.client();code,s=self.request(client,'session')
        self.assertEqual(code,200)
        code,_=self.request(client,'login',{'username':'admin','password':self.password},s['csrf'])
        self.assertEqual(code,200)
        _,s=self.request(client,'session')
        return client,s['csrf']

    def test_01_authentication_and_security_headers(self):
        c=self.client()
        self.assertEqual(self.request(c,'snapshot')[0],401)
        self.assertEqual(self.request(c,'save',{})[0],403)
        with c.open(self.base) as response:
            self.assertIn("default-src 'self'",response.headers['Content-Security-Policy'])
            self.assertEqual(response.headers['X-Frame-Options'],'DENY')
        with self.client().open(self.base) as response:
            self.assertIn('HttpOnly',response.headers.get('Set-Cookie',''))
            self.assertIn('SameSite=Strict',response.headers.get('Set-Cookie',''))
        with c.open(self.base+'../src/bootstrap.php') as response:
            self.assertIn('<!doctype html>',response.read().decode())
        c,token=self.login()
        self.assertEqual(self.request(c,'save',{},'wrong-token')[0],403)
        self.assertEqual(self.request(c,'logout',{},token)[0],200)
        self.assertEqual(self.request(c,'snapshot')[0],401)

    def test_02_crud_and_validation(self):
        c,t=self.login()
        body={'name':'API <script>alert(1)</script>','port':free_port(),'type':'http','path':'/health?mode=ok','expected':200,'enabled':True}
        self.assertEqual(self.request(c,'save',body,t)[0],200)
        _,data=self.request(c,'snapshot')
        service=next(x for x in data['services'] if x['port']==body['port'])
        self.assertEqual(service['name'],body['name'])
        self.assertEqual(self.request(c,'save',body,t)[0],400)
        for update in [{'port':0},{'port':65536},{'path':'http://example.com/'},{'path':'//example.com/'},{'path':'/health\r\nHost: example.com'},{'path':'/../admin'},{'type':'shell'},{'expected':100}]:
            invalid=dict(body,**update);invalid['port']=update.get('port',free_port())
            self.assertEqual(self.request(c,'save',invalid,t)[0],400,update)
        service.update(name='Updated',enabled=False)
        self.assertEqual(self.request(c,'save',service,t)[0],200)
        _,data=self.request(c,'snapshot');self.assertFalse(next(x for x in data['services'] if x['id']==service['id'])['enabled'])
        self.assertEqual(self.request(c,'delete',{'id':service['id']},t)[0],200)
        backup=json.loads((Path(self.temp.name)/'services.json.bak').read_text())
        self.assertEqual(next(x for x in backup if x['id']==service['id'])['name'],'Updated')
        self.assertEqual(self.request(c,'save',None,t,raw=b'{bad')[0],400)
        self.assertEqual(self.request(c,'save',None,t,raw=b' '*9000)[0],413)

    def test_03_collector_and_config_freshness(self):
        self.run_php('bin/collector.php')
        c,t=self.login();_,data=self.request(c,'snapshot')
        self.assertFalse(data['stale'])
        self.assertIsNotNone(data['snapshot'])
        if os.name!='nt':
            self.assertEqual(data['snapshot']['system']['status'],'unavailable')
            self.assertNotIn('cpu',data['snapshot']['system'])
        self.assertGreater(len(data['snapshot']['history']),0)
        service=data['services'][0];service['name']='Changed after collection'
        self.assertEqual(self.request(c,'save',service,t)[0],200)
        _,data=self.request(c,'snapshot');self.assertNotIn(service['id'],data['snapshot']['services'])
        self.assertEqual(self.request(c,'check',{},t)[0],200)
        self.assertEqual(self.request(c,'check',{},t)[0],429)
        old=json.loads((Path(self.temp.name)/'snapshot.json').read_text());old['timestamp']=time.time()-100
        (Path(self.temp.name)/'snapshot.json').write_text(json.dumps(old))
        _,data=self.request(c,'snapshot');self.assertTrue(data['stale'])

    def test_04_probes_and_redirect_safety(self):
        hits=[]
        class Handler(BaseHTTPRequestHandler):
            def do_GET(self):
                hits.append(self.path)
                if self.path=='/redirect':self.send_response(302);self.send_header('Location','http://127.0.0.1:1/forbidden')
                else:self.send_response(200)
                self.end_headers();self.wfile.write(b'OK')
            def log_message(self,*args):pass
        server=ThreadingHTTPServer(('127.0.0.1',0),Handler)
        thread=threading.Thread(target=server.serve_forever,daemon=True);thread.start()
        try:
            code=f"require 'src/metrics.php'; echo json_encode([localHttp({server.server_port},'/health'),localHttp({server.server_port},'/redirect'),tcpProbe({server.server_port}),tcpProbe({free_port()})]);"
            rows=json.loads(self.run_php('-r',code).stdout)
            self.assertEqual(rows[0]['code'],200);self.assertEqual(rows[1]['code'],302)
            self.assertEqual(rows[2]['status'],'healthy');self.assertEqual(rows[3]['status'],'unreachable')
            self.assertEqual(hits,['/health','/redirect'])
        finally:server.shutdown();server.server_close()

    def test_05_atomic_concurrent_writes_and_corruption(self):
        code="require 'src/bootstrap.php'; for($i=0;$i<15;$i++)mutateJson('counter',fn($v)=>$v+1,0);"
        with concurrent.futures.ThreadPoolExecutor(max_workers=5) as pool:
            list(pool.map(lambda _:self.run_php('-r',code),range(5)))
        self.assertEqual(json.loads((Path(self.temp.name)/'counter.json').read_text()),75)
        file=Path(self.temp.name)/'services.json';original=file.read_text();file.write_text('{broken')
        try:
            c,t=self.login();self.assertEqual(self.request(c,'save',{'name':'Test','port':1234,'type':'tcp'},t)[0],400)
            self.assertEqual(file.read_text(),'{broken')
        finally:file.write_text(original)

    def test_06_rate_limit(self):
        c=self.client();_,s=self.request(c,'session')
        for _ in range(5):self.assertEqual(self.request(c,'login',{'username':'admin','password':'wrong'},s['csrf'])[0],401)
        self.assertEqual(self.request(c,'login',{'username':'admin','password':self.password},s['csrf'])[0],429)
        self.run_php('bin/admin.php','reset-password','admin',input=self.password)

    def test_07_viewer_and_password_reset(self):
        c,t=self.login()
        self.run_php('bin/admin.php','set-viewer','viewer',input='Viewer-password-test-42')
        self.assertEqual(self.request(c,'snapshot')[0],401)
        c=self.client();_,s=self.request(c,'session')
        self.assertEqual(self.request(c,'login',{'username':'viewer','password':'Viewer-password-test-42'},s['csrf'])[0],200)
        _,s=self.request(c,'session');self.assertEqual(s['role'],'viewer')
        self.assertEqual(self.request(c,'snapshot')[0],200)
        self.assertEqual(self.request(c,'save',{},s['csrf'])[0],403)
        self.assertEqual(self.request(c,'audit')[0],403)
        self.run_php('bin/admin.php','reset-password','admin',input=self.password)
        self.assertEqual(self.request(c,'snapshot')[0],401)

    def test_08_idle_session_and_private_storage(self):
        c,t=self.login()
        jar=next(h.cookiejar for h in c.handlers if isinstance(h,urllib.request.HTTPCookieProcessor))
        sid=next(cookie.value for cookie in jar if cookie.name=='HAYSESSID')
        code="ini_set('session.save_path',getenv('HAY_STORAGE')); session_name('HAYSESSID');session_id('"+sid+"');session_start();$_SESSION['last']=time()-1801;session_write_close();"
        self.run_php('-r',code)
        self.assertEqual(self.request(c,'snapshot')[0],401)
        check="require 'src/bootstrap.php';$_SERVER['DOCUMENT_ROOT']=dirname(getenv('HAY_STORAGE'));storage();"
        result=subprocess.run([PHP,'-n','-r',check],cwd=ROOT,env=self.env,text=True,capture_output=True)
        self.assertNotEqual(result.returncode,0)
        self.assertIn('di luar document root',result.stdout+result.stderr)

    def test_09_xampp_storage_without_apache_configuration(self):
        # Reproduksi struktur XAMPP tanpa mengubah konfigurasi Apache atau environment.
        base=Path(self.temp.name)/'portable-xampp'
        project=base/'htdocs'/'tools'/'HAY'
        (project/'src').mkdir(parents=True)
        (project/'src'/'bootstrap.php').write_text((ROOT/'src'/'bootstrap.php').read_text())
        settings=project/'src'/'settings.php'
        settings.write_text("<?php return ['storage'=>null];")
        env=dict(self.env);env.pop('HAY_STORAGE',None)
        script="require "+json.dumps(str(project/'src'/'bootstrap.php'))+";"
        def run(extra):
            r=subprocess.run([PHP,'-n','-r',script+extra],env=env,text=True,capture_output=True,check=True)
            return r.stdout.strip()
        expected=str(base/'hay-private')
        self.assertEqual(run('echo storageLocation();'),expected)
        self.assertFalse((base/'hay-private').exists(),'Path discovery must not create storage before ACL setup')
        document_root=json.dumps(str(base/'htdocs'))
        self.assertEqual(run("$_SERVER['DOCUMENT_ROOT']="+document_root+";echo storage();"),expected)
        self.assertEqual(run('echo storage();'),expected)
        custom=str(base/'custom-private')
        settings.write_text("<?php return ['storage'=>"+json.dumps(custom)+"];")
        self.assertEqual(run('echo storageLocation();'),custom)
        self.assertEqual(run("$_SERVER['DOCUMENT_ROOT']="+document_root+";echo storage();"),custom)
        self.assertEqual(run("echo defaultStorageLocation('D:/xampp/HTDOCS/tools/HAY');"),'D:/xampp/hay-private')

    def test_10_cmd_setup_wizard_local_token_and_shutdown(self):
        with tempfile.TemporaryDirectory(prefix='hay-wizard-') as temp:
            private=str(Path(temp)/'private')
            env=dict(os.environ,HAY_STORAGE=private)
            p=subprocess.Popen([PHP,'-n',str(ROOT/'bin/setup-console.php')],env=env,stdout=subprocess.PIPE,stderr=subprocess.STDOUT,text=True)
            lines=queue.Queue()
            def reader():
                for line in p.stdout:lines.put(line)
            threading.Thread(target=reader,daemon=True).start()
            try:
                output=[];url=None
                for _ in range(20):
                    line=lines.get(timeout=10);output.append(line)
                    if line.startswith('http://'):url=line.strip();break
                    if line.startswith('ERROR:'):self.fail(''.join(output))
                self.assertIsNotNone(url)
                origin=urllib.parse.urlsplit(url);origin=origin.scheme+'://'+origin.netloc
                def request(url,body=None,origin_header=None):
                    headers={};data=None
                    if body is not None:
                        data=urllib.parse.urlencode(body).encode();headers={'Content-Type':'application/x-www-form-urlencoded','Origin':origin_header or origin}
                    try:r=urllib.request.urlopen(urllib.request.Request(url,data=data,headers=headers),timeout=5)
                    except urllib.error.HTTPError as e:r=e
                    return r.status,r.read().decode()
                self.assertEqual(request(origin+'/?token=wrong')[0],403)
                code,html=request(url);self.assertEqual(code,200);self.assertIn('type="password"',html)
                body={'username':'wizardadmin','password':'Wizard-test-password-42','confirm':'Wizard-test-password-42'}
                self.assertEqual(request(url,body,'http://evil.example')[0],403)
                wrong=dict(body,confirm='different')
                self.assertIn('tidak sama',request(url,wrong)[1]);self.assertFalse((Path(private)/'auth.json').exists())
                code,html=request(url,body);self.assertEqual(code,200);self.assertIn('Akun berhasil disimpan',html)
                auth=json.loads((Path(private)/'auth.json').read_text());self.assertEqual(auth['username'],'wizardadmin');self.assertNotIn(body['password'],json.dumps(auth))
                self.assertEqual(request(url)[0],410)
                self.assertEqual(p.wait(timeout=10),0)
                with self.assertRaises(urllib.error.URLError):urllib.request.urlopen(url,timeout=2)
                self.assertFalse(list(Path(private).glob('.setup-*')))
            finally:
                if p.poll() is None:p.terminate();p.wait(timeout=5)
                p.stdout.close()

if __name__=='__main__':unittest.main(verbosity=2)
