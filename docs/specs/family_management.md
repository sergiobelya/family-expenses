# Family Expenses MVP — Family Management UI Specification

**Scenario:** Create and switch families, view members, and invite people to join

**UI source language:** English; localize per user (Business Requirements §12).

**Scope:** First-run family setup, family selection, member list, invitations, and joining a family.

**Business requirements:** [business_requirements.md](../business_requirements.md) §§4–5, 12

**NFRs:** [nfr_requirements.md](../nfr_requirements.md) §§1–3

Use [Settings UI](settings.md) for family setup values and [Main Menu UI](main_menu.md) for navigation. This document specifies family and invitation interactions.

## 1. Interaction model

- A user may belong to multiple families. Show and switch only families where the user is an active member; keep each action scoped to its selected family (Business Requirements §4; NFR §1).
- All family members may view the member list. With no role-based restrictions in MVP, any active member may create or revoke invitations.
- Keep UI strings in localization resources under `family.*`; English is the source language. Escape family and member names in Telegram HTML.
- Do not offer leave-family or remove-member actions in MVP (NFR §2).

## 2. Flow map

| State | Action | Result |
|---|---|---|
| FM-01 No family | Create family | FM-03 Family setup |
| FM-01 No family | Open invitation link | FM-06 Join confirmation |
| FM-02 Family selector | Select family | Activate family and open Main Menu MN-01 |
| MN-01 Main menu | Members | FM-04 Members |
| MN-01 Main menu | Switch family | FM-02 Family selector |
| FM-04 Members | Create invitation | FM-05 Invitation setup |
| FM-04 Members | View invitations | FM-07 Invitation list |
| FM-05 Invitation setup | Create | FM-08 Invitation details |
| FM-07 Invitation list | Select invitation | FM-08 Invitation details |
| FM-08 Invitation details | Revoke | Confirm, then FM-07 |
| FM-06 Join confirmation | Join | Add membership, activate family, and open MN-01 |
| Any state | Back | Previous logical state; from a flow entry point, return to the main menu or the no-family screen |

## 3. FM-01 — No family

On first interaction, apply the language selection behavior in Business Requirements §12. If the user has no family membership, offer:

**Controls:** `➕ Create family`.

- `Create family` opens FM-03. After required family setup is saved, activate the new family and open the main menu.
- Explain that the user can join by opening an invitation link from a family member. Opening a valid link starts FM-06; do not add membership before explicit confirmation.
- If the user already belongs to one or more families, open the active family's main menu unless an invitation link was opened.

## 4. FM-02 — Family selector

List the user's active families in an inline keyboard and mark the current family. Paginate if needed. If there is only one family, do not show a switch action.

**Controls:** family rows · `➕ Create family` · `⬅️ Main menu`.

- Selecting a family changes only the active family and opens its main menu. Do not move or copy data between families.
- Revalidate membership when selecting a family. If it is no longer available, remove it from the selector and show an explanation.

## 5. FM-03 — Create family

Ask for the family name, then collect required family settings using [Settings UI SE-03](settings.md). Suggest a time zone using available context and ask the user to confirm or change it. Preselect the reporting currency as the expense-entry currency; allow the user to choose them independently. Suggest an exchange-rate provider based on country or reporting currency when possible.

**Controls:** `✅ Create family` · `✖️ Cancel`.

- Require a non-empty family name and all required currency, provider, and time-zone settings before completing creation.
- On success, add the creator as a family member, make the family active, and open MN-01.
- Cancel returns to FM-01 or FM-02, depending on where creation started.

## 6. FM-04 — Members

Show `👪 {family_name} · Members` and the active family members. Display available Telegram names; do not use usernames as identity or membership proof.

**Controls:** `➕ Invite` · `🔗 Invitations` · `⬅️ Main menu`.

Do not show actions to remove members or leave the family.

## 7. FM-05 — Create invitation

Create an invitation link for the active family. Expiration is 48 hours. Default the maximum successful uses to one; allow the member creating the invitation to set another positive limit or choose no limit (Business Requirements §4.3).

- Offer an optional Telegram username hint. Do not search for or message that username, or treat it as identity proof. The invitation link is what grants the ability to request joining.
- If the inviter sends a contact card through the Telegram client, treat it only as a hint. Telegram's contact-request button shares the tapping user's own contact; it is not a recipient picker. Do not promise that the bot can contact the person. Always provide a shareable invitation link.
- Check that the user remains an active member of this family when creating the invitation.

**Controls:** `✅ Create invitation` · `⬅️ Back` · `✖️ Cancel`.

## 8. FM-07 — Invitation list and FM-08 — Invitation details

List invitations for the active family with their status: active, expired, fully used, or revoked. Show the expiration and maximum-use limit. Derive use information from `InvitationUse`; do not store a redundant use count (Business Requirements §4.3).

Invitation details show its status. Show the link and Telegram share/copy actions only while the invitation is active; offer `🗑 Revoke` in that state.

- Confirm revocation. Revalidate membership and invitation family before saving. Revocation prevents future joins and does not remove existing members.
- Expired, fully used, and revoked invitations cannot be reused.

## 9. FM-06 — Join confirmation

When a user opens an invitation deep link, show the target family name and ask them to join explicitly.

**Controls:** `✅ Join family` · `✖️ Cancel`.

- Do not add the user before they confirm. Enforce expiration, revocation, and use limits atomically with the successful join (NFR §3).
- On success, activate the joined family and open its main menu.
- If the user is already a member, explain this and offer to switch to that family without consuming an invitation use.
- If the link is invalid, expired, revoked, or exhausted, do not change membership; explain that the user needs a new invitation.

## 10. Coverage

- Cover no-family onboarding, family creation and switching, family-scoped member and invitation access, explicit joining, invitation expiry/revocation/use limits, and unavailable invitations.
