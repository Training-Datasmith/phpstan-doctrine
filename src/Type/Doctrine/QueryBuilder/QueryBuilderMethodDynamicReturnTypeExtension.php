<?php

declare (strict_types=1);
namespace Php_Stan\Type\Doctrine\Query_Builder;

use function count;
use Doctrine\ORM\Query_Builder;
use function in_array;
use Php_Parser\Node\Expr\Method_Call;
use Php_Parser\Node\Identifier;
use Php_Stan\Analyser\Scope;
use Php_Stan\Reflection\Method_Reflection;
use Php_Stan\Type\Doctrine\Doctrine_Type_Utils;
use Php_Stan\Type\Dynamic_Method_Return_Type_Extension;
use Php_Stan\Type\Mixed_Type;
use Php_Stan\Type\Object_Type;
use Php_Stan\Type\Type;
use Php_Stan\Type\Type_Combinator;
use function strtolower;
class Query_Builder_Method_Dynamic_Return_Type_Extension implements Dynamic_Method_Return_Type_Extension
{
    private const MAX_COMBINATIONS = 16;
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
        $return_type = $method_reflection->get_variants()[0]->get_return_type();
        if ($return_type instanceof Mixed_Type) {
            return false;
        }
        return (new Object_Type(Query_Builder::class))->is_super_type_of($return_type)->yes();
    }
    public function get_type_from_method_call(Method_Reflection $method_reflection, Method_Call $method_call, Scope $scope): Type
    {
        $called_on_type = $scope->get_type($method_call->var);
        if (!$method_call->name instanceof Identifier) {
            return $called_on_type;
        }
        $lower_method_name = strtolower($method_call->name->to_string());
        if (in_array($lower_method_name, ['setparameter', 'setparameters'], true)) {
            return $called_on_type;
        }
        $query_builder_types = Doctrine_Type_Utils::get_query_builder_types($called_on_type);
        if (count($query_builder_types) === 0) {
            return $called_on_type;
        }
        if (count($query_builder_types) > self::MAX_COMBINATIONS) {
            return $called_on_type;
        }
        $result_types = [];
        foreach ($query_builder_types as $query_builder_type) {
            $result_types[] = $query_builder_type->append($method_call);
        }
        return Type_Combinator::union(...$result_types);
    }
}