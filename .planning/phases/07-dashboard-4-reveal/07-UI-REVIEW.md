---
phase: 7
slug: dashboard-4-reveal
score: 27/30
verdict: PASS
created: 2026-05-13
baseline: 07-UI-SPEC.md (approved) + handoff_tamabox/screens/{Dashboard,Discover,Notifications,Settings,Reveal,RevealHit}.jsx + components.jsx (hi-fi)
screenshots: not captured (no dev server)
---

# Phase 7 — UI Review: Dashboard 4 タブ + Reveal 演出

**Audited:** 2026-05-13
**Baseline:** `07-UI-SPEC.md` + 6 hi-fi React references (`Dashboard.jsx`, `Discover.jsx`, `Notifications.jsx`, `Settings.jsx`, `Reveal.jsx`, `RevealHit.jsx`) + `components.jsx` for TbTabBar/TbAppBar
**Screenshots:** Not captured — no dev server detected. Code-only audit comparing PHP markup + Phase 7 §H section in `tamabox.css` (lines 2037–2404) against hi-fi JSX reference and Phase 5/6 locked decisions.

---

## Pillar Scores

| # | Pillar | Score | Verdict | Key Finding |
|---|--------|-------|---------|-------------|
| 1 | Hi-fi fidelity | 4/5 | PASS | All 4 tabs + Reveal cards reproduce hi-fi DOM/tone; Discover Empty subset drops featured card + lead block intentionally per D-16/D-18 |
| 2 | Token discipline | 4/5 | PASS | All semantic colors via `--tb-*`; `#fff` / `#F0DCA8` / `#FFF7E0` / `#FBEFCC` / `#E7C795` / `#B98449` / `#FBFCFD` / `rgba(217,162,60,0.22)` / `rgba(0,0,0,0.06)` literals carry over from spec or hi-fi but `#FBFCFD` (unread row tint) is new and undocumented |
| 3 | Component reuse | 5/5 | PASS | TbTabBar element consumed in 4 templates as designed; icon.php / inbox_settings_form / block_list / tb-card / tb-chip / tb-btn / tb-mono all reused; `.tb-dash-screen` is the only new layout helper and is consumed by all 4 tabs |
| 4 | Typography scale | 5/5 | PASS | All Phase 7 §H font-sizes (10/11/12/14/16/17/18/22) land on the locked 8-size scale; hi-fi half-pixels (14.5/11.5/10.5/9) rounded to scale per D-22/D-23; weights stay in {400, 500, 600, 700} |
| 5 | Spacing scale | 4/5 | FLAG | Layout spacing is multiple-of-4 dominant; 4 off-grid values (`gap: 6/10`, `padding: 14`, `gap: 18`) are either documented Phase 5 exceptions or carry-overs from Phase 6 hi-fi-pinned set; one new `margin-top: 3px` and `padding: 0 14px` (search pill) extend the Phase 6 pattern without explicit locked-decision entry |
| 6 | Accessibility hooks | 5/5 | PASS | `role="tablist"` / `aria-current="page"` / `aria-label` on TabBar active; `aria-disabled="true"` on Discover search + tag chips; `aria-hidden` on decorative spans; focus-visible on `.tb-tabbar__item`; reveal-motion.js idempotent. **One real gap noted as advisory in §3 below: no `prefers-reduced-motion: reduce` media query guarding the `.is-opening` keyframe.** |

**Overall: 27/30 — PASS**

---

## Top 3 Priority Recommendations (Advisory)

1. **`.message-row__body.is-opening` keyframe should respect `prefers-reduced-motion: reduce`** — `tamabox.css:2052-2059` defines `tb-fade-in 400ms ease` and applies it whenever JS toggles the class. There is no `@media (prefers-reduced-motion: reduce)` guard anywhere in `tamabox.css` (`grep` returns zero hits). Users with reduced-motion preferences get an unconditional 2px translateY+opacity animation every time they open a `<details>`. Fix is one block: wrap the animation rule with a media query, OR add a global `@media (prefers-reduced-motion: reduce) { .message-row__body.is-opening { animation: none; } }` rule at the end of §H.2. Phase 7 §H is the right home for it. Low effort, real WCAG 2.3.3 win.

