# Family Expenses MVP — Main Menu UI Specification

**Scenario:** Navigate the bot and select the active family in a private Telegram chat

**UI source language:** English; localize per user (Business Requirements §12).

**Scope:** Main reply keyboard, active-family indicator, and navigation to MVP sections.

**Business requirements:** [business_requirements.md](../business_requirements.md) §§4–5, 12

**NFRs:** [nfr_requirements.md](../nfr_requirements.md) §1

## 1. Interaction model

- Use a Telegram reply keyboard for the main menu. Show the active family in the menu message and in family-scoped sections.
- Revalidate active-family membership on every section entry and action. Family selection and switching follow [Family Management UI](family_management.md).
- The expense button label and handler follow Business Requirements §5. A reply keyboard is sent with a bot message and cannot be changed in place; after the expense label or active family changes, send a message with the replacement keyboard. Handle an old label safely by rechecking current state in the handler.
- Keep UI strings in localization resources under `main_menu.*`; English is the source language.

## 2. Flow map

| State | Action | Result |
|---|---|---|
| MN-01 Main menu | Add expense / Continue creating expense | [Add expense UI](add_expense.md) AE-01 |
| MN-01 Main menu | Reports | [Reports UI](expense_reports.md) RP-01 |
| MN-01 Main menu | History / Search | [Expense History UI](expense_history.md) HS-01 |
| MN-01 Main menu | Stores | [Stores UI](stores.md) ST-01 |
| MN-01 Main menu | Categories | [Categories UI](categories.md) CT-01 |
| MN-01 Main menu | Members | [Family Management UI](family_management.md) FM-04 |
| MN-01 Main menu | Recurring expenses | [Recurring Expenses UI](recurring_expenses.md) RE-01 |
| MN-01 Main menu | Settings | [Settings UI](settings.md) SE-01 |
| MN-01 Main menu | Switch family | [Family Management UI](family_management.md) FM-02 |
| First interaction without a family | Start | [Family Management UI](family_management.md) FM-01 |
| Any section | Main menu | MN-01 for the active family |

## 3. MN-01 — Main menu

Show `👪 {family_name}` and the reply-keyboard actions:

- `Add expense` or `Continue creating expense`, according to Business Requirements §5.
- `Reports`
- `History / Search`
- `Stores`
- `Categories`
- `Members`
- `Recurring expenses`
- `Settings`
- `Switch family` when the user belongs to more than one family.

Family creation, selection, and switching follow [Family Management UI](family_management.md). If the user has no family membership, open FM-01 instead of showing family-scoped actions. If the user follows an invitation deep link, open its join flow before the menu.

On every return, show the currently active family. A family switch opens that family's menu and refreshes the expense action label. Do not carry a callback or family-scoped screen from the previous family into the new one.

## 4. Coverage

- Cover menu routes, the draft-dependent expense label, active-family display and switching, no-family onboarding, and return from each section.
