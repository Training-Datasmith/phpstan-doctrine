<?php

declare (strict_types=1);
namespace Php_Stan\Type\Doctrine;

use Php_Stan\Type\Doctrine\Descriptors\Doctrine_Type_Descriptor;
interface Descriptor_Registry
{
    public function get(string $type): Doctrine_Type_Descriptor;
}