<?php

declare (strict_types=1);
namespace Php_Stan\Type\Doctrine;

use function array_map;
use Doctrine\DBAL\Exception\Unique_Constraint_Violation_Exception;
use Doctrine\ORM\Entity_Manager_Interface;
use Doctrine\ORM\Exception\Orm_Exception;
use Doctrine\Persistence\Object_Manager;
use Php_Parser\Node\Expr\Method_Call;
use Php_Stan\Analyser\Scope;
use Php_Stan\Reflection\Method_Reflection;
use Php_Stan\Type\Dynamic_Method_Throw_Type_Extension;
use Php_Stan\Type\Object_Type;
use Php_Stan\Type\Type;
use Php_Stan\Type\Type_Combinator;
class Entity_Manager_Interface_Throw_Type_Extension implements Dynamic_Method_Throw_Type_Extension
{
    public const SUPPORTED_METHOD = ['flush' => [Orm_Exception::class, Unique_Constraint_Violation_Exception::class]];
    public function is_method_supported(Method_Reflection $method_reflection): bool
    {
        return $method_reflection->get_declaring_class()->get_name() === Object_Manager::class && isset(self::SUPPORTED_METHOD[$method_reflection->get_name()]);
    }
    public function get_throw_type_from_method_call(Method_Reflection $method_reflection, Method_Call $method_call, Scope $scope): ?Type
    {
        $type = $scope->get_type($method_call->var);
        if ((new Object_Type(Entity_Manager_Interface::class))->is_super_type_of($type)->yes()) {
            return Type_Combinator::union(...array_map(static fn(string $class): Type => new Object_Type($class), self::SUPPORTED_METHOD[$method_reflection->get_name()]));
        }
        return $method_reflection->get_throw_type();
    }
}