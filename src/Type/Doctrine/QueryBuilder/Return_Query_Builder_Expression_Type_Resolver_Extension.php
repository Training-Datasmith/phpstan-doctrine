<?php

declare (strict_types=1);
namespace Php_Stan\Type\Doctrine\Query_Builder;

use function count;
use Doctrine\ORM\Entity_Manager_Interface;
use Doctrine\ORM\Entity_Repository;
use Doctrine\ORM\Query_Builder;
use Php_Parser\Node\Expr;
use Php_Parser\Node\Expr\Call_Like;
use Php_Parser\Node\Expr\Method_Call;
use Php_Parser\Node\Expr\Static_Call;
use Php_Parser\Node\Identifier;
use Php_Parser\Node\Name;
use Php_Stan\Analyser\Scope;
use Php_Stan\Reflection\Method_Reflection;
use Php_Stan\Reflection\Parameters_Acceptor_Selector;
use Php_Stan\Type\Expression_Type_Resolver_Extension;
use Php_Stan\Type\Object_Type;
use Php_Stan\Type\Type;
use Php_Stan\Type\Type_Combinator;
class Return_Query_Builder_Expression_Type_Resolver_Extension implements Expression_Type_Resolver_Extension
{
    private Other_Method_Query_Builder_Parser $other_method_query_builder_parser;
    public function __construct(Other_Method_Query_Builder_Parser $other_method_query_builder_parser)
    {
        $this->other_method_query_builder_parser = $other_method_query_builder_parser;
    }
    public function get_type(Expr $expr, Scope $scope): ?Type
    {
        if (!$expr instanceof Method_Call && !$expr instanceof Static_Call) {
            return null;
        }
        if ($expr->is_first_class_callable()) {
            return null;
        }
        $method_reflection = $this->get_method_reflection($expr, $scope);
        if ($method_reflection === null) {
            return null;
        }
        $return_type = Parameters_Acceptor_Selector::select_from_args($scope, $expr->get_args(), $method_reflection->get_variants())->get_return_type();
        $returns_query_builder = (new Object_Type(Query_Builder::class))->is_super_type_of($return_type)->yes();
        if (!$returns_query_builder) {
            return null;
        }
        $query_builder_types = $this->other_method_query_builder_parser->find_query_builder_types_in_called_method($scope, $method_reflection);
        if (count($query_builder_types) === 0) {
            return null;
        }
        return Type_Combinator::union(...$query_builder_types);
    }
    /**
     * @param StaticCall|MethodCall $call
     */
    private function get_method_reflection(Call_Like $call, Scope $scope): ?Method_Reflection
    {
        if (!$call->name instanceof Identifier) {
            return null;
        }
        if ($call instanceof Method_Call) {
            $caller_type = $scope->get_type($call->var);
        } else {
            if (!$call->class instanceof Name) {
                return null;
            }
            $caller_type = $scope->resolve_type_by_name($call->class);
        }
        $method_name = $call->name->name;
        foreach ($caller_type->get_object_class_reflections() as $caller_class_reflection) {
            if ($caller_class_reflection->is(Query_Builder::class)) {
                return null;
                // covered by QueryBuilderMethodDynamicReturnTypeExtension
            }
            if ($caller_class_reflection->is(Entity_Repository::class) && $method_name === 'createQueryBuilder') {
                return null;
                // covered by EntityRepositoryCreateQueryBuilderDynamicReturnTypeExtension
            }
            if ($caller_class_reflection->is(Entity_Manager_Interface::class) && $method_name === 'createQueryBuilder') {
                return null;
                // no need to dive there
            }
        }
        return $scope->get_method_reflection($caller_type, $method_name);
    }
}