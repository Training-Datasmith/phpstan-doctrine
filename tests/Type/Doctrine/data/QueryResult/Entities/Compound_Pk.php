<?php

declare(strict_types=1);

namespace QueryResult\Entities;

use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\Id;

/**
 * @Entity
 */
class CompoundPk
{
    /**
     * @Column(type="string")
     * @Id
     *
     * @var string
     */
    public $id;

    /**
     * @Column(type="integer")
     * @Id
     *
     * @var int
     */
    public $version;
}
