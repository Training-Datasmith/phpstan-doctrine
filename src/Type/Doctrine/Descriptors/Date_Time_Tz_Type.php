<?php

declare (strict_types=1);
namespace Php_Stan\Type\Doctrine\Descriptors;

use DateTime;
use DateTimeInterface;
use Php_Stan\Type\Object_Type;
use Php_Stan\Type\String_Type;
use Php_Stan\Type\Type;
class Date_Time_Tz_Type implements Doctrine_Type_Descriptor
{
    public function get_type(): string
    {
        return \Doctrine\DBAL\Types\Date_Time_Tz_Type::class;
    }
    public function get_writable_to_property_type(): Type
    {
        return new Object_Type(DateTime::class);
    }
    public function get_writable_to_database_type(): Type
    {
        return new Object_Type(DateTimeInterface::class);
    }
    public function get_database_internal_type(): Type
    {
        return new String_Type();
    }
}