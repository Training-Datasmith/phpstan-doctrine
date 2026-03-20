<?php

declare (strict_types=1);
namespace Php_Stan\Type\Doctrine\Collection;

use Php_Parser\Node\Expr\Method_Call;
use Php_Stan\Analyser\Scope;
use Php_Stan\Analyser\Specified_Types;
use Php_Stan\Analyser\Type_Specifier;
use Php_Stan\Analyser\Type_Specifier_Aware_Extension;
use Php_Stan\Analyser\Type_Specifier_Context;
use Php_Stan\Reflection\Method_Reflection;
use Php_Stan\Type\Constant\Constant_Boolean_Type;
use Php_Stan\Type\Method_Type_Specifying_Extension;
final class Is_Empty_Type_Specifying_Extension implements Method_Type_Specifying_Extension, Type_Specifier_Aware_Extension
{
    private const IS_EMPTY_METHOD_NAME = 'isEmpty';
    private const FIRST_METHOD_NAME = 'first';
    private const LAST_METHOD_NAME = 'last';
    private Type_Specifier $type_specifier;
    /** @var class-string */
    private string $collection_class;
    /**
     * @param class-string $collectionClass
     */
    public function __construct(string $collection_class)
    {
        $this->collection_class = $collection_class;
    }
    public function get_class(): string
    {
        return $this->collection_class;
    }
    public function is_method_supported(Method_Reflection $method_reflection, Method_Call $node, Type_Specifier_Context $context): bool
    {
        return $method_reflection->get_declaring_class()->is($this->collection_class) && $method_reflection->get_name() === self::IS_EMPTY_METHOD_NAME;
    }
    public function specify_types(Method_Reflection $method_reflection, Method_Call $node, Scope $scope, Type_Specifier_Context $context): Specified_Types
    {
        $first = $this->type_specifier->create(new Method_Call($node->var, self::FIRST_METHOD_NAME), new Constant_Boolean_Type(false), $context, $scope);
        $last = $this->type_specifier->create(new Method_Call($node->var, self::LAST_METHOD_NAME), new Constant_Boolean_Type(false), $context, $scope);
        return $first->union_with($last);
    }
    public function set_type_specifier(Type_Specifier $type_specifier): void
    {
        $this->type_specifier = $type_specifier;
    }
}