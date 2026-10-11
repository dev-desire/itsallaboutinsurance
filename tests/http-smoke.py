#!/usr/bin/env python3
"""Real HTTP output checks. This does not replace responsive/browser testing."""
import urllib.request,urllib.error,json,os,subprocess,time,http.cookiejar
from pathlib import Path
root=Path(__file__).resolve().parents[1];wp=Path(os.environ['IAAI_TEST_WP_PATH'])
php=os.environ.get('IAAI_TEST_PHP','php');port=8090;site=f'http://localhost:{port}'
log=(root/'.test-http.log').open('w')
server=subprocess.Popen([php,'-S',f'127.0.0.1:{port}','-t',str(wp),str(root/'tests/http-router.php')],stdout=log,stderr=log)
opener=urllib.request.build_opener(urllib.request.ProxyHandler({}),urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
def get(path,method='GET',data=None,headers=None):
    req=urllib.request.Request(site+path,method=method,headers=headers or {},data=json.dumps(data).encode() if data is not None else None)
    try:r=opener.open(req,timeout=20)
    except urllib.error.HTTPError as e:r=e
    return r.code,r.headers,r.read()
try:
    for attempt in range(40):
        try:get('/wp-json/iaai/v1/health');break
        except urllib.error.URLError:time.sleep(.1)
    code,headers,body=get('/');print('Home render:', {'status':code,'glass':b'iaai-home-section' in body,'planner':b'data-iaai-term-planner' in body,'legacy':b'data-iaai-calculator' in body,'benefits':b'insurance-benefits' in body,'english':b'lang="en' in body},flush=True);assert code==200 and b'iaai-home-section' in body and b'data-iaai-term-planner' in body and b'data-iaai-calculator' not in body and b'insurance-benefits' in body and b'lang="en' in body
    code,headers,body=get('/hi/');assert code==200 and b'lang="hi' in body
    for path in ['/account/','/my-dashboard/','/my-reports/']:
        code,headers,body=get(path);assert code==200 and 'no-store' in headers.get('Cache-Control','') and 'noindex' in headers.get('X-Robots-Tag',''),path
    code,headers,body=get('/wp-json/iaai/v1/dashboard');assert code in [401,403] and 'no-store' in headers.get('Cache-Control','')
    code,headers,body=get('/wp-json/iaai/v1/reports/00000000-0000-0000-0000-000000000000/content');assert code in [401,403,404] and b'%PDF' not in body
    code,headers,body=get('/wp-json/iaai/v1/auth/context');context=json.loads(body);assert context['csrf'] and 'no-store' in headers.get('Cache-Control','')
    code,headers,body=get('/wp-json/iaai/v1/enquiries','POST',{'service':'health'}, {'Content-Type':'application/json','Origin':'https://attacker.invalid','X-IAAI-CSRF':context['csrf']});assert code==403
    print('Real HTTP: English/Hindi render, private no-store/noindex, anonymous file denial and cross-origin rejection passed')
finally:
    server.terminate();server.wait(timeout=10);log.close()
