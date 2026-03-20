<?php

declare (strict_types=1);
namespace Php_Stan\Rules\Doctrine\ORM;

use function get_class;
use function in_array;
use Php_Parser\Node;
use Php_Stan\Analyser\Scope;
use Php_Stan\Node\Class_Property_Node;
use Php_Stan\Rules\Rule;
use Php_Stan\Rules\Rule_Error_Builder;
use Php_Stan\Type\Doctrine\Object_Metadata_Resolver;
use Php_Stan\Type\Error_Type;
use Php_Stan\Type\Iterable_Type;
use Php_Stan\Type\Mixed_Type;
use Php_Stan\Type\Never_Type;
use Php_Stan\Type\Object_Type;
use Php_Stan\Type\Type_Combinator;
use Php_Stan\Type\Typehint_Helper;
use Php_Stan\Type\Verbosity_Level;
use function sprintf;
use Throwable;
/**
 * @implements Rule<ClassPropertyNode>
 */
class Entity_Relation_Rule implements Rule
{
    private Object_Metadata_Resolver $object_metadata_resolver;
    private bool $allow_nullable_property_for_required_field;
    public function __construct(Object_Metadata_Resolver $object_metadata_resolver, bool $allow_nullable_property_for_required_field)
    {
        $this->object_metadata_resolver = $object_metadata_resolver;
        $this->allow_nullable_property_for_required_field = $allow_nullable_property_for_required_field;
    }
    public function get_node_type(): string
    {
        return Class_Property_Node::class;
    }
    public function process_node(Node $node, Scope $scope): array
    {
        $class = $scope->get_class_reflection();
        if ($class === null) {
            return [];
        }
        $class_name = $class->get_name();
        $metadata = $this->object_metadata_resolver->get_class_metadata($class_name);
        if ($metadata === null) {
            return [];
        }
        $property_name = $node->get_name();
        if (!isset($metadata->association_mappings[$property_name])) {
            return [];
        }
        $association_mapping = $metadata->association_mappings[$property_name];
        $identifiers = [];
        try {
            $identifiers = $metadata->get_identifier_field_names();
        } catch (Throwable $e) {
            $mapping_exception = 'Doctrine\ORM\Mapping\MappingException';
            if (!$e instanceof $mapping_exception) {
                throw $e;
            }
        }
        $column_type = null;
        $to_many = false;
        if ((bool) ($association_mapping['type'] & 3)) {
            // ClassMetadata::TO_ONE
            $column_type = new Object_Type($association_mapping['targetEntity']);
            if (in_array($property_name, $identifiers, true)) {
                $nullable = false;
            } else {
                /** @var bool $nullable */
                $nullable = $association_mapping['joinColumns'][0]['nullable'] ?? true;
            }
            if ($nullable) {
                $column_type = Type_Combinator::add_null($column_type);
            }
        } elseif ((bool) ($association_mapping['type'] & 12)) {
            // ClassMetadata::TO_MANY
            $to_many = true;
            $column_type = Type_Combinator::intersect(new Object_Type('Doctrine\Common\Collections\Collection'), new Iterable_Type(new Mixed_Type(), new Object_Type($association_mapping['targetEntity'])));
        }
        $php_doc_type = $node->get_php_doc_type();
        $native_type = $node->get_native_type() ?? new Mixed_Type();
        $property_type = Typehint_Helper::decide_type($native_type, $php_doc_type);
        $errors = [];
        if ($column_type !== null) {
            if (get_class($property_type) === Mixed_Type::class || $property_type instanceof Error_Type || $property_type instanceof Never_Type) {
                return [];
            }
            $collection_object_type = new Object_Type('Doctrine\Common\Collections\Collection');
            $property_type_to_check_against = $property_type;
            if ($to_many && $collection_object_type->is_super_type_of($property_type)->yes() && $property_type->is_iterable()->yes()) {
                $property_type_to_check_against = Type_Combinator::intersect($collection_object_type, new Iterable_Type(new Mixed_Type(true), $property_type->get_iterable_value_type()));
            }
            if (!$property_type_to_check_against->is_super_type_of($column_type)->yes()) {
                $errors[] = Rule_Error_Builder::message(sprintf('Property %s::$%s type mapping mismatch: database can contain %s but property expects %s.', $class_name, $property_name, $column_type->describe(Verbosity_Level::type_only()), $property_type->describe(Verbosity_Level::type_only())))->identifier('doctrine.associationType')->build();
            }
            if (!$column_type->is_super_type_of($this->allow_nullable_property_for_required_field ? Type_Combinator::remove_null($property_type) : $property_type)->yes()) {
                $errors[] = Rule_Error_Builder::message(sprintf('Property %s::$%s type mapping mismatch: property can contain %s but database expects %s.', $class_name, $property_name, $property_type->describe(Verbosity_Level::type_only()), $column_type->describe(Verbosity_Level::type_only())))->identifier('doctrine.associationType')->build();
            }
        }
        return $errors;
    }
}