<?php

declare (strict_types=1);
namespace Php_Stan\Type\Doctrine;

use function class_exists;
use Doctrine\Common\Annotations\Annotation_Exception;
use Doctrine\ORM\Configuration;
use Doctrine\ORM\Entity_Manager_Interface;
use Doctrine\ORM\Mapping\Class_Metadata;
use Doctrine\ORM\Mapping\Mapping_Exception;
use Doctrine\Persistence\Object_Manager;
use function is_file;
use function is_readable;
use function method_exists;
use const PHP_VERSION_ID;
use Php_Stan\Doctrine\Mapping\Class_Metadata_Factory;
use Php_Stan\Should_Not_Happen_Exception;
use Reflection_Exception;
use function sprintf;
final class Object_Metadata_Resolver
{
    private ?string $object_manager_loader = null;
    /** @var ObjectManager|false|null */
    private $object_manager;
    private ?Class_Metadata_Factory $metadata_factory = null;
    private string $tmp_dir;
    public function __construct(?string $object_manager_loader, string $tmp_dir)
    {
        $this->object_manager_loader = $object_manager_loader;
        $this->tmp_dir = $tmp_dir;
    }
    public function has_object_manager_loader(): bool
    {
        return $this->object_manager_loader !== null;
    }
    /** @api */
    public function get_object_manager(): ?Object_Manager
    {
        if ($this->object_manager === false) {
            return null;
        }
        if ($this->object_manager !== null) {
            return $this->object_manager;
        }
        if ($this->object_manager_loader === null) {
            $this->object_manager = false;
            return null;
        }
        $this->object_manager = $this->load_object_manager($this->object_manager_loader);
        return $this->object_manager;
    }
    public function is_native_lazy_objects_enabled(): bool
    {
        $object_manager = $this->get_object_manager();
        if ($object_manager instanceof Entity_Manager_Interface) {
            $config = $object_manager->get_configuration();
            // @phpstan-ignore function.impossibleType, function.alreadyNarrowedType (Available since Doctrine ORM 3.4)
            if (method_exists($config, 'isNativeLazyObjectsEnabled') && $config->is_native_lazy_objects_enabled()) {
                return true;
            }
            return false;
        }
        // No object manager - check if the standalone ClassMetadataFactory would enable native lazy objects
        // @phpstan-ignore function.impossibleType, function.alreadyNarrowedType (Available since Doctrine ORM 3.4)
        if (PHP_VERSION_ID >= 80400 && class_exists(Configuration::class) && method_exists(Configuration::class, 'enableNativeLazyObjects')) {
            return true;
        }
        return false;
    }
    /**
     * @param class-string $className
     */
    public function is_transient(string $class_name): bool
    {
        if (!class_exists($class_name)) {
            return true;
        }
        $object_manager = $this->get_object_manager();
        try {
            if ($object_manager === null) {
                $metadata_factory = $this->get_metadata_factory();
                if ($metadata_factory === null) {
                    return true;
                }
                return $metadata_factory->is_transient($class_name);
            }
            return $object_manager->get_metadata_factory()->is_transient($class_name);
        } catch (Reflection_Exception $e) {
            return true;
        }
    }
    private function get_metadata_factory(): ?Class_Metadata_Factory
    {
        if ($this->metadata_factory !== null) {
            return $this->metadata_factory;
        }
        if (!class_exists(\Doctrine\ORM\Mapping\Class_Metadata_Factory::class)) {
            return null;
        }
        return $this->metadata_factory = new Class_Metadata_Factory($this->tmp_dir);
    }
    /**
     * @api
     *
     * @template T of object
     * @param class-string<T> $className
     * @return ClassMetadata<T>|null
     */
    public function get_class_metadata(string $class_name): ?Class_Metadata
    {
        if ($this->is_transient($class_name)) {
            return null;
        }
        $object_manager = $this->get_object_manager();
        try {
            if ($object_manager === null) {
                $metadata_factory = $this->get_metadata_factory();
                if ($metadata_factory === null) {
                    return null;
                }
                /** @throws \Doctrine\Persistence\Mapping\MappingException | MappingException | AnnotationException */
                $metadata = $metadata_factory->get_metadata_for($class_name);
            } else {
                /** @throws \Doctrine\Persistence\Mapping\MappingException | MappingException | AnnotationException */
                $metadata = $object_manager->get_class_metadata($class_name);
            }
        } catch (\Doctrine\Persistence\Mapping\Mapping_Exception|Mapping_Exception|Annotation_Exception $e) {
            return null;
        }
        if (!$metadata instanceof Class_Metadata) {
            return null;
        }
        /** @var ClassMetadata<T> $ormMetadata */
        $orm_metadata = $metadata;
        return $orm_metadata;
    }
    private function load_object_manager(string $object_manager_loader): ?Object_Manager
    {
        if (!is_file($object_manager_loader)) {
            throw new Should_Not_Happen_Exception(sprintf('Object manager could not be loaded: file "%s" does not exist', $object_manager_loader));
        }
        if (!is_readable($object_manager_loader)) {
            throw new Should_Not_Happen_Exception(sprintf('Object manager could not be loaded: file "%s" is not readable', $object_manager_loader));
        }
        return require $object_manager_loader;
    }
}