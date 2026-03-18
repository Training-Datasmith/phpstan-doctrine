<?php

declare(strict_types=1);

namespace QueryResult\Entities;

use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;

/**
 * @Entity
 */
class SingleTableChild extends SingleTableParent
{
    /**
     * @Column(type="integer", nullable=true)
     *
     * @var int|null
     */
    public $childNullColumn;
}
