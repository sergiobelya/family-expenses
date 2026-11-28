<?php

namespace App\Tests\Unit\Presentation\Telegram\Flow\Catalog;

use App\Core\Domain\ValueObject\CurrencyCode;
use App\Core\Domain\ValueObject\Timezone;
use App\Core\Infrastructure\Messenger\MessageBusDispatcherInterface;
use App\Core\Infrastructure\Persistence\Redis\UserInteractionState;
use App\Core\Infrastructure\Persistence\Redis\UserInteractionStateRepository;
use App\Family\Domain\Entity\Family;
use App\Identity\Domain\Entity\User;
use App\Presentation\Telegram\Client\TelegramApiClientInterface;
use App\Presentation\Telegram\Context\TelegramFamilyMemberContext;
use App\Presentation\Telegram\Context\TelegramUserContext;
use App\Presentation\Telegram\Flow\Catalog\CatalogFlowUtils;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

final class CatalogFlowUtilsTest extends TestCase
{
    public function testFailedCleanupDoesNotPreventReplacementCardStateFromBeingSaved(): void
    {
        $family = Family::create(
            'Smiths',
            Timezone::fromString('UTC'),
            CurrencyCode::fromString('USD'),
            CurrencyCode::fromString('USD')
        );
        $context = new TelegramFamilyMemberContext(
            new TelegramUserContext(new User(123456), 'en', 123456, 111),
            $family
        );
        $previousState = new UserInteractionState(123456, CatalogFlowUtils::FLOW_NAME, 'list', ['message_id' => 9]);

        $states = $this->createMock(UserInteractionStateRepository::class);
        $states->expects(self::exactly(2))->method('get')->with(123456)->willReturn($previousState);
        $states->expects(self::once())->method('set')->with(self::callback(
            static fn (UserInteractionState $state): bool => $state->data['message_id'] === 10
        ));

        $telegram = $this->createMock(TelegramApiClientInterface::class);
        $telegram->expects(self::exactly(2))
            ->method('editMessageText')
            ->willReturnOnConsecutiveCalls(['ok' => false], ['ok' => false]);
        $telegram->expects(self::once())
            ->method('sendMessage')
            ->with(111, 'Replacement card', ['inline_keyboard' => []])
            ->willReturn(['ok' => true, 'result' => ['message_id' => 10]]);
        $telegram->expects(self::once())->method('deleteMessage')->with(111, 9)->willReturn(false);

        $utils = new CatalogFlowUtils(
            $telegram,
            $states,
            $this->createMock(MessageBusDispatcherInterface::class),
            $this->createMock(TranslatorInterface::class)
        );

        $utils->render($context, 'Replacement card', ['inline_keyboard' => []], $previousState);
    }
}
