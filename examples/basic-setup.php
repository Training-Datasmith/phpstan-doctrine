<?php

declare(strict_types=1);

/**
 * Example: Setting up phpstan-doctrine for a project using Doctrine ORM.
 *
 * After installing via Composer:
 *   composer require --dev phpstan/phpstan-doctrine
 *
 * Configure phpstan.neon:
 *
 *   includes:
 *     - vendor/phpstan/phpstan-doctrine/extension.neon
 *
 *   parameters:
 *     doctrine:
 *       objectManagerLoader: tests/object-manager.php
 *
 * The object manager loader returns a configured EntityManager that PHPStan uses
 * to load entity metadata for DQL and type checking.
 */

// tests/object-manager.php — example bootstrap:
//
// <?php
// require_once __DIR__ . '/../vendor/autoload.php';
//
// use Doctrine\ORM\EntityManager;
// use Doctrine\ORM\ORMSetup;
//
// $config = ORMSetup::createAttributeMetadataConfiguration(
//     paths: [__DIR__ . '/../src'],
//     isDevMode: true,
// );
//
// $connection = \Doctrine\DBAL\DriverManager::getConnection([
//     'driver' => 'pdo_sqlite',
//     'memory' => true,
// ]);
//
// return new EntityManager($connection, $config);

// Example entity that PHPStan will analyze with type-aware rules:

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'users')]
class User
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private int $id;

    #[ORM\Column(type: 'string', length: 255)]
    private string $email;

    #[ORM\Column(type: 'boolean')]
    private bool $active;

    public function getId(): int
    {
        return $this->id;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function isActive(): bool
    {
        return $this->active;
    }
}
