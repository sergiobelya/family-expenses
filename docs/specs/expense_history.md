# Family Expenses MVP — History and Search UI Specification

**Scenario:** Browse, search, view, edit, and delete saved family expenses in a private Telegram chat  
**UI source language:** English; localize per user (Business Requirements §12).  
**Business requirements:** [business_requirements.md](../business_requirements.md) §§4, 5, 6.5–6.7, 7.1–7.5, 9, 12  
**NFRs:** [nfr_requirements.md](../nfr_requirements.md) §§1–3

This document specifies how users browse and change saved expenses. Report creation and export have separate scenarios.

## 1. Interaction model

- Use one editable card for the current history page, search/filter form, or expense details. Text prompts and errors appear on that card; keep the user's messages in chat.
- Show the active family in the card header. Scope all reads and mutations to it and revalidate membership on every action (NFR §1).
- Show edit and delete actions only to the expense author. Enforce authorship on the server for each mutation (Business Requirements §6.6).
- Treat indexed search results as candidates; re-fetch each expense from the database within the active family before showing or editing it (NFR §2).
- Use inline buttons for filters, pagination, editing, and confirmation. Answer every callback query.
- Keep UI strings in localization resources under `expense.history.*`; English is the source language. Escape all user-provided names in Telegram HTML.

## 2. Flow map

| State | Action | Result |
|---|---|---|
| HS-01 History | Search | HS-02 Filters |
| HS-01 History | Select expense | HS-03 Expense details |
| HS-02 Filters | Apply | HS-01 Filtered results |
| HS-03 Expense details | Edit | HS-04 Edit expense |
| HS-03 Expense details | Delete | HS-05 Delete confirmation |
| HS-04 Edit expense | Review changes | HS-04 Review |
| HS-04 Review | Save changes | HS-03 Updated details |
| HS-05 Delete confirmation | Confirm | HS-01 Updated history |
| Any state | Back | Previous state or main menu |

## 3. UI states

### HS-01 — History and results

Show non-deleted expenses for the active family, most recent purchase date first. Each result shows date, store, original amount/currency, and category (or the number of categories when split). Do not show converted match values in the result row.

**Controls:** `🔎 Search / Filter` · expense buttons · pagination when needed · `⬅️ Main menu`.

- Empty history: `No expenses yet.`
- Empty filtered results: `No expenses match these filters.` Offer `✏️ Change filters` and `🧹 Clear filters`.
- Selecting an expense opens HS-03.

### HS-02 — Search and filters

Allow users to combine family-scoped filters for store, category, purchase date, and amount.

**Controls:** `🏪 Store` · `🏷 Category` · `📅 Date` · `💰 Amount` · `📊 Currency` (when Amount is active) · `🔎 Apply filters` · `🧹 Clear all` · `⬅️ Back`.

- Store and category filters use selectors populated only from the active family.
- Date accepts a single date or a date range using the [unambiguous date entry rules](add_expense.md#63-purchase-date).
- Amount filter uses inclusive `From` and `To` bounds. Require both; an exact amount can be searched by entering the same value twice. Reject a lower bound greater than the upper bound. Validate them using the [Add expense amount rules](add_expense.md#61-amount).
- Currency defaults to the family's reporting currency. The user may select another supported currency; this changes only the search filter, not family settings.
- Apply the currency matching rules in Business Requirements §9. Show the filter range and currency in the results; result rows continue to show original amounts and currencies. The details card follows Business Requirements §6.5.
- When Business Requirements §9 indicates potentially incomplete results, show `Some expenses could not be converted. Results may be incomplete.` Do not write an `ExpenseConversion` during search or wait indefinitely for a rate provider (NFR §3).
- Combine selected filters with AND. Applying them opens HS-01 with the active filters shown and results paginated. Filters remain active while paging or opening and closing an expense.

### HS-03 — Expense details

Show the fields from Business Requirements §6.5: store, purchase date, original amount/currency, category allocations, and reporting-currency amount.

- If original currency equals reporting currency, show the original amount without a conversion.
- Otherwise show the saved applicable conversion, or an on-demand display conversion with its basis/date (Business Requirements §6.5). If no rate is available, show `Not converted`. Current-rate fallback behavior follows Business Requirements §7.6. Never save a report-time/display conversion from this view.
- The author sees `✏️ Edit` and `🗑 Delete`. Other family members see the card without these controls.
- `⬅️ History` returns to the prior result page and retains its filters.

### HS-04 — Edit expense

Open an edit card populated with the saved values. The original expense remains unchanged until confirmation.

**Edit controls:** `🏪 Store` · `💰 Amount / currency` · `🏷 Categories` · `📅 Date` · `✍️ Exchange rate` when source and reporting currencies differ · `✅ Review changes` · `✖️ Cancel`.

- Reuse input patterns from Add expense states AE-03–AE-12 and its [amount](add_expense.md#61-amount), [date](add_expense.md#63-purchase-date), [category allocation](add_expense.md#64-category-allocation), and [manual rate](add_expense.md#65-manual-exchange-rate) validation. Apply saved-expense edit rules from Business Requirements §6.6 and conversion rules from Business Requirements §§7.2–7.5.
- Changing the store does not silently replace the expense's existing category allocation. Category changes are explicit; allocations must reconcile before saving (Business Requirements §6.4).
- Editing an amount may keep an existing allocation split open for adjustment. Do not save incomplete or unreconciled allocations.
- An on-demand display conversion is not a saved rate and cannot be reused. For amount-only edits, retain and recalculate only an applicable saved rate; currency/date changes require a new lookup or manual rate and clear any prior manual rate. A reporting-currency change requires a refreshed conversion and another review (Business Requirements §6.6). If no rate is available, show that conversion is unavailable and offer `Save without conversion`.
- When the expense currency equals the current reporting currency, omit the conversion and do not create a conversion record.
- Review shows the proposed values and conversion result. **Review controls:** `✅ Save changes` · `✏️ Continue editing` · `✖️ Cancel`. Save applies the edit; Continue returns to the edit card; Cancel discards the edit session and returns to the unchanged expense card.
- Revalidate membership, authorship, fields, store/category family ownership, and conversion against current settings before saving. Persist the expense, allocations, and any required available conversion atomically (NFR §3). If membership or authorship is no longer valid, stop editing and return to history with an access message.
- On success, refresh HS-03 from persisted data and show `Expense updated.` On a validation or save error, keep the edit session and explain what needs attention.

### HS-05 — Delete confirmation

Text: `Delete this expense? It will be removed from history and reports.`

**Controls:** `🗑 Delete expense` · `⬅️ Keep expense`.

- Revalidate membership and authorship at confirmation. If either check fails, do not delete; return to history with an access message.
- On confirmation, soft-delete the expense. Remove it from standard history, search results, totals, and reports (Business Requirements §6.6).
- Return to HS-01 with `Expense deleted.` and keep the current filters and page when possible.
- Keep expense returns to HS-03 without changes.

## 4. Shared behavior

- `Open expense` from Add expense success opens HS-03 for the saved expense.
- If the expense is deleted or no longer available, show `This expense is no longer available.` and return to HS-01.
- On family switch, discard the current view context and open History for the newly active family after membership validation. Never show the previous family's results.
- If Telegram cannot edit the current card, send a new card and close the old one without buttons.

## 5. Coverage

- Cover paginated history, combined filters, expense details, author-only editing/deletion, conversion display and edit behavior, family switching, and recovery from unavailable expenses. Business rules and security constraints remain in the linked requirements.
