<?php

declare (strict_types=1);
namespace Php_Stan\Type\Doctrine\Query_Builder;

use function array_unshift;
use function count;
use Php_Parser\Node\Arg;
use Php_Parser\Node\Expr\Method_Call;
use Php_Parser\Node\Identifier;
use Php_Parser\Node\Scalar\String_;
use Php_Stan\Analyser\Scope;
use Php_Stan\Reflection\Method_Reflection;
use Php_Stan\Type\Dynamic_Method_Return_Type_Extension;
use Php_Stan\Type\Type;
class Entity_Repository_Create_Query_Builder_Dynamic_Return_Type_Extension implements Dynamic_Method_Return_Type_Extension
{
    public function get_class(): string
    {
        return 'Doctrine\ORM\EntityRepository';
    }
    public function is_method_supported(Method_Reflection $method_reflection): bool
    {
        return $method_reflection->get_name() === 'createQueryBuilder';
    }
    public function get_type_from_method_call(Method_Reflection $method_reflection, Method_Call $method_call, Scope $scope): ?Type
    {
        $entity_name_expr = new Method_Call($method_call->var, new Identifier('getEntityName'));
        $entity_name_expr_type = $scope->get_type($entity_name_expr);
        if ($entity_name_expr_type->is_class_string()->yes() && count($entity_name_expr_type->get_class_string_object_type()->get_object_class_names()) === 1) {
            $entity_name_expr = new String_($entity_name_expr_type->get_class_string_object_type()->get_object_class_names()[0]);
        }
        if (!isset($method_call->get_args()[0])) {
            return null;
        }
        $from_args = $method_call->get_args();
        array_unshift($from_args, new Arg($entity_name_expr));
        $call_stack = new Method_Call($method_call->var, new Identifier('getEntityManager'));
        $call_stack = new Method_Call($call_stack, new Identifier('createQueryBuilder'));
        $call_stack = new Method_Call($call_stack, new Identifier('select'), [$method_call->get_args()[0]]);
        $call_stack = new Method_Call($call_stack, new Identifier('from'), $from_args);
        return $scope->get_type($call_stack);
    }
}