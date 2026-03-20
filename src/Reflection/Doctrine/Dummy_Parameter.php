<?php

declare (strict_types=1);
namespace Php_Stan\Reflection\Doctrine;

use Php_Stan\Reflection\Parameter_Reflection;
use Php_Stan\Reflection\Passed_By_Reference;
use Php_Stan\Type\Type;
class Dummy_Parameter implements Parameter_Reflection
{
    private string $name;
    private Type $type;
    private bool $optional;
    private Passed_By_Reference $passed_by_reference;
    private bool $variadic;
    private ?Type $default_value = null;
    public function __construct(string $name, Type $type, bool $optional, ?Passed_By_Reference $passed_by_reference, bool $variadic, ?Type $default_value)
    {
        $this->name = $name;
        $this->type = $type;
        $this->optional = $optional;
        $this->passed_by_reference = $passed_by_reference ?? Passed_By_Reference::create_no();
        $this->variadic = $variadic;
        $this->default_value = $default_value;
    }
    public function get_name(): string
    {
        return $this->name;
    }
    public function is_optional(): bool
    {
        return $this->optional;
    }
    public function get_type(): Type
    {
        return $this->type;
    }
    public function passed_by_reference(): Passed_By_Reference
    {
        return $this->passed_by_reference;
    }
    public function is_variadic(): bool
    {
        return $this->variadic;
    }
    public function get_default_value(): ?Type
    {
        return $this->default_value;
    }
}