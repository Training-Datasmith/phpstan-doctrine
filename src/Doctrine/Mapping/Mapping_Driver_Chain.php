<?php

declare (strict_types=1);
namespace Php_Stan\Doctrine\Mapping;

use Doctrine\Common\Annotations\Annotation_Exception;
use Doctrine\ORM\Mapping\Mapping_Exception;
use Doctrine\Persistence\Mapping\Class_Metadata;
use Doctrine\Persistence\Mapping\Driver\Mapping_Driver;
class Mapping_Driver_Chain implements Mapping_Driver
{
    /** @var MappingDriver[] */
    private array $drivers;
    /**
     * @param MappingDriver[] $drivers
     */
    public function __construct(array $drivers)
    {
        $this->drivers = $drivers;
    }
    /**
     * @param class-string $className
     */
    public function load_metadata_for_class($class_name, Class_Metadata $metadata): void
    {
        foreach ($this->drivers as $driver) {
            try {
                $driver->load_metadata_for_class($class_name, $metadata);
                return;
            } catch (\Doctrine\Persistence\Mapping\Mapping_Exception|Mapping_Exception|Annotation_Exception $e) {
                // pass
            }
        }
    }
    /**
     * @return mixed[]
     */
    public function get_all_class_names(): array
    {
        $all = [];
        foreach ($this->drivers as $driver) {
            foreach ($driver->get_all_class_names() as $class_name) {
                $all[] = $class_name;
            }
        }
        return $all;
    }
    /**
     * @param class-string $className
     */
    public function is_transient($class_name): bool
    {
        foreach ($this->drivers as $driver) {
            try {
                if ($driver->is_transient($class_name)) {
                    continue;
                }
                return false;
            } catch (\Doctrine\Persistence\Mapping\Mapping_Exception|Mapping_Exception|Annotation_Exception $e) {
                // pass
            }
        }
        return true;
    }
}