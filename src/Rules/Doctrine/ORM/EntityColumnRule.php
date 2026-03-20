<?php

declare (strict_types=1);
namespace Php_Stan\Rules\Doctrine\ORM;

use function count;
use function get_class;
use function in_array;
use function is_array;
use function is_string;
use Php_Parser\Node;
use Php_Stan\Analyser\Scope;
use Php_Stan\Node\Class_Property_Node;
use Php_Stan\Reflection\Reflection_Provider;
use Php_Stan\Rules\Rule;
use Php_Stan\Rules\Rule_Error_Builder;
use Php_Stan\Type\Array_Type;
use Php_Stan\Type\Constant\Constant_String_Type;
use Php_Stan\Type\Doctrine\Descriptor_Not_Registered_Exception;
use Php_Stan\Type\Doctrine\Descriptor_Registry;
use Php_Stan\Type\Doctrine\Object_Metadata_Resolver;
use Php_Stan\Type\Error_Type;
use Php_Stan\Type\Mixed_Type;
use Php_Stan\Type\Never_Type;
use Php_Stan\Type\Object_Type;
use Php_Stan\Type\Type;
use Php_Stan\Type\Type_Combinator;
use Php_Stan\Type\Typehint_Helper;
use Php_Stan\Type\Type_Traverser;
use Php_Stan\Type\Type_Utils;
use Php_Stan\Type\Verbosity_Level;
use function sprintf;
use Throwable;
/**
 * @implements Rule<ClassPropertyNode>
 */