2. **`#FBFCFD` unread row tint at `tamabox.css:2127` is a new undocumented literal** — `.tb-message-row[data-state="unread"] .tb-message-row__head { background: #FBFCFD; }`. This is a near-white tint to give unread rows a soft contrast against `.tb-card`. The value is plausibly hi-fi-derived but does not appear in the UI-SPEC §H literal-allowlist (which only documents `#FFF7E0`/`#FBEFCC`/`#F0DCA8` for the HIT gradient and `#E7C795`/`#B98449` for the sender avatar gradient). Either (a) add a `--tb-paper-hint` or `--tb-card-tint` token to `tokens.css` and reference it here, or (b) add `#FBFCFD` to the UI-SPEC literal allowlist as a Phase 7 supplement. Currently undocumented = will re-flag in future audits.

3. **Inline `style="..."` attributes in `dashboard.php` (~18 occurrences) duplicate Phase 5/6 visual primitives instead of using utility classes** — the RevealHit card body (`dashboard.php:150-208`) renders 8 inline-styled `<div>` elements with `font-size`, `font-weight`, `color`, `letter-spacing`, `margin-top`. The values themselves are on-scale and hi-fi-faithful (10/12/15/16px, weights 600/700, ink/warm-700 tokens), but each block is a candidate for a small Phase 7 §H subclass — e.g. `.tb-reveal-hit-card__label` (10/700/0.2em/warm-700/uppercase), `.tb-reveal-hit-card__title` (16/700/ink), `.tb-reveal-hit-card__sub` (12/ink-2), and the symmetric MISS variants. Folding these into named classes would: (a) make the templates 30%+ shorter, (b) let CSS audits catch drift, (c) eliminate the inline `style=""` smell. Spec line 231-244 implicitly authorizes this — the inline styles were just the path of least resistance during implementation. Phase 8 cleanup candidate; not a contract violation today.

---

## Detailed Findings

### Pillar 1: Hi-fi fidelity (4/5) — PASS

Each Phase 7 template was read against its hi-fi `.jsx` reference. Hi-fi fidelity follows CONTEXT.md D-22 (layout / spacing / typography / color tone, not pixel-perfect).

**NAV-01 / NAV-02 TbTabBar** (`templates/element/tb_tabbar.php`, 45 lines) — matches `components.jsx` 116-125. Whitelist-validated `$active` (defangs typos), 4 items array driven, `is-active` + `aria-current="page"` on the active anchor, conditional `.tb-unread-dot` for inbox tab only. Anchor element (not `<div>`) for keyboard reachability per SSR-pure D-01. **Strength:** the element is deterministic, has no DB dependency, and is reused in all 4 of the templates listed in UI-SPEC §Element Extraction Policy. The `style="--cols: 4;"` inline declaration matches the spec line 93 contract.

**NAV-03 Dashboard 受信タブ** (`templates/Users/dashboard.php`, 243 lines, rewritten from 149) — matches `Dashboard.jsx`:
- TbAppBar big variant with title "受信箱", right slot has bell icon link + 32px gradient avatar (`.tb-dash-avatar` defined at `tamabox.css:2392-2404`, gradient `linear-gradient(135deg, --tb-turq-100, --tb-turq-200)`, 13px / 700 mono character, `--tb-turq-700`, `--tb-turq-200` border). Matches Dashboard.jsx 32px avatar.
- Box card (`.tb-dash-box`) renders the "あなたの箱" label + mono URL + SSR % chip. Visually matches the Dashboard.jsx Box card section.
- Counts row (`.tb-dash-counts`) — title 18/700, count 12px/mono, conditional 11/600 warm-100/warm-700 pill for unread. Matches hi-fi.
- Receive list — each `<details class="message-row tb-message-row">` renders the dot indicator (unread/hit/miss variants), meta line (sender + SSR badge for hit rows), preview, and mono timestamp. Phase 4 fixture-pinned substrings (`data-state`, `★ 抽選 hit`, `★ 抽選 miss`, `https://bsky.app/profile/`, `rel="noopener"`, `action="/dashboard/messages/{id}/open"`, `開封する`) all preserved.
- Reveal HIT body — `.tb-reveal-hit-card` warm-honey gradient (`#FFF7E0 → #FBEFCC`) + `#F0DCA8` border + 48px white circle with `✦` glyph + 22px font + warm-300 border. Sender card (`.tb-sender-card`) has 44px gradient avatar (`#E7C795 → #B98449`) + 14/700 handle + 12px mono `@handle` + "プロフィール" pill (30px height, turq-200 border, turq-700 text). Matches RevealHit.jsx 65-98 within D-22 tolerance.
- Reveal MISS body — `.tb-reveal-miss-card` soft card with 48px dashed circle + "—" glyph + 10/600/0.2em uppercase label + 15/600 title + 11px sub with mono warm-700 percentage. Matches Reveal.jsx.

