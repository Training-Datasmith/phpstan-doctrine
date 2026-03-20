<?php

declare (strict_types=1);
namespace Php_Stan\Type\Doctrine\Descriptors;

use function class_exists;
use Composer\Installed_Versions;
use Php_Stan\Type\Mixed_Type;
use Php_Stan\Type\Resource_Type;
use Php_Stan\Type\String_Type;
use Php_Stan\Type\Type;
use function strpos;
class Binary_Type implements Doctrine_Type_Descriptor
{
    public function get_type(): string
    {
        return \Doctrine\DBAL\Types\Binary_Type::class;
    }
    public function get_writable_to_property_type(): Type
    {
        if ($this->has_dbal4()) {
            return new String_Type();
        }
        return new Resource_Type();
    }
    public function get_writable_to_database_type(): Type
    {
        return new Mixed_Type();
    }
    public function get_database_internal_type(): Type
    {
        return new String_Type();
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