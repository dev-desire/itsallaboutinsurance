<?php
namespace IAAI\Portal;
defined('ABSPATH') || exit;
trait KitExportPayload {
    public static function get_name(): string { return 'iaai-blueprint'; }
    public function should_export(array $data) { return in_array('content',$data['include']??[],true); }
    public function export(array $data) {
        $root=IAAI_PATH.'templates';$manifest=json_decode(file_get_contents($root.'/manifest.json'),true,64,JSON_THROW_ON_ERROR);
        $files=[['path'=>'iaai/manifest','data'=>$manifest]];
        foreach($manifest['templates']as$item){$files[]=['path'=>'iaai/'.substr($item['file'],0,-5),'data'=>json_decode(file_get_contents($root.'/'.$item['file']),true,64,JSON_THROW_ON_ERROR)];}
        return ['files'=>$files,'manifest'=>[['iaai-blueprint'=>['schema'=>1,'version'=>IAAI_VERSION,'templates'=>count($manifest['templates'])]]]];
    }
}
trait KitImportPayload {
    public static function get_name(): string { return 'elementor-content'; }
    public function should_import(array $data) { return !empty($data['manifest']['iaai-blueprint']); }
    public function import(array $data,array $imported) {
        if(($data['manifest']['iaai-blueprint']['version']??'')!==IAAI_VERSION){throw new \RuntimeException('Install the matching IAAI support plugin before kit import');}
        $root=realpath($data['extracted_directory_path'].'/iaai');if(!$root){throw new \RuntimeException('Blueprint missing');}
        $result=Templates::import($root,IAAI_VERSION);if($result['conflicts']){throw new \RuntimeException('Blueprint conflicts');}
        return ['iaai-blueprint'=>$result];
    }
}
final class KitExporter extends \Elementor\App\Modules\ImportExport\Runners\Export\Export_Runner_Base { use KitExportPayload; }
final class KitImporter extends \Elementor\App\Modules\ImportExport\Runners\Import\Import_Runner_Base { use KitImportPayload; }
final class ModernKitExporter extends \Elementor\App\Modules\ImportExportCustomization\Runners\Export\Export_Runner_Base { use KitExportPayload; }
final class ModernKitImporter extends \Elementor\App\Modules\ImportExportCustomization\Runners\Import\Import_Runner_Base { use KitImportPayload; }
trait BlueprintOnlyWpContent {
    public static function get_name(): string { return 'wp-content'; }
    public function should_export(array $data) { return true; }
    public function export(array $data) { return ['files'=>[], 'manifest'=>[['wp-content'=>[]]]]; }
}
final class KitWpExporter extends \Elementor\App\Modules\ImportExport\Runners\Export\Export_Runner_Base { use BlueprintOnlyWpContent; }
final class ModernKitWpExporter extends \Elementor\App\Modules\ImportExportCustomization\Runners\Export\Export_Runner_Base { use BlueprintOnlyWpContent; }
