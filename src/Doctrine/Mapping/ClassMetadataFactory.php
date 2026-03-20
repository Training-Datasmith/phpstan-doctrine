<?php

declare (strict_types=1);
namespace Php_Stan\Doctrine\Mapping;

use function class_exists;
use function count;
use Doctrine\Common\Annotations\Annotation_Reader;
use Doctrine\Common\Annotations\Doc_Parser;
use Doctrine\DBAL\Driver_Manager;
use Doctrine\ORM\Configuration;
use Doctrine\ORM\Entity_Manager;
use Doctrine\ORM\Mapping\Class_Metadata;
use Doctrine\ORM\Mapping\Driver\Annotation_Driver;
use Doctrine\ORM\Mapping\Driver\Attribute_Driver;
use function method_exists;
use const PHP_VERSION_ID;
class Class_Metadata_Factory extends \Doctrine\ORM\Mapping\Class_Metadata_Factory
{
    private string $tmp_dir;
    public function __construct(string $tmp_dir)
    {
        $this->tmp_dir = $tmp_dir;
    }
    protected function initialize(): void
    {
        $drivers = [];
        if (class_exists(Annotation_Driver::class) && class_exists(Annotation_Reader::class)) {
            $doc_parser = new Doc_Parser();
            $doc_parser->set_ignore_not_imported_annotations(true);
            $drivers[] = new Annotation_Driver(new Annotation_Reader($doc_parser));
        }
        if (class_exists(Attribute_Driver::class) && PHP_VERSION_ID >= 80000) {
            $drivers[] = new Attribute_Driver([]);
        }
        $config = new Configuration();
        $config->set_metadata_driver_impl(count($drivers) === 1 ? $drivers[0] : new Mapping_Driver_Chain($drivers));
        // @phpstan-ignore function.impossibleType, function.alreadyNarrowedType (Available since Doctrine ORM 3.4)
        if (PHP_VERSION_ID >= 80400 && method_exists($config, 'enableNativeLazyObjects')) {
            $config->enable_native_lazy_objects(true);
        } else {
            $config->set_auto_generate_proxy_classes(true);
            $config->set_proxy_dir($this->tmp_dir);
            $config->set_proxy_namespace('__PHPStanDoctrine__\Proxy');
        }
        $connection = Driver_Manager::get_connection(['driver' => 'pdo_sqlite', 'memory' => true], $config);
        if (!method_exists(Entity_Manager::class, 'create')) {
            $em = new Entity_Manager($connection, $config);
        } else {
            $em = Entity_Manager::create($connection, $config);
        }
        $this->set_entity_manager($em);
        parent::initialize();
        $this->initialized = true;
    }
    /**
     * @template T of object
     * @param class-string<T> $className
     * @return ClassMetadata<T>
     */
    protected function new_class_metadata_instance($class_name): Class_Metadata
    {
        return new Class_Metadata($class_name);
    }
}