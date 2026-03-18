<?php

declare(strict_types=1);

namespace PDOResultRowCount;

use Doctrine\DBAL\Driver\Result as DriverResult;
use Doctrine\DBAL\Result;

use function PHPStan\Testing\assertType;

function (Result $r): void {
    assertType('int', $r->rowCount());
};

function (DriverResult $r): void {
    assertType('int', $r->rowCount());
};