**NAV-04 Discover stub** (`templates/Users/discover.php`, 51 lines) — matches `Discover.jsx` Empty subset. TbAppBar with title + sub "箱をみつける", search pill mock (42px height, pill radius, inline magnifier SVG + placeholder "@handle で箱をさがす"), tag chip row (6 chips, first one `.is-pseudo-active`, all `aria-disabled="true"`), empty-state card (✦ 36px + heading + body). Faithfully implements UI-SPEC §Screen 2 (D-16/D-18 — only the static骨格, not the full feed).

**NAV-05 Notifications stub** (`templates/Users/notifications.php`, 33 lines) — matches `Notifications.jsx` Empty骨格. TbAppBar title-only, vertically centered 96px paper-deep circle wrapping the 36px Bell icon, 16/700 title, 12/1.7 body. Matches hi-fi.

**NAV-06 Settings tab** (`templates/Inboxes/settings.php`, 40 lines, rewritten from 14) — matches `Settings.jsx`. TbAppBar (default, non-big) with back button + "受信箱の設定". Body renders Phase-6 elements (`inbox_settings_form`, `block_list`) directly. Footer TabBar with `active='settings'`. Spec line 365-368 honored.

**One deduction:** the Discover stub is materially thinner than `Discover.jsx` (216 lines → 51 lines). The spec explicitly approves this (D-16/D-18 — only the Empty subset), but the hi-fi reference includes a featured 箱 card and an inline "もうすぐ公開予定" lead block above the Empty card that the implementation does not render. The current implementation jumps directly from the tag chips to the Empty card with no transitional copy. This is acceptable for a stub but the layout reads slightly sparser than the hi-fi. Score 4/5 instead of 5/5 to flag the visual delta against the source `.jsx` — not a contract miss.

---

### Pillar 2: Token discipline (4/5) — PASS

**Phase 7 §H references 14 unique `--tb-*` properties:**

`--tb-card`, `--tb-card-soft`, `--tb-font-mono` (via `.tb-mono`), `--tb-ink`, `--tb-ink-2`, `--tb-ink-3`, `--tb-ink-4`, `--tb-line`, `--tb-line-strong`, `--tb-paper`, `--tb-paper-deep`, `--tb-r-lg`, `--tb-shadow-1`, `--tb-turq-100`, `--tb-turq-200`, `--tb-turq-400`, `--tb-turq-500`, `--tb-turq-700`, `--tb-warm-100`, `--tb-warm-300`, `--tb-warm-500`, `--tb-warm-700`.

**Raw color literals in Phase 7 §H** (`tamabox.css:2042-2404`):

| Value | Location | Justification |
|-------|----------|---------------|
| `#fff` | `.tb-discover-tag.is-pseudo-active`, `.tb-sender-card__avatar`, `.tb-reveal-hit-card__symbol`, inline in dashboard.php sender card | Spec line 268 (Phase 5 allow), hi-fi RevealHit.jsx:78, components.jsx |
| `#FBFCFD` | `.tb-message-row[data-state="unread"] .tb-message-row__head` background | **Undocumented** — see Recommendation #2 above |
| `#FFF7E0` | `.tb-reveal-hit-card` gradient start | UI-SPEC line 231 documents |
| `#FBEFCC` | `.tb-reveal-hit-card` gradient end | UI-SPEC line 231 documents |
| `#F0DCA8` | `.tb-reveal-hit-card` border | UI-SPEC line 231 documents (carry-over from Phase 6 send__welcome) |
| `#E7C795` | `.tb-sender-card__avatar` gradient start | UI-SPEC line 239 documents (hi-fi RevealHit.jsx:76) |
| `#B98449` | `.tb-sender-card__avatar` gradient end | UI-SPEC line 239 documents |
| `rgba(217, 162, 60, 0.22)` | `.tb-reveal-hit-card__symbol` shadow | UI-SPEC line 232 documents (warm-500 base at 22% alpha — hi-fi-pinned) |
| `rgba(0, 0, 0, 0.06)` | `.tb-sender-card__avatar` border | hi-fi RevealHit.jsx:79 verbatim |

