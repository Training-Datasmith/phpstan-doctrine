<?php

declare (strict_types=1);
namespace Php_Stan\Stubs\Doctrine;

use function class_exists;
use Composer\Installed_Versions;
use function dirname;
use OutOfBoundsException;
use Php_Stan\Better_Reflection\Reflector\Exception\Identifier_Not_Found;
use Php_Stan\Better_Reflection\Reflector\Reflector;
use Php_Stan\Php_Doc\Stub_Files_Extension;
use function strpos;
class Stub_Files_Extension_Loader implements Stub_Files_Extension
{
    private Reflector $reflector;
    public function __construct(Reflector $reflector)
    {
        $this->reflector = $reflector;
    }
    public function get_files(): array
    {
        $stubs_dir = dirname(__DIR__, 3) . '/stubs';
        $files = [];
        if ($this->is_installed_version('doctrine/dbal', 4)) {
            $files[] = $stubs_dir . '/DBAL/Connection4.stub';
            $files[] = $stubs_dir . '/DBAL/ArrayParameterType.stub';
            $files[] = $stubs_dir . '/DBAL/ParameterType.stub';
        } else {
            $files[] = $stubs_dir . '/DBAL/Connection.stub';
        }
        $has_lazy_service_entity_repository_as_parent = false;
        try {
            $service_entity_repository = $this->reflector->reflect_class('Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository');
            if ($service_entity_repository->get_parent_class() !== null) {
                /** @var class-string $lazyServiceEntityRepositoryName */
                $lazy_service_entity_repository_name = 'Doctrine\Bundle\DoctrineBundle\Repository\LazyServiceEntityRepository';
                $has_lazy_service_entity_repository_as_parent = $service_entity_repository->get_parent_class()->get_name() === $lazy_service_entity_repository_name;
            }
        } catch (Identifier_Not_Found $e) {
            // pass
        }
        if ($has_lazy_service_entity_repository_as_parent) {
            $files[] = $stubs_dir . '/LazyServiceEntityRepository.stub';
        } else {
            $files[] = $stubs_dir . '/ServiceEntityRepository.stub';
        }
        try {
            $collection_version = class_exists(Installed_Versions::class) ? Installed_Versions::get_version('doctrine/collections') : null;
        } catch (OutOfBoundsException $e) {
            $collection_version = null;
        }
        if ($collection_version !== null && strpos($collection_version, '1.') === 0) {
            $files[] = $stubs_dir . '/Collections/ReadableCollection1.stub';
            $files[] = $stubs_dir . '/Collections/Collection1.stub';
        } else {
            $files[] = $stubs_dir . '/Collections/ReadableCollection.stub';
            $files[] = $stubs_dir . '/Collections/Collection.stub';
        }
        return $files;
    }
    private function is_installed_version(string $package, int $major_version): bool
    {
        if (!class_exists(Installed_Versions::class)) {
            return false;
        }
        try {
            $installed_version = Installed_Versions::get_version($package);
        } catch (OutOfBoundsException $e) {
            return false;
        }
        return $installed_version !== null && strpos($installed_version, $major_version . '.') === 0;
    }
}