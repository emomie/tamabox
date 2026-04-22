# Phase 1: Foundation & Schema — Discussion Log

> **Audit trail only.** Do not use as input to planning, research, or execution agents.
> Decisions are captured in `01-CONTEXT.md` — this log preserves the alternatives considered.

**Date:** 2026-04-22
**Phase:** 01-foundation-schema
**Areas discussed:** server_secret management, Migration granularity, Table class scaffolding, CI scope

---

## Framing

Phase 1 の gray area は少ない。discovery の DB-SCHEMA.md v0.2 でスキーマ・FK cascade・CHECK 制約まで確定しており、REQUIREMENTS.md の INFRA-02/03/04/05/07 も具体的。従って以下の 4 項目のみユーザーに判断を仰いだ:

1. `server_secret` の保管・ローテーション方式
2. migration ファイルの粒度(1 file vs 6 files)
3. Table クラスの生成方式(`bin/cake bake` vs 手書き)
4. CI(GitHub Actions)を Phase 1 で整備するか Phase 4 まで保留するか

---

## server_secret Management

| Option | Description | Selected |
|--------|-------------|----------|
| A | `.env` に 1 つ固定文字列、ローテーションなし(最シンプル、互換性◎) | ✓ |
| B | `.env` に固定 + `ssr_secret_version` 列を追加(将来ローテ可能、スキーマ変更あり) | |
| C | 環境変数で KMS 風に管理(本番で過剰、Lolipop 制約的に現実味薄) | |

**User's choice:** A — 推奨採用
**Notes:** MVP スコープ的に妥当、将来 B に移行しやすい。Phase 1 では messages テーブルに version 列を追加せず、ローテーション必要時に別 phase で ALTER 打つ。

---

## Migration Granularity

| Option | Description | Selected |
|--------|-------------|----------|
| A | **1 テーブル = 1 migration**(6 ファイル、CakePHP bake 出力の自然形) | ✓ |
| B | 1 big migration で全部(可読性は下がるがロールバック単純) | |

**User's choice:** A — 推奨採用
**Notes:** bake との整合、rollback も個別にできる。FK 依存順にタイムスタンプ採番する。

---

## Table Class Scaffolding

| Option | Description | Selected |
|--------|-------------|----------|
| A | `bin/cake bake model` で自動生成 → 必要に応じて編集 | ✓ |
| B | 手書き(厳密に意図コントロール、余計な generated code なし) | |

**User's choice:** A — 推奨採用
**Notes:** CakePHP 標準、速い、validation もひな型付き。tamabox 固有の振る舞いは後続 phase で追加する。

---

## CI Scope

| Option | Description | Selected |
|--------|-------------|----------|
| A | Phase 1 で GitHub Actions(phpcs/phpstan/phpunit)まで整備 | |
| B | Phase 4(production launch)まで CI はなしで進める | |
| C | Phase 1 で `composer.json` の scripts(`composer phpcs` 等)だけ整備、CI は Phase 4 | ✓ |

**User's choice:** C — 推奨採用
**Notes:** ローカル lint は早く欲しい、CI の timer や secret 連携は launch 時に纏める方が綺麗。

---

## Claude's Discretion

- Migration file timestamp 採番
- generated migration 内の column order
- `composer.json` scripts の具体名
- `config/.env.example` の具体的なキー一覧
- テーブルクラスの namespace(CakePHP 標準を踏襲)

## Deferred Ideas

- GitHub Actions CI 実装 → Phase 4
- `ssr_secret_version` カラム追加 → MVP 後にローテ必要になった時点
- account_linking 用の部分一意 index 化 → MVP 後
- `Pages::display` + `fallbacks()` リタイア → Phase 4
- `DebugKit` 本番無効化 → Phase 4 (INFRA-06)
- PSR ログ設定強化 → 運営体制整備時に別 phase
