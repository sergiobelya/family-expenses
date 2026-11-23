<?php

namespace App\Presentation\Telegram\ExceptionMapper;

use App\Core\Domain\Exception\NotFamilyMemberException;
use App\Family\Domain\Exception\FamilyNotFoundException;
use App\Family\Domain\Exception\InvitationExpiredException;
use App\Family\Domain\Exception\InvitationLimitExceededException;
use App\Family\Domain\Exception\InvitationNotFoundException;
use App\Family\Domain\Exception\InvitationRevokedException;
use App\Family\Domain\Exception\UserAlreadyMemberException;
use Symfony\Component\Translation\TranslatableMessage;
use Throwable;

/**
 * Maps Family domain exceptions to TranslatableMessage.
 */
final readonly class FamilyExceptionMapper implements ExceptionMapperInterface
{
    public function supports(Throwable $exception): bool
    {
        return $exception instanceof FamilyNotFoundException
            || $exception instanceof InvitationExpiredException
            || $exception instanceof InvitationRevokedException
            || $exception instanceof UserAlreadyMemberException
            || $exception instanceof InvitationNotFoundException
            || $exception instanceof InvitationLimitExceededException
            || $exception instanceof NotFamilyMemberException;
    }

    public function map(Throwable $exception): TranslatableMessage
    {
        return match (true) {
            $exception instanceof FamilyNotFoundException => new TranslatableMessage('family.not_found'),
            $exception instanceof InvitationExpiredException => new TranslatableMessage('family.invitation.expired'),
            $exception instanceof InvitationRevokedException => new TranslatableMessage('family.invitation.revoked'),
            $exception instanceof UserAlreadyMemberException => new TranslatableMessage('family.already_member'),
            $exception instanceof InvitationNotFoundException => new TranslatableMessage('family.invitation.not_found'),
            $exception instanceof InvitationLimitExceededException => new TranslatableMessage('family.invitation.limit_exceeded'),
            $exception instanceof NotFamilyMemberException => new TranslatableMessage('family.not_member'),
            default => throw new \RuntimeException('Unhandled exception type in FamilyExceptionMapper.'),
        };
    }
}
