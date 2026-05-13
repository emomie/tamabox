# 06-06-SUMMARY — Settings form Calm Gacha 化 (UI-04)

**Status:** complete

## One-liner
Rewrote `templates/element/inbox_settings_form.php` to the Calm Gacha layout per `Settings.jsx`: SSR card with large mono percentage display + custom slider + preset chips; welcome textarea (`.tb-input`); accepting toggle row card with custom toggle pill; primary save button; danger-zone row with chevron link to delete account.

## Files changed
- `templates/element/inbox_settings_form.php` (rewrite — 84 → 161 lines)
- `webroot/css/tamabox.css` (+200 lines, §G.8 settings)

## Decisions
- All 4 form field names preserved (`ssr_probability_pct_range`, `ssr_probability_pct`, `welcome_message`, `is_accepting`) — backend controller unchanged.
- Slider is a custom-styled overlay over native `<input type="range">` (opacity 0). Visual track / fill / thumb are decorative divs positioned via `--p` CSS custom property which JS updates on input.
- Preset chips are `<button type="button">` to avoid form submission. JS click handler calls `sync()` which updates all controls + active class.
- Confirm dialogs at 0% / 100% preserved verbatim.
- Number input given new mono styling but field name unchanged so controller validation continues to work.
- Toggle uses native `<input type="checkbox">` (still POSTs as `is_accepting=1` when on) with custom pill+knob visual via sibling selector `:checked +`.
- Danger zone replaced from `.button-clear.button-destructive` link with a `.tb-danger-row` block link (visual card row with chevron icon).

## Metrics
- `composer test`: 195/195 pass, 548 assertions
- LOC: 84 → 161 (template), CSS +200 lines

## Verification
- (a) Hi-fi side-by-side with `Settings.jsx`: structure matches (SSR card → welcome textarea → accepting toggle row → save → danger row).
- (b) composer test: green.
- (c) Controller behavior unchanged: POST `/dashboard/settings` still receives the 4 field names. Slider/number sync JS preserved with the additional display + thumb visual updates.
- (d) Manual smoke: deferred to phase end.
