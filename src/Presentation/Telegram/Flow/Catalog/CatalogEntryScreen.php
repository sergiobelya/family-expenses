<?php

namespace App\Presentation\Telegram\Flow\Catalog;

use App\Presentation\Telegram\Context\TelegramFamilyMemberContext;
use Symfony\Component\Uid\Uuid;

interface CatalogEntryScreen
{
    public function renderList(TelegramFamilyMemberContext $context, int $page, ?string $notice = null): void;

    public function renderDetails(TelegramFamilyMemberContext $context, Uuid $id, ?string $notice = null): void;

    public function renderDeleteConfirmation(TelegramFamilyMemberContext $context, Uuid $id): void;

    public function deleteEntry(TelegramFamilyMemberContext $context, Uuid $id): void;

    /** @param array<string, mixed>|null $expenseReturnContext */
    public function startNameInput(
        TelegramFamilyMemberContext $context,
        string $operation,
        ?Uuid $id = null,
        ?string $returnStoreId = null,
        ?string $prefill = null,
        ?array $expenseReturnContext = null
    ): void;

    /**
     * @param array<string, mixed> $data
     */
    public function processNameInput(
        TelegramFamilyMemberContext $context,
        array $data,
        string $value,
        bool $similarityConfirmed = false
    ): void;

    public function startSearch(TelegramFamilyMemberContext $context): void;

    /**
     * Searches entries by query text and renders the results.
     */
    public function searchAndRender(TelegramFamilyMemberContext $context, string $query): void;

    public function getListCallback(): string;
}
