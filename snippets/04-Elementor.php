<?php
// Managed IAAI module; canonical code lives in the versioned iaai-portal support package.
add_action('plugins_loaded', static function () {
    if (!defined('IAAI_RUNTIME_MODE') || IAAI_RUNTIME_MODE !== 'snippets') { return; }
    if (!class_exists('IAAI\\Portal\\Snippets')) { return; }
    \IAAI\Portal\Snippets::register('Elementor', '0.1.0', '7680ac7ff6d8bf20b530cf874a7ed3b3b2a423e0a4cbba4c817cd9089276105f');
}, 20);
