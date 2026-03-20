<?php

declare (strict_types=1);
namespace Php_Stan\Type\Doctrine\Query;

use Php_Stan\Type\Generic\Generic_Object_Type;
use Php_Stan\Type\Is_Super_Type_Of_Result;
use Php_Stan\Type\Mixed_Type;
use Php_Stan\Type\Type;
/** @api */
class Query_Type extends Generic_Object_Type
{
    private Type $index_type;
    private Type $result_type;
    private string $dql;
    public function __construct(string $dql, ?Type $index_type = null, ?Type $result_type = null, ?Type $subtracted_type = null)
    {
        $this->index_type = $index_type ?? new Mixed_Type();
        $this->result_type = $result_type ?? new Mixed_Type();
        parent::__construct('Doctrine\ORM\Query', [$this->index_type, $this->result_type], $subtracted_type);
        $this->dql = $dql;
    }
    public function equals(Type $type): bool
    {
        if ($type instanceof self) {
            return $this->get_dql() === $type->get_dql();
        }
        return parent::equals($type);
    }
    public function change_subtracted_type(?Type $subtracted_type): Type
    {
        return new self('Doctrine\ORM\Query', $this->index_type, $this->result_type, $subtracted_type);
    }
    public function is_super_type_of(Type $type): Is_Super_Type_Of_Result
    {
        if ($type instanceof self) {
            return Is_Super_Type_Of_Result::create_from_boolean($this->equals($type));
        }
        return parent::is_super_type_of($type);
    }
    public function get_dql(): string
    {
        return $this->dql;
    }
}