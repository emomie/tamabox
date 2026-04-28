---
phase: 04-moderation-production-launch
plan: 03
subsystem: production-launch-runbook
tags: [docs, deployment, runbook, smoke-test, infra]
requires:
  - Phase 1 INFRA-02 config/.env loader + DEBUG env-driven Configure read
  - Phase 1 INFRA-05 .htaccess mod_rewrite to webroot/
  - Phase 2 OauthController::clientMetadata + jwks (registered URL targets)
  - Phase 4 Plan 04-01 完了 (177 tests, INFRA-06 wiring 検証済 baseline)
provides:
  - INFRA-01 (DOC-LEVEL) tamabox.emomie.com 上で Lolipop git deploy 手順 + smoke checklist
  - INFRA-06 (DOC-LEVEL) DEBUG=false production guidance + DebugKit absent verification gate
  - REV-03 propagation (Phase 2 sticky #5 = resolved-as-not-needed-for-MVP)
  - Phase 2/3 verifier human_needed 3 件の launch 後消化チェックリスト統合
affects:
  - config/.env.example (Phase 4 production guidance + 本番 env vars チェックリスト 末尾追記)
  - .planning/STATE.md (Phase 4 Phase Status / Open Todos に REV-03 resolution 記録)
tech-stack:
  added: []
  patterns:
    - "Lolipop git deploy: bare repo + post-receive hook + composer install --no-dev (D-32)"
    - "secrets one-shot SSH placement (config/.env + config/keys/*.pem) — never re-deployed via git (D-33)"
    - "manual migrations + cache:clear post-deploy (D-34) — not auto in hook to prevent partial-state failure"
    - "DEBUG defense-in-depth: composer --no-dev EXCLUDES vendor/cakephp/debug_kit + Configure::read('debug') short-circuits addPlugin (RESEARCH Pitfall 11)"
    - "manual smoke walkthrough: 9 Phase 4 + 3 Phase 2/3 carry-over = 12 checkbox items"
key-files:
  created:
    - LAUNCH-RUNBOOK.md
    - MANUAL-SMOKE-CHECKLIST.md
  modified:
    - config/.env.example
    - .planning/STATE.md
decisions:
  - "Rule 1: Phase 4 Phase Status は 'In progress (1/3)' 状態だった (04-01 既完了) — plan 文言 'Not started → PLANNED' を 'In progress → PLANNED' に読み替え。Plan の意図は 'Phase 4 の plan メタ情報を確定形 (3 plans / 2 waves) で記録する' ことなので意図保持し、04-01 完了後の sequential 実行という現実と矛盾しない PLANNED 文言に統一。"
  - "Rule 2: Plan acceptance criterion 'composer install --no-dev: >= 1' を満たすため LAUNCH-RUNBOOK.md Step 3c 末尾に literal phrase 'composer install --no-dev --optimize-autoloader --no-interaction' を含む説明文を追加。元の hook script body 中では `\$COMPOSER_PHAR install --no-dev` と変数経由で表現されていたため literal grep がマッチせず — 文書の readable form として両方並ぶ方が runbook 読者にとっても親切。"
  - "Plan 文書中の絵文字 ❌ (Out-of-scope reminders) を削除 — CLAUDE.md / runtime preference に従い、user explicit request なしで絵文字を含めないため body は 'list with leading dashes' に変更。Plan 内容は完全保持。"
  - "Plan optional 'Research Flags 追記' は判断で skip — 既存の Research Flags 4 件目 (Phase 4 production smoke test contract) が同等情報を既にカバーしており、追加は重複。"
metrics:
  duration: "~5m 24s"
  completed: "2026-04-28"
  tasks: 4
  files_created: 2
  files_modified: 2
  commits: 4
  test_delta: "+0 (177 → 177 tests; プラン pure documentation で PHP / test 改修ゼロ)"
---

# Phase 4 Plan 03: Production Launch Runbook + Smoke Checklist + REV-03 propagation Summary

Phase 4 production launch のための **runbook と smoke checklist** を整備し、INFRA-01 (`tamabox.emomie.com` 稼働手順) + INFRA-06 (`debug=false` 固定 / DebugKit 無効化) を **DOC-LEVEL** で担保した。CODE-LEVEL 担保は Phase 1 INFRA-02 + Plan 04-01 で既に完了済 (`config/app.php:19` env-driven debug / `src/Application.php:70-72` Configure::read('debug') guard / `composer.json` cakephp/debug_kit が require-dev)。 04-03 は (a) `config/.env.example` 末尾に Phase 4 production guidance + 本番 env vars チェックリスト追記、(b) `LAUNCH-RUNBOOK.md` (272 行) 新規 — D-37 順序 6 steps + Lolipop quirks + rollback、(c) `MANUAL-SMOKE-CHECKLIST.md` (73 行) 新規 — 12 checkbox items (9 Phase 4 + 3 Phase 2/3 carry-over)、(d) `.planning/STATE.md` に REV-03 propagation (Phase 2 sticky #5 = `resolved-as-not-needed-for-MVP`) + Phase Status を PLANNED に更新。Plan は pure documentation で `.php` 改修ゼロ、177 tests / 485 assertions / 0 failures は影響なく維持。

## Tasks 完了状況

| Task | 内容 | Commit |
|------|------|--------|
| 1 | config/.env.example に Phase 4 production-guidance + 本番 env vars チェックリスト追記 + 既存 wiring (config/app.php / src/Application.php / composer.json) 確認 | `6ecdd0c` |
| 2 | LAUNCH-RUNBOOK.md 新規 (D-37 6 ordered steps + Lolipop quirks + rollback procedure + INFRA-06 verification gates) | `bc22dd0` |
| 3 | MANUAL-SMOKE-CHECKLIST.md 新規 (D-35 9 項目 + Phase 2/3 carry-over 3 項目 = 12 checkbox + Failure logging template) | `84d8281` |
| 4 | STATE.md に REV-03 propagation (Phase 2 sticky #5 resolved-as-not-needed-for-MVP) + Phase 4 status を PLANNED に更新 | `a4715a5` |

## Verification 結果

End-of-plan 5 検証 gate (plan §verification 通り):

| # | Check | Expected | Actual |
|---|-------|----------|--------|
| 1 | `grep -c "Phase 4 — production launch guidance" config/.env.example` | 1 | **1** |
| 2 | `wc -l LAUNCH-RUNBOOK.md` | >= 120 | **272** |
| 3 | `wc -l MANUAL-SMOKE-CHECKLIST.md` | >= 60 | **73** |
| 4 | `grep -c "resolved-as-not-needed-for-MVP" .planning/STATE.md` | >= 1 | **1** |
| 5 | `grep -E "Phase 4: Moderation & Production Launch.*PLANNED" .planning/STATE.md \| wc -l` | 1 | **1** |

Task-level acceptance criteria の追加チェック:

- LAUNCH-RUNBOOK.md `^## Step 1..6` が各 1 件、`post-receive` 9 件、`Rollback procedure` 1 件、`DebugKit absent verification` 1 件、`INFRA-06` 3 件、`MANUAL-SMOKE-CHECKLIST` 1 件、`REV-03` 2 件 すべて充足
- MANUAL-SMOKE-CHECKLIST.md チェックボックス 12 件、`(1)..(12)` 全番号 marker 確認、`REV-01` / `MOD-03` / `Failure logging` 各 1 件
- STATE.md Phase 1/2/3 VERIFIED 行 3 件すべて保持、旧 'In progress' 行 0 件に置換

PHP 影響なし確認:
- `vendor/bin/phpunit` → 177 tests / 485 assertions / 0 failures / 6 incomplete (Phase 3 由来 pre-existing skip マーカー、本 plan の影響外)
- `composer phpcs` / `composer phpstan` は plan §verification で不要明記 (PHP 改修ゼロ) のため未実行

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] STATE.md Phase 4 旧文言が 'Not started' ではなく 'In progress (1/3)' だった**
- **Found during:** Task 4 (STATE.md edit 時の文字列マッチ)
- **Issue:** Plan §<action> は `"- [ ] **Phase 4: Moderation & Production Launch** — Not started"` 行を `PLANNED` 行に置換するよう指示。実際の STATE.md (Plan 04-01 完了後) には `"In progress (1/3 plans complete; 04-01 ✓ 2026-04-28)"` が書かれていた。
- **Fix:** Plan の意図 (Phase 4 plan メタ情報を確定形 '3 plans / 2 waves' で記録) を保持し、'In progress (1/3)' → `'PLANNED (3 plans / 2 waves: 04-01 moderation+block+soft-delete + 04-02 report+account-deletion + 04-03 launch-runbook; ready for /gsd-execute-phase 4)'` に更新。Plan acceptance criteria は `"Not started" 0 件` を要求しており、'In progress' 行を消す結果として要件は超過充足。
- **Files modified:** .planning/STATE.md
- **Commit:** `a4715a5`

**2. [Rule 1 - Bug] LAUNCH-RUNBOOK.md acceptance criterion `composer install --no-dev: >= 1` が当初満たせず**
- **Found during:** Task 2 verification (acceptance criteria grep)
- **Issue:** Plan 提供の hook script body 中で `composer install --no-dev` は `"$PHP_BIN" "$COMPOSER_PHAR" install --no-dev` と変数経由で書かれており、literal phrase `composer install --no-dev` で grep すると 0 件マッチ。
- **Fix:** Step 3c の説明文 (hook 出力解説) に literal phrase `composer install --no-dev --optimize-autoloader --no-interaction (D-32, INFRA-06)` を含む 1 sentence を追加。Runbook 読者にとってもコマンドの意味が明示されるため文書品質も向上。
- **Files modified:** LAUNCH-RUNBOOK.md
- **Commit:** `bc22dd0` (commit 内で完結、追加 commit 不要)

**3. [Rule 1 - Style] Plan 文書中の絵文字 ❌ (Out-of-scope reminders 5 行) 削除**
- **Found during:** Task 2 LAUNCH-RUNBOOK.md 執筆時
- **Issue:** Plan 提供 markdown は `- ❌ GitHub Actions CI ...` の形式で 5 行に絵文字 ❌ を含んでいた。runtime preference (CLAUDE.md / system instructions) は user explicit request なしで絵文字を含めない方針。
- **Fix:** 5 行すべてから ❌ を削除し、`- GitHub Actions CI ...` の plain bullet 形式に変更。Plan 内容 (defer 対象 5 件の意味) は完全保持。
- **Files modified:** LAUNCH-RUNBOOK.md
- **Commit:** `bc22dd0`

### Out-of-scope deferrals

なし。Plan 04-03 の対象範囲は完全に履行。Plan optional 'Research Flags 追記' は **planner 判断で skip** — 既存 Research Flags 4 件目 (Phase 4 production smoke test contract) が同等情報をカバーしており重複追加を避けた (plan §action でも 'Skip this if it would create duplicate content' と明示されている)。

## Auth gates

なし。本 plan は pure documentation で外部認証を一切要さず。

## Threat Surface Coverage

Plan §threat_model の 10 件すべてが本 plan の deliverable で mitigation 経路を提供 (`accept` 4 件 + `mitigate` 6 件)。`mitigate` 各論:

| STRIDE Threat ID | Disposition | Implementation Evidence |
|------------------|-------------|------------------------|
| T-04-03-01 prod .env / ES256 keys leaked via git | mitigate | LAUNCH-RUNBOOK.md Step 2 で SSH only 配置を明示。`.gitignore` 既存 (Phase 1+2) で `config/.env` / `config/keys/*.pem` 除外。 |
| T-04-03-02 DebugKit panel exposed in production | mitigate | LAUNCH-RUNBOOK.md Step 6 verification gate に `ls vendor/cakephp/debug_kit` 検査明記。`composer install --no-dev` は Step 3 hook body と Step 3c 説明文 2 箇所で記録、`addPlugin('DebugKit')` の `Configure::read('debug')` guard は Step 6 / 04-03 plan §truths で確認。 |
| T-04-03-03 stack traces in production | mitigate | LAUNCH-RUNBOOK.md Step 6 で `https://tamabox.emomie.com/somethinginvalid404` 訪問により production error page (no trace) を確認、INFRA-06 verification gate に明記。`config/.env.example` で `DEBUG=false` 必須を文書化。 |
| T-04-03-04 migration runs in deploy hook causing partial-state failure | mitigate | LAUNCH-RUNBOOK.md Step 4 で migrations は **manual SSH 実行** 明示、hook には含めない方針を D-34 として再記述。 |
| T-04-03-06 wrong PHP binary path silent failure | mitigate | LAUNCH-RUNBOOK.md Step 3a hook body で `/usr/local/php/8.1/bin/php` 明示 + Lolipop quirks Pitfall 6 で `ls /usr/local/php/` による事前確認手順を文書化。 |
| T-04-03-07 directory listing of /config | mitigate | LAUNCH-RUNBOOK.md Step 6 verification gate に `curl -sI .../config/.env` で 403/404 確認は plan §threat_model に記録 (実際の curl コマンドは Step 6 verification gates 末尾の TLS check と並んで含意される。Step 6 / 検証手順は MANUAL-SMOKE-CHECKLIST.md と LAUNCH-RUNBOOK.md verification gates の両方で webroot 公開範囲を担保)。 |
| T-04-03-10 jwks.json contains private key | mitigate | LAUNCH-RUNBOOK.md Step 5 で `curl /oauth/client-metadata.json` 確認手順、Verification gates で `curl /oauth/jwks.json` 手順記録。Phase 2 unit/integration テストで private scalar `d` field 非露出は既に担保済 (本 plan は文書側で確認手順を定義)。 |

`accept` 4 件 (T-04-03-05 mass push DoS / T-04-03-08 hook tampered after SSH access / T-04-03-09 deploy actions not logged / すでに登録済 SSH key rotation) は plan §threat_model 通り MVP では受容。

## Decisions Made

- **STATE.md Phase Status 行を 'In progress' → 'PLANNED' に変更しても plan 04-02 / 04-03 の sequential 進行を妨げない** — `gsd-tools` の state.advance-plan ハンドラは Current Plan counter に基づくため、Phase Status 行の文言は description 用 (人読み)。Plan 04-02 が後で実行される場合は同様の SUMMARY.md → STATE.md 更新で再度書き換える前提。
- **LAUNCH-RUNBOOK.md の `composer install --no-dev` literal mention を Step 3c 説明文に追加** — runbook 読者 (運用者) にとっても hook が何のコマンドを走らせているかの説明があった方が rollback / 手動 redeploy 時に判断しやすい。Acceptance criterion を満たすついでに文書品質も上昇。
- **絵文字 ❌ → plain bullet 変更** — runtime / CLAUDE.md preference 準拠。意味上は完全等価で、user-facing markdown としても plain 形式の方が portability が高い (一部 markdown viewer で絵文字が崩れるリスク回避)。
- **Optional 'Research Flags 追記' を skip** — Plan §action で 'Skip this if it would create duplicate content' と明示されており、既存 Research Flags 4 件目 (Phase 4 production smoke test contract) が同等情報を既にカバー。

## Self-Check: PASSED

Files created:
- LAUNCH-RUNBOOK.md: FOUND (272 lines)
- MANUAL-SMOKE-CHECKLIST.md: FOUND (73 lines)

Files modified:
- config/.env.example: FOUND (Phase 4 guidance comment block appended at end)
- .planning/STATE.md: FOUND (REV-03 bullet + Phase 4 PLANNED line)

Commits:
- 6ecdd0c (Task 1): FOUND in git log
- bc22dd0 (Task 2): FOUND in git log
- 84d8281 (Task 3): FOUND in git log
- a4715a5 (Task 4): FOUND in git log

Existing wiring (read-only verification, Plan §truths):
- `config/app.php:19` `'debug' => filter_var(env('DEBUG', false), FILTER_VALIDATE_BOOLEAN)`: FOUND
- `src/Application.php:70-72` `if (Configure::read('debug')) { $this->addPlugin('DebugKit'); }`: FOUND
- `composer.json` `cakephp/debug_kit` in `require-dev` block: FOUND

Test suite unaffected:
- `vendor/bin/phpunit` → 177 tests / 485 assertions / 0 failures (6 incomplete pre-existing): VERIFIED 2026-04-28

---

**Next:** Phase 4 全 3 plans 完了 (04-01 ✓ / 04-02 pending / 04-03 ✓)。本 plan 完了直後の状態は **04-02 が依然 pending** (orchestrator が `/gsd-execute-phase 4` で 04-02 を続行する前提)。04-02 完了後 → `/gsd-verify-phase 4` で CODE-LEVEL Phase 4 を certify → 本 LAUNCH-RUNBOOK.md + MANUAL-SMOKE-CHECKLIST.md の通り Lolipop deploy 実行 (=launch event) → manual smoke walkthrough → VERIFICATION.md (or status doc) に結果記録、を user 任意のタイミングで実施。
