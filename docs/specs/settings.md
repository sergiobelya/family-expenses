# Family Expenses MVP — Settings UI Specification

**Scenario:** View and change user and family settings in a private Telegram chat

**UI source language:** English; localize per user (Business Requirements §12).

**Scope:** User language and active-family settings.

**Business requirements:** [business_requirements.md](../business_requirements.md) §§4, 5, 7.1–7.5, 12

**NFRs:** [nfr_requirements.md](../nfr_requirements.md) §§1–3

Setting meanings and effects are defined in the linked requirements. This document specifies their Telegram interface.

## 1. Interaction model

- Keep user settings separate from family settings. Show which family the family settings apply to.
- Any active family member may change family settings. Revalidate membership and the family context when saving (NFR §1).
- Save each setting explicitly. Do not silently change another setting when one is changed.
- Keep UI strings in localization resources under `settings.*`; English is the source language.

## 2. Flow map

| State | Action | Result |
|---|---|---|
| SE-01 Settings | Language | SE-02 Language selection |
| SE-01 Settings | Family settings | SE-03 Family settings |
| SE-02 Language selection | Select language | Save preference and return to SE-01 in the new language |
| SE-03 Family settings | Select a setting | Corresponding editor |
| Setting editor | Save | SE-03 with saved value |
| Setting editor | Cancel | Previous state without changes |
| Any settings state | Back | Previous state or main menu |

## 3. SE-01 — Settings

Show two sections:

- **User settings:** `Language`.
- **Family settings · {family_name}:** `Family name` · `Country` · `Time zone` · `Expense-entry currency` · `Reporting currency` · `Default exchange-rate provider`.

If the user has no family, show only user settings and a link to [Family Management UI FM-01](family_management.md). Otherwise show family settings for the active family.

## 4. SE-02 — Language

Show the supported languages in Business Requirements §12 and mark the current selection.

- Selecting a language saves it to the user profile and applies it immediately across all families, including the current settings screen.
- Use the fallback behavior in Business Requirements §12 when the Telegram language is unsupported and the user has not selected a language.

## 5. SE-03 — Family settings

Show each setting's current value. Use the same screen during family setup, where all required values must be set before completing family creation (Family Management UI FM-03).

- **Family name:** edit and save the name.
- **Country:** optionally select or clear a country. Use it only for provider and time-zone suggestions; changing it does not silently change other settings.
- **Time zone:** show the IANA time-zone identifier, such as `Europe/Warsaw`. During setup, preselect a suggestion based on available context and ask the user to confirm or change it; use UTC if no reasonable suggestion exists (Business Requirements §7.1). When changed later, confirm the new value before saving. After saving, show the recalculated `next_due_date` and reminder time for affected recurring templates.
- **Expense-entry currency:** select from the supported currency list (Business Requirements §6.1). This changes only the default for new expenses.
- **Reporting currency:** select from the supported currency list. Do not change the expense-entry currency automatically. Offer the current or suggested exchange-rate provider for explicit selection as described below. Explain that saving does not rewrite historical conversions.
- **Default exchange-rate provider:** show the current provider and a suggestion based on country or reporting currency when available. Allow selection from supported providers. Changing it affects future automatic retrieval; it does not rewrite historical conversions.

Changing reporting currency and changing the default provider are separate saved choices. Selecting a reporting currency may open the provider selector, but keep the current provider selected unless the user explicitly chooses another. Do not provide a manual-rate setting here (Business Requirements §7.2).

**Controls:** setting-specific value choices · `✅ Save` · `✖️ Cancel`.

After saving, show the updated value. If a concurrent family switch or membership change makes the edit stale, do not save to the previous family; explain the issue and return to the active family's Settings.

## 6. Coverage

- Cover user-wide language changes, family-scoped settings authorization, independent currency/provider changes, time-zone confirmation and recurring-date recalculation, and stale family context.
