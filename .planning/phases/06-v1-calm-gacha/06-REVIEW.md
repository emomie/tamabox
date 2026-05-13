---
phase: 06-v1-calm-gacha
reviewed: 2026-05-13T00:00:00Z
depth: standard
files_reviewed: 9
files_reviewed_list:
  - templates/Account/delete.php
  - templates/Messages/send.php
  - templates/Messages/send_done.php
  - templates/Pages/home.php
  - templates/Reports/create.php
  - templates/element/avatar_handle_chip.php
  - templates/element/block_list.php
  - templates/element/inbox_settings_form.php
  - webroot/css/tamabox.css
findings:
  critical: 0
  high: 1
  medium: 2
  low: 2
  info: 3
  total: 8
status: findings
---

# Phase 6: Code Review Report

**Reviewed:** 2026-05-13
**Depth:** standard
**Files Reviewed:** 9 (8 templates + 1 CSS, ~900 new CSS lines §G.1–§G.10)
**Status:** issues_found

---

## Summary

Phase 6 ports 8 v1 PHP templates to the Calm Gacha design system established in Phase 5. The implementation is solid: backend (`src/`, `config/Migrations/`) is untouched per CONTEXT.md D-14; CakePHP form/link helpers are preserved on every form so CSRF protection and validation survive; `.tb-*` markup replaces all legacy `.button` / `.primary-button` etc. across all 8 screens; XSS escaping via `h()` is consistent on every user-derived value; the ~900-line CSS extension is structurally clean (brace-balanced, no `*/` comment traps — the Phase 5 regression class is not repeated); all `.tb-icon-btn` elements carry `aria-label` per the UI-SPEC accessibility requirement.

One **HIGH** finding: `templates/element/block_list.php:49` introduces an `onerror=` JS-in-HTML-attribute pattern that embeds two user-derived strings (`$initial` and an outer-XSS-relevant inline single-quoted span). `h()` does not escape JavaScript context, and the construct concatenates a JS string literal with HTML-decoded attribute content that includes an attacker-influenceable Bluesky handle initial. Realistic exploitability is constrained by Bluesky's handle character set, but the pattern is fragile and inconsistent with the rest of the codebase, which uses a clean PHP-side fallback `<span>` when the avatar is null.

Two **MEDIUM** findings: (a) the home page hero title uses `30px` which is outside Phase 5's locked typography scale and is correctly flagged in VERIFICATION.md §Deviations point 1, but is not added to a locked-decision entry — leaving a precedent for future ad-hoc additions; (b) the home page lead uses `13.5px` which is **not** in the locked set (22 / 18 / 16 / 15 / 14 / 12 / 11 / 10) and is **not** acknowledged in VERIFICATION.md.

The remaining findings are minor style / consistency observations.

---

## High

### H-01: `onerror=` in `block_list.php` builds JS from user-derived strings via HTML-attribute concatenation

**File:** `templates/element/block_list.php:44-49`
**Issue:** The avatar `<img>` carries an inline `onerror` handler that reconstructs the fallback `<span>` via `this.outerHTML = "..."` when image loading fails:

```php
<img class="tb-block-row__avatar"
     src="<?= h($avatar) ?>"
     alt=""
     width="40" height="40"
     onerror="this.outerHTML=&quot;<span class='tb-block-row__avatar tb-block-row__avatar--fallback'><?= h($initial) ?></span>&quot;">
```

Three problems compound:

