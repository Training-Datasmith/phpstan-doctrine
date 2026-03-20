<?php

declare (strict_types=1);
namespace Php_Stan\Doctrine\Driver;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\IBMDB2\Driver as IbmDb2Driver;
use Doctrine\DBAL\Driver\Mysqli\Driver as MysqliDriver;
use Doctrine\DBAL\Driver\OCI8\Driver as Oci8Driver;
use Doctrine\DBAL\Driver\PDO\My_Sql\Driver as PdoMysqlDriver;
use Doctrine\DBAL\Driver\PDO\OCI\Driver as PdoOciDriver;
use Doctrine\DBAL\Driver\PDO\Pg_Sql\Driver as PdoPgSQLDriver;
use Doctrine\DBAL\Driver\PDO\Sq_Lite\Driver as PdoSQLiteDriver;
use Doctrine\DBAL\Driver\PDO\Sql_Srv\Driver as PdoSqlSrvDriver;
use Doctrine\DBAL\Driver\Pg_Sql\Driver as PgSQLDriver;
use Doctrine\DBAL\Driver\Sq_Lite3\Driver as SQLite3Driver;
use Doctrine\DBAL\Driver\Sql_Srv\Driver as SqlSrvDriver;
use function get_class;
use function is_a;
class Driver_Detector
{
    public const IBM_DB2 = 'ibm_db2';
    public const MYSQLI = 'mysqli';
    public const OCI8 = 'oci8';
    public const PDO_MYSQL = 'pdo_mysql';
    public const PDO_OCI = 'pdo_oci';
    public const PDO_PGSQL = 'pdo_pgsql';
    public const PDO_SQLITE = 'pdo_sqlite';
    public const PDO_SQLSRV = 'pdo_sqlsrv';
    public const PGSQL = 'pgsql';
    public const SQLITE3 = 'sqlite3';
    public const SQLSRV = 'sqlsrv';
    /**
     * @return self::*|null
     */
    public function detect(Connection $connection): ?string
    {
        $driver = $connection->get_driver();
        return $this->deduce_from_driver_class(get_class($driver)) ?? $this->deduce_from_params($connection);
    }
    /**
     * @return array<mixed>
     */
    public function detect_driver_options(Connection $connection): array
    {
        return $connection->get_params()['driverOptions'] ?? [];
    }
    /**
     * @return self::*|null
     */
    private function deduce_from_driver_class(string $driver_class): ?string
    {
        if (is_a($driver_class, Mysqli_Driver::class, true)) {
            return self::MYSQLI;
        }
        if (is_a($driver_class, Pdo_Mysql_Driver::class, true)) {
            return self::PDO_MYSQL;
        }
        if (is_a($driver_class, Pdo_Sq_Lite_Driver::class, true)) {
            return self::PDO_SQLITE;
        }
        if (is_a($driver_class, Pdo_Sql_Srv_Driver::class, true)) {
            return self::PDO_SQLSRV;
        }
        if (is_a($driver_class, Pdo_Oci_Driver::class, true)) {
            return self::PDO_OCI;
        }
        if (is_a($driver_class, Pdo_Pg_Sql_Driver::class, true)) {
            return self::PDO_PGSQL;
        }
        if (is_a($driver_class, Sq_Lite3driver::class, true)) {
            return self::SQLITE3;
        }
        if (is_a($driver_class, Pg_Sql_Driver::class, true)) {
            return self::PGSQL;
        }
        if (is_a($driver_class, Sql_Srv_Driver::class, true)) {
            return self::SQLSRV;
        }
        if (is_a($driver_class, Oci8Driver::class, true)) {
            return self::OCI8;
        }
        if (is_a($driver_class, Ibm_Db2driver::class, true)) {
            return self::IBM_DB2;
        }
        return null;
    }
    /**
     * @return self::*|null
     */
    private function deduce_from_params(Connection $connection): ?string
    {
        $params = $connection->get_params();
        if (isset($params['driver'])) {
            switch ($params['driver']) {
                case 'pdo_mysql':
                    return self::PDO_MYSQL;
                case 'pdo_sqlite':
                    return self::PDO_SQLITE;
                case 'pdo_pgsql':
                    return self::PDO_PGSQL;
                case 'pdo_oci':
                    return self::PDO_OCI;
                case 'oci8':
                    return self::OCI8;
                case 'ibm_db2':
                    return self::IBM_DB2;
                case 'pdo_sqlsrv':
                    return self::PDO_SQLSRV;
                case 'mysqli':
                    return self::MYSQLI;
                case 'pgsql':
                    // @phpstan-ignore-line never matches on PHP 7.3- with old dbal
                    return self::PGSQL;
                case 'sqlsrv':
                    return self::SQLSRV;
                case 'sqlite3':
                    // @phpstan-ignore-line never matches on PHP 7.3- with old dbal
                    return self::SQLITE3;
                default:
                    return null;
            }
        }
        if (isset($params['driverClass'])) {
            return $this->deduce_from_driver_class($params['driverClass']);
        }
        return null;
    }
}