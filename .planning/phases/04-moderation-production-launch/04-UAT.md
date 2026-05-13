---
status: complete
phase: 04-moderation-production-launch
source:
  - 04-01-SUMMARY.md
  - 04-02-SUMMARY.md
  - 04-03-SUMMARY.md
  - MANUAL-SMOKE-CHECKLIST.md
started: 2026-05-13T08:30:00Z
updated: 2026-05-13T08:30:30Z
---

## Current Test

[testing complete]

## Tests

### 1. Signup (Bluesky OAuth A → /dashboard)
expected: 実 Bluesky アカウント A でサインアップ → OAuth callback 後 `/dashboard` にリダイレクト → ヘッダに `/<a-slug>` URL → 受信リスト空 (empty state)
result: pass
note: "MANUAL-SMOKE-CHECKLIST item (1) + carry-over (10) live AS handshake"

### 2. Send Flow (B → /<a-slug>)
expected: アカウント B で `/<a-slug>` を開く → 送信フォーム表示 → 本文 + 同意チェック → 送信 → 完了ページにリダイレクト
result: pass
note: "MANUAL-SMOKE-CHECKLIST item (2)"

### 3. Open + SSR Reveal
expected: A の `/dashboard` → B からのメッセージ表示 → 展開 → 「開封する」 → SSR hit (送信者カード付き) or miss (匿名)
result: pass
note: "MANUAL-SMOKE-CHECKLIST item (3)"

### 4. Block (SSR hit sender)
expected: SSR hit 送信者カードの「このユーザーをブロック」 → Flash 成功 + `/dashboard` → ブロック中ユーザーセクションに B 表示
result: pass
note: "MANUAL-SMOKE-CHECKLIST item (4)"

### 5. Send Prevention after Block
expected: B が `/<a-slug>` 再オープン → 「この受信箱には送信できません」エラーバナー + 送信フォーム disabled → DevTools で強行しても Flash エラー + リダイレクトで弾かれる
result: pass
note: "MANUAL-SMOKE-CHECKLIST item (5)"

### 6. Report Flow
expected: メッセージ行フッタ「通報する」 → `/report/<id>` → カテゴリ選択 → 送信 → Flash 成功 + `/dashboard` → 「通報済」バッジに変わる
result: pass
note: "MANUAL-SMOKE-CHECKLIST item (6)"

### 7. Soft Delete
expected: 削除ボタン → ネイティブ confirm() → OK → Flash 成功 + リダイレクト → 受信リストから消える
result: pass
note: "MANUAL-SMOKE-CHECKLIST item (7)"

### 8. Account Deletion + Retired 404
expected: settings → danger-zone → `/account/delete` → 確認チェックボックス + 退会 → `/` リダイレクト + セッション破棄 → `/<a-slug>` を直接開くと 404 (REV-01)
result: pass
note: "MANUAL-SMOKE-CHECKLIST item (8)"

### 9. MOD-03 Sender Snapshot Retention
expected: B 退会後も messages.sender_handle_snapshot / sender_avatar_url_snapshot / sender_profile_url_snapshot が B 送信時データのまま残る (SSH SQL 確認)
result: pass
note: "MANUAL-SMOKE-CHECKLIST item (9)"

### 10. Logout Cookie Destroy
expected: `/oauth/logout` POST → CakePHP session cookie 消失 (DevTools) → `/dashboard` 再アクセス → `/` にリダイレクト
result: pass
note: "MANUAL-SMOKE-CHECKLIST item (11), Phase 2 carry-over"

### 11. Handle Change Re-login Sync
expected: bsky.app で A のハンドル変更 → tamabox ログアウト → 再ログイン → ヘッダが新ハンドル + 新 slug 自動派生 + 旧 slug → 新 slug 301 リダイレクト (1 世代 grace)
result: pass
note: "MANUAL-SMOKE-CHECKLIST item (12), Phase 3 carry-over"

### 12. Production DEBUG=false / DebugKit Absent / TLS+JWKS Live
expected: `/somethinginvalid404` → CakePHP production error page (no stack trace) + Lolipop SSH `ls vendor/cakephp/debug_kit` で no such directory + `curl https://tamabox.emomie.com/oauth/jwks.json` + `/oauth/client-metadata.json` が valid JSON over TLS
result: pass
note: "LAUNCH-RUNBOOK Verification gates / INFRA-06 live confirmation"

## Summary

total: 12
passed: 12
issues: 0
pending: 0
skipped: 0
blocked: 0

## Gaps

[none — manual smoke walkthrough completed with zero blockers reported by user]
