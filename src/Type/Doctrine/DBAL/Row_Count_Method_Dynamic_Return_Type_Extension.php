<?php

declare (strict_types=1);
namespace Php_Stan\Type\Doctrine\DBAL;

use Doctrine\DBAL\Driver\Result as DriverResult;
use Doctrine\ORM\Entity_Manager_Interface;
use Php_Parser\Node\Expr\Method_Call;
use Php_Stan\Analyser\Scope;
use Php_Stan\Doctrine\Driver\Driver_Detector;
use Php_Stan\Reflection\Method_Reflection;
use Php_Stan\Reflection\Reflection_Provider;
use Php_Stan\Type\Doctrine\Object_Metadata_Resolver;
use Php_Stan\Type\Dynamic_Method_Return_Type_Extension;
use Php_Stan\Type\Type;
class Row_Count_Method_Dynamic_Return_Type_Extension implements Dynamic_Method_Return_Type_Extension
{
    /** @var class-string */
    private string $class;
    private Object_Metadata_Resolver $object_metadata_resolver;
    private Driver_Detector $driver_detector;
    private Reflection_Provider $reflection_provider;
    /**
     * @param class-string $class
     */
    public function __construct(string $class, Object_Metadata_Resolver $object_metadata_resolver, Driver_Detector $driver_detector, Reflection_Provider $reflection_provider)
    {
        $this->class = $class;
        $this->object_metadata_resolver = $object_metadata_resolver;
        $this->driver_detector = $driver_detector;
        $this->reflection_provider = $reflection_provider;
    }
    public function get_class(): string
    {
        return $this->class;
    }
    public function is_method_supported(Method_Reflection $method_reflection): bool
    {
        return $method_reflection->get_name() === 'rowCount';
    }
    public function get_type_from_method_call(Method_Reflection $method_reflection, Method_Call $method_call, Scope $scope): ?Type
    {
        $object_manager = $this->object_metadata_resolver->get_object_manager();
        if (!$object_manager instanceof Entity_Manager_Interface) {
            return null;
        }
        $connection = $object_manager->get_connection();
        $driver = $this->driver_detector->detect($connection);
        if ($driver === null) {
            return null;
        }
        $result_class = $this->get_result_class($driver);
        if (!$this->reflection_provider->has_class($result_class)) {
            return null;
        }
        $result_reflection = $this->reflection_provider->get_class($result_class);
        if (!$result_reflection->has_native_method('rowCount')) {
            return null;
        }
        $row_count_method = $result_reflection->get_native_method('rowCount');
        $variant = $row_count_method->get_only_variant();
        return $variant->get_return_type();
    }
    /**
     * @param DriverDetector::* $driver
     * @return class-string<DriverResult>
     */
    private function get_result_class(string $driver): string
    {
        switch ($driver) {
            case Driver_Detector::IBM_DB2:
                return 'Doctrine\DBAL\Driver\IBMDB2\Result';
            case Driver_Detector::MYSQLI:
                return 'Doctrine\DBAL\Driver\Mysqli\Result';
            case Driver_Detector::OCI8:
                return 'Doctrine\DBAL\Driver\OCI8\Result';
            case Driver_Detector::PDO_MYSQL:
            case Driver_Detector::PDO_OCI:
            case Driver_Detector::PDO_PGSQL:
            case Driver_Detector::PDO_SQLITE:
            case Driver_Detector::PDO_SQLSRV:
                return 'Doctrine\DBAL\Driver\PDO\Result';
            case Driver_Detector::PGSQL:
                return 'Doctrine\DBAL\Driver\PgSQL\Result';
            // @phpstan-ignore return.type
            case Driver_Detector::SQLITE3:
                return 'Doctrine\DBAL\Driver\SQLite3\Result';
            // @phpstan-ignore return.type
            case Driver_Detector::SQLSRV:
                return 'Doctrine\DBAL\Driver\SQLSrv\Result';
        }
    }
}