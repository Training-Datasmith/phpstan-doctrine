<?php

declare (strict_types=1);
namespace Php_Stan\Type\Doctrine\Query_Builder\Expr;

use Php_Stan\Type\Object_Type;
/** @api */
class Expr_Type extends Object_Type
{
    private object $expr_object;
    /**
     * @param object $exprObject
     */
    public function __construct(string $class_name, $expr_object)
    {
        parent::__construct($class_name);
        $this->expr_object = $expr_object;
    }
    public function get_expr_object(): object
    {
        return $this->expr_object;
    }
}