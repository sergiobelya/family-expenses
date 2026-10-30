# Family Expenses MVP — Stores UI Specification

**Scenario:** Browse and manage family stores in a private Telegram chat  
**UI source language:** English; localize per user (Business Requirements §12).  
**Scope:** List, search, create, rename, set a default category, manage explicit aliases, and delete stores.  
**Business requirements:** [business_requirements.md](../business_requirements.md) §§4, 5, 6.3, 6.3.1, 10, 12  
**NFRs:** [nfr_requirements.md](../nfr_requirements.md) §§1–3

Store behavior and duplicate rules are defined in Business Requirements §§6.3 and 10. This document specifies their Telegram interface. Expense creation returns to this section according to [add_expense.md](add_expense.md) Appendix A.

## 1. Interaction model

- Use one editable store card for the current list, form, or store details. Text prompts and validation errors appear on that card; keep the user's messages in chat.
- Show the active family in the card header. All list, search, selection, and mutation actions use that family and revalidate membership when handled.
- Callback authorization and family/entity checks follow NFR §1. Treat search-index matches as candidates and re-fetch the selected store from the database within the active family before showing or mutating it (NFR §2).
- Use inline buttons for actions, selection, pagination, and confirmation. Answer every callback query.
- Keep UI strings in localization resources under `stores.*`; English is the source language.
- Escape store, alias, category, and family names when rendering Telegram HTML.

## 2. Flow map

| State | Action | Result |
|---|---|---|
| ST-01 Store list | Search | ST-02 Search results |
| ST-01 Store list | Add store | ST-03 Create |
| ST-02 Search results | Select a store | ST-04 Store details |
| ST-03 Create or rename | Valid name, no similar candidates | ST-04 Store details |
| ST-03 Create or rename | Fuzzy candidates with different hard keys | ST-05 Similar-name warning |
| ST-04 Store details | Rename | ST-03 with the current name |
| ST-04 Store details | Default category / aliases / delete | Corresponding picker, form, or confirmation |
| Any state | Back | Previous state or main menu |

## 3. UI states

### ST-01 — Store list

**Card:** `🏪 Stores · 👪 {family_name}`; show the current page of family stores, with each store's default category when set.

**Controls:** `➕ Add store` · `🔎 Search` · store buttons · pagination when needed · `⬅️ Main menu`.

- Selecting a store opens ST-04.
- An empty list shows `No stores yet. Add the first store.` and the same Add store action.
- Sort consistently by store name; newly created or renamed records appear in their corresponding position.

### ST-02 — Search stores

Prompt: `🔎 Send a store name to search.` Search using the matching rules in Business Requirements §6.3 and show only current-family results.

- Selecting a result opens ST-04.
- No result: show `No stores found for “{query}”.` with `➕ Create store “{query}”`, `🔎 Search again`, and `⬅️ Back`.
- Create store opens ST-03 with the trimmed query prefilled and editable.
- Search results are suggestions. Never merge or select a store automatically.

### ST-03 — Create or rename a store

Prompt: `🏪 Send the store name.` If opened from search, prefill the trimmed query. Controls: `⬅️ Back` · `✖️ Cancel`.

- Trim input and validate it using Business Requirements §§6.3 and 6.3.1. Keep the entered name on validation errors.
- Keep the operation context: create a new store or rename the existing store. A rename updates that store; it must never create another record.
- If the name collides with an existing hard key, do not save; show ST-06 with the conflicting store.
- If fuzzy matches have different hard keys, show ST-05. Its confirmation saves the current operation: create the store or save the renamed name. If there are no fuzzy matches, complete the current operation and open ST-04.
- If the search index is unavailable and fuzzy suggestions cannot be checked, disclose this before allowing creation or rename. The database hard-key constraint still applies.
- If the operation fails because another request created a collision concurrently, show the same hard-collision state as above.

### ST-04 — Store details

Show the canonical name, default category (or `None`), and explicit aliases (or `None`).

**Controls:** `✏️ Rename` · `🏷 Default category` · `🔤 Aliases` · `🗑 Delete` · `⬅️ Stores`.

