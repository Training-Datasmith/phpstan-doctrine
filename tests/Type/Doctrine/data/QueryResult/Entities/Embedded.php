<?php

declare(strict_types=1);

namespace QueryResult\Entities;

use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Embeddable;
use Doctrine\ORM\Mapping\Embedded as ORMEmbedded;

/**
 * @Embeddable
 */
class Embedded
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

    /**
     * @ORMEmbedded(class="QueryResult\Entities\NestedEmbedded")
     *
     * @var NestedEmbedded
     */
    public $nestedEmbedded;
}
