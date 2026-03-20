<?php

declare (strict_types=1);
namespace Php_Stan\Type\Doctrine;

use Doctrine\ORM\Abstract_Query;
use Doctrine\Persistence\Object_Manager;
use Php_Stan\Type\Accessory\Accessory_Array_List_Type;
use Php_Stan\Type\Array_Type;
use Php_Stan\Type\Benevolent_Union_Type;
use Php_Stan\Type\Constant\Constant_Integer_Type;
use Php_Stan\Type\Integer_Range_Type;
use Php_Stan\Type\Integer_Type;
use Php_Stan\Type\Iterable_Type;
use Php_Stan\Type\Object_Without_Class_Type;
use Php_Stan\Type\Type;
use Php_Stan\Type\Type_Combinator;
use Php_Stan\Type\Type_Utils;
use Php_Stan\Type\Void_Type;
class Hydration_Mode_Return_Type_Resolver
{
    public function get_method_return_type_for_hydration_mode(string $method_name, Type $hydration_mode, Type $query_key_type, Type $query_result_type, ?Object_Manager $object_manager): ?Type
    {
        $is_void_type = (new Void_Type())->is_super_type_of($query_result_type);
        if ($is_void_type->yes()) {
            // A void query result type indicates an UPDATE or DELETE query.
            // In this case all methods return the number of affected rows.
            return Integer_Range_Type::from_interval(0, null);
        }
        if ($is_void_type->maybe()) {
            // We can't be sure what the query type is, so we return the
            // declared return type of the method.
            return null;
        }
        if (!$hydration_mode instanceof Constant_Integer_Type) {
            return null;
        }
        switch ($hydration_mode->get_value()) {
            case Abstract_Query::HYDRATE_OBJECT:
                break;
            case Abstract_Query::HYDRATE_SIMPLEOBJECT:
                $query_result_type = $this->get_simple_object_hydrated_return_type($query_result_type);
                break;
            default:
                return null;
        }
        if ($query_result_type === null) {
            return null;
        }
        switch ($method_name) {
            case 'getSingleResult':
                return $query_result_type;
            case 'getOneOrNullResult':
                $nullable_query_result_type = Type_Combinator::add_null($query_result_type);
                if ($query_result_type instanceof Benevolent_Union_Type) {
                    return Type_Utils::to_benevolent_union($nullable_query_result_type);
                }
                return $nullable_query_result_type;
            case 'toIterable':
                return new Iterable_Type($query_key_type->is_null()->yes() ? new Integer_Type() : $query_key_type, $query_result_type);
            default:
                if ($query_key_type->is_null()->yes()) {
                    return Type_Combinator::intersect(new Array_Type(new Integer_Type(), $query_result_type), new Accessory_Array_List_Type());
                }
                return new Array_Type($query_key_type, $query_result_type);
        }
    }
    private function get_simple_object_hydrated_return_type(Type $query_result_type): ?Type
    {
        if ((new Object_Without_Class_Type())->is_super_type_of($query_result_type)->yes()) {
            return $query_result_type;
        }
        return null;
    }
}