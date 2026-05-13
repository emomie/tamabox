# tamabox — Changelog

Milestone-level release notes. Per-phase detail lives in `.planning/phases/0X-*/0X-SUMMARY.md` and per-milestone archive in `.planning/milestones/`.

---

## v2.0.0 — 2026-05-13 — Calm Gacha UI / 4-tab 構造

- **DS×6**: Design tokens (`tokens.css` + `colors_and_type.css`) + Noto Sans JP + JetBrains Mono + base components (`.tb-btn` 5 variants / `.tb-card` family / `.tb-letter` / `.tb-chip` / `.tb-input` / `.tb-tabbar` / `.tb-appbar` / `.tb-icon-btn` + 13 SVG icons)
- **UI×8**: 8 v1 screens migrated to hi-fi (Home / Send / SendDone / Settings / Report / Account-Delete / Block list / AvatarHandleChip)
- **NAV×6**: 4-tab dashboard (受信 / 発見 / 通知 / 設定) with SSR-pure routing; unread dot; Discover + Notifications empty-state stubs (backend deferred to v3)
- **MOTION×3**: Universal `.tb-btn:active` scale(0.985) 80ms press feedback + `.is-opening` reveal fade-in 400ms + RevealHit sender card (HIT) + Reveal MISS variants; all motion respects `prefers-reduced-motion: reduce`
- **EDGE×5**: Send error 4 variants (SendNotFound / SendInboxClosed / SendOverflow / SendFailed) + Block confirm modal as native `<dialog>` bottom sheet

**Scope**: 28 requirements / 4 phases / 29 plans / 203 tests (+8 from v1 baseline)
**Commits**: `ed54894..960fc11` (104 commits)
**Audit**: PASS — `.planning/v2-MILESTONE-AUDIT.md`
**Production deploys**: Phase 5 `e420a78`, Phase 6 `1777c2a` (both smoke verified 2026-05-13). Phase 7+8 (HEAD `960fc11`) awaits final push.

### Notable Locked Decisions

- Typography 8-size scale + 4-weight set (ad-hoc additions prohibited)
- Half-pixel font-sizes from hi-fi rounded to scale where possible; pinned exceptions documented per phase
- Spacing 4-grid + 3 exceptions (6/14/18px) + 3px sub-grid micro-offset
- Single-use literal hex registry: `#FBFCFD`, `#F0DCA8`, `#FFFBEF`, `#EFD5D2`
- 2 single-use rgba literals locked
- `processSend` catch switched from Flash+redirect to render `send_failed.php`

### Backend Immutability

Zero migrations, zero `src/Model/` changes, zero OAuth/moderation logic changes. Controller delta limited to 2 new tab endpoints + Settings GET branch + processSend catch render switch + private `computeUnreadCount` helper.

### Tech Debt → v3

Enumerated in `.planning/REQUIREMENTS.md` v3 Requirements (Deferred) section (DISC-01/NOTIF-01 backends, ONB/STATIC/SHARE, MOTION-X1/X2, DESKTOP-01..03, ASSET-01, A11Y-01/02, TECH-01..05).

---

## v1.0.0 — 2026-05-13 — MVP

「確率で名前がバレる」匿名メッセージ箱 v1 — Bluesky OAuth + SSR 抽選開封 + モデレーションレーンを `tamabox.emomie.com` で稼働。

- **AUTH×9**: Bluesky OAuth (AT Protocol, ES256 confidential client, PAR + DPoP + PKCE) + AES-256-GCM トークン暗号化 + `/oauth/jwks.json` + `/oauth/client-metadata.json` + OAuthProviderInterface 抽象化 + 1 user = 1 SNS account DB UNIQUE
- **INBOX×6**: 自動 slug 派生 (Bluesky handle 由来) + SSR 確率 0〜100% 設定 + Dashboard 受信一覧 + per-inbox ブロック + handle 改名追従 (slug rotation + grace 1 世代)
- **MSG×8**: 同意 UI 必須の送信フォーム + `is_ssr` + `ssr_seed = sha256(server_secret + message_id + created_at)` 送信時 bake-in + sender snapshot 永続化 + SSR 開封演出 (未開封/開封済み区別) + 論理削除
- **MOD×4**: 4-カテゴリ通報 (`harassment` / `spam` / `illegal` / `other`) + UNIQUE 重複防止 + 退会後 sender snapshot 保持 + per-inbox ブロックスコープ (グローバル BAN なし)
- **INFRA×7**: Lolipop 共有鯖 + `tamabox.emomie.com` + `.env` ローダ + httpoxy 対策 + 6 テーブル Phinx migration + `debug=false` 固定 + DebugKit 物理除去 + `allowFallbackClass(false)` Table 明示作成

**Scope**: 34 requirements / 4 phases / 15 plans / 24 tasks / 195 tests
**Commits**: `3bea20b..edc6803` (109 commits)
**Git tag**: `v1`
**Production**: `tamabox.emomie.com` (live, smoke verified)
**Quality bar**: 0 failures / phpstan level 8 [OK] / phpcs 69/69 clean / 29/29 STRIDE threats closed

Full archive: `.planning/milestones/v1-ROADMAP.md`, `.planning/milestones/v1-REQUIREMENTS.md`.

---

_Format: see [Keep a Changelog](https://keepachangelog.com/) for conventions. Each milestone gets one section here; per-phase detail lives in phase summaries._
