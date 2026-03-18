<?php

declare(strict_types=1);

namespace QueryResult\Entities;

use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Embeddable;

/**
 * @Embeddable
 */
class NestedEmbedded
{
    /**
     * @Column(type="integer")
     *
     * @var int
     */
    public $intColumn;

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
}
