# tamabox — SSR箱

## What This Is

送信者のSNSハンドル・アイコン・プロフリンクが確率で露出する、匿名メッセージ箱の Web アプリ。Bluesky OAuth で受け手認証、送り手も SNS OAuth 経由でないと送信できない。SSR(露出)確率は受け手がカスタマイズ可能で 0% に設定すれば普通の匿名箱としても機能する。

**v1 (2026-05-13)** で MVP を `tamabox.emomie.com` に live deploy。Bluesky OAuth + SSR 開封演出 + 通報・ブロック・論理削除・退会レーン完備、195 tests / 0 failures、29/29 STRIDE threats closed。

## Core Value

**「確率で名前がバレる」仕組みが、悪意送信者の自己抑止になる**(V1 仮説)。これが偽ならプロダクト全体が成立しない。他の価値(好意送信者の祝福体験、クリエイターの心理負荷低減)はすべてこの一点が成立することに依存する。**v1 launch をもってこの仮説の実地検証が始まる。**

## Current State

**Shipped**: v1 MVP (2026-05-13)
**Live**: [https://tamabox.emomie.com](https://tamabox.emomie.com)
**Stack**: CakePHP 4.5 / PHP 8.3 / MySQL 8.0 on Lolipop shared hosting
**Stats**: 4 phases, 15 plans, 24 tasks, 109 commits, 22 days
**Coverage**: 34/34 v1 requirements shipped

## Requirements

### Validated

<!-- Shipped and confirmed scope-validated (実地検証はこれから). -->

**認証 / アイデンティティ**
- ✓ Bluesky OAuth サインアップ / ログイン (AT Protocol, ES256 confidential client, PAR+DPoP+PKCE) — v1
- ✓ 送り手も Bluesky OAuth 経由必須(完全匿名送信不可) — v1
- ✓ 1 ユーザー = 1 SNS アカウント (1:1) DB 制約担保 — v1
- ✓ SNS handle / avatar / profile_url のログイン時最新同期 — v1
- ✓ マルチプロバイダ抽象化 (`OAuthProviderInterface`) — v1
- ✓ OAuth トークン AES-GCM 暗号化 (`*_enc` 列) — v1
- ✓ ES256 鍵ペア `config/keys/` 配置 + `jwks.json` / `client-metadata.json` 公開 — v1

**受信箱 (inbox)**
- ✓ SNS handle 由来 slug 自動派生 + 改名時の自動追従 (1 世代 grace) — v1
- ✓ SSR 確率 0〜100% カスタマイズ(デフォルト 10%) — v1
- ✓ 特定送信者ブロック + 送信時エラー表示 (per-inbox スコープ) — v1

**メッセージ / SSR メカニクス**
- ✓ 送信フォーム本文送信 — v1
- ✓ SSR 判定送信時確定 + DB 刻印 (`is_ssr`, `ssr_seed`) — v1
- ✓ 送信者 handle/avatar/profile_url の snapshot 焼き込み — v1
- ✓ 開封時 SSR 露出ガチャ演出 — v1
- ✓ 開封済識別 — v1

**通報 / モデレーション**
- ✓ 4 カテゴリ通報 (harassment / spam / illegal / other) — v1
- ✓ 事後レビュー方式 (AI 事前検閲なし) — v1
- ✓ メッセージ論理削除 (`deleted_at`) — v1
- ✓ 退会時 sender snapshot 保持 (MOD-03) — v1

**インフラ / 運用**
- ✓ 本番 `tamabox.emomie.com` 稼働 (Lolipop git deploy) — v1
- ✓ DEBUG=false 固定 + DebugKit 二重防衛 (composer --no-dev + Configure::read('debug') guard) — v1
- ✓ webroot 外 config + `.htaccess` rewrite — v1
- ✓ `.env` ベース秘匿値注入 — v1

### Active

<!-- Next milestone (v2) scope — see /gsd-new-milestone to refine. -->

(空 — v2 milestone を `/gsd-new-milestone` で開始すると整理される)

### Out of Scope

<!-- Explicit boundaries. Always include reasoning. -->

- X (Twitter) OAuth — v1 では Bluesky のみ。v2+ で抽象化済みの provider interface を埋める予定
- Google / メールアドレス認証 — SNS 性を重視するため意図的に不採用
- AI 悪意度判定 / NG ワードフィルター — 事後通報方式(A2)を採用、F1/E3 仮説検証後に再考
- 送信頻度レート制限 — MVP 不採用(post-MVP 拡張対象)
- メッセージ本文の暗号化 — 共有サーバー前提、通報レビュー運営要件とのバランスで不採用
- プレミアム課金 / カスタム演出 — Vi1 仮説検証後に判断
- ネイティブアプリ(iOS / Android) — Web only で完結
- DB セッションストレージ — PHP デフォルト(ファイル)で出発
- SSR 殿堂ページ — E2 仮説(二次的晒し行為化リスク)のため MVP 不採用
- 完全匿名送信モード — V1 仮説の根幹を崩すため不採用継続

## Context

**v1 launch 後の現状** (2026-05-13):
- `tamabox.emomie.com` で OAuth ログイン + 送信 + 開封 + 通報 + ブロック + 論理削除 + 退会の全レーン稼働
- post-launch hotfix 2 件(callback aud + KeyManager filename)を経て安定化
- 12 項目の manual smoke walkthrough を本番で完走(REV-01 retired-user 404、MOD-03 sender snapshot 保持等を含む)
- Security audit で 29 STRIDE threats を 20 mitigate + 9 accept で全件 close
- 課題: V1 仮説(悪意送信者の自己抑止効果)の実ユーザー観測はこれから

**Tech stack 確定状態**:
- CakePHP 4.5, PHP 8.3.6 (Lolipop runtime, composer 8.0+ constraint), MySQL 8.0.45
- UUID (CHAR(36)) PK, AES-GCM トークン暗号化, ES256 OAuth signing
- 195 tests / 546 assertions, phpstan level 8 clean, phpcs 69/69 clean

**運用参照**: `altotoo.emomie.com`(AT Protocol OAuth 稼働中)の知見を踏襲して実装した結果、本番で大きな破綻なく動いた(Phase 2 verifier human-needed 項目も Phase 4 deploy で実地クリア)。

## Constraints

- **Tech stack**: PHP 8.0+ / CakePHP 4.5 / MySQL 8.0 / UUID PK — Lolipop 共有鯖で動作実績ありの固定構成
- **Hosting**: ロリポップ共有レンタルサーバー — `SUPER` 権限なし、ストアドプロシージャ最小限、webroot 外 config 必須、CHECK 制約は raw SQL で migration 記述
- **Security**: OAuth トークン AES-GCM 暗号化済、ES256 private key は `config/keys/` (gitignore + SSH-only 配置)、DebugKit は require-dev で物理除去 + Configure::read('debug') guard
- **Legal / Ethics**: プロバイダ責任制限法 / 開示請求対応運用想定、送信前同意 UI(E1 仮説対応)で「確率名前バレ」明示済
- **Deployment**: Lolipop git deploy (bare repo + post-receive hook + composer install --no-dev)、migrations は SSH 手動実行(deploy hook 内に組み込まない)

## Key Decisions

| Decision | Rationale | Outcome |
|----------|-----------|---------|
| Web only(ネイティブアプリ非対応) | LP + 送信フォーム + 受信ダッシュボードを単一 URL で完結 | ✓ v1 達成 — Web UI で全機能成立 |
| Bluesky OAuth 先行、X は v2+ | Bluesky API 無料・制約少ない、マルチプロバイダ抽象化は最初から組み込み | ✓ v1 達成 — `OAuthProviderInterface` 抽象 + Bluesky concrete 実装で着地 |
| slug は SNS handle 由来で自動付与・改名追従 | 受け手と送り手が共通の SNS identity 表示を持つコア体験の一貫性 | ✓ v1 達成 — 1 世代 grace で 301 redirect 動作 |
| SNS OAuth 送信必須(完全匿名送信不可) | SSR 露出時に identity が紐づくことが V1 仮説の根幹 | ✓ v1 達成 — PKCE OAuth gate 機能、検証は実ユーザー観測待ち |
| SSR 判定は送信時確定、開封時は「開示」のみ | F2 仮説(乱数に不正なし)の監査性 | ✓ v1 達成 — `ssr_seed = sha256(server_secret + message_id + created_at)` |
| メッセージ本文は暗号化しない(トークンのみ暗号化) | 共有サーバー前提、通報レビュー運営要件とのバランス | ✓ v1 達成 |
| AI 事前検閲は採用しない(事後通報のみ / A2) | 言論抑圧リスク(E3)と MVP コストを回避 | ✓ v1 達成 — 通報フロー稼働、AI フィルタ呼び出しゼロ確認 |
| UUID (CHAR(36)) PK 採用 | 共有鯖で安全、`Text::uuid()` で CakePHP 統合容易 | ✓ v1 達成 |
| 退会時も送信者 snapshot を保持 (MOD-03) | V1 仮説補強(悪意送信者の逃げ得防止) | ✓ v1 達成 — `AccountController::delete` は `users.deleted_at` のみ UPDATE、messages 行は不変 |
| `DatabaseException \| PDOException` union catch + SQLSTATE 23000 match | CakePHP 5 / MySQL driver で raw PDOException が漏れるケース対応 (Phase 4 deviation) | ✓ v1 — `ReportsController::create` UNIQUE collision dedupe を race-safe に |
| REV-01 retired-user slug 404 | 退会後の slug 列挙を非存在 slug と区別不可にする (timing oracle 防止) | ✓ v1 達成 — `InboxesTable::findBySlugOrPrevious` 両 branch に `Users.deleted_at IS NULL` フィルタ |
| Lolipop git deploy で migrations は hook に含めず手動 SSH (D-34) | hook 内 migration 失敗で deploy 全体が partial state にならないように分離 | ✓ v1 達成 — runbook で手順分離 |

## Evolution

This document evolves at phase transitions and milestone boundaries.

**After each phase transition** (via `/gsd-transition`):
1. Requirements invalidated? → Move to Out of Scope with reason
2. Requirements validated? → Move to Validated with phase reference
3. New requirements emerged? → Add to Active
4. Decisions to log? → Add to Key Decisions
5. "What This Is" still accurate? → Update if drifted

**After each milestone** (via `/gsd-complete-milestone`):
1. Full review of all sections
2. Core Value check — still the right priority?
3. Audit Out of Scope — reasons still valid?
4. Update Context with current state

---
*Last updated: 2026-05-13 after v1 MVP milestone shipped*
