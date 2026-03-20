<?php

declare (strict_types=1);
namespace Php_Stan\Type\Doctrine\Query_Builder;

use function array_slice;
use AssertionError;
use function count;
use Doctrine\Common\Common_Exception;
use Doctrine\DBAL\Dbal_Exception;
use Doctrine\ORM\Entity_Manager_Interface;
use Doctrine\ORM\Orm_Exception;
use Doctrine\Persistence\Mapping\Mapping_Exception;
use function in_array;
use function method_exists;
use Php_Parser\Node\Expr\Method_Call;
use Php_Parser\Node\Identifier;
use Php_Stan\Analyser\Scope;
use Php_Stan\Doctrine\Driver\Driver_Detector;
use Php_Stan\Php\Php_Version;
use Php_Stan\Reflection\Method_Reflection;
use Php_Stan\Rules\Doctrine\ORM\Dynamic_Query_Builder_Argument_Exception;
use Php_Stan\Type\Doctrine\Arguments_Processor;
use Php_Stan\Type\Doctrine\Descriptor_Registry;
use Php_Stan\Type\Doctrine\Doctrine_Type_Utils;
use Php_Stan\Type\Doctrine\Object_Metadata_Resolver;
use Php_Stan\Type\Doctrine\Query\Query_Result_Type_Builder;
use Php_Stan\Type\Doctrine\Query\Query_Result_Type_Walker;
use Php_Stan\Type\Doctrine\Query\Query_Type;
use Php_Stan\Type\Dynamic_Method_Return_Type_Extension;
use Php_Stan\Type\Type;
use Php_Stan\Type\Type_Combinator;
use function strtolower;
use Throwable;
class Query_Builder_Get_Query_Dynamic_Return_Type_Extension implements Dynamic_Method_Return_Type_Extension
{
    /**
     * Those are critical methods where we need to understand arguments passed to them, the rest is allowed to be more dynamic
     * - this list reflects what is implemented in QueryResultTypeWalker
     */
    private const METHODS_NOT_AFFECTING_RESULT_TYPE = ['where', 'andwhere', 'orwhere', 'setparameter', 'setparameters', 'addcriteria', 'addorderby', 'orderby', 'addgroupby', 'groupby', 'having', 'andhaving', 'orhaving'];
    private Object_Metadata_Resolver $object_metadata_resolver;
    private Arguments_Processor $arguments_processor;
    /** @var class-string|null */
    private ?string $query_builder_class = null;
    private Descriptor_Registry $descriptor_registry;
    private Php_Version $php_version;
    private Driver_Detector $driver_detector;
    /**
     * @param class-string|null $queryBuilderClass
     */
    public function __construct(Object_Metadata_Resolver $object_metadata_resolver, Arguments_Processor $arguments_processor, ?string $query_builder_class, Descriptor_Registry $descriptor_registry, Php_Version $php_version, Driver_Detector $driver_detector)
    {
        $this->object_metadata_resolver = $object_metadata_resolver;
        $this->arguments_processor = $arguments_processor;
        $this->query_builder_class = $query_builder_class;
        $this->descriptor_registry = $descriptor_registry;
        $this->php_version = $php_version;
        $this->driver_detector = $driver_detector;
    }
    public function get_class(): string
    {
        return $this->query_builder_class ?? 'Doctrine\ORM\QueryBuilder';
    }
    public function is_method_supported(Method_Reflection $method_reflection): bool
    {
        return $method_reflection->get_name() === 'getQuery';
    }
    public function get_type_from_method_call(Method_Reflection $method_reflection, Method_Call $method_call, Scope $scope): ?Type
    {
        $called_on_type = $scope->get_type($method_call->var);
        $query_builder_types = Doctrine_Type_Utils::get_query_builder_types($called_on_type);
        if (count($query_builder_types) === 0) {
            return null;
        }
        $object_manager = $this->object_metadata_resolver->get_object_manager();
        if ($object_manager === null) {
            return null;
        }
        $entity_manager_interface = 'Doctrine\ORM\EntityManagerInterface';
        if (!$object_manager instanceof $entity_manager_interface) {
            return null;
        }
        $result_types = [];
        foreach ($query_builder_types as $query_builder_type) {
            $query_builder = $object_manager->create_query_builder();
            foreach ($query_builder_type->get_method_calls() as $called_method_call) {
                if (!$called_method_call->name instanceof Identifier) {
                    continue;
                }
                $method_name = $called_method_call->name->to_string();
                $lower_method_name = strtolower($method_name);
                if (in_array($lower_method_name, ['setparameter', 'setparameters'], true)) {
                    continue;
                }
                if ($lower_method_name === 'setfirstresult') {
                    $query_builder->set_first_result(0);
                    continue;
                }
                if ($lower_method_name === 'setmaxresults') {
                    $query_builder->set_max_results(10);
                    continue;
                }
                if ($lower_method_name === 'set') {
                    try {
                        $args = $this->arguments_processor->process_args($scope, $method_name, array_slice($called_method_call->get_args(), 0, 1));
                    } catch (Dynamic_Query_Builder_Argument_Exception $e) {
                        return null;
                    }
                    if (count($args) === 1) {
                        $query_builder->set($args[0], $args[0]);
                        continue;
                    }
                }
                if (!method_exists($query_builder, $method_name)) {
                    continue;
                }
                try {
                    $args = $this->arguments_processor->process_args($scope, $method_name, $called_method_call->get_args());
                } catch (Dynamic_Query_Builder_Argument_Exception $e) {
                    if (in_array($lower_method_name, self::METHODS_NOT_AFFECTING_RESULT_TYPE, true)) {
                        continue;
                    }
                    return null;
                }
                try {
                    $query_builder->{$method_name}(...$args);
                } catch (Throwable $e) {
                    return null;
                }
            }
            $result_types[] = $this->get_query_type($query_builder->get_dql());
        }
        return Type_Combinator::union(...$result_types);
    }
    private function get_query_type(string $dql): Type
    {
        $em = $this->object_metadata_resolver->get_object_manager();
        if (!$em instanceof Entity_Manager_Interface) {
            return new Query_Type($dql);
        }
        $type_builder = new Query_Result_Type_Builder();
        try {
            $query = $em->create_query($dql);
            Query_Result_Type_Walker::walk($query, $type_builder, $this->descriptor_registry, $this->php_version, $this->driver_detector);
        } catch (Orm_Exception|Dbal_Exception|Common_Exception|Mapping_Exception|\Doctrine\ORM\Exception\Orm_Exception|AssertionError $e) {
            return new Query_Type($dql);
        }
        return new Query_Type($dql, $type_builder->get_index_type(), $type_builder->get_result_type());
    }
}