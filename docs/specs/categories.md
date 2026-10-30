# Family Expenses MVP — Categories UI Specification

**Scenario:** Browse and manage family categories in a private Telegram chat  
**UI source language:** English; localize per user (Business Requirements §12).  
**Scope:** List, search, create, rename, and delete categories.  
**Business requirements:** [business_requirements.md](../business_requirements.md) §§4, 5, 6.3, 6.3.1, 6.4, 10, 12  
**NFRs:** [nfr_requirements.md](../nfr_requirements.md) §§1–3

Category behavior and duplicate rules are defined in Business Requirements §§6.3, 6.3.1, and 10. This document specifies their Telegram interface. Expense creation returns to this section according to [add_expense.md](add_expense.md) Appendix A.

## 1. Interaction model

- Use one editable category card for the current list, form, or details. Text prompts and validation errors appear on that card; keep the user's messages in chat.
- Show the active family in the card header. Scope every list, search, selection, and mutation to that family and revalidate membership when handled.
- Callback authorization and family/entity checks follow NFR §1. Treat search-index matches as candidates and re-fetch the selected category from the database within the active family before showing or mutating it (NFR §2).
- Use inline buttons for actions, selection, pagination, and confirmation. Answer every callback query.
- Keep UI strings in localization resources under `categories.*`; English is the source language.
- Escape category and family names when rendering Telegram HTML.

## 2. Flow map

| State | Action | Result |
|---|---|---|
| CT-01 Category list | Search | CT-02 Search results |
| CT-01 Category list | Add category | CT-04 Create |
| CT-02 Search results | Select a category | CT-03 Category details |
| CT-03 Category details | Rename | CT-04 with the current name |
| CT-03 Category details | Delete | CT-07 Delete confirmation or reference warning |
| CT-04 Create or rename | Valid name, no similar candidates | CT-03 Category details |
| CT-04 Create or rename | Fuzzy candidates with different hard keys | CT-05 Similar-name warning |
| Any state | Back | Previous state or main menu |

## 3. UI states

### CT-01 — Category list

**Card:** `🏷 Categories · 👪 {family_name}`; show the current page of family categories.

**Controls:** `➕ Add category` · `🔎 Search` · category buttons · pagination when needed · `⬅️ Main menu`.

- Selecting a category opens CT-03.
- An empty list shows `No categories yet. Add the first category.` and the same Add category action.
- Sort consistently by category name; newly created or renamed categories appear in their corresponding position.

### CT-02 — Search categories

Prompt: `🔎 Send a category name to search.` Use the matching rules in Business Requirements §6.3 and show only current-family results.

- Selecting a result opens CT-03.
- No result: show `No categories found for “{query}”.` with `➕ Create category “{query}”`, `🔎 Search again`, and `⬅️ Back`.
- Create category opens CT-04 with the trimmed query prefilled and editable.
- Results are suggestions. Never merge or select a category automatically.

### CT-03 — Category details

Show the canonical category name.

**Controls:** `✏️ Rename` · `🗑 Delete` · `⬅️ Categories`.

- Rename opens CT-04 with the current name prefilled. A case-only or normalization-equivalent rename is allowed for this category.
- Delete opens CT-07. If the category is referenced, show the reference warning instead of the confirmation.
- After a successful mutation, refresh the details card from persisted data.

### CT-04 — Create or rename a category

Prompt: `🏷 Send the category name.` If opened from search, prefill the trimmed query. Controls: `⬅️ Back` · `✖️ Cancel`.

- Trim input and validate it using Business Requirements §§6.3 and 6.3.1. Keep the entered name on validation errors.
- Keep the operation context: create a new category or rename the existing category. A rename updates that category; it must never create another record.
- Apply the matching, hard-key collision, unavailable-index, and concurrent-collision behavior from [Stores UI ST-03–ST-06](stores.md), using category-specific content and retaining the create/rename context.

### CT-05 — Similar-name warning

Show likely category matches and use the warning behavior in Stores UI ST-05 with category-specific text: `A similar category already exists. Are you sure you want to create a new category?` for creation; `A similar category exists. Do you want to use this name?` for rename. Selecting a category candidate opens CT-03; Back returns here with the operation context intact.

### CT-06 — Hard-key collision

Text: `A category with this name already exists: {category_name}. Choose it from the list instead.`

**Controls:** `Open category` · `✏️ Edit name` · `⬅️ Back`.

Use the hard-key collision behavior in Stores UI ST-06. Do not save the attempted operation; editing returns to its name prompt with the rejected text preserved.

### CT-07 — Delete confirmation

Text: `Delete “{category_name}”? This cannot be undone.`

**Controls:** `🗑 Delete category` · `⬅️ Keep category`.

- Recheck references and authorization at confirmation. If an expense allocation, store default, recurring template/occurrence, or other record references the category, do not delete; show `This category is used by existing records and cannot be deleted.`
- On success, remove the category and return to CT-01 with `Category deleted.`
- Keep category returns to CT-03 without changes.

## 4. Shared behavior

- If a category is created from Add expense, offer `↩️ Back to expense`. Returning selects the new category in the preserved expense draft as specified in add_expense.md Appendix A. From the split editor, continue to enter the new row's amount. Other navigation remains available; do not force a return.
- If a category is created from a recurring-template form, offer `↩️ Back to recurring template`. Return to the preserved form with the new category selected; if creating a split row, continue to allocation amount entry. Revalidate the family context. Other navigation remains available.
- If a category is created from a store's default-category picker, offer `↩️ Back to store`. Return to the preserved picker with the new category preselected; setting the default requires the user's confirmation there. Revalidate that the store and family context are still available. If they are not, do not apply the category as a default. Other navigation remains available.
- If the draft has expired, use the response in add_expense.md Appendix A; do not imply its data can be recovered.
- If a card is stale or the category was deleted, reload the current family-scoped state; show an unavailable message and return to the list if it no longer exists.
- On a family switch, do not keep showing or mutate the previous family's categories. Re-enter the section in the newly active family after membership validation.
- If Telegram cannot edit the current card, send a new card and close the old one without buttons.

## 5. Coverage

- Cover list/search, create/rename collisions, deletion eligibility, family switching, and return to expense, recurring-template, or store settings. Business rules and security constraints remain in the linked requirements.
