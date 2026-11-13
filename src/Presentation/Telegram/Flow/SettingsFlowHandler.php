<?php

namespace App\Presentation\Telegram\Flow;

use App\Core\Infrastructure\Persistence\Redis\UserInteractionStateRepository;
use App\Family\Application\Query\GetUserFamiliesHandler;
use App\Family\Application\Query\GetUserFamiliesQuery;
use App\Identity\Application\Command\ChangeUserLanguageCommand;
use App\Identity\Application\Command\ChangeUserLanguageHandler;
use App\Presentation\Telegram\Client\TelegramApiClientInterface;
use App\Presentation\Telegram\Context\TelegramFamilyMemberContext;
use App\Presentation\Telegram\Context\TelegramUserContext;
use App\Presentation\Telegram\DTO\TelegramUpdate;
use App\Presentation\Telegram\Keyboard\InlineKeyboardBuilder;
use App\Presentation\Telegram\Keyboard\MainMenuKeyboard;
use App\Presentation\Telegram\Keyboard\SupportedLanguages;
use Symfony\Contracts\Translation\LocaleAwareInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class SettingsFlowHandler implements UserContextFlowHandlerInterface
{
    public function __construct(
        private TelegramApiClientInterface $telegramClient,
        private ChangeUserLanguageHandler $changeUserLanguageHandler,
        private GetUserFamiliesHandler $getUserFamiliesHandler,
        private UserInteractionStateRepository $stateRepository,
        private TranslatorInterface $translator,
        private LocaleAwareInterface $localeAwareTranslator,
        private SettingsMenuRenderer $settingsMenuRenderer,
        private MainMenuKeyboard $mainMenuKeyboard
    ) {}

    public function supports(TelegramUpdate $update, TelegramUserContext $context): bool
    {
        if ($update->text === $this->translator->trans('main_menu.settings') || $update->text === '/settings') {
            return !($context instanceof TelegramFamilyMemberContext);
        }

        if ($update->callbackData === 'settings:main') {
            return !($context instanceof TelegramFamilyMemberContext);
        }

        return str_starts_with($update->callbackData ?? '', 'settings:lang:');
    }

    public function handle(TelegramUpdate $update, TelegramUserContext $context): void
    {
        if ($update->isCallbackQuery()) {
            $this->telegramClient->answerCallbackQuery($update->callbackQueryId);
        }

        if (
            $update->text === $this->translator->trans('main_menu.settings')
            || $update->text === '/settings'
            || $update->callbackData === 'settings:main'
        ) {
            $this->settingsMenuRenderer->render($context);
            return;
        }

        if ($update->callbackData === 'settings:lang:select') {
            $this->renderLanguageSelector($context);
            return;
        }

        if (!str_starts_with($update->callbackData ?? '', 'settings:lang:set:')) {
            return;
        }

        $language = substr($update->callbackData, strlen('settings:lang:set:'));
        if (!SupportedLanguages::isSupported($language)) {
            return;
        }

        $this->stateRepository->clear($context->telegramUserId);
        ($this->changeUserLanguageHandler)(new ChangeUserLanguageCommand(
            userId: $context->user->getId(),
            language: $language
        ));

        $this->localeAwareTranslator->setLocale($language);
        $families = ($this->getUserFamiliesHandler)(new GetUserFamiliesQuery($context->user->getId()));
        $this->telegramClient->sendMessage(
            chatId: $context->chatId,
            text: $this->translator->trans('settings.lang_changed', ['%{language}' => strtoupper($language)]),
            replyMarkup: $this->mainMenuKeyboard->build(count($families) > 1)
        );
        $this->settingsMenuRenderer->render($context);
    }

    private function renderLanguageSelector(TelegramUserContext $context): void
    {
        $builder = new InlineKeyboardBuilder();
        foreach (SupportedLanguages::localNames() as $code => $name) {
            $badge = $context->user->getLanguage() === $code ? ' ✅' : '';
            $builder->button($name . $badge, 'settings:lang:set:' . $code)->row();
        }
        $builder->button($this->translator->trans('common.back'), 'settings:main')->row();

        $this->telegramClient->sendMessage(
            chatId: $context->chatId,
            text: $this->translator->trans('settings.lang_selector_title'),
            replyMarkup: $builder->toArray()
        );
    }
}
