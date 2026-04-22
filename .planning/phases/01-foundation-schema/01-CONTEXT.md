# Phase 1: Foundation & Schema — Context

**Gathered:** 2026-04-22
**Status:** Ready for planning

<domain>
## Phase Boundary

ロリポップ共有鯖で安全に CakePHP 4.5 を運用するための土台衛生化と、v1 で必要な全テーブル(users / user_identities / inboxes / messages / blocks / reports)を `bin/cake migrations migrate` で投入できる状態にする。スキーマの DDL・FK cascade は discovery の DB-SCHEMA.md v0.2 で確定済のため、Phase 1 は「その確定仕様を CakePHP migrations + 明示 Table クラスに落とし込む」実装作業。

**Requirements mapped to this phase:** INFRA-02, INFRA-03, INFRA-04, INFRA-05, INFRA-07 (5 件)

**Not in this phase:** OAuth 実装(Phase 2)、OAuth トークン暗号化の鍵管理(Phase 2)、`tamabox.emomie.com` 本番デプロイと `debug=false` 固定(Phase 4)、DebugKit 無効化(Phase 4)。
</domain>

<decisions>
## Implementation Decisions

### Infrastructure Hygiene
- **D-01**: `composer.json` の PHP 要件を `^7.4` から `^8.0` に変更する。既存 `cakephp/cakephp` 系 deps は 4.5 系互換のまま、composer update でロックファイルを更新する。
- **D-02**: `config/bootstrap.php` の `.env` loader のコメントアウトを解除し、`config/.env` から `SECURITY_SALT` / `DATABASE_URL` / `SERVER_SECRET` 等の秘匿値を注入する。`config/.env.example` を git-tracked の雛形として用意し、`config/.env` 自体は `.gitignore` で除外。
- **D-03**: `.htaccess` の `RequestHeader unset Proxy` (httpoxy ブロック) を有効化する(現在コメントアウト)。
- **D-04**: `composer install` / `composer update` を実行して `composer.lock` を生成し、コミットする(MVP は再現性優先、Lolipop 側でも `composer install --no-dev` で同じ環境が作れるように)。

### SSR Secret Management
- **D-05**: `server_secret` は `.env` に 1 つの固定文字列で保管する。MVP 期はローテーションなし。
  - 鍵は 32 バイト以上のランダム文字列(`openssl rand -hex 32` 等)で生成する。
  - `Configure::read('Security.serverSecret')` 経由で読み、`src/Service/` 配下(Phase 2-3 で導入)が使用する。
  - 将来ローテーションが必要になった場合は `messages` テーブルに `ssr_secret_version TINYINT NOT NULL DEFAULT 1` を追加する migration を別 phase で打つ(Phase 1 ではカラム追加しない)。

### Migration Granularity & Tooling
- **D-06**: migration は **1 テーブル = 1 migration file** で作成する。ファイル数 6。FK 依存順で番号を振る(users → user_identities → inboxes → messages → blocks → reports)。
- **D-07**: `bin/cake bake migration Create<Table>` で Phinx 雛形を生成し、DB-SCHEMA.md v0.2 の DDL(CHECK 制約 / composite index / FK cascade 方向)に合わせて手動調整する。bake 標準出力の `id` 列(BIGINT auto_increment)は削除して UUID CHAR(36) PK に置き換える。
- **D-08**: Table クラスは `bin/cake bake model <Table>` で自動生成する。bake 出力の validator / association 定義はベースとして受け入れ、tamabox 固有の振る舞い(UUID 生成 behavior、snapshot 保持、SSR seed 生成 hook 等)は必要になった phase で追加する(Phase 1 では最小限)。
- **D-09**: CakePHP Timestamp Behavior を使い、`created_at` / `updated_at` にマッピングする(`fields` オプションで `Model.beforeSave` 時に `created_at` と `updated_at` を自動設定、`modified => updated_at` 指定)。

### Schema Alignment with Discovery
- **D-10**: DDL は DB-SCHEMA.md v0.2 を single source of truth とする。migration コード内で以下を厳守:
  - すべての timestamp 系は `DATETIME(6)` (マイクロ秒精度)
  - `utf8mb4_0900_ai_ci` コレーション(MySQL 8.0 デフォルト)
  - 各テーブルの CHECK 制約(`display_name` 長、`ssr_probability` レンジ、`slug` フォーマット regex、`body_length` 等)を Phinx の `->addConstraint()` または生 SQL で反映
  - FK cascade 方向は DB-SCHEMA.md 通り(`messages.sender_user_id` は `ON DELETE RESTRICT` で逃げ得防止、`reports.reporter_user_id` は `ON DELETE SET NULL`、他は `CASCADE`)