1. **Wrong-context escaping.** `h()` is `htmlspecialchars($s, ENT_QUOTES|ENT_HTML5)` — it escapes HTML metacharacters, not JavaScript ones. The value lands inside a JS string literal that lives inside an HTML attribute. Even though `h()` happens to escape `"` and `'` (which would normally close JS strings), the JS string here is delimited by `&quot;` (the literal characters `&`, `q`, `u`, `o`, `t`, `;`, which the browser decodes to `"` before JS parses), and the inner `<span class='...'>` uses single quotes that `h()` escapes to `&#039;` — these decode back to `'`, terminating the inline JS single-quoted attribute usage if anything similar appears. The escaping coincidence-works for one-char ASCII initials but is fragile: if `$initial` were ever multi-character or contained a `\` or backtick, JS injection becomes plausible.

2. **The `src` value triggers `onerror` from attacker-controlled URL.** `avatar_url_cached` is validated only as `maxLength(2048)` + `scalar` (`src/Model/Table/UserIdentitiesTable.php:99-101`) — no URL format check. A blocked user controlling their own Bluesky profile can pick any string for the avatar URL; pointing it at a non-existent path deliberately triggers the `onerror` handler when their entry is rendered in the box owner's block list. The handler then runs the constructed `this.outerHTML = "..."` with `$initial` interpolated.

3. **`$initial` is `mb_strtoupper(mb_substr($handle, 0, 1))` of the Bluesky handle.** Handles are nominally constrained but cached from external profiles; if upstream validation drifted or a homoglyph passed `scalar` validation, the value reaches the `onerror` attribute. Defense-in-depth says: do not concatenate user-derived strings into JavaScript via HTML attributes.

**Impact:** Stored-XSS-shaped construct on the dashboard block list — an authenticated victim viewing their own block list could execute JS originating from a previously-blocked user's profile data. Phase 6 did not introduce this pattern from scratch (avatar `onerror` likely came from earlier work) but Phase 6's rewrite of `block_list.php` kept and re-emitted it in the new markup, so it is in scope for this review. Realistic exploitability today is low because Bluesky handles use a constrained charset, but the construct should be eliminated.

**Fix:** Delete the `onerror` attribute and let the existing PHP-side fallback handle missing avatars cleanly. The template already emits a `<span class="tb-block-row__avatar--fallback">` branch when `$avatar === null` (line 51), and the CSS at `webroot/css/tamabox.css:1833-1849` already styles broken `<img>` (the `background: var(--tb-paper-deep)` on `.tb-block-row__avatar` gives a non-empty visual for broken loads). If a runtime-fallback for HTTP 404 is required, do it via a tiny script block at the bottom of the element with an event listener — never inline in an attribute:

```php
<?php /* templates/element/block_list.php — replace lines 44-52 with: */ ?>
<?php if ($avatar !== null): ?>
    <img class="tb-block-row__avatar"
         src="<?= h($avatar) ?>"
         alt=""
         data-fallback-initial="<?= h($initial) ?>"
         width="40" height="40">
<?php else: ?>
    <span class="tb-block-row__avatar tb-block-row__avatar--fallback"><?= h($initial) ?></span>
