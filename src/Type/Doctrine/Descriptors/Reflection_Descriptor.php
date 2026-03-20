<?php

declare (strict_types=1);
namespace Php_Stan\Type\Doctrine\Descriptors;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\Abstract_Platform;
use Doctrine\DBAL\Types\Type as DbalType;
use Php_Stan\Dependency_Injection\Container;
use Php_Stan\Reflection\Parameters_Acceptor_Selector;
use Php_Stan\Reflection\Reflection_Provider;
use Php_Stan\Type\Doctrine\Default_Descriptor_Registry;
use Php_Stan\Type\Doctrine\Descriptor_Not_Registered_Exception;
use Php_Stan\Type\Mixed_Type;
use Php_Stan\Type\Object_Type;
use Php_Stan\Type\Type;
use Php_Stan\Type\Type_Combinator;
class Reflection_Descriptor implements Doctrine_Type_Descriptor, Doctrine_Type_Driver_Aware_Descriptor
{
    /** @var class-string<DbalType> */
    private string $type;
    private Reflection_Provider $reflection_provider;
    private Container $container;
    /**
     * @param class-string<DbalType> $type
     */
    public function __construct(string $type, Reflection_Provider $reflection_provider, Container $container)
    {
        $this->type = $type;
        $this->reflection_provider = $reflection_provider;
        $this->container = $container;
    }
    public function get_type(): string
    {
        return $this->type;
    }
    public function get_writable_to_property_type(): Type
    {
        $method = $this->reflection_provider->get_class($this->type)->get_native_method('convertToPHPValue');
        $type = Parameters_Acceptor_Selector::select_from_types([new Mixed_Type(), new Object_Type(Abstract_Platform::class)], $method->get_variants(), false)->get_return_type();
        return Type_Combinator::remove_null($type);
    }
    public function get_writable_to_database_type(): Type
    {
        $method = $this->reflection_provider->get_class($this->type)->get_native_method('convertToDatabaseValue');
        $type = Parameters_Acceptor_Selector::select_from_types([new Mixed_Type(), new Object_Type(Abstract_Platform::class)], $method->get_variants(), false)->get_parameters()[0]->get_type();
        return Type_Combinator::remove_null($type);
    }
    public function get_database_internal_type(): Type
    {
        return $this->do_get_database_internal_type(null);
    }
    public function get_database_internal_type_for_driver(Connection $connection): Type
    {
        return $this->do_get_database_internal_type($connection);
    }
    private function do_get_database_internal_type(?Connection $connection): Type
    {
        if (!$this->reflection_provider->has_class($this->type)) {
            return new Mixed_Type();
        }
        $registry = $this->container->get_by_type(Default_Descriptor_Registry::class);
        $parents = $this->reflection_provider->get_class($this->type)->get_parent_classes_names();
        foreach ($parents as $dbal_type_parent_class) {
            try {
                // this assumes that if somebody inherits from DecimalType,
                // the real database type remains decimal and we can reuse its descriptor
                $descriptor = $registry->get_by_class_name($dbal_type_parent_class);
                return $descriptor instanceof Doctrine_Type_Driver_Aware_Descriptor && $connection !== null ? $descriptor->get_database_internal_type_for_driver($connection) : $descriptor->get_database_internal_type();
            } catch (Descriptor_Not_Registered_Exception $e) {
                continue;
            }
        }
        return new Mixed_Type();
    }
}