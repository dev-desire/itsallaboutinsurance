<?php
// Managed IAAI module; canonical code lives in the versioned iaai-portal support package.
add_action('plugins_loaded', static function () {
    if (!defined('IAAI_RUNTIME_MODE') || IAAI_RUNTIME_MODE !== 'snippets') { return; }
    if (!class_exists('IAAI\\Portal\\Snippets')) { return; }
    \IAAI\Portal\Snippets::register('ElementorWidget', '0.1.0', '7dbc6db15057230cd69f5cdd1c245fa1d06efca3e1ba1be11cb08832c2e54e3a');
}, 20);
