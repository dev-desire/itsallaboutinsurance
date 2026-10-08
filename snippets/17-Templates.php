<?php
// Managed IAAI module; canonical code lives in the versioned iaai-portal support package.
add_action('plugins_loaded', static function () {
    if (!defined('IAAI_RUNTIME_MODE') || IAAI_RUNTIME_MODE !== 'snippets') { return; }
    if (!class_exists('IAAI\\Portal\\Snippets')) { return; }
    \IAAI\Portal\Snippets::register('Templates', '0.1.0', '80665498f86da917cdccff68ab100683cde5ff9db585f52cd583ced841b5d3f8');
}, 20);
