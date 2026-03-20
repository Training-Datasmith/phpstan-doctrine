<?php

declare (strict_types=1);
namespace Php_Stan\Classes;

use Doctrine\Persistence\Proxy;
use Php_Stan\Type\Doctrine\Object_Metadata_Resolver;
class Doctrine_Proxy_Forbidden_Class_Names_Extension implements Forbidden_Class_Name_Extension
{
    private Object_Metadata_Resolver $object_metadata_resolver;
    public function __construct(Object_Metadata_Resolver $object_metadata_resolver)
    {
        $this->object_metadata_resolver = $object_metadata_resolver;
    }
    public function get_class_prefixes(): array
    {
        $object_manager = $this->object_metadata_resolver->get_object_manager();
        if ($object_manager === null) {
            return [];
        }
        $entity_manager_interface = 'Doctrine\ORM\EntityManagerInterface';
        if (!$object_manager instanceof $entity_manager_interface) {
            return [];
        }
        return ['Doctrine' => $object_manager->get_configuration()->get_proxy_namespace() . '\\' . Proxy::MARKER];
    }
}