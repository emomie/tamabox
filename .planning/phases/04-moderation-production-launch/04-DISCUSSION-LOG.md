# Phase 4: Moderation & Production Launch — Discussion Log

> **Audit trail only.** Do not use as input to planning, research, or execution agents.
> Decisions are captured in `04-CONTEXT.md` — this log preserves the alternatives considered.

**Date:** 2026-04-28
**Phase:** 04-moderation-production-launch
**Areas discussed:** Block UX / Block Error / Report UX / Report Review / Soft Delete / Account Deletion / Token Refresh / Production Launch
**Selection mode:** User selected "全部議論する (A)" then chose `おまかせ` (Claude picks recommended defaults) for every area.

---

## Area 1: Block UX (INBOX-04)

| Q | Question | Options | Selected |
|---|----------|---------|----------|
| Q1-1 | ブロックボタン設置 | a) SSR hit カードのみ / b) hit+miss 両方(技術不可) / c) a + dashboard 一覧 | **a** |
| Q1-2 | ブロック単位 | a) user_identities.id / b) users.id / c) provider+account_id | **a (initial) → b (revised in CONTEXT.md, see note)** |
| Q1-3 | ブロック実行 | a) 即時+取り消し / b) confirm() / c) Flash で undo リンク | **c** |
| Q1-4 | 解除 UI | a) Dashboard 専用セクション / b) メッセージカード上 / c) 両方 | **a** |

**Note on Q1-2 revision**: 当初 `a) user_identities.id` で回答したが、Phase 1 既存スキーマ (`config/Migrations/20260422120005_CreateBlocks.php`) が `blocker_user_id` / `blocked_user_id`(`users.id` 基準) として既に作成済であることを確認。AUTH-04 で 1 user = 1 identity (1:1) のため operationally 等価だが、スキーマ整合のため CONTEXT.md D-02 では `users.id` 基準に修正。

---

## Area 2: Block Error Display (INBOX-05)

| Q | Question | Options | Selected |
|---|----------|---------|----------|
| Q2-1 | エラー判定タイミング | a) GET のみ / b) POST のみ / c) 両方 | **c** |
| Q2-2 | エラー画面 | a) 専用ページ全画面 / b) フォーム disabled + バナー / c) Flash + redirect | **b** |
| Q2-3 | 未認証時 | a) callback で判定 / b) 再表示で判定 | **a** |
| Q2-4 | 文言 | a) シンプル / b) 詳細(誰がブロックか明示)/ c) 主語ぼかし | **c** |

---

## Area 3: Report UX (MOD-01, MOD-02)

| Q | Question | Options | Selected |
|---|----------|---------|----------|
| Q3-1 | フォーム形 | a) モーダル / b) 別ページ / c) inline form | **b** |
| Q3-2 | カテゴリ選択 | a) ラジオ必須 / b) チェックボックス複数 / c) ドロップダウン | **a** |
| Q3-3 | 自由記述 | a) 任意 / b) other 時のみ必須 / c) 全カテゴリ必須 | **b** |
| Q3-4 | 重複制限 | a) 1 通報まで(UNIQUE) / b) 制限なし / c) UPDATE | **a** |

---

## Area 4: Report Review (運営側)

| Q | Question | Options | Selected |
|---|----------|---------|----------|
| Q4-1 | レビュー手段 | a) 管理画面なし / b) 薄い CLI / c) 最小 admin UI | **a** |
| Q4-2 | 通報通知 | a) なし / b) email / c) Bluesky DM | **a** |
| Q4-3 | receiver UI | a) 何も出さない / b) 「通報済」バッジ / c) バッジ+取消 | **b** |
| Q4-4 | 対応 | a) DB 直接 / b) CLI / c) admin UI | **a** |

---

## Area 5: Soft Delete UX (MSG-08)

| Q | Question | Options | Selected |
|---|----------|---------|----------|
| Q5-1 | 削除ボタン位置 | a) リスト行 icon / b) 展開後フッタ / c) 開封済みのみ | **b** |
| Q5-2 | 確認 | a) 即時 / b) confirm() / c) 即時+undo | **b** |
| Q5-3 | 削除後表示 | a) 完全消去 / b) 「削除済み」バッジ / c) /trash 別ページ | **a** |
| Q5-4 | 復元 | a) UI なし / b) 復元可 | **a** |

---

## Area 6: Account Deletion Flow (MOD-03)

| Q | Question | Options | Selected |
|---|----------|---------|----------|
| Q6-1 | 退会導線 | a) Dashboard ボタン / b) 別ページ /account/delete / c) Bluesky revoke 前提 | **b** |
| Q6-2 | DB 処理範囲 | a) users.deleted_at + inbox HIDE / b) 上記+identity anonymize / c) users.deleted_at のみ | **a** |
| Q6-3 | slug 再利用 | a) 解放しない / b) 解放 / c) quarantine 後解放 | **a** |
| Q6-4 | 退会後の sender snapshot 表示 | a) そのまま / b) 「(退会済み)」prefix / c) anonymize | **a** |

---

## Area 7: Token Refresh (Phase 2 sticky #5)

| Q | Question | Options | Selected |
|---|----------|---------|----------|
| Q7-1 | 発火ポイント | a) upsertBlueskyIdentity 内 / b) middleware eager / c) 401 lazy | **a** |
| Q7-2 | 失敗時 | a) silently logout + LP / b) 再 OAuth 強制 / c) エラーページ | **a** |
| Q7-3 | rotation | a) あり(AS 推奨) / b) なし | **a** |
| Q7-4 | Phase 4 スコープ | a) Full 実装 / b) skeleton のみ / c) 実装しない | **a** |

---

## Area 8: Production Launch (INFRA-01, INFRA-06)

| Q | Question | Options | Selected |
|---|----------|---------|----------|
| Q8-1 | デプロイ trigger | a) Lolipop git deploy / b) GitHub Actions / c) 手動 | **a** |
| Q8-2 | .env / 鍵配置 | a) SSH 手動配置 / b) scp 転送 / c) Lolipop env 管理画面 | **a** |
| Q8-3 | Migration 適用 | a) Hook 自動 / b) SSH 手動 / c) 初回のみ手動 | **b** |
| Q8-4 | Smoke test | a) Manual のみ / b) Manual + CLI / c) + synthetic monitor | **a** |

---

## Claude's Discretion (CONTEXT.md `### Claude's Discretion` セクション参照)

すべての「おまかせ」回答は recommended default を Claude が選択した。具体的な実装判断(class 配置、migration 形、Flash 文言、Lolipop hook script 詳細など)は planner / executor 段階で確定する。

## Deferred Ideas

CONTEXT.md `<deferred>` セクション参照(13 項目)。Phase 4 では新規発生なく、Phase 3 までに識別済の deferred items を整理して継承。

---

*Discussion duration: 約 25 分(8 areas × 各 ~3 分の Discord 往復)*
*All answers via Discord by user satie___ (2026-04-26 〜 2026-04-28)*
