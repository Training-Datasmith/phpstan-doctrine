<?php

declare(strict_types=1);

namespace PHPStan;

use const PHP_VERSION_ID;

use PHPStan\PhpDoc\StubFilesExtension;

class BackedEnumStubExtension implements StubFilesExtension
{
    public function getFiles(): array
    {
        if (PHP_VERSION_ID >= 80100) {
            return [];
        }

        return [
            __DIR__ . '/../compatibility/BackedEnum.stub',
        ];
    }

}
