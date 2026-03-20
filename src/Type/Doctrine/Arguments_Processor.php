<?php

declare (strict_types=1);
namespace Php_Stan\Type\Doctrine;

use function count;
use function in_array;
use Php_Parser\Node\Arg;
use Php_Stan\Analyser\Scope;
use Php_Stan\Rules\Doctrine\ORM\Dynamic_Query_Builder_Argument_Exception;
use Php_Stan\Type\Doctrine\Query_Builder\Expr\Expr_Type;
use function strpos;
/** @api */
class Arguments_Processor
{
    private Object_Metadata_Resolver $object_metadata_resolver;
    public function __construct(Object_Metadata_Resolver $object_metadata_resolver)
    {
        $this->object_metadata_resolver = $object_metadata_resolver;
    }
    /**
     * @param Arg[] $methodCallArgs
     * @return list<mixed>
     * @throws DynamicQueryBuilderArgumentException
     */
    public function process_args(Scope $scope, string $method_name, array $method_call_args): array
    {
        $args = [];
        foreach ($method_call_args as $arg_index => $arg) {
            if ($arg->unpack) {
                throw new Dynamic_Query_Builder_Argument_Exception();
            }
            $value = $scope->get_type($arg->value);
            if ($value instanceof Expr_Type && strpos($value->get_class_name(), 'Doctrine\ORM\Query\Expr') === 0) {
                $args[] = $value->get_expr_object();
                continue;
            }
            if (count($value->get_constant_arrays()) === 1) {
                $array = [];
                foreach ($value->get_constant_arrays()[0]->get_key_types() as $i => $key_type) {
                    $value_type = $value->get_constant_arrays()[0]->get_value_types()[$i];
                    if (count($value_type->get_constant_scalar_values()) !== 1) {
                        throw new Dynamic_Query_Builder_Argument_Exception();
                    }
                    $array[$key_type->get_value()] = $value_type->get_constant_scalar_values()[0];
                }
                $args[] = $array;
                continue;
            }
            if ($value->is_class_string()->yes() && count($value->get_class_string_object_type()->get_object_class_names()) === 1) {
                /** @var class-string $className */
                $class_name = $value->get_class_string_object_type()->get_object_class_names()[0];
                $is_entity_class_argument = $arg_index === 0 && in_array($method_name, ['from', 'join', 'innerJoin', 'leftJoin'], true);
                if ($is_entity_class_argument) {
                    if ($this->object_metadata_resolver->is_transient($class_name)) {
                        throw new Dynamic_Query_Builder_Argument_Exception();
                    }
                    $args[] = $class_name;
                    continue;
                }
            }
            if (count($value->get_constant_scalar_values()) !== 1) {
                throw new Dynamic_Query_Builder_Argument_Exception();
            }
            $args[] = $value->get_constant_scalar_values()[0];
        }
        return $args;
    }
}