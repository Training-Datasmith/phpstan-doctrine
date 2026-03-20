<?php

declare (strict_types=1);
namespace Php_Stan\Type\Doctrine\Query;

use Doctrine\ORM\Abstract_Query;
use Php_Parser\Node\Expr\Method_Call;
use Php_Stan\Analyser\Scope;
use Php_Stan\Reflection\Method_Reflection;
use Php_Stan\Reflection\Parameters_Acceptor_Selector;
use Php_Stan\Should_Not_Happen_Exception;
use Php_Stan\Type\Doctrine\Hydration_Mode_Return_Type_Resolver;
use Php_Stan\Type\Doctrine\Object_Metadata_Resolver;
use Php_Stan\Type\Dynamic_Method_Return_Type_Extension;
use Php_Stan\Type\Null_Type;
use Php_Stan\Type\Type;
final class Query_Result_Dynamic_Return_Type_Extension implements Dynamic_Method_Return_Type_Extension
{
    private const METHOD_HYDRATION_MODE_ARG = ['getResult' => 0, 'toIterable' => 1, 'execute' => 1, 'executeIgnoreQueryCache' => 1, 'executeUsingQueryCache' => 1, 'getOneOrNullResult' => 0, 'getSingleResult' => 0];
    private Object_Metadata_Resolver $object_metadata_resolver;
    private Hydration_Mode_Return_Type_Resolver $hydration_mode_return_type_resolver;
    public function __construct(Object_Metadata_Resolver $object_metadata_resolver, Hydration_Mode_Return_Type_Resolver $hydration_mode_return_type_resolver)
    {
        $this->object_metadata_resolver = $object_metadata_resolver;
        $this->hydration_mode_return_type_resolver = $hydration_mode_return_type_resolver;
    }
    public function get_class(): string
    {
        return Abstract_Query::class;
    }
    public function is_method_supported(Method_Reflection $method_reflection): bool
    {
        return isset(self::METHOD_HYDRATION_MODE_ARG[$method_reflection->get_name()]);
    }
    public function get_type_from_method_call(Method_Reflection $method_reflection, Method_Call $method_call, Scope $scope): ?Type
    {
        $method_name = $method_reflection->get_name();
        if (!isset(self::METHOD_HYDRATION_MODE_ARG[$method_name])) {
            throw new Should_Not_Happen_Exception();
        }
        $arg_index = self::METHOD_HYDRATION_MODE_ARG[$method_name];
        $args = $method_call->get_args();
        if (isset($args[$arg_index])) {
            $hydration_mode = $scope->get_type($args[$arg_index]->value);
        } else {
            $parameters_acceptor = Parameters_Acceptor_Selector::select_from_args($scope, $method_call->get_args(), $method_reflection->get_variants());
            $parameter = $parameters_acceptor->get_parameters()[$arg_index];
            $hydration_mode = $parameter->get_default_value() ?? new Null_Type();
        }
        $query_type = $scope->get_type($method_call->var);
        return $this->hydration_mode_return_type_resolver->get_method_return_type_for_hydration_mode($method_reflection->get_name(), $hydration_mode, $query_type->get_template_type(Abstract_Query::class, 'TKey'), $query_type->get_template_type(Abstract_Query::class, 'TResult'), $this->object_metadata_resolver->get_object_manager());
    }
}