<?php

namespace App\Family\Application\Command;

use App\Family\Domain\Entity\Family;
use App\Family\Domain\Entity\FamilyMember;
use App\Family\Domain\Repository\FamilyMemberRepositoryInterface;
use App\Family\Domain\Repository\FamilyRepositoryInterface;
use App\Identity\Domain\Repository\UserRepositoryInterface;
use App\Core\Domain\ValueObject\CurrencyCode;
use App\Core\Domain\ValueObject\Timezone;
use InvalidArgumentException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
class CreateFamilyHandler
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private FamilyRepositoryInterface $familyRepository,
        private FamilyMemberRepositoryInterface $familyMemberRepository
    ) {
    }

    public function __invoke(CreateFamilyCommand $command): Family
    {
        $user = $this->userRepository->findById($command->userId);
        if ($user === null) {
            throw new InvalidArgumentException(sprintf('User with id "%s" was not found.', $command->userId));
        }

        $family = Family::create(
            name: $command->name,
            timezone: Timezone::fromString($command->timezone),
            defaultExpenseCurrency: CurrencyCode::fromString($command->defaultExpenseCurrency),
            reportingCurrency: CurrencyCode::fromString($command->reportingCurrency),
            defaultExchangeRateProvider: $command->defaultExchangeRateProvider,
            countryCode: $command->countryCode,
            creatorUserId: $user->getId()
        );
        $this->familyRepository->save($family);

        $member = FamilyMember::join(
            familyId: $family->getId(),
            userId: $user->getId()
        );
        $this->familyMemberRepository->save($member);

        $user->setActiveFamilyId($family->getId());
        $this->userRepository->save($user);

        return $family;
    }
}
