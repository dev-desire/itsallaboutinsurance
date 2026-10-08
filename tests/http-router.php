<?php
// Local synthetic HTTP harness, never a production router.
if(!getenv('IAAI_TEST_WP_PATH'))exit(1);
$path=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);$root=getenv('IAAI_TEST_WP_PATH');
$file=realpath($root.$path);
if($file&&str_starts_with($file,realpath($root).DIRECTORY_SEPARATOR)&&is_file($file))return false;
require $root.'/index.php';
