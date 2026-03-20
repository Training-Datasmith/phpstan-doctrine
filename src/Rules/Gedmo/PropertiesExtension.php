<?php

declare (strict_types=1);
namespace Php_Stan\Rules\Gedmo;

use function class_exists;
use Doctrine\Common\Annotations\Annotation_Exception;
use Doctrine\Common\Annotations\Annotation_Reader;
use Gedmo\Mapping\Annotation as Gedmo;
use function get_class;
use function in_array;
use Php_Stan\Reflection\Property_Reflection;
use Php_Stan\Rules\Properties\Read_Write_Properties_Extension;
use Php_Stan\Type\Doctrine\Object_Metadata_Resolver;
class Properties_Extension implements Read_Write_Properties_Extension
{
    private const GEDMO_WRITE_CLASSLIST = [Gedmo\Blameable::class, Gedmo\Ip_Traceable::class, Gedmo\Locale::class, Gedmo\Language::class, Gedmo\Slug::class, Gedmo\Sortable_Position::class, Gedmo\Timestampable::class, Gedmo\Tree_Left::class, Gedmo\Tree_Level::class, Gedmo\Tree_Parent::class, Gedmo\Tree_Path::class, Gedmo\Tree_Path_Hash::class, Gedmo\Tree_Right::class, Gedmo\Tree_Root::class, Gedmo\Uploadable_File_Mime_Type::class, Gedmo\Uploadable_File_Name::class, Gedmo\Uploadable_File_Path::class, Gedmo\Uploadable_File_Size::class];
    private const GEDMO_READ_CLASSLIST = [Gedmo\Locale::class, Gedmo\Language::class];
    private ?Annotation_Reader $annotation_reader = null;
    private Object_Metadata_Resolver $object_metadata_resolver;
    public function __construct(Object_Metadata_Resolver $object_metadata_resolver)
    {
        $this->annotation_reader = class_exists(Annotation_Reader::class) ? new Annotation_Reader() : null;
        $this->object_metadata_resolver = $object_metadata_resolver;
    }
    public function is_always_read(Property_Reflection $property, string $property_name): bool
    {
        return $this->is_gedmo_annotation_or_attribute($property, $property_name, self::GEDMO_READ_CLASSLIST);
    }
    public function is_always_written(Property_Reflection $property, string $property_name): bool
    {
        return $this->is_gedmo_annotation_or_attribute($property, $property_name, self::GEDMO_WRITE_CLASSLIST);
    }
    public function is_initialized(Property_Reflection $property, string $property_name): bool
    {
        return false;
    }
    /**
     * @param array<class-string> $classList
     */
    private function is_gedmo_annotation_or_attribute(Property_Reflection $property, string $property_name, array $class_list): bool
    {
        $class_reflection = $property->get_declaring_class();
        if ($this->object_metadata_resolver->is_transient($class_reflection->get_name())) {
            return false;
        }
        $property_reflection = $class_reflection->get_native_reflection()->get_property($property_name);
        $attributes = $property_reflection->get_attributes();
        foreach ($attributes as $attribute) {
            if (in_array($attribute->get_name(), $class_list, true)) {
                return true;
            }
        }
        if ($this->annotation_reader === null) {
            return false;
        }
        try {
            $annotations = $this->annotation_reader->get_property_annotations($property_reflection);
        } catch (Annotation_Exception $e) {
            // Suppress the "The annotation X was never imported." exception in case the `objectManagerLoader` is not configured
            return false;
        }
        foreach ($annotations as $annotation) {
            if (in_array(get_class($annotation), $class_list, true)) {
                return true;
            }
        }
        return false;
    }
}