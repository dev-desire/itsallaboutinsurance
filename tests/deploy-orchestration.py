#!/usr/bin/env python3
"""Run real release shell script with command doubles, not an SSH/live-host test."""
from pathlib import Path
import tempfile,subprocess,os,json,tarfile,shlex
root=Path(__file__).resolve().parents[1]
with tempfile.TemporaryDirectory(prefix='iaai-deploy-') as directory:
    base=Path(directory);commands=base/'bin';commands.mkdir()
    wp_script='''#!/usr/bin/env python3
import sys,os,json
from pathlib import Path
p=Path(os.environ['MOCK_STATE']);s=json.loads(p.read_text());a=[x for x in sys.argv[1:] if not x.startswith('--path=')]
if a[:3]==['option','get','home']:print('https://synthetic.invalid')
elif a[:3]==['option','get','iaai_release']:print(s['release'])
elif a[:3]==['option','update','iaai_release']:s['release']=a[3]
elif a and a[0]=='eval':print('plugin')
elif a[:2]==['iaai','install-pages']:
 s['release']=a[2].split('=',1)[1];p.write_text(json.dumps(s))
 if os.environ.get('MOCK_FAIL')=='templates':sys.exit(11)
elif a[:2]==['iaai','rollback-templates']:s['rollback_called']=True
elif a[:2]==['maintenance-mode','activate']:s['maintenance']=True
elif a[:2]==['maintenance-mode','deactivate']:s['maintenance']=False
p.write_text(json.dumps(s))
'''
    curl_script='''#!/usr/bin/env python3
import sys,os,json
from pathlib import Path
a=sys.argv[1:];s=json.loads(Path(os.environ['MOCK_STATE']).read_text());url=next((x for x in a if x.startswith('https://')),'')
if os.environ.get('MOCK_FAIL')=='http' and url.endswith('/hi/'):sys.exit(22)
if url.endswith('/health'):print(json.dumps({'release':s['release']}))
if url.endswith('/dashboard'):
 Path(a[a.index('-D')+1]).write_text('HTTP/1.1 401 Unauthorized\\nCache-Control: private, no-store\\n');print('401',end='')
'''
    mv_script='''#!/usr/bin/env python3
import sys,os,subprocess
if os.environ.get('MOCK_FAIL')=='swap' and sys.argv[1].endswith('/code/iaai-base'):sys.exit(23)
sys.exit(subprocess.call(['/bin/mv']+sys.argv[1:]))
'''
    for name,source in [('wp',wp_script),('curl',curl_script),('mv',mv_script)]:
        p=commands/name;p.write_text(source);p.chmod(0o755)
    if os.environ.get('IAAI_TEST_PHP'):
        p=commands/'php';p.write_text('#!/bin/bash\nexec '+shlex.quote(os.environ['IAAI_TEST_PHP'])+' "$@"\n');p.chmod(0o755)
    for case in ['success','http','templates','swap']:
        site=base/case/'wordpress';releases=base/case/'releases';state=base/case/'state.json';state.parent.mkdir(parents=True,exist_ok=True)
        for kind,name in [('plugins','iaai-portal'),('themes','iaai-base')]:
            p=site/'wp-content'/kind/name;p.mkdir(parents=True);(p/'old-marker').write_text('previous '+name)
        untouched=[site/'wp-content/uploads/synthetic-record.bin',site/'wp-content/plugins/unmanaged/plugin.php',base/case/'private-report.bin',base/case/'synthetic-database.json']
        for p in untouched:p.parent.mkdir(parents=True,exist_ok=True);p.write_bytes(b'SYNTHETIC LIVE DATA MUST SURVIVE')
        state.write_text(json.dumps({'release':'previous-release','maintenance':False}));(releases/'incoming').mkdir(parents=True)
        release='a'*40
        with tarfile.open(releases/'incoming'/f'{release}.tar.gz','w:gz')as archive:
            for name in ['iaai-portal.zip','iaai-base.zip','checksums.json']:archive.add(root/'dist'/name,arcname=name)
        env=dict(os.environ,PATH=str(commands)+os.pathsep+os.environ['PATH'],MOCK_STATE=str(state),MOCK_FAIL=case)
        command=['bash',str(root/'scripts/host-release.sh'),str(site),str(releases),release,'https://synthetic.invalid']
        result=subprocess.run(command,env=env,capture_output=True,text=True,timeout=45);s=json.loads(state.read_text())
        assert all(p.read_bytes()==b'SYNTHETIC LIVE DATA MUST SURVIVE' for p in untouched),case
        assert not s['maintenance'],case
        if case=='success':
            assert result.returncode==0 and s['release']==release,(result.returncode,result.stderr,result.stdout)
            repeat=subprocess.run(command,env=env,capture_output=True,text=True,timeout=45);assert repeat.returncode==0,repeat.stderr
        else:
            assert result.returncode!=0 and s['release']=='previous-release' and s.get('rollback_called'),case
            for kind,name in [('plugins','iaai-portal'),('themes','iaai-base')]:assert (site/'wp-content'/kind/name/'old-marker').exists(),case
        print('PASS release orchestration '+case+'; unrelated data preserved')
print('Command-double release checks passed; real SSH, live cache and host rollback remain unverified')
