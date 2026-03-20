<?php

declare (strict_types=1);
namespace Php_Stan\Rules\Doctrine\ORM;

use AssertionError;
use function count;
use Doctrine\ORM\Query\Query_Exception;
use Php_Parser\Node;
use Php_Stan\Analyser\Scope;
use Php_Stan\Rules\Rule;
use Php_Stan\Rules\Rule_Error_Builder;
use Php_Stan\Type\Doctrine\Object_Metadata_Resolver;
use Php_Stan\Type\Object_Type;
use function sprintf;
/**
 * @implements Rule<Node\Expr\MethodCall>
 */
class Dql_Rule implements Rule
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
        if (!$node->name instanceof Node\Identifier) {
            return [];
        }
        if (count($node->get_args()) === 0) {
            return [];
        }
        $method_name = $node->name->to_lower_string();
        if ($method_name !== 'createquery') {
            return [];
        }
        $called_on_type = $scope->get_type($node->var);
        $entity_manager_interface = 'Doctrine\ORM\EntityManagerInterface';
        if (!(new Object_Type($entity_manager_interface))->is_super_type_of($called_on_type)->yes()) {
            return [];
        }
        $dqls = $scope->get_type($node->get_args()[0]->value)->get_constant_strings();
        if (count($dqls) === 0) {
            return [];
        }
        $object_manager = $this->object_metadata_resolver->get_object_manager();
        if ($object_manager === null) {
            return [];
        }
        if (!$object_manager instanceof $entity_manager_interface) {
            return [];
        }
        $messages = [];
        foreach ($dqls as $dql) {
            $query = $object_manager->create_query($dql->get_value());
            try {
                $query->get_ast();
            } catch (Query_Exception $e) {
                $messages[] = Rule_Error_Builder::message(sprintf('DQL: %s', $e->get_message()))->identifier('doctrine.dql')->build();
            } catch (AssertionError $e) {
                continue;
            }
        }
        return $messages;
    }
}