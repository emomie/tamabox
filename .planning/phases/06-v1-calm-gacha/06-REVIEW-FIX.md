---
phase: 06-v1-calm-gacha
fixed_at: 2026-05-13T00:00:00Z
review_path: .planning/phases/06-v1-calm-gacha/06-REVIEW.md
iteration: 1
findings_in_scope: 8
fixed: 5
deferred: 3
status: partial
---

# Phase 6: Code Review Fix Report

**Fixed at:** 2026-05-13
**Source review:** `.planning/phases/06-v1-calm-gacha/06-REVIEW.md`
**Iteration:** 1

**Summary:**
- Findings in scope (Critical + High + Medium + Low + Info): 8
- Fixed: 5 (H-01, M-01, M-02, L-01, IN-02)
- Deferred: 3 (L-02, IN-01, IN-03)
- Test result after all fixes: **195 tests, 548 assertions, 0 failures, 6 incomplete (pre-existing)**

---

## Fixed Issues

### H-01: `onerror=` JS-in-HTML-attribute on block list avatar — dropped

**Files modified:** `templates/element/block_list.php`
**Commit:** `a0fef7f`
**Applied fix:** Removed the inline `onerror="this.outerHTML=..."` attribute from the `.tb-block-row__avatar` `<img>`. The attribute interpolated `$initial` (a user-derived Bluesky handle initial) into a JavaScript string literal living inside an HTML attribute, mixing two escaping contexts (`h()` is HTML-safe, not JS-safe). The PHP-side branch at `$avatar === null` (line 50) already covers the missing-avatar case; if `avatar_url_cached` 404s at runtime, the `--tb-paper-deep` background on `.tb-block-row__avatar` gives a non-empty visual fallback so degradation is acceptable. Did not implement the optional `dataset` + script-block runtime fallback — the simpler "drop onerror entirely" path satisfies the security concern and leaves a graceful visual.

---

### M-01: 30px Home title — documented as locked exception

**Files modified:** `.planning/phases/06-v1-calm-gacha/06-CONTEXT.md`
**Commit:** `1e50d9b`
**Applied fix:** Added a "Locked Decision — Home display title typography exception (Phase 6 追加)" subsection under the existing "Locked Decisions (Phase 5 から継承)" block. Captured: scope (`.tb-home__title` only), value (`font-size: 30px; font-weight: 700;`), justification (hi-fi `Home.jsx` marketing display heading; 22px is insufficient), and scope-of-override (this one selector — any further use requires a new locked decision entry). Dimension 4 (`gsd-ui-checker`) will now treat this as an explicit exception rather than an ad-hoc deviation flagged only in VERIFICATION.md.

---

### M-02: Half-pixel font-sizes rounded to locked typography scale

**Files modified:** `webroot/css/tamabox.css`
**Commit:** `539036b`
**Applied fix:** All 8 half-pixel font-sizes inherited verbatim from handoff_tamabox hi-fi were outside the Phase 5 locked set (22/18/16/15/14/12/11/10). Rounded each to the closest locked value, biased to keep "body 14 / mono 12 / meta 11 / label 10" distinguishable:

| Selector | Line | Before | After |
|---|---|---|---|
| `.tb-home__lead` | 1202 | 13.5px | 14px |
| `.tb-section-label` | 1456 | 10.5px | 10px |
| `.tb-radio-tile__sub` | 1564 | 11.5px | 12px |
| `.tb-slider__scale` | 1659 | 10.5px | 10px |
| `.tb-settings__hint` | 1693 | 11.5px | 12px |
| `.tb-pill-btn` | 1874 | 11.5px | 12px |
| `.tb-send__welcome-body` | 1965 | 13.5px | 14px |
| `.tb-send__consent-body` | 2023 | 12.5px | 12px |

Visual delta is sub-pixel. Resolves the Dimension 4 strictness gap so `gsd-ui-checker` will no longer flag these as ad-hoc additions. `composer test` still green (195/0).

---

### L-01: Pre-escape removed from send.php title assign

