<?php

declare (strict_types=1);
namespace Php_Stan\Type\Doctrine\Query;

use Doctrine\ORM\Query\AST\Literal;
use Php_Stan\Type\Constant\Constant_String_Type;
class Dql_Constant_String_Type extends Constant_String_Type
{
    /** @var Literal::* */
    private int $origin_literal_type;
    /**
     * @param Literal::* $originLiteralType
     */
    public function __construct(string $value, int $origin_literal_type)
    {
        parent::__construct($value);
        $this->origin_literal_type = $origin_literal_type;
    }
    /**
     * @return Literal::*
     */
    public function get_origin_literal_type(): int
    {
        return $this->origin_literal_type;
    }
}