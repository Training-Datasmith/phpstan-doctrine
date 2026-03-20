<?php

declare (strict_types=1);
namespace Php_Stan\Type\Doctrine;

use Php_Stan\Type\Doctrine\Query\Query_Type;
use Php_Stan\Type\Doctrine\Query_Builder\Query_Builder_Type;
use Php_Stan\Type\Type;
use Php_Stan\Type\Union_Type;
class Doctrine_Type_Utils
{
    /**
     * @return QueryBuilderType[]
     */
    public static function get_query_builder_types(Type $type): array
    {
        if ($type instanceof Query_Builder_Type) {
            return [$type];
        }
        if ($type instanceof Union_Type) {
            $types = [];
            foreach ($type->get_types() as $inner_type) {
                if (!$inner_type instanceof Query_Builder_Type) {
                    return [];
                }
                $types[] = $inner_type;
            }
            return $types;
        }
        return [];
    }
    /**
     * @return QueryType[]
     */
    public static function get_query_types(Type $type): array
    {
        if ($type instanceof Query_Type) {
            return [$type];
        }
        if ($type instanceof Union_Type) {
            $types = [];
            foreach ($type->get_types() as $inner_type) {
                if (!$inner_type instanceof Query_Type) {
                    return [];
                }
                $types[] = $inner_type;
            }
            return $types;
        }
        return [];
    }
}