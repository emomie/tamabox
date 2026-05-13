# Milestones

## v1 MVP (Shipped: 2026-05-13)

**Phases:** 4 phases, 15 plans, 24 tasks
**Timeline:** 2026-04-22 → 2026-05-13 (22 days, 109 commits)
**Range:** `3bea20b` → `edc6803`
**Diff:** 176 files changed, +52,862 / −288
**Coverage:** 34/34 v1 requirements shipped (AUTH×9, INBOX×6, MSG×8, MOD×4, INFRA×7)
**Live:** [https://tamabox.emomie.com](https://tamabox.emomie.com)

### What Shipped

「確率で名前がバレる」匿名メッセージ箱を Lolipop 共有鯖上で稼働。Bluesky OAuth (ES256 confidential client / PAR+DPoP+PKCE) で受け手認証、SSR (Super Rare) 抽選で送信者の identity を確率露出する開封体験、受信箱ごとに調整可能な露出確率、通報・ブロック・論理削除・退会レーンを揃え、MVP の Core Value 仮説検証に必要な最小セットを本番運用可能にした。

### Key Accomplishments

1. **CakePHP 4.5 + Lolipop PHP 8.0+ foundation** — `.env` ローダ + httpoxy 対策 + 6 テーブル Phinx migration スイート (Phase 1)
2. **Bluesky OAuth ES256 confidential client** — PAR+PKCE+DPoP+private_key_jwt の本物ハンドシェイク、AES-GCM トークン暗号化、`/oauth/jwks.json` + `/oauth/client-metadata.json` 動的生成 (Phase 2)
3. **Inbox + Send + SSR 開封演出** — Bluesky handle→slug 自動派生 + 1 世代 grace、PKCE OAuth ゲート付き送信フォーム、送信時 SSR シード bake-in + 開封時確定演出、sender snapshot 永続化 (Phase 3)
4. **モデレーション運用レーン** — ブロック (per-inbox スコープ)、4 カテゴリ通報 + UNIQUE 重複防止、論理削除、退会フロー + 退会後 slug 404 (REV-01)、MOD-03 sender snapshot 保持 (Phase 4)
5. **Production launch on `tamabox.emomie.com`** — `LAUNCH-RUNBOOK.md` (Lolipop git deploy + quirks + rollback) + `MANUAL-SMOKE-CHECKLIST.md` 12 項目、`composer install --no-dev` で DebugKit 物理除去 + `Configure::read('debug')` ガードで二重防衛 (Phase 4)
6. **Quality bar** — 195 tests / 546 assertions / 0 failures / phpstan level 8 [OK] / phpcs 69/69 clean / 29/29 STRIDE threats closed (20 mitigate + 9 accept)

### Verification & Audit

- Phase 2: VERIFIED 2026-04-24 (code-level 7/7, human-needed deferred to Phase 4 deploy)
- Phase 3: VERIFIED 2026-04-26 (code-level 7/7, human-needed deferred to Phase 4 deploy)
- Phase 4: VERIFIED 2026-05-13 (code-level 9/9 + manual smoke 12/12 on live site)
- Security: 29/29 threats closed across all phases (see `04-SECURITY.md`)

### Deferred / Known Items

- Phase 1 verifier never ran formally (skipped to Phase 2 with implicit code-level satisfaction via downstream phase tests)
- Phase 2 sticky #5 (REV-03) was resolved-as-not-needed-for-MVP during Phase 4
- Per-IP/per-user global rate limiting deferred post-MVP (Accepted Risks Log)
- No `/gsd-audit-milestone` run prior to close — requirements coverage hand-verified via REQUIREMENTS.md traceability (34/34 `[x]`) + Phase 4 manual smoke

### Reference

- Roadmap archive: `.planning/milestones/v1-ROADMAP.md`
- Requirements archive: `.planning/milestones/v1-REQUIREMENTS.md`
- Git tag: `v1`

---