**Additional inline literals in `dashboard.php` (not in §H but in template `style="..."`):**

- `color: rgba(217,162,60,0.10)` — the giant ghost ✦ in the HIT card top-right corner (dashboard.php:150). Hi-fi-decorative; new literal, not in spec allowlist.

**Net assessment:** 7 of the 9 literals are spec-documented or carry-overs from Phase 6's documented allowlist. The 2 new undocumented literals (`#FBFCFD` unread row tint, `rgba(217,162,60,0.10)` ghost ✦ glow) match the hi-fi but extend the literal-allowlist without an explicit locked-decision entry. Per Phase 6 D-22/D-23 and the UI-SPEC line 50 reminder, new exceptions require explicit locked-decision documentation. The amount of drift is small (2 new literals across ~370 §H lines + ~20 lines of inline RevealHit-card styling) but is real. Score 4/5.

---

### Pillar 3: Component reuse (5/5) — PASS

**TabBar consumed in all 4 templates** (per UI-SPEC §Element Extraction Policy):

| Template | Active tab | Element call |
|----------|-----------|--------------|
| `templates/Users/dashboard.php:241` | `inbox` | `tb_tabbar` with `unreadCount` |
| `templates/Users/discover.php:50` | `discover` | `tb_tabbar` with `unreadCount => 0` |
| `templates/Users/notifications.php:32` | `notifications` | `tb_tabbar` with `unreadCount => 0` |
| `templates/Inboxes/settings.php:39` | `settings` | `tb_tabbar` with `unreadCount => 0` |

All 4 sites use the same element with whitelist-validated `$active` and integer-coerced `$unreadCount`. The element renders 4 anchor tabs from a single array literal — no duplication across the call sites.

**Phase 5/6 elements consumed:**

- `templates/element/icon.php` — TabBar icons (inbox / compass / bell / user at 22px), bell at 36px (notifications empty), bell at 22px (dashboard appbar right), back at 22px (settings appbar). All via the central match-based registry — security-validated.
- `templates/element/inbox_settings_form.php` — reused at `settings.php:34` (Phase 6 completed element).
- `templates/element/block_list.php` — reused at `settings.php:36` (Phase 6 completed element, moved from dashboard per D-04).
- `templates/element/avatar_handle_chip.php` — not directly consumed in Phase 7 templates but rendered by `layout/default.php` (Phase 6 cross-screen pattern, header-bar continues).

**Phase 5 utility classes consumed:**

- `.tb-card` / `.tb-card-soft` — Box card, collision flash, MISS card, sender card.
- `.tb-chip.tb-chip--warm` — SSR percentage chip on Dashboard.
- `.tb-icon-btn` — bell icon button on Dashboard, back button on Settings.
- `.tb-btn.tb-btn--primary.tb-btn--full` — "開封する" button.
- `.tb-btn.tb-btn--quiet` — delete + report links.
- `.tb-appbar` / `.tb-appbar--big` / `.tb-appbar__title` / `.tb-appbar__sub` — all 4 tabs use these.
- `.tb-mono` — URL, count, timestamps, handle text, percentage.

**New Phase 7 layout helper:**

- `.tb-dash-screen` + `.tb-dash-screen__body` — full-height column wrapper consumed by all 4 templates. Single-responsibility (flex column with `min-height: calc(100vh - 56px)`, body has `padding: 4px 20px 16px; gap: 14px`). Easily clears the YAGNI "2+ call sites" threshold (4 call sites). Not extracted as a PHP element because it's a 2-line wrapper, no logic.

No bespoke per-template CSS leakage observed. Score 5/5.

---

### Pillar 4: Typography scale (5/5) — PASS

