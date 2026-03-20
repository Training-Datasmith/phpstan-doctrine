<?php

declare (strict_types=1);
namespace Php_Stan\Type\Doctrine\Descriptors\Symfony;

use Php_Stan\Type\Doctrine\Descriptors\Doctrine_Type_Descriptor;
use Php_Stan\Type\Object_Type;
use Php_Stan\Type\String_Type;
use Php_Stan\Type\Type;
use Php_Stan\Type\Type_Combinator;
use Symfony\Component\Uid\Uuid;
class Uuid_Type_Descriptor implements Doctrine_Type_Descriptor
{
    /** @var class-string<\Doctrine\DBAL\Types\Type> */
    private string $uuid_type_name;
    /**
     * @param class-string<\Doctrine\DBAL\Types\Type> $uuidTypeName
     */
    public function __construct(string $uuid_type_name)
    {
        $this->uuid_type_name = $uuid_type_name;
    }
    public function get_type(): string
    {
        return $this->uuid_type_name;
    }
    public function get_writable_to_property_type(): Type
    {
        return new Object_Type(Uuid::class);
    }
    public function get_writable_to_database_type(): Type
    {
        return Type_Combinator::union(new String_Type(), new Object_Type(Uuid::class));
    }
    public function get_database_internal_type(): Type
    {
        return new String_Type();
    }
}