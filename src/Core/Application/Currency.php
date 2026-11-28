<?php

namespace App\Core\Application;

final readonly class Currency
{
    public function __construct(
        public string $code,
        public string $localizedName,
    ) {
    }
}