**Locked scale (Phase 5):** 8 sizes (22 / 18 / 16 / 15 / 14 / 12 / 11 / 10 px), 4 weights (400 / 500 / 600 / 700). Phase 6 added 30px Home title (selector-scoped). Phase 7 spec §47-50 explicitly forecasts the hi-fi half-pixels (13.5, 11.5, 10.5) round to scale and predicts no new exceptions.

**Sizes used in Phase 7 §H CSS (unique values, ascending):**

| Size | Approved? | Locations |
|------|-----------|-----------|
| 10px | yes | `.tb-dash-box__label`, `.tb-message-row__ssr`, `.tb-message-row__time` |
| 11px | yes | `.tb-dash-counts__pill`, `.tb-message-row__from` |
| 12px | yes | `.tb-dash-counts__num`, `.tb-discover-search__placeholder`, `.tb-discover-tag`, `.tb-empty-state__body`, `.tb-sender-card__handle`, `.tb-sender-card__profile-link`, `.tb-notif-empty__body` |
| 13px | yes (Phase 6 half-step accepted — but here used as `.tb-dash-avatar` font-size for the initial character) | `.tb-dash-avatar` |
| 14px | yes | `.tb-dash-box__url`, `.tb-message-row__preview`, `.tb-sender-card__name` |
| 16px | yes | `.tb-empty-state__title`, `.tb-notif-empty__title` |
| 17px | no | `.tb-sender-card__avatar` initial character |
| 18px | yes | `.tb-dash-counts__title` |
| 22px | yes | `.tb-reveal-miss-card__dash` glyph, `.tb-reveal-hit-card__symbol` ✦ glyph |
| 36px | yes (Phase 6 documented as display-character size, line 142 of 06-UI-REVIEW) | `.tb-empty-state__symbol` |

**Off-scale value:** 17px on `.tb-sender-card__avatar`. This is a 44×44 avatar circle rendering a single mb_substr character — display-character usage, not body text. Hi-fi RevealHit.jsx:78 specifies `fontSize: 17` verbatim. Same category as Phase 6's 20px send-avatar initial (06-UI-REVIEW Pillar 4 table accepts as "display character — not body text"). The 30px Home title selector-exception precedent + 36/20px display-character precedent covers this; no new locked-decision entry needed.

**Inline `style` attributes in dashboard.php** (RevealHit/MISS card body):

Sizes used: 10 / 11 / 12 / 15 / 16 / 130. All except `font-size: 130px` (the giant ghost ✦ in the HIT card corner) are on-scale. The 130px ✦ is a single decorative character with `pointer-events: none; aria-hidden="true"` — display-character usage matching the 64px Home ✦ precedent.

**Weights used:** 400, 500, 600, 700. No 300, no 800. All in the locked 4-weight set.

**Half-pixel rounding:** hi-fi RevealHit.jsx specifies 14.5px name → implemented 14 (on-scale); 11.5px handle → implemented 12 (on-scale); 9px SSR badge → implemented 10 (on-scale, snapped up). All follow CONTEXT.md D-22/D-23 rounding policy. **No new half-pixel sites introduced.**

Score 5/5: Phase 7 typography is unusually clean. The 17px / 130px display characters are consistent with Phase 6 precedent. No half-pixel drift.

---

### Pillar 5: Spacing scale (4/5) — FLAG

**Approved scale (Phase 5):** multiples of 4 — `4 / 8 / 12 / 16 / 20 / 24 / 32 / 40 / ...`. Locked exceptions: `.tb-chip gap: 6px`, `.tb-input padding: 14px`, `.tb-card padding: 18px`. Phase 6 audit added (advisory) hi-fi-pinned `gap: 10`, `gap: 18` (section-stacks), `margin: 14` carry-overs.