<?php endif; ?>
```

If runtime fallback is wanted, add (once, at the bottom of the element):

```html
<script>
document.querySelectorAll('.tb-block-row__avatar[data-fallback-initial]').forEach(function (img) {
    img.addEventListener('error', function () {
        var span = document.createElement('span');
        span.className = 'tb-block-row__avatar tb-block-row__avatar--fallback';
        span.textContent = img.dataset.fallbackInitial || '?';
        img.replaceWith(span);
    });
});
</script>
```

`textContent` is JS-safe; `dataset` reads the HTML-decoded `data-fallback-initial` attribute. No string concatenation into JS.

---

## Medium

### M-01: `30px` Home title is outside the locked Typography Scale and is not codified as a locked exception

**File:** `webroot/css/tamabox.css:1194` (`.tb-home__title { font-size: 30px; ... }`)
**Issue:** The Phase 5 locked Typography Scale (`05-CONTEXT.md` / `05-UI-SPEC.md`) explicitly enumerates 8 approved sizes: 22 / 18 / 16 / 15 / 14 / 12 / 11 / 10 px. The override policy is: "新しい size/weight を追加する場合のみ、再度判断 (ad-hoc 追加禁止)" — additions require a fresh locked-decision entry in the relevant phase CONTEXT.md.

`06-VERIFICATION.md` Deviations §1 acknowledges this as a "one-off display heading exception consistent with hi-fi visual identity" but **no locked-decision entry was added to `06-CONTEXT.md`** — the deviation lives only in the verification narrative. This sets a precedent that ad-hoc sizes can survive review without being formally adopted, weakening the Dimension 4 contract for Phases 7-8.

**Impact:** Dimension 4 (`gsd-ui-checker`) will mark this as a violation unless the verifier references VERIFICATION.md (which the checker does not consume). Phase 7-8 templates will reasonably copy `30px` from Home, propagating an ungoverned size.

**Fix (choose one):**

A. **Codify** — add a locked-decision entry to `06-CONTEXT.md`:
```markdown
### Locked Decision — Display heading size addition (UI-01 only)
- Add `30px` to the approved set for the role "marketing display heading", scoped strictly to `.tb-home__title`.
- Reason: hi-fi `Home.jsx` visual identity; `22px` (next-largest approved) is insufficient for the Home marketing context.
- Scope: this one selector. Any further use requires re-entry.
```

B. **Align** — drop to `22px` (the largest approved display size). Hi-fi visual identity loses a bit but enters the locked set.

Option A is preferred and matches the project's pattern of explicit lock entries (cf. Phase 5 spacing exceptions for `6px` / `14px` / `18px`).

---

### M-02: `13.5px` (`.tb-home__lead`) is outside the locked Typography Scale and is NOT acknowledged anywhere

**File:** `webroot/css/tamabox.css:1202` (`.tb-home__lead { font-size: 13.5px; ... }`); same pattern at `webroot/css/tamabox.css:1965` (`.tb-send__welcome-body { font-size: 13.5px; ... }`)
**Issue:** `13.5px` is not a member of the locked set (22 / 18 / 16 / 15 / 14 / 12 / 11 / 10). Unlike M-01, this size is **not flagged in `06-VERIFICATION.md`** — neither Deviations §1 (which only mentions 30px) nor §2 covers it. There are also other half-pixel sizes in the new section: `10.5px` (`.tb-section-label`:1456, `.tb-slider__scale`:1659), `11.5px` (`.tb-radio-tile__sub`:1564, `.tb-pill-btn`:1874, `.tb-settings__hint`:1693), `12.5px` (`.tb-send__consent-body`:2023). None are in the locked set.

The Phase 5 typography override policy explicitly prohibits ad-hoc additions. These look like inheritance from `handoff_tamabox` hi-fi but were not surfaced for governance.

**Impact:** Dimension 4 will fail strictly. Phase 7-8 templates have no clear signal whether half-pixel sizes are blanket-approved or one-off violations.

**Fix:** Either:

A. **Round to the locked set** — `13.5px` → `14px` (negligible visual diff), `10.5px` → `10px` or `11px`, `11.5px` → `11px`, `12.5px` → `12px`. Verify each replacement visually against hi-fi.

B. **Add a locked-decision entry to `06-CONTEXT.md`** acknowledging hi-fi inheritance:
```markdown
### Locked Decision — Half-pixel font sizes from hi-fi
- Approve {10.5, 11.5, 12.5, 13.5} px for component-internal use, sourced verbatim from handoff_tamabox.
- These are NOT layout sizes; they are component-internal nuances paired with letter-spacing/line-height tunings from hi-fi.
- Scope: existing Phase 6 selectors only. New use requires re-entry.
```

A is preferred for simplicity and Dimension 4 cleanliness; B is preferred for hi-fi fidelity. Either way, the choice must be in CONTEXT.md, not VERIFICATION.md.

---

## Low

### L-01: Pre-existing `$this->assign('title', h($displayName) . ...)` causes double-escaping in `<title>`

**File:** `templates/Messages/send.php:22`
**Issue:** Line 22 pre-escapes `$displayName` with `h()` and assigns it as the page title. The layout (`templates/layout/default.php:13`) then renders the title with `<?= h($this->fetch('title')) ?>` — escaping again. Result: characters like `&` in display names render in the browser tab as `&amp;amp;`, an apostrophe as `&amp;#039;`, etc.

