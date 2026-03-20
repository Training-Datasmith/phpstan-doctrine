<?php

declare (strict_types=1);
namespace Php_Stan\Type\Doctrine\Descriptors;

use Php_Stan\Type\Integer_Type;
use Php_Stan\Type\Type;
class Small_Int_Type implements Doctrine_Type_Descriptor
{
    public function get_type(): string
    {
        return \Doctrine\DBAL\Types\Small_Int_Type::class;
    }
    public function get_writable_to_property_type(): Type
    {
        return new Integer_Type();
    }
    public function get_writable_to_database_type(): Type
    {
        return new Integer_Type();
    }
    public function get_database_internal_type(): Type
    {
        return new Integer_Type();
    }
}