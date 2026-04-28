# Phase 4 Manual Smoke Test Checklist

**Trigger**: After LAUNCH-RUNBOOK.md Steps 1-5 complete; this is Step 6 of the runbook.
**Operator**: solo developer (the user).
**Devices needed**: 2 separate browsers / devices for accounts A and B (e.g., laptop Firefox + phone Bluesky app, OR two private windows + 2 distinct Bluesky test accounts).
**Time estimate**: 30-45 min for full walkthrough.

Fill in metadata + check each box as you go. If a step fails, capture the failure mode + screenshot link inline; afterward summarize in `.planning/phases/04-moderation-production-launch/VERIFICATION.md`.

---

**Date**: ____________
**Operator**: ____________
**Production URL tested**: https://tamabox.emomie.com
**Bluesky test account A**: ____________
**Bluesky test account B**: ____________

---

## Phase 4 new flows (D-35 walkthrough)

- [ ] **(1)** Sign up via real Bluesky account A → after OAuth callback redirects to `/dashboard` → `/<a-slug>` URL is shown in dashboard header → confirm dashboard renders empty receive list with "まだ受信したメッセージはありません" or similar empty state copy.

- [ ] **(2)** Switch to device 2, login via Bluesky account B → navigate to `/<a-slug>` → confirm send form renders → fill body + tick consent checkbox → submit. Expect redirect to send_done page.

- [ ] **(3)** Switch back to device 1 (account A) → `/dashboard` → see B's message in receive list (closed `<details>`) → click to expand → click "開封する" form → confirm SSR hit/miss reveal copy and animation as appropriate (`★ 抽選 hit` with sender card, OR `★ 抽選 miss(送信者は匿名のまま)`). The probability inbox setting governs the actual outcome.

- [ ] **(4)** **(SSR-hit only)** With the sender card visible, click `このユーザーをブロック` button → confirm Flash success message + redirect to `/dashboard` → block list section now shows B's handle. (If outcome was SSR-miss, repeat Step 2-3 with another B-side message until you get a hit, OR temporarily set inbox `ssr_probability_pct` to 100 in `/dashboard/settings` for testing.)

- [ ] **(5)** Switch to device 2 (account B) → re-navigate to `/<a-slug>` → confirm "この受信箱には送信できません" error banner is visible above the send form → form is visibly grayed out (is-disabled) → if you bypass the disabled state via DevTools and POST anyway, server-side defense rejects with Flash error redirect.

- [ ] **(6)** From device 1 (account A), in dashboard message-row footer, click `通報する` link → `/report/<message_id>` page opens → choose `嫌がらせ・誹謗中傷` radio → submit → Flash success "通報を送信しました…" + redirect to `/dashboard` → message-row footer now shows `通報済` badge instead of `通報する` link.

- [ ] **(7)** From device 1 (account A), expand a message → click `削除` button in footer → native `confirm()` dialog "このメッセージを削除しますか?(削除後は元に戻せません)" → click OK → Flash success "メッセージを削除しました" + redirect → message disappears from list.

- [ ] **(8)** From device 1 (account A), `/dashboard/settings` → scroll to danger-zone fieldset → click `退会の手続きへ` link → `/account/delete` page opens → tick "上記の内容を理解した上で、退会します" checkbox → click `退会する` button → redirect to `/` → Flash info "退会が完了しました…" → confirm session destroyed (LP shows Bluesky CTA, not dashboard) → navigate to `/<a-slug>` → confirm 404 error page (REV-01).

- [ ] **(9)** From device 2 (account B), inspect any of B's previously-sent messages to A's inbox via `/dashboard` (B has no receive entries from A; this step is the MOD-03 sentinel). To verify MOD-03 strict snapshot retention, use SSH SQL on Lolipop: `mysql tamabox -e "SELECT sender_handle_snapshot, sender_avatar_url_snapshot, sender_profile_url_snapshot FROM messages WHERE sender_user_id='<B-user-id>'\G"` — confirm the row count is unchanged AND the snapshot values are non-null and match B's data at send time.

---

## Phase 2 / Phase 3 carry-over human items

(Deferred from Phase 2 / Phase 3 verify-phase as `human_needed`; consumed at Phase 4 launch per CONTEXT D-39.)

- [ ] **(10)** **Live Bluesky AS handshake** (Phase 2 verifier item): Step (1) above implicitly exercises this — the actual signup goes through PAR + DPoP + private_key_jwt + token exchange + getProfile against `bsky.social`. If Step (1) succeeded, this item is satisfied. (If signup failed at any of those substeps, log the error_url and capture POST/redirect HAR.)

- [ ] **(11)** **Browser cookie destroy on logout** (Phase 2 verifier item): from device 1, account A logged in → `/oauth/logout` (POST via dashboard logout button or equivalent) → confirm session cookie is unset (browser DevTools > Application > Cookies; the CakePHP session cookie should be gone) → re-navigating to `/dashboard` redirects to `/` (LP). Do this BEFORE Step (8) for cleanest test, or test on a fresh sign-up after Step (8).

- [ ] **(12)** **Handle-change sync via second login** (Phase 3 verifier item): rename account A's Bluesky handle (via bsky.app handle settings) → log out of tamabox → log in again → confirm `/dashboard` header reflects the new handle AND `/<a-slug>` resolves to the new slug (auto-derived from new handle) → the OLD slug returns 301 redirect to the new slug for 1 generation (Phase 3 D-04 grace period).

---

## Failure logging

For each failed step, fill out:

```
Step #: __
Expected: __
Actual: __
Reproduction: __
Workaround / next-action: __
Screenshot: <path or imgur link>
```

Then summarize all failures in `.planning/phases/04-moderation-production-launch/VERIFICATION.md` and decide:
- **Block-launch**: rollback (LAUNCH-RUNBOOK.md Rollback procedure) + open gap-closure plan via `/gsd-plan-phase --gaps 4`.
- **Non-blocking**: log in `STATE.md` Open Todos for next milestone.

---

*Authored from .planning/phases/04-moderation-production-launch/04-CONTEXT.md D-35 specifics + Phase 2/3 verifier human_needed carry-overs (STATE.md Research Flags). Last updated: Phase 4 plan 04-03.*
