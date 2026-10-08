<?php
/** Real native Elementor import on a fresh, synthetic database prefix. */
if (PHP_SAPI !== 'cli' || !getenv('IAAI_TEST_WP_PATH')) exit(1);
$_SERVER['HTTP_HOST']='localhost:8090'; $_SERVER['REQUEST_URI']='/';
require getenv('IAAI_TEST_WP_PATH').'/wp-load.php';
if (!defined('IAAI_TEST_SITE') || !IAAI_TEST_SITE || !IAAI\Portal\Core::dev()) exit(1);
wp_set_current_user(1);
$root=dirname(__DIR__); $module=new Elementor\App\Modules\ImportExport\Module();
$result=$module->import_kit($root.'/elementor/iaai-website-kit.zip',['include'=>['content','templates','site-settings'],'selected_plugins'=>[]]);
$manifest=json_decode(file_get_contents($root.'/elementor/manifest.json'),true);
global $wpdb; $checks=0;
foreach($manifest['templates'] as $item) {
    $id=(int)$wpdb->get_var($wpdb->prepare('SELECT post_id FROM '.IAAI\Portal\Core::table('managed_templates').' WHERE deployment_key=%s AND locale=%s',$item['key'],$item['locale']));
    if (!$id || get_post_status($id)!==(!empty($item['draft'])?'draft':'publish') || !get_post_meta($id,'_elementor_data',true)) throw new RuntimeException('Kit page status/data mismatch: '.$item['key'].'/'.$item['locale']);
    if (!empty($item['private']) && !get_post_meta($id,'_iaai_private',true)) throw new RuntimeException('Private metadata missing');
    $checks++;
}
if ($checks!==53 || (int)$wpdb->get_var('SELECT COUNT(*) FROM '.IAAI\Portal\Core::table('managed_templates'))!==53) throw new RuntimeException('Kit duplicated or missed pages');
$before=(int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts}");$refused=false;
try {$module->import_kit($root.'/elementor/iaai-website-kit.zip',['include'=>['content','templates','site-settings']]);} catch(Throwable $e) {$refused=str_contains($e->getMessage(),'fresh installation');}
if (!$refused || $before!==(int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts}")) throw new RuntimeException('Repeat full kit import must refuse without mutation');
echo "Native Elementor kit: 53 editable templates imported; draft/private states and repeat-import protection verified\n";
