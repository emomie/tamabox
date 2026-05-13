# Phase 7: Dashboard 4 タブ分離 + Reveal 演出 - Discussion Log

> **Audit trail only.** Decisions are captured in CONTEXT.md.

**Date:** 2026-05-13
**Phase:** 07-dashboard-4-reveal
**Mode:** `--auto`

---

## Area 1 — タブ実装戦略

| Option | Selected |
|--------|----------|
| 4 独立 URL (SSR-pure) | ✓ |
| 単一 URL + JS タブトグル | |
| 単一 URL + AJAX タブロード | |

**Auto pick rationale:** SSR ネイティブ、browser back/forward 自然動作、accessibility きれい、CakePHP の route + action パターンと整合。hi-fi の React state はピクセル等価でなくて良い (Phase 6 D-11 継承)

## Area 2 — TabBar 抽出

| Option | Selected |
|--------|----------|
| `tb_tabbar` element 抽出 (4 画面で再利用) | ✓ |
| 各テンプレートで inline | |

**Auto pick rationale:** Phase 6 D-04/D-05 の YAGNI 抽出ポリシー (2 画面以上で再利用) を満たすケース、4 画面で確定使用

## Area 3 — Reveal fade-in (MOTION-02)

| Option | Selected |
|--------|----------|
| CSS animation `.is-opening` + 既存 details toggle に class 付与 | ✓ |
| JS で transform 直制御 | |
| Web Animations API | |

**Auto pick rationale:** 最小実装、CSS animation はパフォーマンス良好、details の native toggle と整合

## Area 4 — RevealHit sender カード (MOTION-03)

| Option | Selected |
|--------|----------|
| 既存 dashboard `<details>` body 内の sender カード markup を hi-fi 一致書き換え | ✓ |
| `RevealHit.jsx` 全体を独立画面化 | |

**Auto pick rationale:** v1 既存ロジックを破壊せず、視覚一致だけ達成。独立画面化は v3 候補で十分

## Area 5 — Discover / Notifications stub

| Option | Selected |
|--------|----------|
| Empty state スタブ画面 + 新規 controller action | ✓ |
| Dashboard 内で `<div hidden>` でモック | |

**Auto pick rationale:** 4 URL SSR-pure 戦略 (D-01) と整合、後で backend 本体を埋めやすい

## Area 6 — バックエンド変更スコープ

| Option | Selected |
|--------|----------|
| Controller + routes 追加 OK / Model + Migration 禁止 | ✓ |
| Phase 6 並みに backend touch 禁止 | |

**Auto pick rationale:** 4 タブ実装には必然的に新ルート + アクション追加が必要、Phase 6 の精神 (機能挙動不変) は維持しつつ拡張枠を確保

---

## Deferred Ideas

- 3D rotateX 演出 → v3
- Full-screen Reveal page → v3
- Discover/Notifications backend → v3
- Phase 8: Send errors / Block modal / press scale
