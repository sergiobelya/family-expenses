# Family Expenses MVP — Recurring Expenses UI Specification

**Scenario:** Create recurring expense templates and review due occurrences in a private Telegram chat

**UI source language:** English; localize per user (Business Requirements §12).

**Scope:** Manage templates and confirm, edit, or skip pending occurrences.

**Business requirements:** [business_requirements.md](../business_requirements.md) §§4, 5, 6.1–6.7, 7.1–7.5, 11, 12

**NFRs:** [nfr_requirements.md](../nfr_requirements.md) §§1–3

Use the standard expense validation and confirmation behavior in [Add expense UI](add_expense.md). This document specifies recurring-template and occurrence interactions.

## 1. Interaction model

- Show the active family and scope templates and occurrences to it. Verify membership and template creator authorization on every action (NFR §1; Business Requirements §11).
- Use one editable card for the template list, template form, pending occurrence list, or current occurrence. Keep text prompts in chat and answer every callback query.
- Keep UI strings in localization resources under `recurring.*`; English is the source language. Escape user-provided names in Telegram HTML.
- Process due occurrences at their scheduled date and time, or at the nearest opportunity afterward. After saving all due occurrences, send each affected creator one Telegram message per family with that family's total unresolved pending count and an action to review its list. Pending occurrences never block other bot actions; also show a non-blocking in-bot reminder on the creator's next interaction.
- If the selected store or category does not exist, open its separate Stores or Categories section and preserve the template form. On return, revalidate the template context and select the new entity.

## 2. Flow map

| State | Action | Result |
|---|---|---|
| RE-01 Recurring home | Create template | RE-03 Template form |
| RE-01 Recurring home | Open templates | RE-02 Template list |
| RE-01 Recurring home | Review pending | RE-04 Pending list |
| RE-01 Recurring home | Review missed | RE-08 Missed scheduled dates |
| RE-02 Template list | Select template | RE-05 Template details |
| RE-05 Template details | Edit | RE-03 Template form |
| RE-05 Template details | Pause / resume / cancel | Confirm action, then RE-05 |
| RE-03 Template form (new) | Save | RE-02 Template list |
| RE-03 Template form (edit) | Save | RE-05 Template details |
| RE-03 Template form | Cancel | Return to previous state |
| RE-04 Pending list | Select occurrence | RE-06 Occurrence review |
| Recovery message | Review missed | RE-08 Missed scheduled dates |
| RE-04 Pending list | Skip all listed | Confirm, then RE-04 |
| RE-06 Occurrence review | Confirm / edit | RE-07 Expense review |
| RE-06 Occurrence review | Skip | Confirm skip, then RE-04 |
| RE-07 Expense review | Confirm and save | RE-04 Pending list |
| RE-07 Expense review | Back to occurrence | RE-06 with proposed edits preserved |
| Any state | Back | Previous state or main menu |

## 3. UI states

### RE-01 — Recurring home

Show `🔁 Recurring expenses · 👪 {family_name}`, the current user's pending occurrence count, and a non-blocking reminder with a compact pending list. Keep the other controls usable while the reminder is shown.

**Controls:** `📋 Templates` · `⏳ Pending ({count})` when any are pending · `🕓 Missed ({count})` when restorable missed dates exist · `➕ Create template` · `⬅️ Main menu`.

- The in-bot reminder offers `Review pending` and `Later`. `Review pending` opens the full paginated list. `Later` leaves occurrences pending; show them again on the creator's next interaction. The scheduled Telegram message opens the pending list. A recovery message's `Review missed` button opens the missed-schedule list.
- Do not send an occurrence to the expense save flow automatically.

### RE-02 — Template list

Show family templates with name when set, store, amount/currency, schedule, next due date, and state. Without a name, identify a template by its store, amount/currency, and schedule. Templates with no future due date because their end date has passed go at the bottom and show the end date instead of a `Finished` status; they remain editable by their creator. All family members can view templates; only the creator can edit, pause, or cancel.

**Controls:** template rows · `➕ Create template` · `⏳ Pending ({count})` when any are pending · `⬅️ Main menu`.

### RE-03 — Create or edit template

Show a form for optional name, store, amount/currency, category allocation(s), schedule, start date, reminder time, and optional end date. Default reminder time to 09:00 in the family's time zone. Show the time zone in the hint (for example, `Europe/Warsaw`) and format the time according to the user's locale when supported; otherwise use unambiguous 24-hour `HH:mm`. The Telegram Bot API has no native timepicker, so accept a localized time entry and validate it. Reuse the store, amount, currency, and category controls and validation from Add expense UI; template amounts and allocations use the template currency. Do not request a purchase date or exchange rate here.

**Schedule control:** `Repeat every [N] [days/weeks/months]`. Shortcuts: `Daily` · `Weekly` · `Monthly`. Limit N to 730 days, 104 weeks, or 24 calendar months, as applicable (Business Requirements §11).

