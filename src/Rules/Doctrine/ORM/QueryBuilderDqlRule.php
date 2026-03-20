<?php

declare (strict_types=1);
namespace Php_Stan\Rules\Doctrine\ORM;

use function array_values;
use AssertionError;
use function count;
use Doctrine\ORM\Query\Query_Exception;
use Php_Parser\Node;
use Php_Parser\Node\Expr\Method_Call;
use Php_Stan\Analyser\Scope;
use Php_Stan\Rules\Rule;
use Php_Stan\Rules\Rule_Error_Builder;
use Php_Stan\Type\Doctrine\Doctrine_Type_Utils;
use Php_Stan\Type\Doctrine\Object_Metadata_Resolver;
use Php_Stan\Type\Object_Type;
use function sprintf;
use function strpos;
use Throwable;
/**
 * @implements Rule<Node\Expr\MethodCall>
 */
class Query_Builder_Dql_Rule implements Rule
{
    private Object_Metadata_Resolver $object_metadata_resolver;
    private bool $report_dynamic_query_builders;
    public function __construct(Object_Metadata_Resolver $object_metadata_resolver, bool $report_dynamic_query_builders)
    {
        $this->object_metadata_resolver = $object_metadata_resolver;
        $this->report_dynamic_query_builders = $report_dynamic_query_builders;
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
        if ($node->name->to_lower_string() !== 'getquery') {
            return [];
        }
        $called_on_type = $scope->get_type($node->var);
        $query_builder_types = Doctrine_Type_Utils::get_query_builder_types($called_on_type);
        if (count($query_builder_types) === 0) {
            if ($this->report_dynamic_query_builders && (new Object_Type('Doctrine\ORM\QueryBuilder'))->is_super_type_of($called_on_type)->yes()) {
                return [Rule_Error_Builder::message('Could not analyse QueryBuilder with unknown beginning.')->identifier('doctrine.queryBuilderDynamic')->build()];
            }
            return [];
        }
        try {
            $dql_type = $scope->get_type(new Method_Call($node, new Node\Identifier('getDQL'), []));
        } catch (Throwable $e) {
            return [Rule_Error_Builder::message(sprintf('Internal error: %s', $e->get_message()))->non_ignorable()->identifier('doctrine.internalError')->build()];
        }
        $dqls = $dql_type->get_constant_strings();
        if (count($dqls) === 0) {
            if ($this->report_dynamic_query_builders) {
                return [Rule_Error_Builder::message('Could not analyse QueryBuilder with dynamic arguments.')->identifier('doctrine.queryBuilderDynamicArgument')->build()];
            }
            return [];
        }
        $object_manager = $this->object_metadata_resolver->get_object_manager();
        if ($object_manager === null) {
            return [];
        }
        $entity_manager_interface = 'Doctrine\ORM\EntityManagerInterface';
        if (!$object_manager instanceof $entity_manager_interface) {
            return [];
        }
        $messages = [];
        foreach ($dqls as $dql) {
            try {
                $object_manager->create_query($dql->get_value())->get_ast();
            } catch (Query_Exception $e) {
                $message = sprintf('QueryBuilder: %s', $e->get_message());
                if (strpos($e->get_message(), '[Syntax Error]') === 0) {
                    $message .= sprintf("\nDQL: %s", $dql->get_value());
                }
                $builder = Rule_Error_Builder::message($message)->identifier('doctrine.dql');
                if (count($dqls) > 1) {
                    $builder->add_tip('Detected from DQL branch: ' . $dql->get_value());
                }
                // Use message as index to prevent duplicate
                $messages[$message] = $builder->build();
            } catch (AssertionError $e) {
                continue;
            }
        }
        return array_values($messages);
    }
}