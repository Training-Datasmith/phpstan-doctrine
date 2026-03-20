<?php

declare (strict_types=1);
namespace Php_Stan\Type\Doctrine;

use function count;
use Doctrine\Common\Annotations\Annotation_Exception;
use Doctrine\ODM\Mongo_Db\Document_Manager;
use Doctrine\ODM\Mongo_Db\Mapping\Class_Metadata;
use Doctrine\ODM\Mongo_Db\Repository\Document_Repository;
use Doctrine\ORM\Entity_Repository;
use Doctrine\ORM\Mapping\Mapping_Exception;
use Doctrine\Persistence\Object_Repository;
use Php_Parser\Node\Arg;
use Php_Parser\Node\Expr\Method_Call;
use Php_Stan\Analyser\Scope;
use Php_Stan\Reflection\Method_Reflection;
use Php_Stan\Reflection\Parameters_Acceptor_Selector;
use Php_Stan\Reflection\Reflection_Provider;
use Php_Stan\Type\Dynamic_Method_Return_Type_Extension;
use Php_Stan\Type\Error_Type;
use Php_Stan\Type\Generic\Generic_Object_Type;
use Php_Stan\Type\Object_Type;
use Php_Stan\Type\Object_Without_Class_Type;
use Php_Stan\Type\Type;
use Php_Stan\Type\Type_Combinator;
class Get_Repository_Dynamic_Return_Type_Extension implements Dynamic_Method_Return_Type_Extension
{
    private Reflection_Provider $reflection_provider;
    private ?string $repository_class = null;
    private ?string $orm_repository_class = null;
    private ?string $odm_repository_class = null;
    /** @var class-string */
    private string $manager_class;
    private Object_Metadata_Resolver $metadata_resolver;
    /**
     * @param class-string $managerClass
     */
    public function __construct(Reflection_Provider $reflection_provider, ?string $repository_class, ?string $orm_repository_class, ?string $odm_repository_class, string $manager_class, Object_Metadata_Resolver $metadata_resolver)
    {
        $this->reflection_provider = $reflection_provider;
        $this->repository_class = $repository_class;
        $this->orm_repository_class = $orm_repository_class;
        $this->odm_repository_class = $odm_repository_class;
        $this->manager_class = $manager_class;
        $this->metadata_resolver = $metadata_resolver;
    }
    public function get_class(): string
    {
        return $this->manager_class;
    }
    public function is_method_supported(Method_Reflection $method_reflection): bool
    {
        return $method_reflection->get_name() === 'getRepository';
    }
    public function get_type_from_method_call(Method_Reflection $method_reflection, Method_Call $method_call, Scope $scope): Type
    {
        $called_on_type = $scope->get_type($method_call->var);
        if ((new Object_Type(Document_Manager::class))->is_super_type_of($called_on_type)->yes()) {
            $default_repository_class = $this->odm_repository_class ?? $this->repository_class ?? Document_Repository::class;
        } else {
            $default_repository_class = $this->orm_repository_class ?? $this->repository_class ?? Entity_Repository::class;
        }
        if (count($method_call->get_args()) === 0) {
            return new Generic_Object_Type($default_repository_class, [new Object_Without_Class_Type()]);
        }
        $arg_type = $scope->get_type($method_call->get_args()[0]->value);
        if (!$arg_type->is_class_string()->yes()) {
            return $this->get_default_return_type($scope, $method_call->get_args(), $method_reflection, $default_repository_class);
        }
        $class_type = $arg_type->get_class_string_object_type();
        $object_names = $class_type->get_object_class_names();
        if (count($object_names) === 0) {
            return new Generic_Object_Type($default_repository_class, [$class_type]);
        }
        $repository_types = [];
        foreach ($object_names as $object_name) {
            try {
                $repository_class = $this->get_repository_class($object_name, $default_repository_class);
            } catch (\Doctrine\Persistence\Mapping\Mapping_Exception|Mapping_Exception|Annotation_Exception $e) {
                return $this->get_default_return_type($scope, $method_call->get_args(), $method_reflection, $default_repository_class);
            }
            $repository_types[] = new Generic_Object_Type($repository_class, [$class_type]);
        }
        return Type_Combinator::union(...$repository_types);
    }
    /**
     * @param Arg[] $args
     */
    private function get_default_return_type(Scope $scope, array $args, Method_Reflection $method_reflection, string $default_repository_class): Type
    {
        $default_type = Parameters_Acceptor_Selector::select_from_args($scope, $args, $method_reflection->get_variants())->get_return_type();
        $entity = $default_type->get_template_type(Object_Repository::class, 'TEntityClass');
        if (!$entity instanceof Error_Type) {
            return new Generic_Object_Type($default_repository_class, [$entity]);
        }
        return $default_type;
    }
    private function get_repository_class(string $class_name, string $default_repository_class): string
    {
        if (!$this->reflection_provider->has_class($class_name)) {
            return $default_repository_class;
        }
        $class_reflection = $this->reflection_provider->get_class($class_name);
        if ($class_reflection->is_interface() || $class_reflection->is_trait()) {
            return $default_repository_class;
        }
        $metadata = $this->metadata_resolver->get_class_metadata($class_reflection->get_name());
        if ($metadata !== null) {
            return $metadata->custom_repository_class_name ?? $default_repository_class;
        }
        $object_manager = $this->metadata_resolver->get_object_manager();
        if ($object_manager === null) {
            return $default_repository_class;
        }
        $metadata = $object_manager->get_class_metadata($class_reflection->get_name());
        $odm_metadata_class = 'Doctrine\ODM\MongoDB\Mapping\ClassMetadata';
        if ($metadata instanceof $odm_metadata_class) {
            /** @var ClassMetadata<object> $odmMetadata */
            $odm_metadata = $metadata;
            return $odm_metadata->custom_repository_class_name ?? $default_repository_class;
        }
        return $default_repository_class;
    }
}