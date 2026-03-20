<?php

declare (strict_types=1);
namespace Php_Stan\Type\Doctrine\Query_Builder;

use function array_keys;
use function count;
use Php_Stan\Type\Is_Super_Type_Of_Result;
use Php_Stan\Type\Type;
class Branching_Query_Builder_Type extends Query_Builder_Type
{
    public function equals(Type $type): bool
    {
        if ($type instanceof parent) {
            if (count($this->get_method_calls()) !== count($type->get_method_calls())) {
                return false;
            }
            foreach (array_keys($this->get_method_calls()) as $id) {
                if (!isset($type->get_method_calls()[$id])) {
                    return false;
                }
            }
            foreach (array_keys($type->get_method_calls()) as $id) {
                if (!isset($this->get_method_calls()[$id])) {
                    return false;
                }
            }
            return true;
        }
        return parent::equals($type);
    }
    public function is_super_type_of(Type $type): Is_Super_Type_Of_Result
    {
        if ($type instanceof parent) {
            return Is_Super_Type_Of_Result::create_from_boolean($this->equals($type));
        }
        return parent::is_super_type_of($type);
    }
}