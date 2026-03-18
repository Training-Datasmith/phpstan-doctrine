<?php

declare(strict_types=1);

namespace QueryResult\Entities;

use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\JoinColumn;
use Doctrine\ORM\Mapping\ManyToOne;

/**
 * @Entity
 */
class CompoundPkAssoc
{
    /**
     * @ManyToOne(targetEntity="QueryResult\Entities\One")
     * @JoinColumn(nullable=false)
     * @Id
     *
     * @var One
     */
    public $one;

    /**
     * @Column(type="integer")
     * @Id
     *
     * @var int
     */
    public $version;
}
