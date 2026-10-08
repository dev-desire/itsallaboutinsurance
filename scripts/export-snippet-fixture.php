<?php
if (PHP_SAPI !== 'cli' || !getenv('IAAI_TEST_WP_PATH')) { exit(1); }
$_SERVER['HTTP_HOST'] = 'localhost:8090'; $_SERVER['REQUEST_URI'] = '/';
require getenv('IAAI_TEST_WP_PATH') . '/wp-load.php';
if (!IAAI\Portal\Core::dev()) { exit(1); }
$snippet = new Code_Snippets\Model\Snippet(['name' => 'IAAI export schema fixture', 'code' => '// Synthetic inactive fixture', 'desc' => 'Generated through Code Snippets 3.10.2 Export_JSON.', 'tags' => ['iaai-fixture'], 'scope' => 'global', 'priority' => 10]);
$saved = Code_Snippets\save_snippet($snippet);
$result = (new Code_Snippets\Migration\Export\Export_JSON([$saved->id]))->generate_export();
file_put_contents(dirname(__DIR__) . '/tests/code-snippets-export-fixture.json', wp_json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
Code_Snippets\delete_snippet($saved->id);
echo "Official Code Snippets export fixture generated\n";