- For week intervals, select one or more weekdays (Monday–Sunday). For month intervals, select one or more days (1–31). Preselect the weekday or month day matching the start date; allow the user to change it.
- Day intervals count from the start date. Week intervals anchor to the week containing the start date; month intervals anchor to its calendar month. The first scheduled occurrence must not precede the start date.
- For monthly schedules, apply Business Requirements §11 for days 29–31. If multiple selected days resolve to the same last day of a short month, create one occurrence for that date.
- Show a readable schedule summary and preview the next three dates before saving.
- Require a valid start date and, if present, an inclusive end date not earlier than the start date. Use the unambiguous date formats from [Add expense UI](add_expense.md#63-purchase-date), but allow future template schedule dates. Use the family's time zone for schedule dates.
- On edit, keep the existing template context and show the saved values. Recalculate `next_due_date` per Business Requirements §11, skipping schedule dates that already have an occurrence, and show the updated date and reminder time; existing pending occurrences remain unchanged.
- **Controls:** `✅ Save template` · `✖️ Cancel`. Saving a new template returns to RE-02; saving an edit returns to RE-05. Cancel returns to the state from which the form was opened. If the form has unsaved changes, confirm before discarding them.

### RE-05 — Template details

Show the template fields, status, and next due date.

**Creator controls:** Active: `✏️ Edit` · `⏸ Pause` · `⏹ Cancel template`. Paused: `✏️ Edit` · `▶️ Resume` · `⏹ Cancel template`. Cancelled: no mutation controls. Other users get no mutation controls.

- Confirm pause and cancellation before applying them. Explain that due occurrences not yet created will be skipped and recorded with the relevant reason, including technical-delay misses not yet restored. Cancellation is terminal; do not offer Edit or Resume.
- Paused or cancelled templates generate no pending occurrences for elapsed schedule dates. On resume, skip and record elapsed dates, then recalculate `next_due_date` to the nearest future scheduled date. Existing pending occurrences remain unchanged and actionable by their creator.

### RE-04 — Pending occurrences

List the current user's pending occurrences, oldest scheduled date first. Each row shows the template's store, amount/currency, and scheduled date. Paginate when the list does not fit in one Telegram message.

**Controls:** occurrence rows · `⏭ Skip all {count} listed` when the list is non-empty · `⬅️ Recurring home`.

- Skip all opens a confirmation naming the number of occurrences in the current pending-list snapshot, including rows on other pages. Confirming skips only that snapshot; occurrences generated later are unaffected.
- Ignored or deferred occurrences remain pending and appear again on the next reminder.

### RE-06 — Occurrence review

Show the proposed expense fields and scheduled date. The expense date defaults to the scheduled date.

**Controls:** `✅ Confirm` · `✏️ Edit` · `⏭ Skip` · `⬅️ Pending occurrences`.

- `Confirm` opens RE-07 using the proposed values.
- `Edit` opens RE-07 with the proposed values editable. Changes apply only to this occurrence, not its template.
- `Skip` requires confirmation. On confirmation, mark this occurrence skipped and return to the pending list.

### RE-07 — Review and create expense

Reuse Add expense input and editing controls AE-03–AE-12 and review/save controls AE-10–AE-14, keeping edits scoped to the occurrence. The final purchase date defaults to the scheduled date, may be changed, and has a one-tap `Today` action (Add expense UI AE-11). Select the exchange rate for the final purchase date. Use `⬅️ Back to occurrence` instead of Add expense's discard action; return with proposed edits preserved.

- Confirming creates an ordinary family expense authored by the template creator and marks the occurrence created (Business Requirements §11; NFR §3).
- If no rate is available, keep the standard choices to enter a manual rate or save without conversion.
- On success, return to the pending list with the occurrence removed. Saving must be idempotent; do not create duplicate expenses or occurrences. Reconcile an unknown save outcome using Add expense AE-15 before allowing another attempt.

### RE-08 — Missed scheduled dates

List this family's restorable scheduled dates missed during a technical outage, oldest first. Show each date, template store, and amount/currency so the creator can compare it with expenses they may have entered manually.

**Controls:** missed-date rows · `➕ Create pending` per row · `⬅️ Recurring home`.

- `Create pending` restores the selected missed event to `pending_confirmation` using the template's current values and the original scheduled date. It does not change `next_due_date`. The creator may later edit, confirm, or skip it through the ordinary pending-occurrence flow.
- Do not offer restoration for events skipped because the template was paused or cancelled. If a template is paused or cancelled, its unrecovered technical-delay misses are removed from this list and permanently un-restorable.

## 4. Coverage

- Cover template creation, schedule/time-zone edits and next-date recalculation, on-time processing, outage logging and opt-in restoration without moving `next_due_date`, grouped notifications with total pending counts and retry behavior, pause/resume/cancel skip records, monthly days 29–31, individual and bulk skip, occurrence edits, final-date rate selection, confirmation, and idempotent saving.
