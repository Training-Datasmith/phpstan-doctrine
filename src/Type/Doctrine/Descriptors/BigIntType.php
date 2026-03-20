<?php

declare (strict_types=1);
namespace Php_Stan\Type\Doctrine\Descriptors;

use function class_exists;
use Composer\Installed_Versions;
use Php_Stan\Type\Integer_Type;
use Php_Stan\Type\String_Type;
use Php_Stan\Type\Type;
use Php_Stan\Type\Type_Combinator;
use function strpos;
class Big_Int_Type implements Doctrine_Type_Descriptor
{
    public function get_type(): string
    {
        return \Doctrine\DBAL\Types\Big_Int_Type::class;
    }
    public function get_writable_to_property_type(): Type
    {
        if ($this->has_dbal4()) {
            return new Integer_Type();
        }
        return (new Integer_Type())->to_string();
    }
    public function get_writable_to_database_type(): Type
    {
        return Type_Combinator::union(new String_Type(), new Integer_Type());
    }
    public function get_database_internal_type(): Type
    {
        return new Integer_Type();
    }
    private function has_dbal4(): bool
    {
        if (!class_exists(Installed_Versions::class)) {
            return false;
        }
        $dbal_version = Installed_Versions::get_version('doctrine/dbal');
        if ($dbal_version === null) {
            return false;
        }
        return strpos($dbal_version, '4.') === 0;
    }
}