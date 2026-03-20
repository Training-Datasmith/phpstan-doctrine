<?php

declare (strict_types=1);
namespace Php_Stan\Type\Doctrine\Descriptors;

use Php_Stan\Type\Type;
/** @api */
interface Doctrine_Type_Descriptor
{
    /**
     * @return class-string<\Doctrine\DBAL\Types\Type>
     */
    public function get_type(): string;
    /**
     * This is used for inferring direct column results, e.g. SELECT e.field
     * It should comply with convertToPHPValue return value
     */
    public function get_writable_to_property_type(): Type;
    public function get_writable_to_database_type(): Type;
    /**
     * This is used for inferring how database fetches column of such type
     *
     * This is not used for direct column type inferring,
     * but when such column appears in expression like SELECT MAX(e.field)
     *
     * Sometimes, the type cannot be reliably decided without driver context,
     * use DoctrineTypeDriverAwareDescriptor in such cases
     */
    public function get_database_internal_type(): Type;
}