<?php
if(PHP_SAPI!=='cli'||!getenv('IAAI_TEST_WP_PATH'))exit(1);
define('WP_INSTALLING',true);$_SERVER['HTTP_HOST']='localhost:8090';$_SERVER['REQUEST_URI']='/';require getenv('IAAI_TEST_WP_PATH').'/wp-load.php';
if(!defined('IAAI_TEST_SITE')||!IAAI_TEST_SITE||wp_get_environment_type()!=='local')exit(1);
require_once ABSPATH.'wp-admin/includes/upgrade.php';require_once ABSPATH.'wp-admin/includes/plugin.php';
if(!is_blog_installed())wp_install('IAAI synthetic installation','fixture_owner','test@example.invalid',false,'',bin2hex(random_bytes(16)));
update_option('siteurl','http://localhost:8090');update_option('home','http://localhost:8090');update_option('permalink_structure','/%postname%/');
foreach(['elementor/elementor.php','two-factor/two-factor.php','code-snippets/code-snippets.php','iaai-portal/iaai-portal.php']as$p){$r=activate_plugin($p);if(is_wp_error($r))throw new RuntimeException($r->get_error_code());}
switch_theme('iaai-base');echo "Isolated WordPress dependencies activated\n";
