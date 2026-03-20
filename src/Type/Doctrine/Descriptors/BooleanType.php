<?php

declare (strict_types=1);
namespace Php_Stan\Type\Doctrine\Descriptors;

use Doctrine\DBAL\Connection;
use function in_array;
use Php_Stan\Doctrine\Driver\Driver_Detector;
use Php_Stan\Type\Constant\Constant_Integer_Type;
use Php_Stan\Type\Type;
use Php_Stan\Type\Type_Combinator;
class Boolean_Type implements Doctrine_Type_Descriptor, Doctrine_Type_Driver_Aware_Descriptor
{
    private Driver_Detector $driver_detector;
    public function __construct(Driver_Detector $driver_detector)
    {
        $this->driver_detector = $driver_detector;
    }
    public function get_type(): string
    {
        return \Doctrine\DBAL\Types\Boolean_Type::class;
    }
    public function get_writable_to_property_type(): Type
    {
        return new \Php_Stan\Type\Boolean_Type();
    }
    public function get_writable_to_database_type(): Type
    {
        return new \Php_Stan\Type\Boolean_Type();
    }
    public function get_database_internal_type(): Type
    {
        return Type_Combinator::union(new Constant_Integer_Type(0), new Constant_Integer_Type(1), new \Php_Stan\Type\Boolean_Type());
    }
    public function get_database_internal_type_for_driver(Connection $connection): Type
    {
        $driver_type = $this->driver_detector->detect($connection);
        if ($driver_type === Driver_Detector::PGSQL || $driver_type === Driver_Detector::PDO_PGSQL) {
            return new \Php_Stan\Type\Boolean_Type();
        }
        if (in_array($driver_type, [Driver_Detector::SQLITE3, Driver_Detector::PDO_SQLITE, Driver_Detector::MYSQLI, Driver_Detector::PDO_MYSQL], true)) {
            return Type_Combinator::union(new Constant_Integer_Type(0), new Constant_Integer_Type(1));
        }
        // not yet supported driver, return the old implementation guess
        return $this->get_database_internal_type();
    }
}