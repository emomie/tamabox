# Phase 8: エッジケース & マイクロインタラクション - Discussion Log

> **Audit trail only.** Decisions in CONTEXT.md.

**Date:** 2026-05-13
**Phase:** 08-edge-microinteractions
**Mode:** `--auto`

---

## Area 1 — EDGE-01: Send 404 実装方法

| Option | Selected |
|--------|----------|
| `templates/Error/error400.php` rewrite (グローバル 404 hi-fi 統一) | ✓ |
| /{slug} 専用カスタム ExceptionRenderer | |

**Auto pick rationale:** Phase 5 で deferred された error layout migration を回収、single-pattern で全 404 hi-fi 化、コスト最小

## Area 2 — EDGE-02 受信停止 markup 位置

| Option | Selected |
|--------|----------|
| 既存 `send.php` の else 経路を hi-fi に書き換え | ✓ |
| 独立 template に分離 | |

**Auto pick rationale:** controller / data-flow 変更不要、既存分岐を活かして markup のみ最小変更

## Area 3 — EDGE-03 文字数オーバー実装

| Option | Selected |
|--------|----------|
| JS による live 視覚フィードバック (counter color + highlight + chip + CTA disabled) | ✓ |
| サーバーラウンドトリップで feedback | |
| CSS-only (textarea の `:invalid` 系) | |

**Auto pick rationale:** UX 即時性、defense in depth でサーバーガード継続、progressive enhancement

## Area 4 — EDGE-04 送信失敗実装

| Option | Selected |
|--------|----------|
| processSend の catch で render('send_failed') 切替 | ✓ |
| 既存 Flash + redirect パターン継続 | |

**Auto pick rationale:** hi-fi の full-page error 表現に合致、Flash バナー (recoverable validation error) と分けて UX 明確化

## Area 5 — EDGE-05 Block modal 実装

| Option | Selected |
|--------|----------|
| `<dialog>` element + minimal JS + element 化 | ✓ |
| カスタム overlay div + JS state | |
| サーバー往復 confirm page | |

**Auto pick rationale:** native HTML5 dialog で a11y デフォルト、ESC キー対応、focus trap、ブラウザ互換性も Lolipop の対象環境では十分

## Area 6 — MOTION-01 実装

| Option | Selected |
|--------|----------|
| CSS-only `:active scale(0.985)` + prefers-reduced-motion | ✓ |
| JS class toggle | |

**Auto pick rationale:** 単純、パフォーマンス良好、a11y 一貫性

## Area 7 — Phase 7 cleanup どこで実施

| Option | Selected |
|--------|----------|
| Phase 8 末の集約 plan で実施 | ✓ |
| 関連 Phase 8 plan に分散 | |
| Phase 9 (v3 候補) に持ち越し | |

**Auto pick rationale:** v2 を clean state で close できる、PR review 単位で隔離

---

## Deferred Ideas

- error500 hi-fi → v3
- Send error i18n → v3
- Block modal rich animation → v3
- 3D rotateX (MOTION-X1) → v3
- Desktop responsive → v3
