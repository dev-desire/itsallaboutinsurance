<?php
namespace IAAI\Portal;
defined('ABSPATH') || exit;
final class Kit {
    public static function boot(): void {
        foreach (['elementor/import-export/export-kit'=>'KitExporter','elementor/import-export-customization/export-kit'=>'ModernKitExporter'] as $hook=>$name) {
            add_action($hook, static function ($process) use ($name) { require_once IAAI_PATH.'src/KitRunners.php'; $class=__NAMESPACE__.'\\'.$name; $process->register(new $class()); $wpClass=__NAMESPACE__.'\\'.($name==='KitExporter'?'KitWpExporter':'ModernKitWpExporter'); $process->register(new $wpClass()); });
        }
        foreach (['elementor/import-export/import-kit'=>'KitImporter','elementor/import-export-customization/import-kit'=>'ModernKitImporter'] as $hook=>$name) {
            add_action($hook, static function ($process) use ($name) {
                $manifest=$process->get_manifest(); if(empty($manifest['iaai-blueprint']))return;
                global $wpdb; if((int)$wpdb->get_var('SELECT COUNT(*) FROM '.Core::table('managed_templates'))){throw new \RuntimeException('Import this complete IAAI kit only into a fresh installation; use managed page reconciliation for an existing site.');}
                require_once IAAI_PATH.'src/KitRunners.php';$class=__NAMESPACE__.'\\'.$name;$process->register(new $class());
            });
        }
    }
}