- **D-11**: `is_primary` カラム(`user_identities`)は MVP で `DEFAULT TRUE` のまま 1:1 UNIQUE 制約と共存させる(将来 account linking 対応時の布石)。
- **D-12**: インデックス方針: MySQL 8.0 は partial index なしのため、`WHERE deleted_at IS NULL` 運用はクエリ側の責務、インデックスは通常 composite(`idx_messages_inbox_deleted` 等) で対応する。

### Dev Workflow (this phase only)
- **D-13**: `composer.json` に scripts を追加する: `composer phpcs`, `composer phpstan`, `composer test`(既存 `phpunit.xml.dist` を使う)。ローカル開発で lint / static analysis / test をワンコマンドで回せる状態にする。
- **D-14**: GitHub Actions の CI 設定は **Phase 4 (Production Launch) まで保留**する。Phase 1 で scripts だけ整備 → Phase 4 で本番運用と一緒に CI を乗せる方が、secrets / deploy key / Lolipop SSH 設定と同じタイミングで扱えて整理しやすい。
- **D-15**: `TableLocator::allowFallbackClass(false)` は維持(`src/Application.php` 既設定)。migrations 適用直後に `bin/cake bake model` で 6 テーブル分の Table クラスを `src/Model/Table/` に全件生成しておく(fallback 解決で実行時 fatal にならないように)。

### Claude's Discretion
- 具体的な migration ファイル名(例: `20260422120000_CreateUsers.php`)のタイムスタンプ順
- 生成 migration 内の column order や `addIndex` の書き順(DDL と機能一致なら OK)
- `composer.json` `scripts` セクションの具体名(標準的なものに倣う)
- `config/.env.example` の具体的なキー一覧(D-05 の `SERVER_SECRET` + DB 接続系 + CakePHP `SECURITY_SALT` + オプション `DEBUG` 程度)
- テーブルクラスの namespace 配置(CakePHP 標準 `App\Model\Table\<Name>Table` に従う)
</decisions>

<canonical_refs>
## Canonical References

**Downstream agents MUST read these before planning or implementing.**

### Domain / Design (external discovery repo, fetch via gh CLI)
- `emomie/ssr-box-discovery:DB-SCHEMA.md` — Phase 1 schema の **single source of truth**。DDL, CHECK 制約, FK cascade 方向, index 方針, v0.2 確定済み判断が全て。
- `emomie/ssr-box-discovery:DESIGN.md` — Q1-Q5 の基本設計決定(プラットフォーム形態・認証方針・SSR 仕様・モデレーション方針)。Phase 1 スコープ判定時に参照。
- `emomie/ssr-box-discovery:ASSUMPTIONS.md` — 8 カテゴリ仮説棚卸し。F2(SSR 乱数監査性)が Phase 1 の `ssr_seed` 列設計の根拠。

### Project-Level
- `.planning/PROJECT.md` — tamabox のコア価値(V1 仮説)と Out of Scope リスト。
- `.planning/REQUIREMENTS.md` — 34 REQ の正規化、このフェーズは INFRA-02/03/04/05/07 を担当。
- `.planning/ROADMAP.md` — Phase 1 の success criteria(5 件の観測可能条件)。

