<?php

declare (strict_types=1);
namespace Php_Stan\Php_Doc\Doctrine;

use Php_Stan\Analyser\Name_Scope;
use Php_Stan\Php_Doc\Type_Node_Resolver_Extension;
use Php_Stan\Php_Doc_Parser\Ast\Type\Identifier_Type_Node;
use Php_Stan\Php_Doc_Parser\Ast\Type\Type_Node;
use Php_Stan\Type\Accessory\Accessory_Literal_String_Type;
use Php_Stan\Type\Intersection_Type;
use Php_Stan\Type\String_Type;
use Php_Stan\Type\Type;
class Doctrine_Literal_String_Type_Node_Resolver_Extension implements Type_Node_Resolver_Extension
{
    private bool $enabled;
    public function __construct(bool $enabled)
    {
        $this->enabled = $enabled;
    }
    public function resolve(Type_Node $type_node, Name_Scope $name_scope): ?Type
    {
        if (!$type_node instanceof Identifier_Type_Node) {
            return null;
        }
        if ($type_node->name !== '__doctrine-literal-string') {
            return null;
        }
        if ($this->enabled) {
            return new Intersection_Type([new String_Type(), new Accessory_Literal_String_Type()]);
        }
        return new String_Type();
    }
}