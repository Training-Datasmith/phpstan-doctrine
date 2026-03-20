<?php

declare (strict_types=1);
namespace Php_Stan\Type\Doctrine\Query_Builder;

use Php_Parser\Node\Expr\Method_Call;
use Php_Stan\Analyser\Scope;
use Php_Stan\Reflection\Method_Reflection;
use Php_Stan\Type\Dynamic_Method_Return_Type_Extension;
use Php_Stan\Type\Type;
class Create_Query_Builder_Dynamic_Return_Type_Extension implements Dynamic_Method_Return_Type_Extension
{
    private ?string $query_builder_class = null;
    public function __construct(?string $query_builder_class)
    {
        $this->query_builder_class = $query_builder_class;
    }
    public function get_class(): string
    {
        return 'Doctrine\ORM\EntityManagerInterface';
    }
    public function is_method_supported(Method_Reflection $method_reflection): bool
    {
        return $method_reflection->get_name() === 'createQueryBuilder';
    }
    public function get_type_from_method_call(Method_Reflection $method_reflection, Method_Call $method_call, Scope $scope): Type
    {
        return new Branching_Query_Builder_Type($this->query_builder_class ?? 'Doctrine\ORM\QueryBuilder');
    }
}