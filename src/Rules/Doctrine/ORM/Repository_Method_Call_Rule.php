<?php

declare (strict_types=1);
namespace Php_Stan\Rules\Doctrine\ORM;

use function count;
use Doctrine\Persistence\Object_Repository;
use function in_array;
use Php_Parser\Node;
use Php_Stan\Analyser\Scope;
use Php_Stan\Rules\Rule;
use Php_Stan\Rules\Rule_Error_Builder;
use Php_Stan\Type\Doctrine\Object_Metadata_Resolver;
use Php_Stan\Type\Verbosity_Level;
use function sprintf;
/**
 * @implements Rule<Node\Expr\MethodCall>
 */
class Repository_Method_Call_Rule implements Rule
{
    private Object_Metadata_Resolver $object_metadata_resolver;
    public function __construct(Object_Metadata_Resolver $object_metadata_resolver)
    {
        $this->object_metadata_resolver = $object_metadata_resolver;
    }
    public function get_node_type(): string
    {
        return Node\Expr\Method_Call::class;
    }
    public function process_node(Node $node, Scope $scope): array
    {
        if (!isset($node->get_args()[0])) {
            return [];
        }
        $arg_type = $scope->get_type($node->get_args()[0]->value);
        $called_on_type = $scope->get_type($node->var);
        $entity_class_type = $called_on_type->get_template_type(Object_Repository::class, 'TEntityClass');
        /** @var list<class-string> $entityClassNames */
        $entity_class_names = $entity_class_type->get_object_class_names();
        if (count($entity_class_names) !== 1) {
            return [];
        }
        $method_name_identifier = $node->name;
        if (!$method_name_identifier instanceof Node\Identifier) {
            return [];
        }
        $method_name = $method_name_identifier->to_string();
        if (!in_array($method_name, ['findBy', 'findOneBy', 'count'], true)) {
            return [];
        }
        $class_metadata = $this->object_metadata_resolver->get_class_metadata($entity_class_names[0]);
        if ($class_metadata === null) {
            return [];
        }
        $messages = [];
        foreach ($arg_type->get_constant_arrays() as $constant_array) {
            foreach ($constant_array->get_key_types() as $key_type) {
                foreach ($key_type->get_constant_strings() as $field_name) {
                    if ($class_metadata->has_field($field_name->get_value())) {
                        continue;
                    }
                    if ($class_metadata->has_association($field_name->get_value())) {
                        continue;
                    }
                    $messages[] = Rule_Error_Builder::message(sprintf('Call to method %s::%s() - entity %s does not have a field named $%s.', $called_on_type->describe(Verbosity_Level::type_only()), $method_name, $entity_class_names[0], $field_name->get_value()))->identifier(sprintf('doctrine.%sArgument', $method_name))->build();
                }
            }
        }
        return $messages;
    }
}