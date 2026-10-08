<?php
/** Export only synthetic blueprint pages through Elementor's native exporter. */
if(PHP_SAPI!=='cli'||!getenv('IAAI_TEST_WP_PATH'))exit(1);
try {
$_SERVER['HTTP_HOST']='localhost:8090';$_SERVER['REQUEST_URI']='/';require getenv('IAAI_TEST_WP_PATH').'/wp-load.php';
if(!defined('IAAI_TEST_SITE')||!IAAI_TEST_SITE||!IAAI\Portal\Core::dev())exit(1);
wp_set_current_user(1);$root=dirname(__DIR__);$manifest=json_decode(file_get_contents($root.'/elementor/manifest.json'),true);
$module=Elementor\Plugin::$instance->app->get_component('import-export');
if(!$module){$module=new Elementor\App\Modules\ImportExport\Module();}
$export=$module->export_kit(['include'=>['content','templates','site-settings'],'plugins'=>[],'kitInfo'=>['title'=>"It's All About Insurance",'description'=>'Native pages plus editable draft layouts. Install matching IAAI plugin and reconcile the deployment manifest; draft layouts remain unpublished pages.']]);
rename($export['file_name'],$root.'/elementor/iaai-website-kit.zip');
foreach(get_posts(['post_type'=>'elementor_library','posts_per_page'=>-1,'meta_key'=>'_iaai_kit_draft','meta_value'=>1])as$post){wp_delete_post($post->ID,true);}
echo "Native Elementor website kit exported\n";

} catch (Throwable $e) { echo get_class($e) . ': ' . $e->getMessage() . "\n"; exit(1); }
