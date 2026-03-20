<?php

declare (strict_types=1);
namespace Php_Stan\Rules\Doctrine\ORM;

use Php_Parser\Node;
use Php_Parser\Node\Stmt\Class_Method;
use Php_Stan\Analyser\Scope;
use Php_Stan\Rules\Rule;
use Php_Stan\Rules\Rule_Error_Builder;
use Php_Stan\Should_Not_Happen_Exception;
use Php_Stan\Type\Doctrine\Object_Metadata_Resolver;
use function sprintf;
/**
 * @implements Rule<ClassMethod>
 */
class Entity_Constructor_Not_Final_Rule implements Rule
{
    private Object_Metadata_Resolver $object_metadata_resolver;
    public function __construct(Object_Metadata_Resolver $object_metadata_resolver)
    {
        $this->object_metadata_resolver = $object_metadata_resolver;
    }
    public function get_node_type(): string
    {
        return Class_Method::class;
    }
    public function process_node(Node $node, Scope $scope): array
    {
        if ($node->name->name !== '__construct') {
            return [];
        }
        if (!$node->is_final()) {
            return [];
        }
        $class_reflection = $scope->get_class_reflection();
        if ($class_reflection === null) {
            throw new Should_Not_Happen_Exception();
        }
        if ($this->object_metadata_resolver->is_transient($class_reflection->get_name())) {
            return [];
        }
        $metadata = $this->object_metadata_resolver->get_class_metadata($class_reflection->get_name());
        if ($metadata !== null && $metadata->is_embedded_class === true) {
            return [];
        }
        return [Rule_Error_Builder::message(sprintf('Constructor of class %s is final which can cause problems with proxies.', $class_reflection->get_display_name()))->identifier('doctrine.finalConstructor')->build()];
    }
}