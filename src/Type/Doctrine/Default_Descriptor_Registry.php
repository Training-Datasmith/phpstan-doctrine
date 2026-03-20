<?php

declare (strict_types=1);
namespace Php_Stan\Type\Doctrine;

use Doctrine\DBAL\Types\Type;
use Php_Stan\Type\Doctrine\Descriptors\Doctrine_Type_Descriptor;
class Default_Descriptor_Registry implements Descriptor_Registry
{
    /** @var array<class-string<Type>, DoctrineTypeDescriptor> */
    private array $descriptors = [];
    /**
     * @param DoctrineTypeDescriptor[] $descriptors
     */
    public function __construct(array $descriptors)
    {
        foreach ($descriptors as $descriptor) {
            $this->descriptors[$descriptor->get_type()] = $descriptor;
        }
    }
    public function get(string $type): Doctrine_Type_Descriptor
    {
        $types_map = Type::get_types_map();
        if (!isset($types_map[$type])) {
            throw new Descriptor_Not_Registered_Exception($type);
        }
        /** @var class-string<Type> $typeClass */
        $type_class = $types_map[$type];
        if (!isset($this->descriptors[$type_class])) {
            throw new Descriptor_Not_Registered_Exception($type_class);
        }
        return $this->descriptors[$type_class];
    }
    /**
     * @throws DescriptorNotRegisteredException
     */
    public function get_by_class_name(string $class_name): Doctrine_Type_Descriptor
    {
        if (!isset($this->descriptors[$class_name])) {
            throw new Descriptor_Not_Registered_Exception($class_name);
        }
        return $this->descriptors[$class_name];
    }
}