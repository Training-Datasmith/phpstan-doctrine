<?php

declare (strict_types=1);
namespace Php_Stan\Type\Doctrine;

use AssertionError;
use Doctrine\Common\Common_Exception;
use Doctrine\DBAL\Dbal_Exception;
use Doctrine\DBAL\Exception as NewDBALException;
use Doctrine\ORM\Entity_Manager_Interface;
use Doctrine\ORM\Orm_Exception;
use Doctrine\ORM\Query;
use Doctrine\Persistence\Mapping\Mapping_Exception;
use Php_Parser\Node\Expr\Method_Call;
use Php_Stan\Analyser\Scope;
use Php_Stan\Doctrine\Driver\Driver_Detector;
use Php_Stan\Php\Php_Version;
use Php_Stan\Reflection\Method_Reflection;
use Php_Stan\Type\Constant\Constant_String_Type;
use Php_Stan\Type\Doctrine\Query\Query_Result_Type_Builder;
use Php_Stan\Type\Doctrine\Query\Query_Result_Type_Walker;
use Php_Stan\Type\Doctrine\Query\Query_Type;
use Php_Stan\Type\Dynamic_Method_Return_Type_Extension;
use Php_Stan\Type\Generic\Generic_Object_Type;
use Php_Stan\Type\Intersection_Type;
use Php_Stan\Type\Mixed_Type;
use Php_Stan\Type\Type;
use Php_Stan\Type\Type_Traverser;
use Php_Stan\Type\Union_Type;
/**
 * Infers TResult in Query<TResult> on EntityManagerInterface::createQuery()
 */
final class Create_Query_Dynamic_Return_Type_Extension implements Dynamic_Method_Return_Type_Extension
{
    private Object_Metadata_Resolver $object_metadata_resolver;
    private Descriptor_Registry $descriptor_registry;
    private Php_Version $php_version;
    private Driver_Detector $driver_detector;
    public function __construct(Object_Metadata_Resolver $object_metadata_resolver, Descriptor_Registry $descriptor_registry, Php_Version $php_version, Driver_Detector $driver_detector)
    {
        $this->object_metadata_resolver = $object_metadata_resolver;
        $this->descriptor_registry = $descriptor_registry;
        $this->php_version = $php_version;
        $this->driver_detector = $driver_detector;
    }
    public function get_class(): string
    {
        return Entity_Manager_Interface::class;
    }
    public function is_method_supported(Method_Reflection $method_reflection): bool
    {
        return $method_reflection->get_name() === 'createQuery';
    }
    public function get_type_from_method_call(Method_Reflection $method_reflection, Method_Call $method_call, Scope $scope): Type
    {
        $query_string_arg_index = 0;
        $args = $method_call->get_args();
        if (!isset($args[$query_string_arg_index])) {
            return new Generic_Object_Type(Query::class, [new Mixed_Type(), new Mixed_Type()]);
        }
        $arg_type = $scope->get_type($args[$query_string_arg_index]->value);
        return Type_Traverser::map($arg_type, function (Type $type, callable $traverse): Type {
            if ($type instanceof Union_Type || $type instanceof Intersection_Type) {
                return $traverse($type);
            }
            if ($type instanceof Constant_String_Type) {
                $query_string = $type->get_value();
                $em = $this->object_metadata_resolver->get_object_manager();
                if (!$em instanceof Entity_Manager_Interface) {
                    return new Query_Type($query_string);
                }
                $type_builder = new Query_Result_Type_Builder();
                try {
                    $query = $em->create_query($query_string);
                    Query_Result_Type_Walker::walk($query, $type_builder, $this->descriptor_registry, $this->php_version, $this->driver_detector);
                } catch (Orm_Exception|Dbal_Exception|New_Dbal_Exception|Common_Exception|Mapping_Exception|\Doctrine\ORM\Exception\Orm_Exception|AssertionError $e) {
                    return new Query_Type($query_string);
                }
                return new Query_Type($query_string, $type_builder->get_index_type(), $type_builder->get_result_type());
            }
            return new Generic_Object_Type(Query::class, [new Mixed_Type(), new Mixed_Type()]);
        });
    }
}