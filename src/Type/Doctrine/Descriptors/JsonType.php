<?php

declare (strict_types=1);
namespace Php_Stan\Type\Doctrine\Descriptors;

use JsonSerializable;
use Php_Stan\Type\Array_Type;
use Php_Stan\Type\Boolean_Type;
use Php_Stan\Type\Float_Type;
use Php_Stan\Type\Integer_Type;
use Php_Stan\Type\Mixed_Type;
use Php_Stan\Type\Never_Type;
use Php_Stan\Type\Null_Type;
use Php_Stan\Type\Object_Type;
use Php_Stan\Type\String_Type;
use Php_Stan\Type\Type;
use Php_Stan\Type\Union_Type;
use stdClass;
class Json_Type implements Doctrine_Type_Descriptor
{
    private static function get_json_type(): Union_Type
    {
        $mixed_type = new Mixed_Type();
        return new Union_Type([new Array_Type($mixed_type, $mixed_type), new Boolean_Type(), new Float_Type(), new Integer_Type(), new Null_Type(), new Object_Type(JsonSerializable::class), new Object_Type(stdClass::class), new String_Type()]);
    }
    public function get_type(): string
    {
        return \Doctrine\DBAL\Types\Json_Type::class;
    }
    public function get_writable_to_property_type(): Type
    {
        return new Never_Type();
    }
    public function get_writable_to_database_type(): Type
    {
        return self::get_json_type();
    }
    public function get_database_internal_type(): Type
    {
        return new String_Type();
    }
}