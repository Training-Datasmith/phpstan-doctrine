<?php

declare(strict_types=1);

namespace QueryResult\Entities;

use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\DiscriminatorColumn;
use Doctrine\ORM\Mapping\DiscriminatorMap;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\InheritanceType;

/**
 * @Entity
 * @InheritanceType("JOINED")
 * @DiscriminatorColumn(name="discr", type="string")
 * @DiscriminatorMap({
 *  "child"="QueryResult\Entities\JoinedChild"
 * })
 */
abstract class JoinedParent
{
    /**
     * @Column(type="bigint")
     * @Id
     *
     * @var string
     */
    public $id;

    /**
     * @Column(type="integer")
     *
     * @var int
     */
    public $parentColumn;

    /**
     * @Column(type="integer", nullable=true)
     *
     * @var int
     */
    public $parentNullColumn;
}
