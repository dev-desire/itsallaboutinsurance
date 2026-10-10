<?php
// Managed IAAI module; canonical code lives in the versioned iaai-portal support package.
add_action('plugins_loaded', static function () {
    if (!defined('IAAI_RUNTIME_MODE') || IAAI_RUNTIME_MODE !== 'snippets') { return; }
    if (!class_exists('IAAI\\Portal\\Snippets')) { return; }
    \IAAI\Portal\Snippets::register('Elementor', '0.1.0', 'f3d4a18b2f540f9744ee80d0fe0017a0734c856b1e36eada77b1780334bc9858');
}, 20);
