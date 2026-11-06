<?php

namespace App\Presentation\Telegram\Keyboard;

use Symfony\Contracts\Translation\TranslatorInterface;

class MainMenuKeyboard
{
    public function __construct(
        private TranslatorInterface $translator
    ) {}

    /**
     * @return array{keyboard: list<list<string>>, resize_keyboard: bool, is_persistent: bool}
     */
    public function build(bool $hasMultipleFamilies = false, bool $hasActiveDraft = false): array
    {
        $addExpenseLabel = $hasActiveDraft
            ? $this->translator->trans('main_menu.continue_expense')
            : $this->translator->trans('main_menu.add_expense');

        $keyboard = [
            [$addExpenseLabel],
            [
                $this->translator->trans('main_menu.reports'),
                $this->translator->trans('main_menu.history'),
            ],
            [
                $this->translator->trans('main_menu.stores'),
                $this->translator->trans('main_menu.categories'),
            ],
            [
                $this->translator->trans('main_menu.members'),
                $this->translator->trans('main_menu.recurring'),
            ],
            [
                $this->translator->trans('main_menu.settings'),
            ],
        ];

        if ($hasMultipleFamilies) {
            $keyboard[4][] = $this->translator->trans('main_menu.switch_family');
        }

        return [
            'keyboard' => $keyboard,
            'resize_keyboard' => true,
            'is_persistent' => true,
        ];
    }

    public function isMenuButton(string $text): bool
    {
        foreach ([
            'main_menu.add_expense',
            'main_menu.continue_expense',
            'main_menu.reports',
            'main_menu.history',
            'main_menu.stores',
            'main_menu.categories',
            'main_menu.members',
            'main_menu.recurring',
            'main_menu.settings',
            'main_menu.switch_family',
        ] as $translationKey) {
            if ($text === $this->translator->trans($translationKey)) {
                return true;
            }
        }

        return false;
    }
}
