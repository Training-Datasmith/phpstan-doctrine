<?php

declare (strict_types=1);
namespace Php_Stan\Php_Doc\Doctrine;

use function count;
use Doctrine\ORM\Abstract_Query;
use Doctrine\ORM\Query;
use Php_Stan\Analyser\Name_Scope;
use Php_Stan\Php_Doc\Type_Node_Resolver;
use Php_Stan\Php_Doc\Type_Node_Resolver_Aware_Extension;
use Php_Stan\Php_Doc\Type_Node_Resolver_Extension;
use Php_Stan\Php_Doc_Parser\Ast\Type\Generic_Type_Node;
use Php_Stan\Php_Doc_Parser\Ast\Type\Type_Node;
use Php_Stan\Type\Generic\Generic_Object_Type;
use Php_Stan\Type\Null_Type;
use Php_Stan\Type\Type;
class Query_Type_Node_Resolver_Extension implements Type_Node_Resolver_Extension, Type_Node_Resolver_Aware_Extension
{
    private Type_Node_Resolver $type_node_resolver;
    public function set_type_node_resolver(Type_Node_Resolver $type_node_resolver): void
    {
        $this->type_node_resolver = $type_node_resolver;
    }
    public function resolve(Type_Node $type_node, Name_Scope $name_scope): ?Type
    {
        if (!$type_node instanceof Generic_Type_Node) {
            return null;
        }
        $type_name = $name_scope->resolve_string_name($type_node->type->name);
        if ($type_name !== Query::class && $type_name !== Abstract_Query::class) {
            return null;
        }
        $count = count($type_node->generic_types);
        if ($count !== 1) {
            return null;
        }
        return new Generic_Object_Type($type_name, [new Null_Type(), $this->type_node_resolver->resolve($type_node->generic_types[0], $name_scope)]);
    }
}