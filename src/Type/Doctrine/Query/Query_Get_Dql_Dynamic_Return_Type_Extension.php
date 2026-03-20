<?php

declare (strict_types=1);
namespace Php_Stan\Type\Doctrine\Query;

use function count;
use Php_Parser\Node\Expr\Method_Call;
use Php_Stan\Analyser\Scope;
use Php_Stan\Reflection\Method_Reflection;
use Php_Stan\Type\Constant\Constant_String_Type;
use Php_Stan\Type\Doctrine\Doctrine_Type_Utils;
use Php_Stan\Type\Dynamic_Method_Return_Type_Extension;
use Php_Stan\Type\Type;
use Php_Stan\Type\Type_Combinator;
class Query_Get_Dql_Dynamic_Return_Type_Extension implements Dynamic_Method_Return_Type_Extension
{
    public function get_class(): string
    {
        return 'Doctrine\ORM\Query';
    }
    public function is_method_supported(Method_Reflection $method_reflection): bool
    {
        return $method_reflection->get_name() === 'getDQL';
    }
    public function get_type_from_method_call(Method_Reflection $method_reflection, Method_Call $method_call, Scope $scope): ?Type
    {
        $called_on_type = $scope->get_type($method_call->var);
        $query_types = Doctrine_Type_Utils::get_query_types($called_on_type);
        if (count($query_types) === 0) {
            return null;
        }
        $dqls = [];
        foreach ($query_types as $query_type) {
            $dqls[] = new Constant_String_Type($query_type->get_dql());
        }
        return Type_Combinator::union(...$dqls);
    }
}