<?php

declare (strict_types=1);
namespace Php_Stan\Rules\Doctrine\ORM;

use Doctrine\ORM\Mapping\Class_Metadata;
use function in_array;
use Php_Stan\Reflection\Property_Reflection;
use Php_Stan\Rules\Properties\Read_Write_Properties_Extension;
use Php_Stan\Type\Doctrine\Object_Metadata_Resolver;
use Throwable;
class Properties_Extension implements Read_Write_Properties_Extension
{
    private Object_Metadata_Resolver $object_metadata_resolver;
    public function __construct(Object_Metadata_Resolver $object_metadata_resolver)
    {
        $this->object_metadata_resolver = $object_metadata_resolver;
    }
    public function is_always_read(Property_Reflection $property, string $property_name): bool
    {
        $class_name = $property->get_declaring_class()->get_name();
        $metadata = $this->object_metadata_resolver->get_class_metadata($class_name);
        if ($metadata === null) {
            return false;
        }
        if ($metadata->has_field($property_name)) {
            return true;
        }
        return (bool) $metadata->has_association($property_name);
    }
    public function is_always_written(Property_Reflection $property, string $property_name): bool
    {
        $declaring_class = $property->get_declaring_class();
        $class_name = $declaring_class->get_name();
        $metadata = $this->object_metadata_resolver->get_class_metadata($class_name);
        if ($metadata === null) {
            return false;
        }
        if (!$metadata->has_field($property_name) && !$metadata->has_association($property_name)) {
            return false;
        }
        if (isset($metadata->field_mappings[$property_name])) {
            $mapping = $metadata->field_mappings[$property_name];
            if (isset($mapping['generated']) && $mapping['generated'] !== Class_Metadata::GENERATED_NEVER) {
                return true;
            }
        }
        if ($metadata->is_read_only && !$declaring_class->has_constructor()) {
            return true;
        }
        if ($metadata->version_field === $property_name) {
            return true;
        }
        return $this->is_generated_identifier($metadata, $property_name);
    }
    public function is_initialized(Property_Reflection $property, string $property_name): bool
    {
        $declaring_class = $property->get_declaring_class();
        $class_name = $declaring_class->get_name();
        $metadata = $this->object_metadata_resolver->get_class_metadata($class_name);
        if ($metadata === null) {
            return false;
        }
        if (!$metadata->has_field($property_name) && !$metadata->has_association($property_name)) {
            return false;
        }
        if ($this->is_generated_identifier($metadata, $property_name)) {
            return true;
        }
        return $metadata->is_read_only && !$declaring_class->has_constructor();
    }
    /**
     * @param ClassMetadata<object> $metadata
     */
    private function is_generated_identifier(Class_Metadata $metadata, string $property_name): bool
    {
        if ($metadata->is_identifier_natural()) {
            return false;
        }
        try {
            return in_array($property_name, $metadata->get_identifier_field_names(), true);
        } catch (Throwable $e) {
            $mapping_exception = 'Doctrine\ORM\Mapping\MappingException';
            if (!$e instanceof $mapping_exception) {
                throw $e;
            }
            return false;
        }
    }
}