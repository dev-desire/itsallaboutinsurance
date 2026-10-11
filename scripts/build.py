#!/usr/bin/env python3
"""Build deterministic WordPress artifacts from canonical source."""
from pathlib import Path
import hashlib, json, zipfile, shutil, subprocess, sys, os
ROOT = Path(__file__).resolve().parents[1]
PLUGIN = ROOT/'wp-content/plugins/iaai-portal'
VERSION = '0.1.0'

def digest(p): return hashlib.sha256(p.read_bytes()).hexdigest()
def write_json(path,value): path.write_text(json.dumps(value,ensure_ascii=False,indent=2)+'\n')
def zip_tree(directory,target,prefix):
    with zipfile.ZipFile(target,'w',zipfile.ZIP_DEFLATED,compresslevel=9) as z:
        for path in sorted(directory.rglob('*')):
            if not path.is_file(): continue
            info=zipfile.ZipInfo(prefix+'/'+path.relative_to(directory).as_posix(),date_time=(2026,10,8,0,0,0))
            info.compress_type=zipfile.ZIP_DEFLATED;info.external_attr=(0o644 << 16)
            z.writestr(info,path.read_bytes())

fixture=json.loads((ROOT/'tests/code-snippets-export-fixture.json').read_text())
if fixture['generator'] != 'Code Snippets v3.10.2': raise SystemExit('Unexpected snippet schema fixture')
modules={p.stem:{'file':'src/'+p.name,'sha256':digest(p),'version':VERSION} for p in sorted((PLUGIN/'src').glob('*.php'))}
manifest={'version':VERSION,'support_package':'iaai-portal','schema_version':2,'code_snippets_version':'3.10.2','modules':modules}
write_json(PLUGIN/'module-manifest.json',manifest);write_json(ROOT/'module-manifest.json',manifest)
for old in (ROOT/'snippets').glob('*'): old.unlink()
for index,(name,module) in enumerate(modules.items(),1):
    code="""// Managed IAAI module; canonical code lives in the versioned iaai-portal support package.
add_action('plugins_loaded', static function () {
    if (!defined('IAAI_RUNTIME_MODE') || IAAI_RUNTIME_MODE !== 'snippets') { return; }
    if (!class_exists('IAAI\\\\Portal\\\\Snippets')) { return; }
    \\IAAI\\Portal\\Snippets::register('%s', '%s', '%s');
}, 20);
"""%(name,VERSION,module['sha256'])
    item={'name':f'IAAI Module: {name}','desc':f'Managed module {name}, version {VERSION}. Requires matching IAAI support package and all declared modules.','code':code,'tags':['iaai-managed',name],'scope':'global','priority':10,'active':False}
    write_json(ROOT/'snippets'/f'{index:02d}-{name}.json',{'generator':fixture['generator'],'date_created':'2026-10-08 00:00','snippets':[item]})
    (ROOT/'snippets'/f'{index:02d}-{name}.php').write_text('<?php\n'+code)
bootstrap="add_action('plugins_loaded', static function () { if (class_exists('IAAI\\\\Portal\\\\Snippets')) { \\IAAI\\Portal\\Snippets::start(); } }, 99);\n"
write_json(ROOT/'snippets/99-Runtime.json',{'generator':fixture['generator'],'date_created':'2026-10-08 00:00','snippets':[{'name':'IAAI Runtime','desc':'Boot only after all verified managed modules are registered. Imports are inactive.','code':bootstrap,'scope':'global','tags':['iaai-managed'],'priority':10,'active':False}]})
(ROOT/'snippets/99-Runtime.php').write_text('<?php\n'+bootstrap)
# Preserve the native exporter scaffold while synchronizing its custom import
# runner payload. Otherwise the bundled kit silently imports obsolete layouts.
kit_path = ROOT/'elementor/iaai-website-kit.zip'
if kit_path.exists():
    with zipfile.ZipFile(kit_path) as archive:
        kit_files = {name: archive.read(name) for name in archive.namelist() if not name.startswith('iaai/')}
    blueprint = json.loads((ROOT/'elementor/manifest.json').read_text())
    native_manifest = json.loads(kit_files['manifest.json'])
    native_manifest['iaai-blueprint'] = {'schema': 1, 'version': VERSION, 'templates': len(blueprint['templates'])}
    kit_files['manifest.json'] = json.dumps(native_manifest, ensure_ascii=False).encode()
    kit_files['iaai/manifest.json'] = (ROOT/'elementor/manifest.json').read_bytes()
    for template in blueprint['templates']:
        kit_files['iaai/' + template['file']] = (ROOT/'elementor'/template['file']).read_bytes()
    with zipfile.ZipFile(kit_path, 'w', zipfile.ZIP_DEFLATED, compresslevel=9) as archive:
        for name, content in sorted(kit_files.items()):
            info = zipfile.ZipInfo(name, date_time=(2026,10,8,0,0,0))
            info.compress_type = zipfile.ZIP_DEFLATED
            info.external_attr = (0o644 << 16)
            archive.writestr(info, content)
for source,dest in [(ROOT/'elementor',PLUGIN/'templates'),(ROOT/'snippets',PLUGIN/'snippets')]:
    if dest.exists(): shutil.rmtree(dest)
    shutil.copytree(source,dest,ignore=shutil.ignore_patterns('*.zip'))
release=ROOT/'dist';release.mkdir(exist_ok=True)
zip_tree(PLUGIN,release/'iaai-portal.zip','iaai-portal')
zip_tree(ROOT/'wp-content/themes/iaai-base',release/'iaai-base.zip','iaai-base')
checks={p.name:digest(p) for p in sorted(release.glob('*.zip'))}
write_json(release/'checksums.json',checks)
print(f'Built plugin, theme and {len(modules)+1} inactive module snippets')
