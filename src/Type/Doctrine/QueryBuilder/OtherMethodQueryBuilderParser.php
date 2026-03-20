<?php

declare (strict_types=1);
namespace Php_Stan\Type\Doctrine\Query_Builder;

use function array_key_exists;
use function is_array;
use Php_Parser\Node;
use Php_Parser\Node\Stmt;
use Php_Parser\Node\Stmt\Class_;
use Php_Parser\Node\Stmt\Class_Method;
use Php_Parser\Node\Stmt\Declare_;
use Php_Parser\Node\Stmt\Namespace_;
use Php_Parser\Node\Stmt\Return_;
use Php_Stan\Analyser\Node_Scope_Resolver;
use Php_Stan\Analyser\Scope;
use Php_Stan\Analyser\Scope_Context;
use Php_Stan\Analyser\Scope_Factory;
use Php_Stan\Dependency_Injection\Container;
use Php_Stan\Parser\Parser;
use Php_Stan\Reflection\Method_Reflection;
use Php_Stan\Type\Generic\Template_Type_Map;
use Php_Stan\Type\Intersection_Type;
use Php_Stan\Type\Type;
use Php_Stan\Type\Type_Traverser;
use Php_Stan\Type\Union_Type;
use function sprintf;
class Other_Method_Query_Builder_Parser
{
    private Parser $parser;
    private Container $container;
    /**
     * Null if the method is currently being processed
     *
     * @var array<string, list<QueryBuilderType>|null>
     */
    private array $cache = [];
    public function __construct(Parser $parser, Container $container)
    {
        $this->parser = $parser;
        $this->container = $container;
    }
    /**
     * @return list<QueryBuilderType>
     */
    public function find_query_builder_types_in_called_method(Scope $scope, Method_Reflection $method_reflection): array
    {
        $method_name = $method_reflection->get_name();
        $class_name = $method_reflection->get_declaring_class()->get_name();
        $file_name = $method_reflection->get_declaring_class()->get_file_name();
        if ($file_name === null) {
            return [];
        }
        $cache_key = $this->build_cache_key($file_name, $class_name, $method_name);
        if (array_key_exists($cache_key, $this->cache)) {
            if ($this->cache[$cache_key] === null) {
                return [];
                // recursion
            }
            return $this->cache[$cache_key];
        }
        $this->cache[$cache_key] = null;
        $nodes = $this->parser->parse_file($file_name);
        $class_node = $this->find_class_node($class_name, $nodes);
        if ($class_node === null) {
            return [];
        }
        $method_node = $this->find_method_node($method_name, $class_node->stmts);
        if ($method_node === null || $method_node->stmts === null) {
            return [];
        }
        $node_scope_resolver = $this->container->get_by_type(Node_Scope_Resolver::class);
        $scope_factory = $this->container->get_by_type(Scope_Factory::class);
        $method_scope = $scope_factory->create(Scope_Context::create($file_name));
        if ($scope->get_namespace() !== null) {
            $method_scope = $method_scope->enter_namespace($scope->get_namespace());
        }
        $method_scope = $method_scope->enter_class($method_reflection->get_declaring_class())->enter_class_method($method_node, Template_Type_Map::create_empty(), [], null, null, null, false, false, false);
        $query_builder_types = [];
        $node_scope_resolver->process_nodes($method_node->stmts, $method_scope, static function (Node $node, Scope $scope) use (&$query_builder_types): void {
            if (!$node instanceof Return_ || $node->expr === null) {
                return;
            }
            $expr_type = $scope->to_mutating_scope()->get_type($node->expr);
            Type_Traverser::map($expr_type, static function (Type $type, callable $traverse) use (&$query_builder_types): Type {
                if ($type instanceof Union_Type || $type instanceof Intersection_Type) {
                    return $traverse($type);
                }
                if ($type instanceof Query_Builder_Type) {
                    $query_builder_types[] = $type;
                }
                return $type;
            });
        });
        $this->cache[$cache_key] = $query_builder_types;
        return $query_builder_types;
    }
    /**
     * @param Node[] $nodes
     */
    private function find_class_node(string $class_name, array $nodes): ?Class_
    {
        foreach ($nodes as $node) {
            if ($node instanceof Class_ && $node->namespaced_name !== null && $node->namespaced_name->to_string() === $class_name) {
                return $node;
            }
            if (!$node instanceof Namespace_ && !$node instanceof Declare_) {
                continue;
            }
            $sub_node_names = $node->get_sub_node_names();
            foreach ($sub_node_names as $sub_node_name) {
                $sub_node = $node->{$sub_node_name};
                if (!is_array($sub_node)) {
                    $sub_node = [$sub_node];
                }
                $result = $this->find_class_node($class_name, $sub_node);
                if ($result === null) {
                    continue;
                }
                return $result;
            }
        }
        return null;
    }
    /**
     * @param Stmt[] $classStatements
     */
    private function find_method_node(string $method_name, array $class_statements): ?Class_Method
    {
        foreach ($class_statements as $statement) {
            if ($statement instanceof Class_Method && $statement->name->to_string() === $method_name) {
                return $statement;
            }
        }
        return null;
    }
    private function build_cache_key(string $file_name, string $declaring_class_name, string $method_name): string
    {
        return sprintf('%s-%s-%s', $file_name, $declaring_class_name, $method_name);
    }
}