<?php

declare(strict_types=1);

namespace QueryResult\Entities;

use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;

/**
 * @Entity
 */
class JoinedChild extends JoinedParent
{
    /**
     * @Column(type="integer")
     *
     * @var int
     */
    public $childColumn;

    /**
     * @Column(type="integer", nullable=true)
     *
     * @var int
     */
    public $childNullColumn;
}
