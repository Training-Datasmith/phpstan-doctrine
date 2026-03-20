<?php

declare (strict_types=1);
namespace Php_Stan\Type\Doctrine\Descriptors;

class Small_Float_Type extends Float_Type
{
    public function get_type(): string
    {
        return \Doctrine\DBAL\Types\Small_Float_Type::class;
    }
}