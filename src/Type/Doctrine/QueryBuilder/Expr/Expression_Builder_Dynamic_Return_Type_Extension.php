<?php

declare (strict_types=1);
namespace Php_Stan\Type\Doctrine\Query_Builder\Expr;

use function get_class;
use function is_object;
use function method_exists;
use Php_Parser\Node\Expr\Method_Call;
use Php_Stan\Analyser\Scope;
use Php_Stan\Reflection\Method_Reflection;
use Php_Stan\Rules\Doctrine\ORM\Dynamic_Query_Builder_Argument_Exception;
use Php_Stan\Type\Doctrine\Arguments_Processor;
use Php_Stan\Type\Doctrine\Object_Metadata_Resolver;
use Php_Stan\Type\Dynamic_Method_Return_Type_Extension;
use Php_Stan\Type\Type;
use Throwable;
class Expression_Builder_Dynamic_Return_Type_Extension implements Dynamic_Method_Return_Type_Extension
{
    private Object_Metadata_Resolver $object_metadata_resolver;
    private Arguments_Processor $arguments_processor;
    public function __construct(Object_Metadata_Resolver $object_metadata_resolver, Arguments_Processor $arguments_processor)
    {
        $this->object_metadata_resolver = $object_metadata_resolver;
        $this->arguments_processor = $arguments_processor;
    }
    public function get_class(): string
    {
        return 'Doctrine\ORM\Query\Expr';
    }
    public function is_method_supported(Method_Reflection $method_reflection): bool
    {
        return true;
    }
    public function get_type_from_method_call(Method_Reflection $method_reflection, Method_Call $method_call, Scope $scope): ?Type
    {
        $object_manager = $this->object_metadata_resolver->get_object_manager();
        if ($object_manager === null) {
            return null;
        }
        $entity_manager_interface = 'Doctrine\ORM\EntityManagerInterface';
        if (!$object_manager instanceof $entity_manager_interface) {
            return null;
        }
        $query_builder = $object_manager->create_query_builder();
        try {
            $args = $this->arguments_processor->process_args($scope, $method_reflection->get_name(), $method_call->get_args());
        } catch (Dynamic_Query_Builder_Argument_Exception $e) {
            return null;
        }
        $called_on_type = $scope->get_type($method_call->var);
        if ($called_on_type instanceof Expr_Type) {
            $expr = $called_on_type->get_expr_object();
        } else {
            $expr = $query_builder->expr();
        }
        if (!method_exists($expr, $method_reflection->get_name())) {
            return null;
        }
        try {
            $expr_value = $expr->{$method_reflection->get_name()}(...$args);
        } catch (Throwable $e) {
            return null;
        }
        if (is_object($expr_value)) {
            return new Expr_Type(get_class($expr_value), $expr_value);
        }
        return $scope->get_type_from_value($expr_value);
    }
}