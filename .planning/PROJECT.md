# tamabox — SSR箱

## What This Is

送信者のSNSハンドル・アイコン・プロフリンクが確率で露出する、匿名メッセージ箱の Web アプリ。X や Bluesky 上のクリエイター(二次創作者・YouTuber 等)が自分の「受信箱」をカスタマイズして運用する。SSR(露出)確率は受け手がカスタマイズ可能で、0% に設定すれば普通の匿名箱としても機能する。

## Core Value

**「確率で名前がバレる」仕組みが、悪意送信者の自己抑止になる**(ASSUMPTIONS V1 = コア仮説)。これが偽ならプロダクト全体が成立しない。他の価値(好意送信者の祝福体験、クリエイターの心理負荷低減)はすべてこの一点が成立することに依存する。

## Requirements

### Validated

<!-- Shipped and confirmed valuable. -->

(None yet — ship to validate)

_注: リポジトリは CakePHP 4.5 skeleton のみで domain code はゼロ。設計は `emomie/ssr-box-discovery` にあるが実装コードとしてはまだ何も validate されていない。_

### Active

<!-- Current scope. Building toward these. Ordered by rough priority. -->

**認証 / アイデンティティ**
- [ ] 受け手が Bluesky OAuth (AT Protocol, ES256 confidential client) でサインアップ / ログインできる
- [ ] 送り手も Bluesky OAuth を経由しなければメッセージ送信できない(完全匿名送信は不可)
- [ ] 1 ユーザー = 1 SNS アカウント (1:1) を DB 制約で担保
- [ ] SNS handle / avatar / profile_url はログイン時に最新同期する
- [ ] マルチプロバイダ抽象化を最初から組み込む(後で X を追加できるように)

**受信箱 (inbox)**
- [ ] 受け手は自分の SNS handle から自動導出された slug で inbox URL を持つ。SNS 改名時は slug が自動追従する(tamabox 単独で slug を変更する機能はない)
- [ ] 受け手は自分の inbox の SSR 確率を 0〜100% でカスタマイズできる(デフォルト 10%)
- [ ] 受け手は特定送信者をブロックできる / ブロックされた送信者は送信時にエラー表示

**メッセージ / SSR メカニクス**
- [ ] 送信フォームで送り手は本文を入れて送信できる
- [ ] SSR 判定は**送信時に確定**させ DB に刻む(`is_ssr`, `ssr_seed = sha256(server_secret + message_id + created_at)`)
- [ ] 送信者の handle / avatar / profile_url は送信時点の値をメッセージに焼き付ける(SNS 側で改名されても当時の記録が残る)
- [ ] 受け手が「開封」すると SSR 露出が明らかになる(ガチャ演出)
- [ ] 開封済みメッセージは一覧上で識別可能

**通報 / モデレーション**
- [ ] 受け手はメッセージを 4 カテゴリ(harassment / spam / illegal / other)で通報できる
- [ ] 通報は事後レビュー方式(AI 事前検閲は行わない)
- [ ] 受け手はメッセージを論理削除できる(`deleted_at`)
- [ ] 退会時も過去メッセージの送信者 snapshot は残す(逃げ得防止 / V1 仮説補強)

**インフラ / 運用**
- [ ] 本番ドメイン `tamabox.emomie.com` で動作(ロリポップ共有レンタルサーバー)
- [ ] OAuth トークンは AES-GCM 等でアプリ側暗号化して DB 格納(`*_enc` 列)
- [ ] ES256 鍵ペアは `config/keys/`(web 公開外)に配置
- [ ] `.env` ベースで秘匿値を注入(現在スケルトンではローダがコメントアウトされている — 有効化必須)

### Out of Scope

<!-- Explicit boundaries. Always include reasoning. -->

- X (Twitter) OAuth — Phase 2 で追加予定。Bluesky 先行(API 無料・制約少なく早期 launch 可能)
- Google / メールアドレス認証 — SNS 性を重視するため意図的に不採用
- AI 悪意度判定 / NG ワードフィルター — 事後通報方式(A2)を採用したため MVP 範囲外(F1 / E3 仮説検証後に再考)
- 送信頻度レート制限 — MVP 不採用(将来拡張)
- メッセージ本文の暗号化 — 共有サーバー前提、通報レビュー運営要件とのバランスで不採用(トークンは暗号化する)
- プレミアム課金 / カスタム演出 — Vi1 仮説検証後に判断。MVP は全機能無料
- ネイティブアプリ(iOS / Android) — Web only で完結(LP + 送信フォーム + 受信ダッシュボードを単一 URL で)
- DB セッションストレージ — PHP デフォルト(ファイル)で出発。共有鯖制約を踏まえて MVP 範囲外
- SSR 殿堂ページ(過去の露出まとめ公開) — E2 仮説(二次的晒し行為化リスク)のため MVP 不採用

