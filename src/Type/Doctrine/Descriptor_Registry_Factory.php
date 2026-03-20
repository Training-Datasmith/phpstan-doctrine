<?php

declare (strict_types=1);
namespace Php_Stan\Type\Doctrine;

use Php_Stan\Dependency_Injection\Container;
class Descriptor_Registry_Factory
{
    public const TYPE_DESCRIPTOR_TAG = 'phpstan.doctrine.typeDescriptor';
    private Container $container;
    public function __construct(Container $container)
    {
        $this->container = $container;
    }
    public function create_registry(): Descriptor_Registry
    {
        return new Default_Descriptor_Registry($this->container->get_services_by_tag(self::TYPE_DESCRIPTOR_TAG));
    }
}