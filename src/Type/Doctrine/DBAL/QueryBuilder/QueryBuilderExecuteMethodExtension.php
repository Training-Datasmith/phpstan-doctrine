<?php

declare (strict_types=1);
namespace Php_Stan\Type\Doctrine\DBAL\Query_Builder;

use Doctrine\DBAL\Driver\Result;
use Doctrine\DBAL\Driver\Result_Statement;
use Doctrine\DBAL\Query\Query_Builder;
use Php_Parser\Node\Expr\Method_Call;
use Php_Parser\Node\Identifier;
use Php_Stan\Analyser\Scope;
use Php_Stan\Reflection\Method_Reflection;
use Php_Stan\Reflection\Parameters_Acceptor_Selector;
use Php_Stan\Reflection\Reflection_Provider;
use Php_Stan\Type\Dynamic_Method_Return_Type_Extension;
use Php_Stan\Type\Object_Type;
use Php_Stan\Type\Type;
use Php_Stan\Type\Type_Combinator;
class Query_Builder_Execute_Method_Extension implements Dynamic_Method_Return_Type_Extension
{
    private Reflection_Provider $reflection_provider;
    public function __construct(Reflection_Provider $reflection_provider)
    {
        $this->reflection_provider = $reflection_provider;
    }
    public function get_class(): string
    {
        return Query_Builder::class;
    }
    public function is_method_supported(Method_Reflection $method_reflection): bool
    {
        return $method_reflection->get_name() === 'execute';
    }
    public function get_type_from_method_call(Method_Reflection $method_reflection, Method_Call $method_call, Scope $scope): Type
    {
        $default_return_type = Parameters_Acceptor_Selector::select_from_args($scope, $method_call->get_args(), $method_reflection->get_variants())->get_return_type();
        $query_builder_type = new Object_Type(Query_Builder::class);
        $var = $method_call->var;
        while ($var instanceof Method_Call) {
            $var_type = $scope->get_type($var->var);
            if (!$query_builder_type->is_super_type_of($var_type)->yes()) {
                return $default_return_type;
            }
            $name_object = $var->name;
            if (!$name_object instanceof Identifier) {
                return $default_return_type;
            }
            $name = $name_object->to_string();
            if ($name === 'select' || $name === 'addSelect') {
                if ($this->reflection_provider->has_class(Result_Statement::class)) {
                    return Type_Combinator::intersect($default_return_type, new Object_Type(Result_Statement::class));
                }
                return Type_Combinator::intersect($default_return_type, new Object_Type(Result::class));
            }
            $var = $var->var;
        }
        return $default_return_type;
    }
}