## Context

**ディスカバリーは完了済み**: 別リポジトリ `emomie/ssr-box-discovery` に以下の設計ドキュメントがある。
- `ASSUMPTIONS.md` — 8 カテゴリ仮説棚卸し(V/U/Vi/F/E/G/S/T)、最優先検証 5 件
- `DESIGN.md` — Q1–Q5 基本設計決定(プラットフォーム形態・受け手/送り手認証・SSR 仕様・モデレーション方針)
- `DB-SCHEMA.md` v0.2 — MySQL 8.0 向け初期スキーマ(users / inboxes / messages / user_identities / blocks / reports)
- `AUTH-FLOW.md` v0.1 — Bluesky (AT Protocol) OAuth 認証フロー(PAR / DPoP / PKCE 全必須、ES256 confidential client)

**tamabox 現状** (`.planning/codebase/` に詳細):
- CakePHP 4.5 skeleton を `composer create-project` した直後の状態
- `vendor/` 未 install、`src/` はデフォルト scaffolding(PagesController / ErrorController / AppController / AppView / AjaxView)のみ
- `Model`, `Component`, `Helper`, `Cell` ディレクトリは `.gitkeep` のみ
- README で言及される `src/Service/` は未存在
- PSR-12 / PSR-4(`App\` → `src/`)、`snake_case 複数形` 命名規約(CakePHP 標準)
- Middleware pipeline: ErrorHandler → Asset → Routing → BodyParser → CSRF
- `TableLocator::allowFallbackClass(false)` 設定済 → 明示 Table クラス強制
- CI なし / composer.lock なし / migrations なし

**運用参照**: `altotoo.emomie.com` が既に AT Protocol OAuth で稼働中。tamabox の OAuth 実装は altotoo の運用知見を踏襲する。

## Constraints

- **Tech stack**: PHP / CakePHP 4.5, MySQL 8.0, UUID (CHAR(36)) PK — 使い慣れた構成 & Lolipop で動作実績あり
- **Hosting**: ロリポップ共有レンタルサーバー — `SUPER` 権限なし前提、ストアドプロシージャ最小限、トリガ登録不可、webroot 外からの config 読み込みが必須
- **PHP version**: 本番 Lolipop は 8.0+ 想定(composer.json は `^7.4` になっているため **整合修正必要**)
- **Security**: OAuth トークン暗号化必須(AES-GCM `*_enc` 列)、ES256 private key は `config/keys/`(gitignore 済)、DebugKit は `debug=true` 時のみ(本番 false 固定)
- **Legal / Ethics**: プロバイダ責任制限法 / 開示請求対応に耐えうる運用(Vi2 未検証だが前提)、送信前同意 UI で「確率名前バレ」を明示(E1)
- **Deployment**: ロリポップの Git deploy(main push トリガー)を使用予定
- **Encoding**: `utf8mb4_0900_ai_ci`(絵文字対応)

## Key Decisions

| Decision | Rationale | Outcome |
|----------|-----------|---------|
| Web only(ネイティブアプリ非対応) | LP + 送信フォーム + 受信ダッシュボードを単一 URL で完結、MVP 工数削減 | — Pending |
| Bluesky OAuth 先行、X は Phase 2 | Bluesky API 無料・制約少ない、マルチプロバイダ抽象化は最初から組み込む | — Pending |
| slug は SNS handle 由来で自動付与・改名追従、tamabox 単独で変更不可 | 受け手と送り手が共通の SNS identity 表示を持つコア体験の一貫性。送り手は SSR 抽選で自分の SNS が暴露されるスリルを楽しむ。slug を独立に持たせると identity の単一性が崩れる(Phase 3 discuss-phase 2026-04-26) | — Pending |
| SNS OAuth 送信必須(完全匿名送信不可) | SSR 露出時に identity が紐づくことが V1 仮説の根幹 | — Pending |
| SSR 判定は送信時確定、開封時は「開示」のみ | F2 仮説(乱数に不正なし)の監査性を担保 | — Pending |
| メッセージ本文は暗号化しない(トークンのみ暗号化) | 共有サーバー前提、通報レビュー運営要件とのバランス | — Pending |
| AI 事前検閲は採用しない(事後通報のみ / A2) | 言論抑圧リスク(E3)と MVP コストを回避 | — Pending |
| UUID (CHAR(36)) PK 採用 | 共有鯖で安全、スケーラビリティ、`Text::uuid()` で CakePHP 統合容易 | — Pending |
| 退会時も送信者 snapshot を保持 | V1 仮説補強(悪意送信者の逃げ得防止) | — Pending |

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
*Last updated: 2026-04-22 after initialization*
