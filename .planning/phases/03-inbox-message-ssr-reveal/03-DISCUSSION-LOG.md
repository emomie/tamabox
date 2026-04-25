# Phase 3: Inbox, Message & SSR Reveal — Discussion Log

> **Audit trail only.** Do not use as input to planning, research, or execution agents.
> Decisions are captured in 03-CONTEXT.md — this log preserves the alternatives considered.

**Date:** 2026-04-26
**Phase:** 03-inbox-message-ssr-reveal
**Areas discussed:** Inbox 作成 / slug 運用、SSR 確率 UI + 実装、送信フォーム (OAuth ゲート + 同意 UI)、受信ダッシュボード + 開封 UX、送信時 profile snapshot + token refresh、Phase 境界設計
**Mode:** Discord interactive (text-mode batched questions, 1 area per message)

---

## Pre-discussion Decision: gray area selection

ユーザに 6 つの gray area を提示。「全部っぽい」と回答 → 全 6 エリア discuss。

---

## Area 1: Inbox 作成 / slug 運用

### Q0 (concept clarification, ユーザ発議): slug 運用の根本方針

| Option | Description | Selected |
|--------|-------------|----------|
| A: 完全自由 slug (現行 INBOX-01/06) | 受け手が任意に slug 入力、衝突は自分で避ける | |
| B: handle 由来でプリフィル、編集可 | 衝突時に suffix 自動提案、後変更も可 | |
| C: slug = 正規化 handle、改名追従、tamabox 単独編集なし | 受け手と送り手の SNS identity 共有が世界観の核 | ✓ |

**User's choice:** C(scope 変更を受け入れる)
**Notes:** ユーザのコンセプト説明:「受け手と送り手が同じようなアカウント表示を行い、送り手はアカウント暴露のリスクと楽しみでやり取りを行う」。identity 単一性がコア体験の土台、URL もその延長線。改名追従は SNS 側の改名を upsertBlueskyIdentity で検知して inbox.slug も更新する。

### C-Q1: handle → slug 正規化ルール

| Option | Selected |
|--------|----------|
| a: ドメイン前部分(`satie.bsky.social` → `satie`) | ✓ |
| b: 全体を `-` 区切り(`satie-bsky-social`) | |
| c: 独自ドメインは suffix(`you-ex`) | |

### C-Q2: 正規化後の衝突処理

| Option | Selected |
|--------|----------|
| a: 先勝ち + `-2`/`-3` 自動 suffix | ✓ |
| b: DID hash 8 文字 suffix | |
| c: ログイン時ユーザ選択 | |

### C-Q3: handle 改名追従トリガー

| Option | Selected |
|--------|----------|
| a: 毎ログイン時の upsertBlueskyIdentity で発火 | ✓ |
| b: 手動同期ボタン | |
| c: 両方 | |

### C-Q4: 古い slug URL リダイレクト

| Option | Selected |
|--------|----------|
| a: 即 404 | |
| b: slug_history テーブル新設 + 301 | |
| c: user_identities 履歴で旧→現 解決(既存カラムで組む) | ✓ |

**Notes:** 実装時に `inboxes.slug_previous` 1 列または `inbox_slug_history` 薄テーブルを planner 判断で導入する余地を残す(c の意図を honor、既存カラムだけでは無理という実装現実を踏まえる)。1 世代分のみ救済。

### C-Q5: display_name(tamabox 上の表示名)

| Option | Selected |
|--------|----------|
| a: 独立編集可 | |
| b: handle 固定・改名追従(コンセプト一貫重視) | ✓ |
| c: Bluesky の displayName に追従 | |

### C-Q6: 衝突時の自動 suffix + ユーザ通知

`OK` で確定(初回ログイン時に自動 `-2`/`-3` 付与、dashboard で 1 度通知 flash)。

---

## Area 2: SSR 確率 UI + 実装

### A2-Q1: 確率設定 UI 形式

| Option | Selected |
|--------|----------|
| a: 数値入力 + スライダ併設(キーボード/gestural 両対応) | ✓ |
| b: スライダのみ | |
| c: プリセットボタンのみ | |
| d: 数値入力のみ | |

### A2-Q2: 刻み幅

| Option | Selected |
|--------|----------|
| a: 1% 刻み | ✓ |
| b: 0.1% 刻み | |
| c: 5% 刻み | |

### A2-Q3: SSR 判定アルゴリズム

| Option | Selected |
|--------|----------|
| a: hexdec(substr(seed,0,8)) / 0xFFFFFFFF < probability | ✓ |
| b: mod 10000 ベース | |
| c: 複数乱数の平均 | |

### A2-Q4: 0% 時の UX

| Option | Selected |
|--------|----------|
| a: 警告なし即受理 | |
| b: 確認ダイアログ「コア体験が失われますが…」 | ✓ |
| c: 不許可(却下: 要件違反) | |

