# Phase 6: v1 画面の Calm Gacha 化 - Discussion Log

> **Audit trail only.** Do not use as input to planning, research, or execution agents.
> Decisions are captured in CONTEXT.md — this log preserves alternatives considered.

**Date:** 2026-05-13
**Phase:** 06-v1-calm-gacha
**Mode:** `--auto` (Claude が推奨デフォを選択、ユーザーへの質問は省略)
**Areas auto-selected:** Migration order, Element extraction policy, Legacy class / milligram, Hi-fi 一致判定, Backend immutability

---

## Area 1 — Per-screen migration order

| Option | Description | Selected |
|--------|-------------|----------|
| 依存度低→高 (AvatarHandleChip → Home → Done → Delete → Report → Settings → BlockList → Send) | shared element 先固めで後続再利用、Send は最も複雑なので最後 | ✓ |
| 重要度高→低 (Home → Send → Settings → ...) | ユーザー導線順、Home から見える化 | |
| ROADMAP 番号順 (UI-01 → UI-08) | requirement 番号と plan 番号を一致 | |

**Auto pick rationale:** 依存度順は shared element (AvatarHandleChip / TbChip) を先に固めることで Settings / BlockList / Send での再利用時に手戻りが起きない。Plan 単位の依存グラフが clean

## Area 2 — PHP element 抽出ポリシー (Phase 5 deferred)

| Option | Description | Selected |
|--------|-------------|----------|
| 本 phase 内で並行抽出 (画面置換と同じ commit) | YAGNI 維持 (2 画面以上使用してから抽出)、Phase 5 の deferred を回収 | ✓ |
| 全 element を最初に一括抽出 | abstraction を最初に固定、画面置換は呼び出すだけ | |
| 抽出しない (テンプレートに直接書く) | Cake helper だけで完結、element 増やさない | |

**Auto pick rationale:** 2 画面以上で再利用が確認できた時点で抽出、という YAGNI ベースの並行抽出が Phase 5 で deferred されていた仕事を自然に回収する。tb_button / tb_card / tb_chip / tb_input / tb_letter の 5 element を本 phase 中に生成見込

## Area 3 — Legacy class / milligram の扱い

| Option | Description | Selected |
|--------|-------------|----------|
| alias 戦略継続 + milligram 残置、phase 末でクリーン判断 | Phase 5 と同じ方針、破壊リスク最小 | ✓ |
| 本 phase で milligram も剥がす | サイズ削減、複雑度減 | |
| 旧 class 完全削除 (alias 削除) | 後戻り不能のため危険 | |

**Auto pick rationale:** Phase 5 で確立した alias 戦略の継承が安全。milligram は phase 末 (final plan) で grep を再実施し未使用なら剥がすが、置換途中で先に消すと form / table デフォルトが崩れる

## Area 4 — Hi-fi 一致判定

| Option | Description | Selected |
|--------|-------------|----------|
| layout / spacing / typography / color tone の一致 (ピクセルパーフェクトは目標にしない) | 実装可能性 + 視覚 fidelity のバランス | ✓ |
| ピクセルパーフェクト | 完全 1:1、コスト過大 | |
| 雰囲気一致 (緩い) | コスト低、後で UI-Review に詰められる | |

**Auto pick rationale:** Phase 5 UI-Review の判定基準と整合、Calm Gacha のトーンが伝わることを優先。各 plan の verifier に hi-fi JSX path を明示し Claude が比較できるようにする

## Area 5 — バックエンド不変ガード

| Option | Description | Selected |
|--------|-------------|----------|
| Controller / Model / Migration touch 禁止、Form / Html / Flash helper は維持 | 機能挙動を変えない、CSRF / validation 保護を維持 | ✓ |
| Form を生 HTML 化 (markup 自由度優先) | Cake の安全機構を捨てる、リスク大 | |

**Auto pick rationale:** v1 で 195 tests / 0 failures、29/29 STRIDE threats closed の状態を maintain することは Phase 6 の必須要件。helper 経由で `.tb-*` class を渡す形にすれば視覚も機能も両立

---

## Claude's Discretion

- element 抽出 commit と画面置換 commit を分けるかは plan-phase で判断 (基本同一)
- icon.php 経由 SVG inline vs img 参照は hi-fi のパターンを mirror
- form validation error 表示の Calm Gacha 化は Send 着手時に判断 (Phase 8 寄せ候補)
- Send welcome message のソースは Send 着手時に grep で確認

## Deferred Ideas

- Dashboard 4 タブ / TabBar / Reveal motion → Phase 7
- Press scale / Send error 系 / Block modal → Phase 8
- Onboarding / Login / Help / Terms / Share / Discover backend → v3
- 3D rotateX 演出 / Desktop responsive → v3