`git show 6fa42ff:templates/Messages/send.php` shows the same `h($displayName) . ' の受信箱'` pattern in the pre-Phase-6 file, so this is **pre-existing**, not introduced in Phase 6. The Phase 6 rewrite preserved it verbatim. Flagged because Phase 6 is the only template in the changed set that uses this dynamic title pattern.

**Impact:** Cosmetic — page title in browser tab and bookmarks shows mojibake for any display name with HTML special chars. Low because real-world Bluesky display names rarely include these, but it's a visible bug for users whose name contains `&`.

**Fix:**
```php
// templates/Messages/send.php:22 — pre-escape removed; layout will h() it once.
$this->assign('title', $displayName . ' の受信箱');
```

---

### L-02: `tb-radio-tile__mark > span` empty `<span>` is decorative-only but has no `aria-hidden`

**File:** `templates/Reports/create.php:53`
**Issue:** The radio tile renders `<span class="tb-radio-tile__mark" aria-hidden="true"><span></span></span>`. The outer wrapper is correctly marked `aria-hidden="true"`, so the inner empty `<span>` is screen-reader-ignored by inheritance. This is fine functionally but the inner empty span is structural-CSS-only (it gets a background dot via `.tb-radio-tile__input:checked ~ .tb-radio-tile__mark > span`). Consider `<i aria-hidden="true">` or a CSS `::before` pseudo-element to keep the markup cleaner.

**Impact:** None functional. Mild code smell — empty `<span>` for purely visual purposes.

**Fix (optional):** Replace the inner empty `<span>` with a `::before` pseudo-element in CSS:
```css
.tb-radio-tile__mark::before {
    content: ""; width: 6px; height: 6px; border-radius: 50%; background: transparent;
}
.tb-radio-tile__input:checked ~ .tb-radio-tile__mark::before { background: #fff; }
```
and drop the inner `<span></span>` from the template. Not worth a re-commit on its own.

---

## Info

### IN-01: Variable shadowing — `$identity` reused inside `block_list.php` loop

**File:** `templates/element/block_list.php:37`
**Issue:** `avatar_handle_chip.php` (a sibling element) consumes `$identity` as the Authenticated identity passed in from the layout. `block_list.php` reuses the same name as a per-iteration local: `$identity = ($blocked !== null && isset($blocked->user_identity)) ? $blocked->user_identity : null;`. CakePHP element scope is isolated so there is no actual variable leak between the two — but the naming collision is confusing to a reader who knows the `avatar_handle_chip.php` convention.

**Impact:** Readability only.

**Fix:** Rename the loop-local to `$blockedIdentity`:
```php
$blockedIdentity = ($blocked !== null && isset($blocked->user_identity)) ? $blocked->user_identity : null;
$handle = $blockedIdentity !== null ? (string)$blockedIdentity->handle_cached : '';
$avatarRaw = $blockedIdentity !== null ? $blockedIdentity->avatar_url_cached : null;
```

---

### IN-02: Redundant `h()` on slug values used in `Form->create()` / `Html->link()` URLs

**Files:**
- `templates/Messages/send.php:45` — `'url' => '/' . h($slug)`
- `templates/Messages/send_done.php:11` — `$slug = h((string)$inbox->slug);` then `'/' . $slug` passed to `Html->link()`
- `templates/Reports/create.php:35` — `'url' => '/report/' . h((string)$message->id)`
- `templates/element/block_list.php:58` — `'url' => '/dashboard/blocks/' . h((string)$block->id) . '/delete'`

**Issue:** CakePHP's `Form->create()` and `Html->link()` HTML-encode URL output by default. Pre-encoding with `h()` is redundant. For values whose validation guarantees a safe character set (slug regex; UUID for message IDs and block IDs), `h()` is a no-op anyway, but in the general case it would risk double-encoding `&` in query strings to `&amp;amp;`. Today none of these URLs carry query strings, so the bug is latent.

