<?php

declare (strict_types=1);
namespace Php_Stan\Type\Doctrine\Query_Builder;

use function count;
use Doctrine\ORM\Query_Builder;
use Php_Parser\Node\Expr\Method_Call;
use Php_Parser\Node\Identifier;
use Php_Stan\Analyser\Scope;
use Php_Stan\Analyser\Specified_Types;
use Php_Stan\Analyser\Type_Specifier;
use Php_Stan\Analyser\Type_Specifier_Aware_Extension;
use Php_Stan\Analyser\Type_Specifier_Context;
use Php_Stan\Reflection\Method_Reflection;
use Php_Stan\Reflection\Parameters_Acceptor_Selector;
use Php_Stan\Type\Doctrine\Doctrine_Type_Utils;
use Php_Stan\Type\Method_Type_Specifying_Extension;
use Php_Stan\Type\Mixed_Type;
use Php_Stan\Type\Object_Type;
use Php_Stan\Type\Type_Combinator;
class Query_Builder_Type_Specifying_Extension implements Method_Type_Specifying_Extension, Type_Specifier_Aware_Extension
{
    private const MAX_COMBINATIONS = 16;
    /** @var class-string|null */
    private ?string $query_builder_class = null;
    private Type_Specifier $type_specifier;
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
    public function set_type_specifier(Type_Specifier $type_specifier): void
    {
        $this->type_specifier = $type_specifier;
    }
    public function is_method_supported(Method_Reflection $method_reflection, Method_Call $node, Type_Specifier_Context $context): bool
    {
        return $context->null();
    }
    public function specify_types(Method_Reflection $method_reflection, Method_Call $node, Scope $scope, Type_Specifier_Context $context): Specified_Types
    {
        if (!$scope->is_in_first_level_statement()) {
            return new Specified_Types([]);
        }
        if (!$node->name instanceof Identifier) {
            return new Specified_Types([]);
        }
        $return_type = Parameters_Acceptor_Selector::select_from_args($scope, $node->get_args(), $method_reflection->get_variants())->get_return_type();
        if ($return_type instanceof Mixed_Type) {
            return new Specified_Types([]);
        }
        if (!(new Object_Type(Query_Builder::class))->is_super_type_of($return_type)->yes()) {
            return new Specified_Types([]);
        }
        $called_on_type = $scope->get_type($node->var);
        $query_builder_types = Doctrine_Type_Utils::get_query_builder_types($called_on_type);
        if (count($query_builder_types) === 0) {
            return new Specified_Types([]);
        }
        if (count($query_builder_types) > self::MAX_COMBINATIONS) {
            return new Specified_Types([]);
        }
        $query_builder_node = $node;
        while ($query_builder_node instanceof Method_Call) {
            $query_builder_node = $query_builder_node->var;
        }
        // If the variable is not a query builder, there is nothing to specify
        if (!(new Object_Type(Query_Builder::class))->is_super_type_of($scope->get_type($query_builder_node))->yes()) {
            return new Specified_Types([]);
        }
        $result_types = [];
        foreach ($query_builder_types as $query_builder_type) {
            $result_types[] = $query_builder_type->append($node);
        }
        return $this->type_specifier->create($query_builder_node, Type_Combinator::union(...$result_types), Type_Specifier_Context::create_truthy(), $scope)->set_always_overwrite_types();
    }
}