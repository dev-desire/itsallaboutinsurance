#!/usr/bin/env python3
import os,json,urllib.request,hashlib,zipfile,io,shutil,base64,secrets
from pathlib import Path
root=Path(__file__).resolve().parents[1];target=root/'.test-runtime';target.mkdir(exist_ok=True)
for name,item in json.loads((root/'dependencies.lock.json').read_text()).items():
 data=urllib.request.urlopen(item['url'],timeout=60).read()
 if hashlib.sha256(data).hexdigest()!=item['sha256']:raise SystemExit('Dependency checksum mismatch: '+name)
 dest=target if name=='wordpress' else target/'wordpress/wp-content/plugins'
 with zipfile.ZipFile(io.BytesIO(data))as z:
  for p in z.namelist():
   if p.startswith('/') or '..' in Path(p).parts:raise SystemExit('Unsafe dependency archive')
  z.extractall(dest)
wp=target/'wordpress';private=target/'private';private.mkdir(exist_ok=True);private.chmod(0o700)
for src in (root/'wp-content').rglob('iaai-*'):
 if src.is_dir()and src.parent.name in ['plugins','themes']:
  dst=wp/'wp-content'/src.parent.name/src.name
  if dst.exists():shutil.rmtree(dst)
  shutil.copytree(src,dst)
def q(v):return "'"+str(v).replace('\\','\\\\').replace("'","\\'")+"'"
config='<?php\n'
values={'DB_NAME':os.environ.get('IAAI_TEST_DB_NAME','iaai_test'),'DB_USER':os.environ.get('IAAI_TEST_DB_USER','root'),'DB_PASSWORD':os.environ.get('IAAI_TEST_DB_PASSWORD','synthetic-ci-password'),'DB_HOST':os.environ.get('IAAI_TEST_DB_HOST','127.0.0.1:3306'),'DB_CHARSET':'utf8mb4','DB_COLLATE':'','WP_ENVIRONMENT_TYPE':'local','IAAI_ENCRYPTION_KEY':base64.b64encode(secrets.token_bytes(32)).decode(),'IAAI_PRIVATE_ROOT':str(private)}
for k in ['AUTH_KEY','SECURE_AUTH_KEY','LOGGED_IN_KEY','NONCE_KEY','AUTH_SALT','SECURE_AUTH_SALT','LOGGED_IN_SALT','NONCE_SALT']:values[k]=secrets.token_hex(32)
for k,v in values.items():config+=f'define({q(k)},{q(v)});\n'
config+="define('IAAI_DEV_MODE',true);define('IAAI_TEST_SITE',true);define('DISABLE_WP_CRON',true);define('WP_DEBUG',false);\nif(getenv('IAAI_TEST_RUNTIME')==='snippets'){define('IAAI_RUNTIME_MODE','snippets');}\n$table_prefix=getenv('IAAI_TEST_PREFIX')?:'wp_';\nif(!defined('ABSPATH'))define('ABSPATH',__DIR__.'/');require_once ABSPATH.'wp-settings.php';\n"
config=config.replace("define('DISABLE_WP_CRON',true);", "define('DISABLE_WP_CRON',true);define('WP_HTTP_BLOCK_EXTERNAL',true);")
(wp/'wp-config.php').write_text(config);(wp/'wp-config.php').chmod(0o600)
mu=wp/'wp-content/mu-plugins';mu.mkdir(exist_ok=True)
(mu/'iaai-synthetic-network.php').write_text("<?php\nif(defined('IAAI_TEST_SITE')&&IAAI_TEST_SITE){add_filter('pre_http_request',static function(){return new WP_Error('synthetic_network_blocked','Outgoing WordPress HTTP is disabled for synthetic tests.');},PHP_INT_MAX,3);}\n")
print('Pinned synthetic WordPress test environment prepared')
