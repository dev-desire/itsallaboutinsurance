#!/usr/bin/env python3
import sys,json,urllib.request
site=sys.argv[1].rstrip('/');release=sys.argv[2]
for path in ['/','/hi/','/account/','/wp-json/iaai/v1/health','/wp-json/iaai/v1/dashboard']:
 try:
  with urllib.request.urlopen(site+path,timeout=20)as r: code=r.status;body=r.read();headers=r.headers
 except urllib.error.HTTPError as e: code=e.code;body=e.read();headers=e.headers
 if path.endswith('dashboard'):
  assert code in [401,403], 'Anonymous dashboard was not denied'
  assert 'no-store' in headers.get('Cache-Control',''), 'Private response cache policy missing'
 elif path.endswith('health'):
  assert code==200 and json.loads(body)['release']==release,'Release health mismatch'
 else: assert code==200 and len(body)>300,'Public page unavailable'
print('Public routes, deployed release and anonymous denial smoke checks passed')