- Rename opens the name prompt from ST-03 with the current name prefilled. A case-only or normalization-equivalent rename is allowed when it refers to the same store.
- Default category opens a family-scoped category picker with `No default category`, the existing categories, and `➕ Create category`. Confirming a selection updates only this store's default; it does not change existing expenses.
- `➕ Create category` opens the separate Categories section's create form. Preserve this store and picker context. On return, preselect the created category; require an explicit confirmation to set it as default. If the user leaves without returning, keep the current default unchanged. See [Categories UI](categories.md).
- Aliases opens ST-07.
- Delete opens ST-08 only if the store has no references. If it is referenced, show `This store is used by existing records and cannot be deleted. You can rename it instead.` with `✏️ Rename` and `⬅️ Back`.
- After a successful mutation, refresh the details card from persisted data.

### ST-05 — Similar-name warning

Show likely store matches with their names and, when available, default categories. Use operation-specific text: `A store or seller with a similar name already exists. Are you sure you want to create a new store?` for creation; `A store or seller with a similar name exists. Do you want to use this name?` for rename.

**Controls:** operation-specific confirmation · `🔎 Show similar stores` · `✏️ Edit name` · `✖️ Cancel`.

- For a new store, confirmation is `✅ Create new store`; for a rename, `✅ Save new name`. Save the operation that opened the warning.
- If a hard-key collision is detected on save, show ST-06.
- `Show similar stores` opens a paginated candidate list. Selecting a candidate opens ST-04; Back returns to this warning with its operation context intact.
- Do not show this warning when there are no fuzzy matches or when the candidate has the same hard key; same-key cases use ST-06.

### ST-06 — Hard-key collision

Use operation-specific text: `A store with this name already exists: {store_name}. Choose it from the list instead.` for store creation/rename; `This name is already used by {store_name} as “{matching_name}”.` for an alias collision.

**Controls:** `Open conflicting store` · `✏️ Edit name` · `⬅️ Back`.

The attempted operation is not saved. Editing returns to its name prompt with the rejected text preserved.

### ST-07 — Manage aliases

List the store's explicit aliases. The canonical name is not shown as an alias.

**Controls:** `➕ Add alias` · alias buttons · `⬅️ Store details`.

- Add or rename an alias asks for one alternate name and applies the family-scoped hard-key rules in Business Requirements §6.3. Save it only after collision checks succeed. An alias cannot duplicate its own store's canonical name or another alias on that store.
- A hard-key collision with a canonical name or alias is rejected and shown in ST-06. Do not apply the fuzzy warning for canonical store names to aliases.
- Selecting an alias offers `✏️ Rename`, `🗑 Remove`, and `⬅️ Back`. Removing an alias never removes or changes the canonical store.
- Show validation errors on the card and retain the entered value.

### ST-08 — Delete confirmation

Text: `Delete “{store_name}”? This cannot be undone.`

**Controls:** `🗑 Delete store` · `⬅️ Keep store`.

- Recheck references and authorization at confirmation. If any reference now exists, do not delete; show the referenced-store message from ST-04.
- On success, remove the store and return to ST-01 with `Store deleted.`
- Keep store returns to ST-04 without changes.

## 4. Shared behavior

- If a store is created from the Add expense flow, offer `↩️ Back to expense`. Returning selects the new store in the preserved draft as specified in add_expense.md Appendix A. Other navigation remains available; do not force a return.
- If a store is created from a recurring-template form, offer `↩️ Back to recurring template`. Return to the preserved form with the new store selected; revalidate its family context.
- If a card is stale or its store was deleted, reload the current family-scoped state; show an unavailable message and return to the list if it no longer exists.
- On a family switch, do not keep showing or mutate the previous family's stores. Re-enter the section in the newly active family after membership validation.
- If Telegram cannot edit the current card, send a new card and close the old one without buttons.

## 5. Coverage

- Cover list/search, create/rename collisions, default-category selection, alias management, deletion eligibility, family switching, and return to expense or recurring-template creation. Business rules and security constraints remain in the linked requirements.
