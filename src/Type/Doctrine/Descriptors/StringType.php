<?php

declare (strict_types=1);
namespace Php_Stan\Type\Doctrine\Descriptors;

use Php_Stan\Type\Type;
class String_Type implements Doctrine_Type_Descriptor
{
    public function get_type(): string
    {
        return \Doctrine\DBAL\Types\String_Type::class;
    }
    public function get_writable_to_property_type(): Type
    {
        return new \Php_Stan\Type\String_Type();
    }
    public function get_writable_to_database_type(): Type
    {
        return new \Php_Stan\Type\String_Type();
    }
    public function get_database_internal_type(): Type
    {
        return new \Php_Stan\Type\String_Type();
    }
}