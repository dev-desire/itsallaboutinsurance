<?php
// Managed IAAI module; canonical code lives in the versioned iaai-portal support package.
add_action('plugins_loaded', static function () {
    if (!defined('IAAI_RUNTIME_MODE') || IAAI_RUNTIME_MODE !== 'snippets') { return; }
    if (!class_exists('IAAI\\Portal\\Snippets')) { return; }
    \IAAI\Portal\Snippets::register('Records', '0.1.0', 'e1a154c2a7ce8f1ec8e0ac39c6fa62f4be94d0a19f4c5682b25662e1b184b66f');
}, 20);
