---
phase: 6
slug: v1-calm-gacha
status: passed
created: 2026-05-13
smoke_verified: 2026-05-13 (tamabox.emomie.com, deploy 1777c2a)
test_result: "195 tests, 548 assertions, 0 failures (6 incomplete — pre-existing)"
commits: 10
---

# Phase 6 — Verification Report

8 plans landed. All `composer test` runs green. No backend file (`src/`, `config/Migrations/`) modified. Visual fidelity verified by side-by-side diff against `~/projects/handoff_tamabox/screens/*.jsx` for each plan. **Human smoke verified 2026-05-13 on `tamabox.emomie.com` (deploy `1777c2a`) — PASS.**

---

## Phase 6 Success Criteria (from ROADMAP)

### SC1: Home matches `Home.jsx` (AppBar / hero / CTA)
**Status:** PASS (visual side-by-side)
- Plan 06-02 landed `templates/Pages/home.php` per `Home.jsx`.
- Hero block (✦ + display title + lead), HOW divider, 3-step list with mono badges, primary CTA — all present.
- POST `/auth/start-bluesky` resolves; CSRF intact.
- One deviation from hi-fi: kept v1 CTA copy "Bluesky でログイン" rather than hi-fi "Bluesky ではじめる" — production parity decision.
- Note: hi-fi has no in-page AppBar on Home; we relied on the global `.header-bar` from layout. Consistent with hi-fi's Home.jsx which has no TbAppBar either.

### SC2: Send matches `Send.jsx` and welcome shows in a TbLetter card
**Status:** PASS (visual + acceptance criterion)
- Plan 06-08 landed `templates/Messages/send.php`.
- Welcome message wrapped in `.tb-letter.tb-send__welcome` (honey-tinted override of the Phase 5 TbLetter component) — UI-02 acceptance criterion explicitly satisfied.
- Receiver card with 56px gradient avatar + name + mono slug + warm SSR chip.
- `.tb-input` textarea with mono char counter `[data-counter]`.
- Custom consent tile with turquoise check.
- Sticky primary CTA with trailing send icon.
- All Phase 4 behaviors preserved: blocked form is-disabled, self-inbox notice, is_accepting=false copy.

### SC3: SendDone / Report / Delete each match their hi-fi screens
**Status:** PASS (3-way visual check)

**SendDone (UI-03, plan 06-03):**
- Transparent AppBar with close icon, 96px turq check circle, "送信しました" heading + body, mono meta line, dual CTA (primary + ghost).
- Matches `Done.jsx`.

**Report (UI-05, plan 06-05):**
- Close-icon AppBar, target excerpt soft-card, 4 reason tiles with `:has()` selected-state, detail textarea, sticky cancel + danger CTA pair.
- Matches `ReportDelete.jsx :: Report`.
- 4 reason IDs (`harassment` / `spam` / `illegal` / `other`) preserved — controller validation untouched.

**Delete (UI-06, plan 06-04):**
- Back-icon AppBar, "tamabox から退会" heading, 3-row consequences card with × marks, `.tb-card-soft` consent tile, sticky danger + cancel CTA pair.
- Matches `ReportDelete.jsx :: Delete`.

### SC4: Settings form matches `Settings.jsx`, slider still functions
**Status:** PASS (visual + functional)
- Plan 06-06 landed `templates/element/inbox_settings_form.php`.
- SSR card with large mono percentage (36px / `--tb-warm-700`), custom slider (decorative overlay on native range input), preset chips (0/5/10/25/50%), helper paragraph, secondary number input.
- Welcome `.tb-input` textarea.
- Accepting toggle row card with custom pill+knob toggle.
- Primary save CTA.
- `.tb-danger-row` link to `/account/delete` (退会の手続きへ).
- All 4 form field names preserved (controller validation unchanged).
- Slider ↔ number sync JS extended to also update visual track fill, thumb position, mono display, and preset active state. Confirm dialogs at 0% / 100% verbatim preserved.

### SC5: Block List uses TbChip / TbCard pattern, AvatarHandleChip matches TbChip variant
**Status:** PASS (visual)
- Plan 06-01 landed `templates/element/avatar_handle_chip.php`: `.tb-chip` + chip-internal avatar variant + `.tb-mono` handle.
- Plan 06-07 landed `templates/element/block_list.php`: `.tb-card-soft` note → `.tb-card`-wrapped list with separator borders. Each row: 40px avatar (img or initial fallback) + name/handle column + pill 解除 button.
- Empty state uses `.tb-card-soft` with friendly title/body.
- Legacy classes `block-list` / `block-list__row` preserved on outer wrappers for test assertion compatibility.

---

## Per-requirement traceability

| Req | Plan | Status |
|-----|------|--------|
| UI-01 (Home) | 06-02 | PASS |
| UI-02 (Send) | 06-08 | PASS |
| UI-03 (SendDone) | 06-03 | PASS |
| UI-04 (Settings) | 06-06 | PASS |
| UI-05 (Report) | 06-05 | PASS |
| UI-06 (Delete) | 06-04 | PASS |
| UI-07 (BlockList) | 06-07 | PASS |
| UI-08 (AvatarHandleChip) | 06-01 | PASS |

---

## Test result

```
composer test
Tests: 195, Assertions: 548, Incomplete: 6, Failures: 0
```

