<?php
if(PHP_SAPI!=='cli'||!getenv('IAAI_TEST_WP_PATH'))exit(1);
$_SERVER['HTTP_HOST']='localhost:8090';$_SERVER['REQUEST_URI']='/';require getenv('IAAI_TEST_WP_PATH').'/wp-load.php';
if(!defined('IAAI_TEST_SITE')||!IAAI_TEST_SITE||!IAAI\Portal\Core::dev())exit(1);
$unrelated=Code_Snippets\save_snippet(new Code_Snippets\Model\Snippet(['name'=>'Synthetic unrelated owner snippet','code'=>'// synthetic owner code','scope'=>'global','active'=>false]));
$first=IAAI\Portal\Snippets::sync(false);$map=get_option('iaai_snippet_map');
if($first['conflicts']||count($map)!==19)throw new RuntimeException('Managed native imports were not adopted');
$key=array_key_first($map);$managed=Code_Snippets\get_snippet($map[$key]['id']);$original=$managed->code;$managed->code.="\n// synthetic manual owner edit";Code_Snippets\save_snippet($managed);
$conflict=IAAI\Portal\Snippets::sync(false);
if(!in_array($key,$conflict['conflicts'],true)||Code_Snippets\get_snippet($managed->id)->code!==$managed->code||Code_Snippets\get_snippet($unrelated->id)->code!=='// synthetic owner code')throw new RuntimeException('Synchronizer overwrote manual or unrelated code');
$managed->code=$original;Code_Snippets\save_snippet($managed);$last=IAAI\Portal\Snippets::sync(true);
if($last['conflicts']||count(get_option('iaai_snippet_map'))!==19)throw new RuntimeException('Managed synchronization did not recover');
Code_Snippets\delete_snippet($unrelated->id);
echo "Snippet synchronization: adopts matching imports, preserves manual/unrelated code and recovers without duplicate IDs\n";
