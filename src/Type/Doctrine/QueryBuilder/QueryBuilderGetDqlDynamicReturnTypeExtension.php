<?php

declare (strict_types=1);
namespace Php_Stan\Type\Doctrine\Query_Builder;

use Php_Parser\Node\Expr\Method_Call;
use Php_Parser\Node\Identifier;
use Php_Stan\Analyser\Scope;
use Php_Stan\Reflection\Method_Reflection;
use Php_Stan\Type\Dynamic_Method_Return_Type_Extension;
use Php_Stan\Type\Type;
use Php_Stan\Type\Type_Combinator;
class Query_Builder_Get_Dql_Dynamic_Return_Type_Extension implements Dynamic_Method_Return_Type_Extension
{
    /** @var class-string|null */
    private ?string $query_builder_class = null;
    /**
     * @param class-string|null $queryBuilderClass
     */
    public function __construct(?string $query_builder_class)
    {
        $this->query_builder_class = $query_builder_class;
    }
    public function get_class(): string
    {
        return $this->query_builder_class ?? 'Doctrine\ORM\QueryBuilder';
    }
    public function is_method_supported(Method_Reflection $method_reflection): bool
    {
        return $method_reflection->get_name() === 'getDQL';
    }
    public function get_type_from_method_call(Method_Reflection $method_reflection, Method_Call $method_call, Scope $scope): Type
    {
        $type = $scope->get_type(new Method_Call(new Method_Call($method_call->var, new Identifier('getQuery')), new Identifier('getDQL')));
        return Type_Combinator::remove_null($type);
    }
}