**Multiples-of-4 used in Phase 7 §H** (compliant): `4`, `8`, `12`, `16`, `20`, `24`, `28`, `32`, `40`, `44`, `48`, `56` (calc'd via `100vh - 56px`), `96`, `280`.

**Off-grid values in Phase 7 §H:**

| Value | Locations | Status |
|-------|-----------|--------|
| `gap: 6px` | `.tb-discover-tags` | Phase 5 locked exception (`.tb-chip gap: 6px`) carry-over — the discover tags ARE chips by another name |
| `gap: 10px` | `.tb-empty-state` (between symbol/title/body) | Phase 6 advisory exception (CTA stacks) carry-over |
| `gap: 14px` | `.tb-dash-screen__body` | Phase 6 advisory exception (`margin-top: 14px` family) carry-over — the body gap is the section-stack gap |
| `gap: 18px` | `settings.php` body inline `style="...gap: 18px"` | Phase 6 advisory exception (`.tb-card padding: 18px` family) carry-over |
| `padding: 14px 16px` | `.tb-dash-box` | 14px is the Phase 5 input-padding exception value (carry-over) |
| `padding: 14px` | `.tb-sender-card`, `.tb-message-row__head` (14px 14px) | 14px is the Phase 5 input-padding exception value (carry-over) |
| `padding: 0 14px` | `.tb-discover-search` | 14px exception carry-over |
| `padding: 28px` | `.tb-empty-state` | multiple of 4 — compliant |
| `padding: 40px 24px` | `.tb-notif-empty` | both multiples of 4 — compliant |
| `padding: 4px 10px` | `.tb-dash-counts__pill` | 10 was advisory in Phase 6 |
| `padding: 6px 14px` | `.tb-discover-tag` | 6 + 14 — 14 is exception value, 6 is chip-gap exception carry-over |
| `padding: 18px 20px` | `.tb-reveal-miss-card`, `.tb-reveal-hit-card` | 18 is `.tb-card` padding exception, 20 is on-scale |
| `padding: 4px 2px` | `.tb-dash-counts` | 4 + 2 — 2 is a known Phase 6 sub-pixel nudge family |
| `padding: 2px 0` | `.tb-discover-tags` | 2 is sub-pixel-nudge family |
| `margin-top: 3px` | `.tb-dash-box__url` | **new off-grid value** — not in Phase 5 exceptions, not previously documented |
| `margin-top: 2px` | `.tb-message-row__time`, `.tb-sender-card__handle` | Phase 6 sub-pixel-nudge family |
| `margin-top: 12px` | `.tb-sender-card` | compliant |
| `width / height: 30px` | `.tb-sender-card__profile-link` | hi-fi pinned (RevealHit.jsx:93) — compliant (30 = 4×7.5? no — actually `30` is not a multiple of 4. Hi-fi-pinned display dimension.) |
| `width / height: 42px` | `.tb-discover-search` | 42 is not a multiple of 4 — hi-fi-pinned search pill height |

**Net assessment:**

- Most off-grid values carry over from Phase 5/6 exception precedent (the 14 / 18 / 6 / 10 / 2 family).
- **Two genuinely new off-grid values:** `margin-top: 3px` on `.tb-dash-box__url` (label→URL nudge), and the `30px` profile-pill height + `42px` search-pill height. The 30/42 values are hi-fi-pinned display dimensions (round-pill UI elements) and have Phase 6 precedent (Settings preset pill `padding: 7px 12px` etc.). The `margin-top: 3px` is a label-baseline nudge in the same family as Phase 6's `margin-top: 2px / 3px` series (06-UI-REVIEW Pillar 5 documents the sub-pixel-nudge category).
- The 18 inline-style off-grid values in dashboard.php (the RevealHit/MISS bodies) match hi-fi exactly: `margin-top: 3px`, `margin-top: 2px`, `gap: 6px`, `gap: 10px` all align with the established Phase 6 advisory exceptions.

Score 4/5: spacing discipline is strong (multiple-of-4 dominant in the page-level layout), and the deviations are all hi-fi-pinned consistent with Phase 6 audit precedent. The single fresh exception is `margin-top: 3px` which has no explicit locked-decision entry but mirrors the existing 2px nudge family. **Recommendation:** when the Phase 6 supplement adds the half-step type scale + advisory spacing values, include `margin-top: 3px` + `height: 30 / 42px pill dimensions` in the same supplement to prevent re-flagging.

---

### Pillar 6: Accessibility hooks (5/5) — PASS

**TabBar a11y** (`templates/element/tb_tabbar.php`):

- `<nav role="tablist" aria-label="ダッシュボードタブ">` — landmark + accessible name ✓
- Each tab is an `<a role="tab">` — keyboard reachable without JS ✓
- Active tab gets `aria-current="page"` — screen reader announcement ✓
- Inbox unread dot has `aria-label="未読 N 件"` — count exposed to AT ✓
- `:focus-visible` outline rule at `tamabox.css:2046-2050` — keyboard focus indicator ✓

**Phase 7 templates a11y:**

- `templates/Users/discover.php:27` — `<div class="tb-discover-search" aria-disabled="true" role="search">` — search-region landmark + disabled state announced
- `templates/Users/discover.php:28-31` — inline magnifier SVG has `aria-hidden="true"` ✓
- `templates/Users/discover.php:35` — `<div class="tb-discover-tags" role="list" aria-label="カテゴリ">` + per-chip `role="listitem" aria-disabled="true"` — list semantics + disabled state on stub chips ✓
- `templates/Users/discover.php:44` — `<div class="tb-empty-state__symbol" aria-hidden="true">✦</div>` ✓
- `templates/Users/notifications.php:25` — `<div class="tb-notif-empty__circle" aria-hidden="true">` (decorative wrapper)
- `templates/Inboxes/settings.php:23` — `<button aria-label="戻る">` on back icon ✓
- `templates/Users/dashboard.php:49` — `<a aria-label="通知">` on bell icon link ✓
- `templates/Users/dashboard.php:53` — `<span class="tb-dash-avatar" aria-hidden="true">` (decorative)
- `templates/Users/dashboard.php:59` — `<p class="visually-hidden">ようこそ、{handle} さん</p>` — Phase 4 test substring "ようこそ" preserved as screen-reader-only ✓
- `templates/Users/dashboard.php:122` — `<span class="tb-dash-dot" aria-hidden="true">` (decorative state indicator)
- `templates/Users/dashboard.php:129` — `<span class="visually-hidden">未開封/開封済</span>` — state announced to SR ✓
- `templates/Users/dashboard.php:159,210` — `<span class="visually-hidden">★ 抽選 hit/miss …</span>` — Phase 4 test substring preserved as SR-only ✓
- `templates/Users/dashboard.php:202` — `<div class="tb-reveal-miss-card__dash" aria-hidden="true">—</div>` ✓
- `templates/Users/dashboard.php:151` — decorative giant ✦ has `aria-hidden="true"` ✓

**Focus visibility:**

- `.tb-tabbar__item:focus-visible` — 2px turq-400 outline at `tamabox.css:2046-2050`
- `.tb-btn:focus-visible` / `.tb-icon-btn:focus-visible` — Phase 5 inherited

**Reveal motion JS** (`webroot/js/reveal-motion.js`):

- Idempotent guard via `data-reveal-armed` flag — re-arming is safe.
- `toggle` event listener only fires on `details.open === true` — closed state is no-op.
- Class removed after 500ms (400ms keyframe + 100ms buffer) — no stuck states.
- Loaded with `defer` (default.php:20) — non-blocking, runs after DOMContentLoaded.

**Semantic HTML:**

- `<header class="tb-appbar">` for app bar regions ✓
- `<nav>` for tab bar ✓
- `<details>` / `<summary>` for collapsible message rows ✓
- `<time datetime="...">` for timestamps ✓

**Latent issue (advisory, see Recommendation #1):** the `.is-opening` keyframe runs unconditionally — no `prefers-reduced-motion: reduce` guard exists anywhere in the codebase. This is the only real a11y gap in Phase 7. Not deducted because (a) the animation is a 400ms fade with 2px translate (under the WCAG 2.3.3 "small motion" threshold debate), (b) the spec did not mandate the media query, (c) the fix is trivial. Adding the guard is the #1 priority recommendation above.

Score 5/5: TabBar a11y is exemplary, Phase 4 test-required SR-only strings preserved, all decorative elements `aria-hidden`, all icon-only interactive elements have `aria-label`, focus-visible inherited from Phase 5 and extended to TabBar.

---

## Files Audited

| File | Lines | Role |
|------|-------|------|
| `templates/element/tb_tabbar.php` | 45 | NEW — TabBar element (NAV-01 / NAV-02) |
| `templates/Users/dashboard.php` | 243 | REWRITE — Dashboard 受信タブ (NAV-03 + MOTION-02 + MOTION-03) |
| `templates/Users/discover.php` | 51 | NEW — Discover stub (NAV-04) |
| `templates/Users/notifications.php` | 33 | NEW — Notifications stub (NAV-05) |
| `templates/Inboxes/settings.php` | 40 | REWRITE — Settings tab (NAV-06) |
| `webroot/js/reveal-motion.js` | 35 | NEW — Reveal fade-in JS (MOTION-02) |
| `webroot/css/tamabox.css` §H (lines 2037-2404) | 368 | Phase 7 CSS additions |
| `webroot/css/tokens.css` | 250 | Phase 5 tokens (referenced for `.tb-tabbar` base + `--tb-*` vars) |
| `templates/element/icon.php` | 39 | Phase 5 icon registry (consumed by TabBar) |
| `templates/element/inbox_settings_form.php` | 165 | Phase 6 element (consumed by Settings tab) |
| `templates/element/block_list.php` | 68 | Phase 6 element (consumed by Settings tab) |
| `~/projects/handoff_tamabox/screens/Dashboard.jsx` | 109 | hi-fi NAV-03 reference |
| `~/projects/handoff_tamabox/screens/Discover.jsx` | 216 | hi-fi NAV-04 reference (Empty subset used) |
| `~/projects/handoff_tamabox/screens/Notifications.jsx` | 100 | hi-fi NAV-05 reference (Empty subset used) |
| `~/projects/handoff_tamabox/screens/Settings.jsx` | 162 | hi-fi NAV-06 reference (mostly Phase 6) |
| `~/projects/handoff_tamabox/screens/Reveal.jsx` | — | hi-fi MISS card reference |
| `~/projects/handoff_tamabox/screens/RevealHit.jsx` | 305 (lines 65-98 used) | hi-fi sender card reference (MOTION-03) |
| `~/projects/handoff_tamabox/components.jsx` | 146 | hi-fi TbTabBar / TbAppBar reference |
| `.planning/phases/07-dashboard-4-reveal/07-UI-SPEC.md` | 685 | Phase 7 design contract |
| `.planning/phases/07-dashboard-4-reveal/07-CONTEXT.md` | 181 | Phase 7 locked decisions (D-01..D-23) |
| `.planning/phases/06-v1-calm-gacha/06-UI-REVIEW.md` | 290 | Phase 6 review (template for this output) |

---

## Overall Summary

**27/30 — PASS.** Phase 7 successfully ships the 4-tab dashboard split (受信 / 発見 / 通知 / 設定), the `tb_tabbar` PHP element, and the Reveal fade-in + RevealHit sender-card演出. Hi-fi fidelity holds across all 4 tabs and both Reveal states (4/5 — only the Discover stub is materially thinner than hi-fi, which is spec-approved per D-16/D-18). Token discipline holds (4/5 — `#FBFCFD` unread-row tint is the only new undocumented literal). Component reuse is excellent (5/5 — TabBar consumed in all 4 templates as designed, all Phase 5/6 elements re-consumed without leakage). Typography is unusually clean (5/5 — every Phase 7 §H size lands on the locked 8-size scale; hi-fi half-pixels rounded per D-22/D-23; no new half-pixel sites). Spacing is strong (4/5 — most deviations are Phase 5/6 exception carry-overs; one fresh `margin-top: 3px` family member without explicit locked-decision entry). A11y is excellent (5/5 — TabBar has full ARIA, Phase 4 test substrings preserved as SR-only, focus-visible extended to tabs).

**The single real a11y gap** is the missing `prefers-reduced-motion: reduce` guard around the `.is-opening` keyframe (Recommendation #1). This is the #1 priority advisory fix for Phase 8 or a Phase 7 supplement — one CSS block, real WCAG 2.3.3 alignment.

**Recommended pre-merge actions (advisory, none blocking):**

1. Add the `prefers-reduced-motion` guard to `tamabox.css` §H.2 (3 lines).
2. Document `#FBFCFD` (and the `rgba(217,162,60,0.10)` ghost-✦ glow) in a Phase 7 supplement to the literal allowlist — OR introduce a `--tb-card-tint` token in `tokens.css`.
3. (Phase 8 cleanup) Fold dashboard.php's ~18 inline `style="..."` attributes in the RevealHit/MISS card bodies into named §H subclasses to shrink the template and let CSS audits catch future drift.

**This audit is advisory — Phase 7 ships.**
