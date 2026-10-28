# Family Expenses MVP — Reports UI Specification

**Scenario:** Generate and export family expense reports in a private Telegram chat

**UI source language:** English; localize per user (Business Requirements §12).

**Scope:** Configure report filters and grouping, review totals and breakdowns, resolve currency rates, and export CSV/XLSX.

**Business requirements:** [business_requirements.md](../business_requirements.md) §§4, 5, 7.1–7.6, 8, 12

**NFRs:** [nfr_requirements.md](../nfr_requirements.md) §§1–3

Report content, currency conversion, and export rules are defined in the linked requirements. This document specifies their Telegram interface.

## 1. Interaction model

- Use one editable report card for report settings, rate choices, and the current report page. Show the active family and reporting currency.
- Scope report data to the selected family and period; verify membership when opening and acting on a report (NFR §1).
- Use inline buttons for period selection, rate choices, pagination, and export. Answer every callback query.
- Keep UI strings in localization resources under `expense.report.*`; English is the source language. Escape user-provided family, store, and category names in Telegram HTML.
- Report generation follows the read-only and rate-cache rules in Business Requirements §§7.4–7.6.

## 2. Flow map

| State | Action | Result |
|---|---|---|
| RP-01 Report settings | Generate | RP-02 Rate choices, or RP-04 Report |
| RP-02 Rate choices | Enter manual rate | RP-03 Manual rate |
| RP-03 Manual rate | Confirm rate | RP-02 Rate choices |
| RP-02 Rate choices | Generate report | RP-04 Report using selected pair choices |
| RP-02 Rate choices | Continue with automatic rates | RP-04 Report using automatic rates for all pairs |
| RP-04 Report | Change report settings | RP-01 prefilled with current settings |
| RP-04 Report | Export | RP-05 Export |
| RP-04 Report | New report | RP-01 with default settings; new session |
| Any state | Back | Previous state or main menu |

## 3. UI states

### RP-01 — Configure report

Show `📊 Reports · 👪 {family_name}` and the family's current reporting currency. The default period is the current calendar month in the family's time zone.

**Report settings:** `This month` · `Month` · `Month range` · `Exact dates` · `Time grouping` · `Group by category` · `Group by store` · `📈 Generate report` · `⬅️ Main menu`.