**Impact:** None today (constrained input + no query strings). Stylistic / defense-in-depth: prefer passing raw values to CakePHP helpers and letting the helper escape once.

**Fix:** Remove the `h()` calls on URL components passed to `Form->create()` / `Html->link()`:
```php
// send.php:45
'url' => '/' . $slug,

// send_done.php:11
$slug = (string)$inbox->slug;

// Reports/create.php:35
'url' => '/report/' . (string)$message->id,

// block_list.php:58
'url' => '/dashboard/blocks/' . (string)$block->id . '/delete',
```

---

### IN-03: `report-form` and `block-list` legacy classes preserved on outer wrappers for test compatibility — document the contract

**Files:**
- `templates/Reports/create.php:37` — `'class' => 'report-form tb-report-form'`
- `templates/element/block_list.php:18` — `class="block-list tb-block-list"`
- `templates/element/block_list.php:43` — `class="block-list__row tb-block-row"`

**Issue:** The legacy class names are intentionally retained because `ReportsControllerTest::testCreateGetRendersForm` and `UsersControllerTest::testDashboardRendersBlockListSection` assert on them. The Phase 6 verification correctly broadened those assertions to substring matches. This is a load-bearing constraint: if a future phase drops the `block-list` / `report-form` outer-wrapper classes, the tests will silently still pass (substring) until someone notices the legacy class is gone. Consider replacing the substring assertion with an exact `block-list tb-block-list` check, or removing the legacy class and rewriting the test to assert on the `tb-*` class instead.

**Impact:** No bug today. Coupling between markup and tests is fragile; documented here so a future phase can either harden the assertion or schedule the cleanup.

**Fix (deferred):** When Phase 7-8 is willing to touch the test, update the assertion to a precise match on `tb-block-list` / `tb-report-form` and drop the legacy class from the markup.

---

## CSS Structural Sign-off

- **Brace balance** verified by AST-like scan (depth returns to 0 at EOF).
- **Comment balance** verified — no `*/` traps. The Phase 5 regression class is not repeated.
- **Selector specificity** is consistent (`.tb-screen--{X} .tb-screen__body { ... }` pattern used everywhere; no `!important` except the two clearly-intentional uses on `.tb-block-list__note-sub` overriding the sibling `.tb-block-list__note p` rule).
- **Token references** all resolve to existing `--tb-*` / `--tb-r-*` / `--tb-shadow-*` variables (no undeclared variable references introduced).
- The `:has()` selector in `.tb-radio-tile:has(.tb-radio-tile__input:checked)` (line 1547) is flagged in `06-VERIFICATION.md` Risks §1 as broad-but-not-universal support; graceful degradation via the inner radio dot is in place — acceptable.

---

## Backend Immutability Sign-off

Verified via `git diff --stat 6fa42ff..HEAD -- 'src/' 'config/Migrations/'`: zero changes. CONTEXT.md D-14 honored.

---

## CakePHP Helper Usage Sign-off

Every form on every page uses `$this->Form->create()` / `$this->Form->end()` (CSRF token injection); every link styled as a button is either a CakePHP `Html->link()` call (send_done) or a benign anchor for navigation (Reports cancel, Account delete cancel, Settings danger-row — all GET-only routes). No bare HTML `<form>` or `<a href>` to a destructive endpoint was introduced. Helper coverage is consistent with CONTEXT.md D-15.

---

## h() Escape Sign-off

Every interpolation of a user-derived value (`$handle`, `$avatar`, `$displayName`, `$slug`, `$welcomeMessage`, `$restoredBody`, `$bodyExcerpt`, `$initial`, reason `id`/`t`/`d`, consequence `t`/`s`) is wrapped in `h()` at every textual emission site. The single problematic site is `block_list.php:49` where the value is correctly `h()`'d but the surrounding context is JavaScript, not HTML — covered by H-01.

---

_Reviewed: 2026-05-13_
_Reviewer: Claude (gsd-code-reviewer)_
_Depth: standard_