class Entity_Column_Rule implements Rule
{
    private Object_Metadata_Resolver $object_metadata_resolver;
    private Descriptor_Registry $descriptor_registry;
    private Reflection_Provider $reflection_provider;
    private bool $report_unknown_types;
    private bool $allow_nullable_property_for_required_field;
    public function __construct(Object_Metadata_Resolver $object_metadata_resolver, Descriptor_Registry $descriptor_registry, Reflection_Provider $reflection_provider, bool $report_unknown_types, bool $allow_nullable_property_for_required_field)
    {
        $this->object_metadata_resolver = $object_metadata_resolver;
        $this->descriptor_registry = $descriptor_registry;
        $this->reflection_provider = $reflection_provider;
        $this->report_unknown_types = $report_unknown_types;
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
        if (!isset($metadata->field_mappings[$property_name])) {
            return [];
        }
        $field_mapping = $metadata->field_mappings[$property_name];
        $errors = [];
        try {
            $descriptor = $this->descriptor_registry->get($field_mapping['type']);
        } catch (Descriptor_Not_Registered_Exception $e) {
            return $this->report_unknown_types ? [Rule_Error_Builder::message(sprintf('Property %s::$%s: Doctrine type "%s" does not have any registered descriptor.', $class_name, $property_name, $field_mapping['type']))->identifier('doctrine.descriptorNotFound')->build()] : [];
        }
        $writable_to_property_type = $descriptor->get_writable_to_property_type();
        $writable_to_database_type = $descriptor->get_writable_to_database_type();
        $enum_type_string = $field_mapping['enumType'] ?? null;
        if ($enum_type_string !== null) {
            if ($writable_to_database_type->is_array()->no() && $writable_to_property_type->is_array()->no()) {
                if ($this->reflection_provider->has_class($enum_type_string)) {
                    $enum_reflection = $this->reflection_provider->get_class($enum_type_string);
                    $backed_enum_type = $enum_reflection->get_backed_enum_type();
                    if ($backed_enum_type !== null) {
                        if (!$backed_enum_type->equals($writable_to_database_type) || !$backed_enum_type->equals($writable_to_property_type)) {
                            $errors[] = Rule_Error_Builder::message(sprintf('Property %s::$%s type mapping mismatch: backing type %s of enum %s does not match database type %s.', $class_name, $property_name, $backed_enum_type->describe(Verbosity_Level::type_only()), $enum_reflection->get_display_name(), $writable_to_database_type->describe(Verbosity_Level::type_only())))->identifier('doctrine.enumType')->build();
                        }
                    }
                }
                $enum_type = new Object_Type($enum_type_string);
                $writable_to_property_type = $enum_type;
                $writable_to_database_type = $enum_type;
            } else {
                $enum_type = new Object_Type($enum_type_string);
                if ($this->reflection_provider->has_class($enum_type_string)) {
                    $enum_reflection = $this->reflection_provider->get_class($enum_type_string);
                    $backed_enum_type = $enum_reflection->get_backed_enum_type();
                    if ($backed_enum_type !== null) {
                        if (!$backed_enum_type->equals($writable_to_database_type->get_iterable_value_type()) || !$backed_enum_type->equals($writable_to_property_type->get_iterable_value_type())) {
                            $errors[] = Rule_Error_Builder::message(sprintf('Property %s::$%s type mapping mismatch: backing type %s of enum %s does not match value type %s of the database type %s.', $class_name, $property_name, $backed_enum_type->describe(Verbosity_Level::type_only()), $enum_reflection->get_display_name(), $writable_to_database_type->get_iterable_value_type()->describe(Verbosity_Level::type_only()), $writable_to_database_type->describe(Verbosity_Level::type_only())))->identifier('doctrine.enumType')->build();
                        }
                    }
                }
                $writable_to_property_type = Type_Combinator::intersect(new Array_Type($writable_to_property_type->get_iterable_key_type(), $enum_type), ...Type_Utils::get_accessory_types($writable_to_property_type));
                $writable_to_database_type = Type_Combinator::intersect(new Array_Type($writable_to_database_type->get_iterable_key_type(), $enum_type), ...Type_Utils::get_accessory_types($writable_to_database_type));
            }
        } elseif ($field_mapping['type'] === 'enum') {
            $values = $field_mapping['options']['values'] ?? null;
            if (is_array($values)) {
                $enum_types = [];
                foreach ($values as $value) {
                    if (!is_string($value)) {
                        $enum_types = [];
                        break;
                    }
                    $enum_types[] = new Constant_String_Type($value);
                }
                if (count($enum_types) > 0) {
                    $union_type = Type_Combinator::union(...$enum_types);
                    $writable_to_property_type = $union_type;
                    $writable_to_database_type = $union_type;
                }
            }
        }
        $identifiers = [];
        if ($metadata->generator_type !== 5) {
            // ClassMetadata::GENERATOR_TYPE_NONE
            try {
                $identifiers = $metadata->get_identifier_field_names();
            } catch (Throwable $e) {
                $mapping_exception = 'Doctrine\ORM\Mapping\MappingException';
                if (!$e instanceof $mapping_exception) {
                    throw $e;
                }
            }
        }
        $nullable = isset($field_mapping['nullable']) && $field_mapping['nullable'] === true;
        if ($nullable) {
            $writable_to_property_type = Type_Combinator::add_null($writable_to_property_type);
            $writable_to_database_type = Type_Combinator::add_null($writable_to_database_type);
        }
        $php_doc_type = $node->get_php_doc_type();
        $native_type = $node->get_native_type() ?? new Mixed_Type();
        $property_type = Typehint_Helper::decide_type($native_type, $php_doc_type);
        if (get_class($property_type) === Mixed_Type::class || $property_type instanceof Error_Type || $property_type instanceof Never_Type) {
            return [];
        }
        // If the type descriptor does not precise the types inside the array, don't report errors if the field has a more precise type
        $property_transformed_type = $writable_to_property_type->equals(new Array_Type(new Mixed_Type(), new Mixed_Type())) ? Type_Traverser::map($property_type, static function (Type $type, callable $traverse): Type {
            if ($type instanceof Array_Type) {
                return new Array_Type(new Mixed_Type(), new Mixed_Type());
            }
            return $traverse($type);
        }) : $property_type;
        if (!$property_transformed_type->is_super_type_of($writable_to_property_type)->yes()) {
            $errors[] = Rule_Error_Builder::message(sprintf('Property %s::$%s type mapping mismatch: database can contain %s but property expects %s.', $class_name, $property_name, $writable_to_property_type->describe(Verbosity_Level::get_recommended_level_by_type($property_transformed_type, $writable_to_property_type)), $property_type->describe(Verbosity_Level::get_recommended_level_by_type($property_transformed_type, $writable_to_property_type))))->identifier('doctrine.columnType')->build();
        }
        if (!$writable_to_database_type->is_super_type_of($this->allow_nullable_property_for_required_field || in_array($property_name, $identifiers, true) && !$nullable ? Type_Combinator::remove_null($property_type) : $property_type)->yes()) {
            $errors[] = Rule_Error_Builder::message(sprintf('Property %s::$%s type mapping mismatch: property can contain %s but database expects %s.', $class_name, $property_name, $property_transformed_type->describe(Verbosity_Level::get_recommended_level_by_type($writable_to_database_type, $property_type)), $writable_to_database_type->describe(Verbosity_Level::get_recommended_level_by_type($writable_to_database_type, $property_type))))->identifier('doctrine.columnType')->build();
        }
        return $errors;
    }
}