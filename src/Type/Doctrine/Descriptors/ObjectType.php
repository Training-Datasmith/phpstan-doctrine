<?php

declare (strict_types=1);
namespace Php_Stan\Type\Doctrine\Descriptors;

use Php_Stan\Type\Object_Without_Class_Type;
use Php_Stan\Type\String_Type;
use Php_Stan\Type\Type;
class Object_Type implements Doctrine_Type_Descriptor
{
    public function get_type(): string
    {
        return \Doctrine\DBAL\Types\Object_Type::class;
    }
    public function get_writable_to_property_type(): Type
    {
        return new Object_Without_Class_Type();
    }
    public function get_writable_to_database_type(): Type
    {
        return new Object_Without_Class_Type();
    }
    public function get_database_internal_type(): Type
    {
        return new String_Type();
    }
}