**Files modified:** `templates/Messages/send.php`
**Commit:** `6ac77ee`
**Applied fix:** Changed `$this->assign('title', h($displayName) . ' の受信箱')` to `$this->assign('title', $displayName . ' の受信箱')`. The layout (`templates/layout/default.php:13`) renders title via `h($this->fetch('title'))`, so pre-escaping double-encodes characters like `&` in display names. Pre-existing pattern from before Phase 6 but fixed opportunistically because send.php is the only template in the Phase 6 changed set with a dynamic title. Added a one-line comment referencing L-01 for future maintainers.

---

### IN-02: Redundant `h()` on URL components removed (4 sites)

**Files modified:** `templates/Messages/send.php`, `templates/Messages/send_done.php`, `templates/Reports/create.php`, `templates/element/block_list.php`
**Commit:** `008e2ab`
**Applied fix:** CakePHP `Form->create()` / `Html->link()` URL output is already HTML-encoded by the helper, so pre-wrapping URL fragments with `h()` is redundant and would risk double-encoding `&` in future query-string-bearing URLs. Four sites cleaned:

- `send.php:45` — `'/' . h($slug)` → `'/' . $slug`
- `send_done.php:11` — `$slug = h((string)$inbox->slug)` → `$slug = (string)$inbox->slug`
- `Reports/create.php:35` — `'/report/' . h((string)$message->id)` → `'/report/' . (string)$message->id`
- `block_list.php:58` — `'/dashboard/blocks/' . h((string)$block->id) . '/delete'` → `'/dashboard/blocks/' . (string)$block->id . '/delete'`

No real-world bug existed today (slug regex / UUID constraints prevent encoding-relevant characters), but the latent defense-in-depth case is now closed. `composer test` still green (195/0).

---

## Deferred Issues

### L-02: `.tb-radio-tile__mark > span` empty `<span>` → `::before` pseudo-element

**File:** `templates/Reports/create.php:53`
**Reason for deferral:** REVIEW.md itself flags this as "Not worth a re-commit on its own" and notes "no bug today — mild code smell only". The fix touches both the template and CSS (`tamabox.css` `.tb-radio-tile__mark::before`), changes the radio tile checkmark rendering path, and would require visual confirmation across all 4 report categories. Schedule this for a future cleanup phase (Phase 7 or 8) when the surrounding markup is being revisited for other reasons.

---

### IN-01: Variable shadowing — `$identity` reused in `block_list.php` loop

**File:** `templates/element/block_list.php:37`
**Reason for deferral:** Readability-only finding with zero functional impact (CakePHP element scope is isolated). Renaming `$identity` → `$blockedIdentity` is mechanical but would muddy the H-01 commit's intent (security fix) and touches the same lines the H-01 commit already modified. Better deferred to a dedicated readability pass to keep blame-history-by-intent clean.

---

### IN-03: `report-form` / `block-list` legacy class contract — markup-test coupling

**Files:** `templates/Reports/create.php:37`, `templates/element/block_list.php:18`, `templates/element/block_list.php:43`
**Reason for deferral:** REVIEW.md explicitly tags this as "Fix (deferred)" — the cleanup requires tightening test assertions from substring match to exact match on the new `.tb-*` class names, which lives outside Phase 6 scope (Phase 6 is UI-only, no controller / model / test changes per D-14). Schedule for Phase 7 or 8 when the test files are being touched for other reasons.

---

## Test Results

After all fixes applied:

```
composer test: 195 tests, 548 assertions, 0 failures, 6 incomplete (pre-existing)
```

All tests pass. The 6 incomplete tests are pre-existing and unrelated to Phase 6 changes.

---

## Commit Trail

| # | Hash | Finding | Subject |
|---|---|---|---|
| 1 | `a0fef7f` | H-01 | `fix(06): H-01 drop onerror= JS-in-HTML-attribute on block list avatar` |
| 2 | `1e50d9b` | M-01 | `docs(06): document 30px Home display title typography exception` |
| 3 | `539036b` | M-02 | `fix(06): round half-pixel font-sizes to locked typography scale` |
| 4 | `6ac77ee` | L-01 | `fix(06): L-01 stop pre-escaping display name in send.php title assign` |
| 5 | `008e2ab` | IN-02 | `fix(06): IN-02 drop redundant h() on URL components in form/link helpers` |

---

_Fixed: 2026-05-13_
_Fixer: Claude (gsd-code-fixer)_
_Iteration: 1_
