<?php

namespace App\Family\Application\Command;

use App\Family\Domain\Entity\Family;
use App\Family\Domain\Exception\FamilyNotFoundException;
use App\Family\Domain\Exception\NotFamilyMemberException;
use App\Family\Domain\Repository\FamilyMemberRepositoryInterface;
use App\Family\Domain\Repository\FamilyRepositoryInterface;
use App\Core\Domain\ValueObject\CurrencyCode;
use App\Core\Domain\ValueObject\Timezone;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
class UpdateFamilySettingsHandler
{
    public function __construct(
        private FamilyRepositoryInterface $familyRepository,
        private FamilyMemberRepositoryInterface $familyMemberRepository
    ) {
    }

    public function __invoke(UpdateFamilySettingsCommand $command): Family
    {
        $familyId = $command->familyId;
        $userId = $command->updatedByUserId;
        $family = $this->familyRepository->findById($familyId);
        if ($family === null) {
            throw FamilyNotFoundException::withId((string) $command->familyId);
        }

        if (!$this->familyMemberRepository->isMember($familyId, $userId)) {
            throw NotFamilyMemberException::forFamily((string) $command->familyId, (string) $command->updatedByUserId);
        }

        $family->updateSettings(
            name: $command->name ?? $family->getName(),
            timezone: $command->timezone !== null ? Timezone::fromString($command->timezone) : $family->getTimezone(),
            defaultExpenseCurrency: $command->defaultExpenseCurrency !== null ? CurrencyCode::fromString($command->defaultExpenseCurrency) : $family->getDefaultExpenseCurrency(),
            reportingCurrency: $command->reportingCurrency !== null ? CurrencyCode::fromString($command->reportingCurrency) : $family->getReportingCurrency(),
            defaultExchangeRateProvider: $command->defaultExchangeRateProvider ?? $family->getDefaultExchangeRateProvider(),
            countryCode: $command->countryCode ?? $family->getCountry(),
            actorUserId: $userId
        );

        $this->familyRepository->save($family);

        return $family;
    }
}
