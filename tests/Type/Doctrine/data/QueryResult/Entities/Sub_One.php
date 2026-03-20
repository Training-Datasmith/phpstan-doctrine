<?php

declare(strict_types=1);

namespace QueryResult\Entities;

use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\Id;

/**
 * @Entity
 */
class SubOne
{
    /**
     * @GeneratedValue()
     * @Column(type="integer")
     * @Id
     *
     * @var string
     */
    public $id;
}
