<?php

declare (strict_types=1);
namespace Php_Stan\Reflection\Doctrine;

use Doctrine\Persistence\Object_Repository;
use function lcfirst;
use Php_Stan\Reflection\Class_Reflection;
use Php_Stan\Reflection\Method_Reflection;
use Php_Stan\Reflection\Methods_Class_Reflection_Extension;
use Php_Stan\Should_Not_Happen_Exception;
use Php_Stan\Type\Array_Type;
use Php_Stan\Type\Doctrine\Object_Metadata_Resolver;
use Php_Stan\Type\Integer_Type;
use Php_Stan\Type\Null_Type;
use Php_Stan\Type\Type_Combinator;
use function str_replace;
use function strlen;
use function strpos;
use function substr;
use function ucwords;
class Entity_Repository_Class_Reflection_Extension implements Methods_Class_Reflection_Extension
{
    private Object_Metadata_Resolver $object_metadata_resolver;
    public function __construct(Object_Metadata_Resolver $object_metadata_resolver)
    {
        $this->object_metadata_resolver = $object_metadata_resolver;
    }
    public function has_method(Class_Reflection $class_reflection, string $method_name): bool
    {
        if (strpos($method_name, 'findBy') === 0 && strlen($method_name) > strlen('findBy')) {
            $method_field_name = substr($method_name, strlen('findBy'));
        } elseif (strpos($method_name, 'findOneBy') === 0 && strlen($method_name) > strlen('findOneBy')) {
            $method_field_name = substr($method_name, strlen('findOneBy'));
        } elseif (strpos($method_name, 'countBy') === 0 && strlen($method_name) > strlen('countBy')) {
            $method_field_name = substr($method_name, strlen('countBy'));
        } else {
            return false;
        }
        $repository_ancesor = $class_reflection->get_ancestor_with_class_name(Object_Repository::class);
        if ($repository_ancesor === null) {
            return false;
        }
        $template_type_map = $repository_ancesor->get_active_template_type_map();
        $entity_class_type = $template_type_map->get_type('TEntityClass');
        if ($entity_class_type === null) {
            return false;
        }
        $entity_class_names = $entity_class_type->get_object_class_names();
        $field_name = $this->classify($method_field_name);
        /** @var class-string $entityClassName */
        foreach ($entity_class_names as $entity_class_name) {
            $class_metadata = $this->object_metadata_resolver->get_class_metadata($entity_class_name);
            if ($class_metadata === null) {
                continue;
            }
            if ($class_metadata->has_field($field_name) || $class_metadata->has_association($field_name)) {
                return true;
            }
        }
        return false;
    }
    private function classify(string $word): string
    {
        return lcfirst(str_replace([' ', '_', '-'], '', ucwords($word, ' _-')));
    }
    public function get_method(Class_Reflection $class_reflection, string $method_name): Method_Reflection
    {
        $repository_ancesor = $class_reflection->get_ancestor_with_class_name(Object_Repository::class);
        if ($repository_ancesor === null) {
            $repository_ancesor = $class_reflection->get_ancestor_with_class_name(Object_Repository::class);
            if ($repository_ancesor === null) {
                throw new Should_Not_Happen_Exception();
            }
        }
        $template_type_map = $repository_ancesor->get_active_template_type_map();
        $entity_class_type = $template_type_map->get_type('TEntityClass');
        if ($entity_class_type === null) {
            throw new Should_Not_Happen_Exception();
        }
        if (strpos($method_name, 'findBy') === 0) {
            $return_type = new Array_Type(new Integer_Type(), $entity_class_type);
        } elseif (strpos($method_name, 'findOneBy') === 0) {
            $return_type = Type_Combinator::union($entity_class_type, new Null_Type());
        } elseif (strpos($method_name, 'countBy') === 0) {
            $return_type = new Integer_Type();
        } else {
            throw new Should_Not_Happen_Exception();
        }
        return new Magic_Repository_Method_Reflection($repository_ancesor, $method_name, $return_type);
    }
}