### A2-Q5: 100% 時の UX

| Option | Selected |
|--------|----------|
| a: 警告なし | |
| b: 警告ダイアログ「全メッセージで開示されます」 | ✓ |
| c: 別ラベル分岐 | |

### A2-Q6: 確率変更の既存メッセージへの遡及

| Option | Selected |
|--------|----------|
| a: 新規 INSERT のみ新確率(送信時契約維持) | ✓ |
| b: 未開封は再判定(MSG-02 概念崩壊) | |

**User's choice (Area 2):** Q4/Q5 ダイアログ、他推奨

---

## Area 3: 送信フォーム (OAuth ゲート + 同意 UI)

### A3-Q1: 未認証時の送信フォーム挙動

| Option | Selected |
|--------|----------|
| a: 本文先入力可、ボタンが OAuth start trigger(下書き保護) | ✓ |
| b: 押下時にモーダル(本文ロスト) | |
| c: 未認証時は textarea 無効 | |

### A3-Q2: 同意 UI 形式

| Option | Selected |
|--------|----------|
| a: チェックボックス必須 | ✓ |
| b: モーダル確認 | |
| c: 初回のみ同意ページ | |

### A3-Q3: 同意文言のトーン

| Option | Selected |
|--------|----------|
| a: 直感的 | ✓ |
| b: 法律寄り | |
| c: 簡潔のみ | |

### A3-Q4: 本文最大長

| Option | Selected |
|--------|----------|
| a: 1000 文字 | |
| b: 2000 文字 | ✓ |
| c: 4000 文字 | |
| d: TEXT max(64KB) | |

### A3-Q5: 本文 HTML / Markdown 扱い

| Option | Selected |
|--------|----------|
| a: プレーンテキスト固定 + エスケープ | ✓ |
| b: 限定 Markdown | |
| c: raw HTML(却下) | |

### A3-Q6: URL / メンションの自動リンク化

| Option | Selected |
|--------|----------|
| a: なし | ✓ |
| b: URL のみ自動リンク | |
| c: メンションも自動リンク | |

### A3-Q7: 送信成功後の画面

| Option | Selected |
|--------|----------|
| a: コア体験文言 + 再送ボタン | ✓ |
| b: 「送信しました」のみ | |
| c: 自分の SSR 結果即表示 | |

### A3-Q7 補足: 送り手は SSR hit を知るか

| Option | Selected |
|--------|----------|
| α: 送信直後にわかる(ゲーム化) | |
| β: 受け手開封時に送り手にも反映(通知必要、v2) | |
| γ: 送り手は永遠に知らない(MVP 最小) | ✓ |

**User's choice (Area 3):** Q7 補足のみ γ、他推奨。MVP 簡略化のため通知系も不要、送り手のドキドキを残す。

---

## Area 4: 受信ダッシュボード + 開封 UX

### A4-Q1: ダッシュボード URL 設計

| Option | Selected |
|--------|----------|
| a: /dashboard 単一 | ✓ |
| b: 機能別 URL 分割 | |
| c: /<myslug>/admin | |

### A4-Q2: 受信一覧レイアウト

| Option | Selected |
|--------|----------|
| a: リスト | ✓ |
| b: カード | |
| c: テーブル | |

### A4-Q3: 未開封 / 開封済み視覚区分

| Option | Selected |
|--------|----------|
| a: 同一リスト + 太字/icon | ✓ |
| b: タブ分離 | |
| c: 色分け | |

### A4-Q4: ソート

| Option | Selected |
|--------|----------|
| a: 新しい順固定 | ✓ |
| b: ユーザ切替 | |

### A4-Q5: ページング

| Option | Selected |
|--------|----------|
| a: ページネーション | ✓ |
| b: 無限スクロール | |
| c: 単一ページ全件 | |

### A4-Q6: 開封操作 UX

| Option | Selected |
|--------|----------|
| a: クリック即開示 | |
| b: 段階的開示(本文 → 開封ボタン → SSR) | ✓ |
| c: 本格ガチャ演出 | |

### A4-Q7: SSR 露出時の表示強度

| Option | Selected |
|--------|----------|
| a: シンプルテキスト | |
| b: 控えめバナー + カード | ✓ |
| c: 派手アニメーション | |

### A4-Q8: 既開封の再閲覧時挙動

| Option | Selected |
|--------|----------|
| a: 再演出なし(opened_at 不更新) | ✓ |
| b: 再閲覧でも段階開示 | |
| c: 一度開封したら閉じれない | |

### A4-Q9: 受信箱設定 UI 統合

| Option | Selected |
|--------|----------|
| a: 1 フォーム統合 | ✓ |
| b: 設定別フォーム | |
| c: 確率のみ Phase 3 | |

**User's choice (Area 4):** all 推奨

---

## Area 5: 送信時 profile snapshot + token refresh

