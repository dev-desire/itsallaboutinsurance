<?php
add_action('plugins_loaded', static function () { if (class_exists('IAAI\\Portal\\Snippets')) { \IAAI\Portal\Snippets::start(); } }, 99);
