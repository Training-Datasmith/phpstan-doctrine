<?php

declare (strict_types=1);
namespace Php_Stan\Doctrine;

use Composer\Installed_Versions;
use function count;
use Doctrine\ORM\Entity_Manager_Interface;
use OutOfBoundsException;
use Php_Stan\Command\Output;
use Php_Stan\Diagnose\Diagnose_Extension;
use Php_Stan\Doctrine\Driver\Driver_Detector;
use Php_Stan\Type\Doctrine\Object_Metadata_Resolver;
use function sprintf;
class Doctrine_Diagnose_Extension implements Diagnose_Extension
{
    private Object_Metadata_Resolver $object_metadata_resolver;
    private Driver_Detector $driver_detector;
    public function __construct(Object_Metadata_Resolver $object_metadata_resolver, Driver_Detector $driver_detector)
    {
        $this->object_metadata_resolver = $object_metadata_resolver;
        $this->driver_detector = $driver_detector;
    }
    public function print(Output $output): void
    {
        $output->write_line_formatted(sprintf('<info>Doctrine\'s objectManagerLoader:</info> %s', $this->object_metadata_resolver->has_object_manager_loader() ? 'In use' : 'No'));
        $object_manager = $this->object_metadata_resolver->get_object_manager();
        if ($object_manager instanceof Entity_Manager_Interface) {
            $connection = $object_manager->get_connection();
            $driver = $this->driver_detector->detect($connection);
            $output->write_line_formatted(sprintf('<info>Detected driver:</info> %s', $driver ?? 'None'));
        }
        $packages = [];
        $candidates = ['doctrine/dbal', 'doctrine/orm', 'doctrine/common', 'doctrine/collections', 'doctrine/persistence'];
        foreach ($candidates as $package) {
            try {
                $installed_version = Installed_Versions::get_pretty_version($package);
            } catch (OutOfBoundsException $e) {
                continue;
            }
            if ($installed_version === null) {
                continue;
            }
            $packages[$package] = $installed_version;
        }
        if (count($packages) > 0) {
            $output->write_line_formatted('<info>Installed Doctrine packages:</info>');
            foreach ($packages as $package => $version) {
                $output->write_line_formatted(sprintf('%s: %s', $package, $version));
            }
        }
        $output->write_line_formatted('');
    }
}