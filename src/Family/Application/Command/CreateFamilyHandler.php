<?php

namespace App\Family\Application\Command;

use App\Family\Domain\Entity\Family;
use App\Family\Domain\Entity\FamilyMember;
use App\Family\Domain\Repository\FamilyMemberRepositoryInterface;
use App\Family\Domain\Repository\FamilyRepositoryInterface;
use App\Core\Domain\ValueObject\CountryCode;
use App\Core\Domain\ValueObject\CurrencyCode;
use App\Core\Domain\ValueObject\Timezone;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class CreateFamilyHandler
{
    public function __construct(
        private FamilyRepositoryInterface $familyRepository,
        private FamilyMemberRepositoryInterface $familyMemberRepository
    ) {
    }

    public function __invoke(CreateFamilyCommand $command): Family
    {
        $family = Family::create(
            name: $command->name,
            timezone: Timezone::fromString($command->timezone),
            defaultExpenseCurrency: CurrencyCode::fromString($command->defaultExpenseCurrency),
            reportingCurrency: CurrencyCode::fromString($command->reportingCurrency),
            defaultExchangeRateProvider: $command->defaultExchangeRateProvider,
            countryCode: $command->countryCode !== null ? CountryCode::fromString($command->countryCode) : null,
            creatorUserId: $command->userId
        );
        $this->familyRepository->save($family);

        $member = FamilyMember::join(
            familyId: $family->getId(),
            userId: $command->userId
        );
        $this->familyMemberRepository->save($member);

        return $family;
    }
}
