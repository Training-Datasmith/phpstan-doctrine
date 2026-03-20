<?php

declare (strict_types=1);
namespace Php_Stan\Rules\Doctrine\ORM;

use Doctrine\Common\Annotations\Annotation_Exception;
use Doctrine\ORM\Mapping\Mapping_Exception;
use Php_Parser\Node;
use Php_Stan\Analyser\Scope;
use Php_Stan\Node\In_Class_Node;
use Php_Stan\Rules\Rule;
use Php_Stan\Rules\Rule_Error_Builder;
use Php_Stan\Type\Doctrine\Object_Metadata_Resolver;
use Reflection_Exception;
/**
 * @implements Rule<InClassNode>
 */
class Entity_Mapping_Exception_Rule implements Rule
{
    private Object_Metadata_Resolver $object_metadata_resolver;
    public function __construct(Object_Metadata_Resolver $object_metadata_resolver)
    {
        $this->object_metadata_resolver = $object_metadata_resolver;
    }
    public function get_node_type(): string
    {
        return In_Class_Node::class;
    }
    public function process_node(Node $node, Scope $scope): array
    {
        $class = $scope->get_class_reflection();
        if ($class === null) {
            return [];
        }
        $object_manager = $this->object_metadata_resolver->get_object_manager();
        if ($object_manager === null) {
            return [];
        }
        $class_name = $class->get_name();
        try {
            if ($object_manager->get_metadata_factory()->is_transient($class_name)) {
                return [];
            }
        } catch (Reflection_Exception $e) {
            return [];
        }
        try {
            $object_manager->get_class_metadata($class_name);
        } catch (\Doctrine\Persistence\Mapping\Mapping_Exception|Mapping_Exception|Annotation_Exception $e) {
            return [Rule_Error_Builder::message($e->get_message())->non_ignorable()->identifier('doctrine.mapping')->build()];
        }
        return [];
    }
}