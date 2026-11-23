<?php

namespace App\Presentation\Telegram\ExceptionMapper;

use Symfony\Component\Translation\TranslatableMessage;
use Throwable;

interface ExceptionMapperInterface
{
    public function supports(Throwable $exception): bool;

    public function map(Throwable $exception): TranslatableMessage;
}
