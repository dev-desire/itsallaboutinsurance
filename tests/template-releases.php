<?php
/** Targeted release rollback; never restore a whole WordPress database. */
use IAAI\Portal\{Core,Templates};
if (PHP_SAPI!=='cli'||!getenv('IAAI_TEST_WP_PATH'))exit(1);
$_SERVER['HTTP_HOST']='localhost:8090';$_SERVER['REQUEST_URI']='/';require getenv('IAAI_TEST_WP_PATH').'/wp-load.php';
if(!defined('IAAI_TEST_SITE')||!IAAI_TEST_SITE||!Core::dev())exit(1);
global $wpdb;wp_set_current_user(1);$root=dirname(__DIR__);Templates::import($root.'/elementor','baseline');
$manifest=json_decode(file_get_contents($root.'/elementor/manifest.json'),true);$items=[];
foreach($manifest['templates']as$item)if($item['locale']==='en'&&in_array($item['key'],['about','contact'],true))$items[]=$item;
$path=sys_get_temp_dir().'/iaai-release-'.bin2hex(random_bytes(6));mkdir($path,0700);
foreach($items as$item){$dir=dirname($path.'/'.$item['file']);if(!is_dir($dir))mkdir($dir,0700,true);}
$ids=[];$before=[];
foreach($items as &$item){$ids[$item['key']]=(int)$wpdb->get_var($wpdb->prepare('SELECT post_id FROM '.Core::table('managed_templates').' WHERE deployment_key=%s AND locale=%s',$item['key'],'en'));$before[$item['key']]=Templates::hash($ids[$item['key']]);$data=json_decode(file_get_contents($root.'/elementor/'.$item['file']),true);$data['title'].=' synthetic release';$item['hash']=hash('sha256',wp_json_encode($data));$item['parent']=null;file_put_contents($path.'/'.$item['file'],wp_json_encode($data));}unset($item);
wp_update_post(['ID'=>$ids['about'],'post_title'=>'Owner manual edit']);$manual=Templates::hash($ids['about']);
$new=$items[0];$new['key']='synthetic-release-page';$new['slug']='synthetic-release-page';$new['file']='new.json';$new['hash']=hash('sha256','synthetic new');$new['parent']=null;$items[]=$new;copy($path.'/'.$items[0]['file'],$path.'/new.json');
file_put_contents($path.'/manifest.json',wp_json_encode(['templates'=>$items]));
update_option('iaai_synthetic_live_record','must survive rollback');
$result=Templates::import($path,'synthetic-release-one');
if(!in_array('about/en',$result['conflicts'],true)||Templates::hash($ids['about'])!==$manual||!in_array('contact/en',$result['updated'],true))throw new RuntimeException('Manual edit reconciliation failed');
$newId=(int)$wpdb->get_var("SELECT post_id FROM ".Core::table('managed_templates')." WHERE deployment_key='synthetic-release-page'");
$rollback=Templates::rollback('synthetic-release-one');
if($rollback['conflicts']||Templates::hash($ids['contact'])!==$before['contact']||get_post($newId)||Templates::hash($ids['about'])!==$manual||get_option('iaai_synthetic_live_record')!=='must survive rollback')throw new RuntimeException('Targeted rollback did not preserve live/manual data');
Templates::import($path,'synthetic-release-two');wp_update_post(['ID'=>$ids['contact'],'post_title'=>'Owner edit after deployment']);$later=Templates::hash($ids['contact']);$rollback=Templates::rollback('synthetic-release-two');
if(!in_array($ids['contact'],$rollback['conflicts'],true)||Templates::hash($ids['contact'])!==$later)throw new RuntimeException('Rollback overwrote a later manual edit');
foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST)as$f){$f->isDir()?rmdir($f->getPathname()):unlink($f->getPathname());}rmdir($path);
echo "Template releases: manual conflicts, targeted restore, new-page cleanup and subsequent-edit protection passed\n";
