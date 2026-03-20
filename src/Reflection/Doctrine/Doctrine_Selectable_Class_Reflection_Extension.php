<?php

declare (strict_types=1);
namespace Php_Stan\Reflection\Doctrine;

use Php_Stan\Reflection\Class_Reflection;
use Php_Stan\Reflection\Method_Reflection;
use Php_Stan\Reflection\Methods_Class_Reflection_Extension;
use Php_Stan\Reflection\Reflection_Provider;
class Doctrine_Selectable_Class_Reflection_Extension implements Methods_Class_Reflection_Extension
{
    private Reflection_Provider $reflection_provider;
    public function __construct(Reflection_Provider $reflection_provider)
    {
        $this->reflection_provider = $reflection_provider;
    }
    public function has_method(Class_Reflection $class_reflection, string $method_name): bool
    {
        return $class_reflection->get_name() === 'Doctrine\Common\Collections\Collection' && $method_name === 'matching';
    }
    public function get_method(Class_Reflection $class_reflection, string $method_name): Method_Reflection
    {
        $selectable_reflection = $this->reflection_provider->get_class('Doctrine\Common\Collections\Selectable');
        return $selectable_reflection->get_native_method($method_name);
    }
}