- `Month` opens a month/year picker. `Month range` selects inclusive start and end months. `Exact dates` selects inclusive start and end dates; typed input uses the unambiguous formats in [Add expense UI](add_expense.md#63-purchase-date). Expense purchase-date limits do not restrict report periods.
- `Time grouping` offers `Entire selected period`, `By month`, `By week`, and `By day`. Default to `By day` for periods up to one calendar month and `By month` for longer periods. Weeks run Monday through Sunday; first and last buckets may be partial at the selected-period boundaries. Aggregation follows Business Requirements §8.1.
- `Group by category` and `Group by store` are independent optional toggles, both off by default. Row aggregation follows Business Requirements §8.1.
- Reject a start date or month after its end. Do not change the family's reporting currency from this flow; its value is configured in Settings.
- Generate a report session for the selected family, period, time grouping, selected breakdowns, and reporting currency. Exclude soft-deleted expenses.
- If there are no expenses in the period, show `No expenses found for this period.` and offer to change the period.

### RP-02 — Resolve report rates

Show only currency pairs that need a report-time conversion because at least one included expense has no applicable saved conversion. Do not ask about pairs where all expenses already have an applicable conversion or use the reporting currency as their original currency.

For each pair, show `{source} → {target}`, the number of expenses requiring a rate, and pair-specific actions: `Use automatic historical rates` (selected by default) or `Enter manual rate`. A manual rate applies to all expenses in that pair without an applicable saved conversion, including those with an available historical provider rate.

**Controls:** `📊 Generate report` · `✅ Continue with automatic rates`.

- `Generate report` uses the selected rate choice for each pair.
- `Continue with automatic rates` is a one-tap action that uses automatic rates for every pair, regardless of manual choices.
- While automatic rates are being retrieved, show `Checking rates…` and keep the report controls responsive. If retrieval times out or fails, follow the unavailable-rate behavior below (NFR §3).
- Explain the manual rate's override scope before the user enters it. After confirming a rate, return to the pair list; the user can review or change each pair's choice before generating the report.
- Apply the current-rate fallback in Business Requirements §7.6 before treating an automatic rate as unavailable. If neither a suitable historical rate nor a current-rate fallback is available, offer `Change provider in Settings`, `Enter manual rate`, or `Continue with these expenses unconverted`. After the provider is changed, return to rate resolution and retry automatic lookup.
- Automatic historical-rate and current-rate fallback behavior follows Business Requirements §7.6. Label each rate basis in the report details.

### RP-03 — Enter report-only manual rate

Prompt: `💱 Send the rate: how many {target} for 1 {source}?` Show the pair and override-all scope above the prompt.

**Controls:** `✅ Use this rate` · `⬅️ Back` · `✖️ Cancel`.

- Validate the positive decimal using [Add expense manual-rate rules](add_expense.md#65-manual-exchange-rate).
- After confirmation, return to RP-02 with the pair marked `Report-only manual rate` and the entered value. The user may edit it before generating the report.
- Keep this value only in the report session. Do not save it as an expense conversion or family setting.

### RP-04 — Report

Show the selected period, reporting currency, total, and one table grouped by the selected time bucket and dimensions (Business Requirements §8.1). If no dimensions are selected with `Entire selected period`, show only the total. Exclude per-person totals.

- If all expenses are converted, label the value `Total`. If any are unconverted, label the value `Converted subtotal`, show the number of unconverted expenses, and state `Report total is incomplete.` Keep this notice visible on every table page; never present partial figures as complete totals.
- Show historical, current-rate fallback, saved, and report-only manual rate bases clearly. `Rate details` lists the applied rates with their currency pair, value, basis, date, and provider or manual source (Business Requirements §7.6). This is rate metadata, not a report grouping dimension.
- Show row amounts in the reporting currency. Order rows by time bucket, then store and category name when those dimensions are selected. Allocate converted expense totals by category as defined in Business Requirements §6.4.1. Display time buckets using the user's locale.
- Paginate the table or rate-details list when it does not fit in one Telegram message; preserve row order and repeat the column headings on each page.
- Show active manual overrides and their currency pairs in the report summary. A report-only manual rate remains active throughout the 24-hour session, including when the user changes the period, paginates, or exports.
- **Controls:** `◀️ Previous` · `Next ▶️` · `ℹ️ Rate details` · `⚙️ Change report settings` · `📤 Export` · `🆕 New report` · `⬅️ Main menu`.
- Changing the period or grouping preserves report-only manual overrides. On regeneration, resolve any newly needed currency pairs before showing the report. `New report` starts a new session with default settings.

### RP-05 — Export

**Controls:** `📄 CSV` · `📊 XLSX` · `⬅️ Back to report`.

- Export the selected period, reporting currency, total, time grouping, and the same aggregate rows and columns as the displayed report. Do not include individual expense rows; users view them in History (Business Requirements §9).
- Include applied-rate metadata in the export as in `Rate details`, including source, basis, date, and rate; include the pair and override-all scope for report-only manual rates. Disclose unconverted expenses.
- Exports use the same rate choices as the displayed report. Generating an export creates no domain records.

## 4. Report session expiry

- Keep report-only manual rates for 24 hours from the last report-session change, following Business Requirements §7.6.
- After expiry, do not imply the previous manual rate is recoverable. Ask the user to start a new report session and explain that fallback rates and totals may differ.

## 5. Coverage

- Cover each period type and time grouping, category/store grouping choices, family scoping, saved and automatic rates, current-rate fallback, report-only manual overrides, incomplete totals, pagination, report-setting changes within a session, expiry, and CSV/XLSX export.
