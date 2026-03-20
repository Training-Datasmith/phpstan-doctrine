<?php

declare (strict_types=1);
namespace Php_Stan\Type\Doctrine\Descriptors;

use Php_Stan\Type\Accessory\Accessory_Array_List_Type;
use Php_Stan\Type\Array_Type;
use Php_Stan\Type\Integer_Type;
use Php_Stan\Type\Mixed_Type;
use Php_Stan\Type\String_Type;
use Php_Stan\Type\Type;
use Php_Stan\Type\Type_Combinator;
class Simple_Array_Type implements Doctrine_Type_Descriptor
{
    public function get_type(): string
    {
        return \Doctrine\DBAL\Types\Simple_Array_Type::class;
    }
    public function get_writable_to_property_type(): Type
    {
        return Type_Combinator::intersect(new Array_Type(new Integer_Type(), new String_Type()), new Accessory_Array_List_Type());
    }
    public function get_writable_to_database_type(): Type
    {
        return new Array_Type(new Mixed_Type(), new String_Type());
    }
    public function get_database_internal_type(): Type
    {
        return new String_Type();
    }
}