### A5-Q1: 送信時 sender profile 取得方針

| Option | Selected |
|--------|----------|
| a: cached を使用(getProfile 再呼びなし) | ✓ |
| b: getProfile 再呼び | |
| c: hybrid (TTL 判定) | |

### A5-Q2: token refresh 必要性

| Option | Selected |
|--------|----------|
| a: Phase 3 不要(refreshTokenIfExpired を Phase 4 へ defer) | ✓ |
| b: ヘルパーだけ実装 | |
| c: 完全先送り | |

### A5-Q3: avatar dead link フォールバック

| Option | Selected |
|--------|----------|
| a: img onerror | ✓ |
| b: picture タグ | |
| c: サーバ側 HEAD 検証 | |

### A5-Q4: profile_url 生成方針

| Option | Selected |
|--------|----------|
| a: bsky.app 固定 URL | ✓ |
| b: getProfile レスポンス利用(a と事実上同義) | |

### A5-Q5: 自己送信(自分の inbox に自分で送る)

| Option | Selected |
|--------|----------|
| a: 許可 | ✓ |
| b: 禁止(422) | |
| c: 確率 100% 強制 | |

### A5-Q6: handle 改名で `*_snapshot` を追従させるか

| Option | Selected |
|--------|----------|
| a: 固定(送信時点の値) | ✓ |
| b: 追従(要件違反) | |

**User's choice (Area 5):** all 推奨

---

## Area 6: Phase 境界設計 (stub / 404 / 退会ユーザ)

### A6-Q1: Phase 4 機能の Phase 3 stub 化

| Option | Selected |
|--------|----------|
| a: 通報 / ブロックボタンを UI に出して 501 stub controller を置く | ✓ |
| b: Phase 3 では UI も出さない | |
| c: 「準備中」プレースホルダのみ | |

### A6-Q2: 不存在 slug アクセス挙動

| Option | Selected |
|--------|----------|
| a: 普通に 404 | ✓ |
| b: 専用ランディング | |
| c: LP に 302 | |

### A6-Q3: 退会ユーザ slug アクセス

| Option | Selected |
|--------|----------|
| a: Phase 3 では考慮しない(Phase 4 で同時設計) | ✓ |
| b: deleted_at IS NULL を Phase 3 で予約 | |
| c: 凍結ランディング(Phase 4 範疇侵犯) | |

### A6-Q4: `/<slug>` URL の挙動分岐

| Option | Selected |
|--------|----------|
| a: 自分の inbox なら送信 + dashboard リンク表示(自己送信許可) | ✓ |
| b: 自分の inbox なら /dashboard へ redirect | |
| c: 自分・他人問わず一律送信フォーム | |

### A6-Q5: verify-phase の human-needed

| Option | Selected |
|--------|----------|
| a: 実 Bluesky AS E2E は Phase 4 デプロイ後に持ち越し | ✓ |
| b: Phase 3 内で staging 立てて完了 | |

### A6-Q6: 要件書き換えのタイミング

| Option | Selected |
|--------|----------|
| a: CONTEXT.md commit と同 commit で書き換え | ✓ |
| b: 別 commit | |
| c: planner が書き換え(却下) | |

**User's choice (Area 6):** all 推奨

---

## Claude's Discretion

以下は CONTEXT.md `<decisions>` 末尾に列挙したとおり、実装時に Claude が判断する範囲(slug suffix 上限、welcome_message 最大長、session key 名、ページサイズ、JS 実装方式、avatar SVG デザイン、stub controller class 配置、slug 履歴解決の DDL 形態、flash 文言、E2E mock fixture 構成)。

## Deferred Ideas

`refreshTokenIfExpired()` 実装(Phase 4)、退会 flow 全般(Phase 4)、複数世代 slug 履歴(永遠に Out of Scope)、送信履歴閲覧 UI(同)、送り手への SSR 結果通知(v2 以降)、無限スクロール / リアルタイム(永遠に Out of Scope)、通報事後レビュー専用 admin UI(v2)、welcome_message の Markdown / リンク化(同)、メッセージ送信レート制限(MVP 不採用)、`bin/cake ssr:verify` CLI(planner 判断)。

## Scope Changes Required (commit 同梱)

- PROJECT.md: 「安定 slug(SNS handle と非連動)」→ 「SNS handle 由来 slug + 改名追従」。Key Decisions に slug 自動付与方針を 1 行追加。
- REQUIREMENTS.md: INBOX-01 と INBOX-06 の文言を改定(本 log の Area 1 C 採用に伴う)。
- ROADMAP.md: Phase 3 Success Criteria #1 の文言を改定。

---

*Discussion captured: 2026-04-26 via Discord interactive session*
*Total decisions: 40 (Area 1: 7, Area 2: 6, Area 3: 8, Area 4: 9, Area 5: 6, Area 6: 6 — 1 concept-level)*