### Codebase State
- `.planning/codebase/STACK.md` — CakePHP 4.5 / PHP >=7.4(要変更) / MySQL 8.0 / composer 構成。
- `.planning/codebase/ARCHITECTURE.md` — Middleware pipeline, entry points, `Application.php` の既設定(TableLocator, CSRF, BodyParser)。
- `.planning/codebase/CONCERNS.md` — Phase 1 で解消すべき pre-build リスク(`.env` loader、httpoxy、PHP 7.4/8.0 整合、DebugKit、`composer.lock` 不在)。特に **retire the homepage `Pages::display` + `$builder->fallbacks()` が本番漏洩のリスク** という指摘は Phase 4 で潰すが、Phase 1 では変更しない(migration が先)。
- `.planning/codebase/CONVENTIONS.md` — PSR-12 / PSR-4 `App\` → `src/`、snake_case 複数形テーブル命名。
- `.planning/codebase/STRUCTURE.md` — `src/Model/Table/` の空 `.gitkeep` 状態確認用。
</canonical_refs>

<code_context>
## Existing Code Insights

### Reusable Assets
- **`bin/cake` CLI** — 既にインストール済(`bin/cake.php` → `Cake\Console\CommandRunner`)。`bake` / `migrations` プラグインは `src/Application.php::bootstrapCli()` で条件付き ロード済み。Phase 1 で `bin/cake bake migration` / `bin/cake bake model` / `bin/cake migrations migrate` をそのまま使える。
- **`config/app.php`** — CakePHP 標準の DB 接続ロジック(`Datasources.default.url` が `DATABASE_URL` env を読む)。`.env` loader を有効化すれば、migrations もこの接続を共有する。
- **`src/Application.php`** — middleware pipeline と `allowFallbackClass(false)` が既設定。Phase 1 は触らない(Phase 2 以降で OAuth middleware を追加する)。

### Established Patterns
- **PSR-4 autoload** (`composer.json` `autoload` セクション) — `App\` → `src/`。Table クラスを `src/Model/Table/<Name>Table.php` に置けば自動で読まれる。
- **Phinx migrations** (via `cakephp/migrations` プラグイン) — `config/Migrations/` 配下に timestamped PHP ファイル。`bake migration` で雛形生成、手動編集で DDL を合わせる。
- **CakePHP Timestamp Behavior** — created_at/updated_at を `fields` オプションで明示マッピングする規約。DB-SCHEMA は `created_at`/`updated_at` を採用している。

### Integration Points
- **`config/bootstrap.php`** — `.env` loader の enable/disable スイッチがここ。Phase 1 の D-02 で変更。
- **`.htaccess` (root と `webroot/`)** — httpoxy 対策の `RequestHeader unset Proxy` コメント解除が root 側で 1 箇所(D-03)。
- **`composer.json`** — PHP 要件と scripts セクションの 2 箇所を同時に触る(D-01 / D-13)。
- **`config/Migrations/`** — migration 格納先。現状空ディレクトリ(`.gitkeep` のみ)。
- **`src/Model/Table/` / `src/Model/Entity/`** — 現状 `.gitkeep` のみ。Phase 1 終了時に 6 テーブル分の Table + Entity クラスが揃う。
</code_context>

<specifics>
## Specific Ideas

- SSR seed の式 `sha256(server_secret + message_id + created_at)` は **Phase 1 では DDL に `ssr_seed VARCHAR(64)` 列を確保するだけ**で、計算実装は Phase 3 (MSG-03) で行う。Phase 1 のスコープは「列を用意する」まで。
- `users.deleted_at` は DDL で定義するが、実際のソフトデリート判定ロジックは Phase 4 (MOD-03) の責務。Phase 1 は列を用意して運用規約(クエリで `WHERE deleted_at IS NULL`)をドキュメント化するところまで。
- `messages.deleted_reason` の ENUM 値(`'user'` / `'report_actioned'` / `'account_removed'`)は VARCHAR(64) で DB-SCHEMA 通りに定義する(MySQL ENUM は拡張時に ALTER 必要なので避ける、ドキュメントで許容値を明示)。
</specifics>

<deferred>
## Deferred Ideas

- **GitHub Actions CI の実装** — Phase 4 (Production Launch) で secrets / deploy key / Lolipop SSH 設定と同時に整備する(D-14)。
- **`ssr_secret_version` カラム追加** — MVP 後に server_secret ローテーションが必要になった時点で別 phase を切る(D-05 備考)。
- **`account_linking` 用の部分一意 index 化** — MVP 後に複数 identity 対応する時点で、`uk_user_identities_user` を drop して `is_primary = TRUE` を一意にする索引に差し替える(DB-SCHEMA.md §2 備考)。
- **`Pages::display` + `$builder->fallbacks()` のリタイア** — CONCERNS.md が Phase 4 本番デプロイ前に警告している本番漏洩リスク。Phase 1 では migration にフォーカスするため触らない。Phase 4 で LP/dashboard ルートを実装するタイミングで `fallbacks()` を削除する。
- **`DebugKit` 無効化の本番設定** — Phase 4 の INFRA-06 で対応。
- **PSR ログ設定の強化 / 通報レビュー用ログ** — MVP 範囲外。運営体制整備時に別 phase。
</deferred>

---

*Phase: 01-foundation-schema*
*Context gathered: 2026-04-22*
