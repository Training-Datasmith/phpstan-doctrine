<?php

declare (strict_types=1);
namespace Php_Stan\Type\Doctrine\Descriptors;

use Doctrine\DBAL\Connection;
use Php_Stan\Type\Type;
/** @api */
interface Doctrine_Type_Driver_Aware_Descriptor
{
    /**
     * This is used for inferring how database fetches column of such type
     * It should return the native type without stringification that may occur on certain PHP versions or driver configuration
     *
     * This is not used for direct column type inferring,
     * but when such column appears in expression like SELECT MAX(e.field)
     *
     * See: https://github.com/janedbal/php-database-drivers-fetch-test
     *
     *              mysql     sqlite  pdo_pgsql    pgsql
     * - decimal:  string  int|float     string   string
     * - float:    float       float     string    float
     * - bigint:   int           int        int      int
     * - bool:     int           int       bool     bool
     */
    public function get_database_internal_type_for_driver(Connection $connection): Type;
}