<?php
// Managed IAAI module; canonical code lives in the versioned iaai-portal support package.
add_action('plugins_loaded', static function () {
    if (!defined('IAAI_RUNTIME_MODE') || IAAI_RUNTIME_MODE !== 'snippets') { return; }
    if (!class_exists('IAAI\\Portal\\Snippets')) { return; }
    \IAAI\Portal\Snippets::register('ElementorWidget', '0.1.0', 'fdcea4284ae8158f03b35bd4455ce569a9f30c16cd959c209d794c9ff3e52dde');
}, 20);
