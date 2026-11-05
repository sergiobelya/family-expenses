<?php

namespace App\Presentation\Telegram\Flow;

use App\Family\Application\Command\JoinFamilyByInvitationCommand;
use App\Family\Application\Command\JoinFamilyByInvitationHandler;
use App\Family\Domain\Exception\FamilyNotFoundException;
use App\Family\Domain\Exception\InvitationExpiredException;
use App\Family\Domain\Exception\InvitationLimitExceededException;
use App\Family\Domain\Exception\InvitationNotFoundException;
use App\Family\Domain\Exception\InvitationRevokedException;
use App\Family\Domain\Repository\FamilyMemberRepositoryInterface;
use App\Family\Domain\Repository\FamilyRepositoryInterface;
use App\Family\Domain\Repository\InvitationRepositoryInterface;
use App\Family\Domain\Repository\InvitationUseRepositoryInterface;
use App\Family\Infrastructure\Security\InvitationTokenGenerator;
use App\Presentation\Telegram\Client\TelegramApiClientInterface;
use App\Presentation\Telegram\Context\TelegramUserContext;
use App\Presentation\Telegram\DTO\TelegramUpdate;
use App\Presentation\Telegram\Keyboard\InlineKeyboardBuilder;
use App\Presentation\Telegram\Keyboard\MainMenuKeyboard;
use Symfony\Contracts\Translation\TranslatorInterface;

class JoinFamilyFlowHandler implements FlowHandlerInterface
{
    public function __construct(
        private TelegramApiClientInterface $telegramClient,
        private JoinFamilyByInvitationHandler $joinFamilyByInvitationHandler,
        private InvitationRepositoryInterface $invitationRepository,
        private InvitationUseRepositoryInterface $invitationUseRepository,
        private FamilyRepositoryInterface $familyRepository,
        private FamilyMemberRepositoryInterface $familyMemberRepository,
        private InvitationTokenGenerator $tokenGenerator,
        private MainMenuKeyboard $mainMenuKeyboard,
        private TranslatorInterface $translator
    ) {}

    public function supports(TelegramUpdate $update, TelegramUserContext $context): bool
    {
        if ($update->text !== null && preg_match('#^/start\s+invite_([a-zA-Z0-9_\-]+)$#', trim($update->text))) {
            return true;
        }

        if (str_starts_with($update->callbackData ?? '', 'family:join:confirm:')) {
            return true;
        }

        return false;
    }

    public function handle(TelegramUpdate $update, TelegramUserContext $context): void
    {
        if ($update->isCallbackQuery()) {
            $this->telegramClient->answerCallbackQuery($update->callbackQueryId);
        }

        if (str_starts_with($update->callbackData ?? '', 'family:join:confirm:')) {
            $plainToken = substr($update->callbackData, strlen('family:join:confirm:'));

            try {
                $result = ($this->joinFamilyByInvitationHandler)(new JoinFamilyByInvitationCommand(
                    token: $plainToken,
                    userId: $context->user->getId()
                ));
                $family = $result['family'];

                $messageKey = $result['already_member'] ? 'family.join.already_member' : 'family.join.joined_success';
                $msg = $this->translator->trans($messageKey, [
                    '%{family_name}' => htmlspecialchars($family->getName(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
                ]);

                $this->telegramClient->sendMessage(
                    chatId: $context->chatId,
                    text: $msg,
                    replyMarkup: $this->mainMenuKeyboard->build(false)
                );
            } catch (InvitationNotFoundException | InvitationExpiredException | InvitationRevokedException | InvitationLimitExceededException | FamilyNotFoundException) {
                $this->telegramClient->sendMessage(
                    chatId: $context->chatId,
                    text: $this->translator->trans('family.join.invalid_or_expired')
                );
            }
            return;
        }

        if ($update->text !== null && preg_match('#^/start\s+invite_([a-zA-Z0-9_\-]+)$#', trim($update->text), $matches)) {
            $plainToken = $matches[1];
            $tokenHash = $this->tokenGenerator->hashToken($plainToken);
            $invitation = $this->invitationRepository->findByTokenHash($tokenHash);

            if ($invitation === null || !$invitation->isActive()) {
                $this->telegramClient->sendMessage(
                    chatId: $context->chatId,
                    text: $this->translator->trans('family.join.invalid_or_expired')
                );
                return;
            }

            if ($invitation->getMaxUses() !== null) {
                $usesCount = $this->invitationUseRepository->countByInvitationId($invitation->getId());
                if ($usesCount >= $invitation->getMaxUses()) {
                    $this->telegramClient->sendMessage(
                        chatId: $context->chatId,
                        text: $this->translator->trans('family.join.invalid_or_expired')
                    );
                    return;
                }
            }

            $family = $this->familyRepository->findById($invitation->getFamilyId());
            if ($family === null) {
                $this->telegramClient->sendMessage(
                    chatId: $context->chatId,
                    text: $this->translator->trans('family.join.invalid_or_expired')
                );
                return;
            }

            // Check if already a member
            $member = $this->familyMemberRepository->findByFamilyAndUser($family->getId(), $context->user->getId());
            if ($member !== null && $member->isActive()) {
                $alreadyMemberMsg = $this->translator->trans('family.join.already_member', [
                    '%{family_name}' => htmlspecialchars($family->getName(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
                ]);

                $kb = (new InlineKeyboardBuilder())
                    ->button($this->translator->trans('family.join.switch_to_family', ['%{family_name}' => $family->getName()]), 'family:select:' . $family->getId()->toRfc4122())
                    ->toArray();

                $this->telegramClient->sendMessage(
                    chatId: $context->chatId,
                    text: $alreadyMemberMsg,
                    replyMarkup: $kb
                );
                return;
            }

            $confirmMsg = $this->translator->trans('family.join.confirm_title', [
                '%{family_name}' => htmlspecialchars($family->getName(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
            ]);

            $kb = (new InlineKeyboardBuilder())
                ->button($this->translator->trans('family.join.join_btn'), 'family:join:confirm:' . $plainToken)
                ->button($this->translator->trans('common.cancel'), 'nav:main_menu')
                ->toArray();

            $this->telegramClient->sendMessage(
                chatId: $context->chatId,
                text: $confirmMsg,
                replyMarkup: $kb
            );
        }
    }
}
