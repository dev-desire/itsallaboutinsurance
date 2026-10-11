<?php
// Managed IAAI module; canonical code lives in the versioned iaai-portal support package.
add_action('plugins_loaded', static function () {
    if (!defined('IAAI_RUNTIME_MODE') || IAAI_RUNTIME_MODE !== 'snippets') { return; }
    if (!class_exists('IAAI\\Portal\\Snippets')) { return; }
    \IAAI\Portal\Snippets::register('ElementorWidget', '0.1.0', '327a60c8d6075be65595cfec2bd8a8fc97e1b6258e5a8fe92d267e82628e9aa0');
}, 20);
