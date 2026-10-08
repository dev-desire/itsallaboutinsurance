<?php
// Managed IAAI module; canonical code lives in the versioned iaai-portal support package.
add_action('plugins_loaded', static function () {
    if (!defined('IAAI_RUNTIME_MODE') || IAAI_RUNTIME_MODE !== 'snippets') { return; }
    if (!class_exists('IAAI\\Portal\\Snippets')) { return; }
    \IAAI\Portal\Snippets::register('ElementorWidget', '0.1.0', '8d326d5e957af493cb73d9ee11f43096e80c57d1205f1dd65e53499ccc45a206');
}, 20);
