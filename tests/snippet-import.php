<?php
if(PHP_SAPI!=='cli'||!getenv('IAAI_TEST_WP_PATH'))exit(1);
$_SERVER['HTTP_HOST']='localhost:8090';$_SERVER['REQUEST_URI']='/';require getenv('IAAI_TEST_WP_PATH').'/wp-load.php';
if(!defined('IAAI_TEST_SITE')||!IAAI_TEST_SITE||!IAAI\Portal\Core::dev())exit(1);
wp_set_current_user(1);
foreach((array)get_option('iaai_test_snippet_ids',[]) as $id){Code_Snippets\delete_snippet($id);}
$controller=new Code_Snippets\REST_API\Import\File_Import_REST_Controller();
$parse=new ReflectionMethod($controller,'parse_json_file');$ids=[];
foreach(glob(dirname(__DIR__).'/snippets/*.json')as$file){
 $parsed=$parse->invoke($controller,$file,basename($file));if(is_wp_error($parsed))throw new RuntimeException('Snippet JSON parser rejected export');
 $r=new WP_REST_Request('POST','');$r->set_param('snippets',$parsed);$r->set_param('duplicate_action','replace');$r->set_param('network',false);
 $import=$controller->import_selected_snippets($r);if(is_wp_error($import))throw new RuntimeException('Snippet import failed');$ids=array_merge($ids,$import->get_data()['imported_ids']);
}
foreach($ids as$id){$s=Code_Snippets\get_snippet($id);if($s->active)throw new RuntimeException('Import unexpectedly activated code');}
echo count($ids)," native Code Snippets imports verified inactive\n";
foreach($ids as$id){Code_Snippets\activate_snippet($id);}
update_option('iaai_test_snippet_ids',$ids,false);
echo "Synthetic snippet runtime enabled for parity tests only\n";