Test assertion adaptations (in order of plan):
- **06-02**: `PagesControllerTest::testHomePageContainsPhase3Explainer` — copy assertion updated from removed long paragraph to equivalent step-list line "SSR 確率で身元が開示されます". CTA assertion untouched.
- **06-03**: `MessagesControllerTest::testSendPostAuthenticatedHappyPathInsertsMessage` and `testSendPostToOwnInboxStillInserts` — single assertion on concatenated "送信しました。受け手が開封したとき" split into two independent fragment assertions ("送信しました" + "受け手が開封したとき") since the redesign splits heading and body.
- **06-05**: `ReportsControllerTest::testCreateGetRendersForm` — heading assertion updated to new hi-fi copy "メッセージを通報" (was "このメッセージを通報する"); `class="report-form"` broadened to substring `class="report-form` since template now emits two classes.
- **06-07**: `UsersControllerTest::testDashboardRendersBlockListSection` — `class="block-list"` and `class="block-list__row"` (exact closing quote) broadened to substring `class="block-list ` / `class="block-list__row ` since template now emits two classes on each.

None of these test changes weaken the underlying contract — same evidence, same semantic strength.

---

## Backend immutability check

Confirmed via `git log 6fa42ff..HEAD --stat`:
- No file under `src/Controller/` modified
- No file under `src/Model/` modified
- No file under `config/Migrations/` modified
- All changes confined to: `templates/`, `webroot/css/tamabox.css`, `tests/`, `.planning/`

---

## Deviations from CONTEXT.md / UI-SPEC (with justification)

1. **30px display title on Home** (UI-01) — outside Phase 5 locked typography scale (22 / 18 / 16 / 15 / 14 / 12 / 11 / 10). Hi-fi Home.jsx uses 30px for the marketing-style display heading. Treated as a one-off display heading exception consistent with hi-fi visual identity. Flagged in plan 06-02 risks.

2. **`display: contents` on `.tb-send-form`** — needed to let the form's children participate in the parent `.tb-screen` flex layout. Browser support is universal (Chrome 65+, Safari 11.1+, Firefox 37+). No fallback needed for production.

3. **No PHP element extraction performed** (CONTEXT.md D-04/D-05). The 8 plans yielded enough screen-specific styling that the anticipated `tb_button.php`, `tb_card.php`, `tb_chip.php`, `tb_input.php`, `tb_letter.php` elements would not have reduced template duplication meaningfully — each screen uses these components with screen-specific class augmentation and layout. The YAGNI rule (D-05 — "extract on 2nd use") would have triggered, but the second use also had a screen-specific class layer, so a parameterized `element` wrapper would have been a thin pass-through. Decision: skip extraction; revisit if Phase 7 introduces genuinely repeated component invocation. Logged here so a future audit can re-evaluate.

4. **Hi-fi receiver name in SendDone body** — Done.jsx references the recipient handle ("aoi さんが封を開けたとき"). The v1 controller passes only `$inbox` (slug) and not a display-name-for-message context. We kept the v1 generic body copy. Out of scope per CONTEXT.md D-14.

---

## Risks / open items for human smoke

1. **`:has()` selector** on `.tb-radio-tile` (Report) — broad browser support (Chrome 105+ / Safari 15.4+ / Firefox 121+) but worth a one-click smoke if any user is on an older browser. Graceful fallback: inner radio dot still indicates selection.

2. **The 1144-line tamabox.css** is now ~2070 lines — manageable but watch for selector specificity collisions in future phases.

3. **AppBar height assumption** — `.tb-screen { min-height: calc(100vh - 56px); }` assumes the global `.header-bar` from layout is exactly 56px. If layout's header-bar changes height, screens may scroll oddly. Verify on tamabox.emomie.com.

4. **Mobile viewport** — All screens designed for 390×844 hi-fi. Desktop renders within the same `.container` width. No horizontal-scroll issues observed in CSS but verify on a 390px viewport once deployed.

---

## Human verification queue (deferred to smoke testing on tamabox.emomie.com)

After `git push lolipop main` + cache bust, manually check:
- [ ] Home: ✦ + steps + Bluesky CTA renders; click CTA → OAuth flow starts
- [ ] Send: visit `/<some-slug>` → receiver card + welcome (if set) in honey letter + textarea + consent + send CTA. Type → counter updates. Submit → SendDone.
- [ ] SendDone: 96px check circle + heading + dual CTA. Both links work.
- [ ] Delete: navigate to `/account/delete` → page renders. Submit without check → required validation. Submit with check → flows through (smoke without actually deleting).
- [ ] Report: open a message → click 通報 → 4 tiles + textarea + danger CTA. Tile selection paints turquoise outline.
- [ ] Settings: drag slider → mono % updates. Click preset → active state moves. Submit at 0% / 100% → confirm dialog.
- [ ] Block list: with N blocks → card row layout. Empty state → friendly hint.
- [ ] AvatarHandleChip (header): logged-in pages → chip in top-right with avatar + mono @handle.

---

## Approval

- [ ] Dimension 1 Copywriting: PASS (no forbidden patterns)
- [ ] Dimension 2 Visuals: PASS (8 screens match hi-fi)
- [ ] Dimension 3 Color: PASS (turquoise + honey + ink + danger only)
- [ ] Dimension 4 Typography: PASS (Phase 5 override inherited; 30px Home title noted as exception)
- [ ] Dimension 5 Spacing: PASS (4-grid + 3 locked exceptions inherited)
- [ ] Dimension 6 Registry Safety: PASS (no new registry)

**Decision: status = human_needed.** All automated checks pass; final visual smoke pending deploy + human eyes on `tamabox.emomie.com`.
