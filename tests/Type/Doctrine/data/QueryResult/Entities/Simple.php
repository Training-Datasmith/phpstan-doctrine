<?php

declare(strict_types=1);

namespace QueryResult\Entities;

use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\Id;/**
 * @Entity
 */

class Simple
{
    /**
     * @Column(type="bigint")
     * @Id
     *
     * @var int|string
     */
    public $id;

    /**
     * @Column(type="integer")
     *
     * @var int
     */
    public $intColumn;

    /**
     * @Column(type="float")
     *
     * @var float
     */
    public $floatColumn;

    /**
     * @Column(type="decimal", scale=1, precision=2)
     *
     * @var string
     */
    public $decimalColumn;

    /**
     * @Column(type="string")
     *
     * @var string
     */
    public $stringColumn;

    /**
     * @Column(type="string", nullable=true)
     *
     * @var string|null
     */
    public $stringNullColumn;

    /**
     * @Column(type="custom_int", nullable=true)
     *
     * @var mixed
     */
    public $mixedColumn;

}
