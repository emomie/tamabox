# Phase 4 手動 smoke テスト チェックリスト

**実施タイミング**: LAUNCH-RUNBOOK.md の Step 1〜5 完了後。runbook の Step 6 がこれ。
**実施者**: ソロ開発者(あなた)
**必要デバイス**: アカウント A / B 用の **2 つの別ブラウザ or 端末** (例: ノート PC の Firefox + スマホの Bluesky アプリ、もしくは 2 つのプライベートウィンドウ + 別々の Bluesky テストアカウント 2 つ)
**所要時間**: フル消化で 30〜45 分

各項目を消化しながらチェックを入れる。失敗したら、その場で失敗内容とスクショリンクをインラインに記録 → 後でまとめて `.planning/phases/04-moderation-production-launch/VERIFICATION.md` に転記。

---

**実施日**: ____________
**実施者**: ____________
**検証対象 URL**: https://tamabox.emomie.com
**Bluesky テストアカウント A**: ____________
**Bluesky テストアカウント B**: ____________

---

## Phase 4 で新規追加された機能の歩き(D-35 walkthrough)

- [ ] **(1) サインアップ** — 実 Bluesky アカウント A でサインアップ → OAuth callback 後 `/dashboard` にリダイレクト → ダッシュボードヘッダに `/<a-slug>` URL が表示される → 受信リストは空(「まだ受信したメッセージはありません」等の empty state コピーが表示される)。

- [ ] **(2) 送信フロー** — デバイス 2 に切り替え、Bluesky アカウント B でログイン → `/<a-slug>` を開く → 送信フォームが表示される → 本文記入 + 同意チェックボックス ON → 送信。送信完了ページにリダイレクトされること。

- [ ] **(3) 開封 + SSR reveal** — デバイス 1 (アカウント A) に戻る → `/dashboard` → B からのメッセージが受信リストに表示される(`<details>` は閉じてる) → クリックで展開 → 「開封する」フォームをクリック → 当落結果が表示される(`★ 抽選 hit` で送信者カード付き、または `★ 抽選 miss(送信者は匿名のまま)`)。実際の当落は受信箱の確率設定で決まる。

- [ ] **(4) ブロック (SSR hit のみ)** — 送信者カードが見えてる状態で `このユーザーをブロック` ボタンをクリック → Flash 成功メッセージ + `/dashboard` にリダイレクト → ブロック中ユーザーセクションに B のハンドルが表示される。
  > miss だった場合は Step 2-3 を別メッセージで繰り返して hit 引くまで試す。または `/dashboard/settings` で受信箱の `ssr_probability_pct` を 100 に一時設定してテスト確率を上げる。

- [ ] **(5) ブロック後の送信防止** — デバイス 2 (アカウント B) に切り替え → `/<a-slug>` を再度開く → 送信フォームの上に「この受信箱には送信できません」エラーバナーが表示される → フォームが視覚的にグレーアウトしてる(disabled) → DevTools で disabled を解除して POST を強行しても、サーバ側で Flash エラー + リダイレクトで弾かれる。

- [ ] **(6) 通報** — デバイス 1 (アカウント A) のダッシュボードでメッセージ行のフッタの `通報する` リンクをクリック → `/report/<message_id>` ページが開く → `嫌がらせ・誹謗中傷` ラジオを選択 → 送信 → Flash 成功 「通報を送信しました…」 + `/dashboard` にリダイレクト → メッセージ行のフッタが `通報する` から `通報済` バッジに変わってる。

- [ ] **(7) ソフト削除** — デバイス 1 (アカウント A) でメッセージを展開 → フッタの `削除` ボタンをクリック → ネイティブ `confirm()` ダイアログ「このメッセージを削除しますか?(削除後は元に戻せません)」 → OK → Flash 成功 「メッセージを削除しました」 + リダイレクト → 受信リストからそのメッセージが消える。

- [ ] **(8) 退会 + 退会後 404** — デバイス 1 (アカウント A) で `/dashboard/settings` → danger-zone フィールドセットまでスクロール → `退会の手続きへ` リンクをクリック → `/account/delete` ページが開く → 「上記の内容を理解した上で、退会します」チェックボックス ON → `退会する` ボタンをクリック → `/` にリダイレクト → Flash info「退会が完了しました…」 → セッション破棄を確認(LP に Bluesky の CTA が出る、ダッシュボードではない) → `/<a-slug>` を直接開く → 404 ページが返る(REV-01: 退会者の slug は他人からも見えなくなる)。

- [ ] **(9) MOD-03 送信者スナップショット保持** — B が過去に A の inbox に送ったメッセージのスナップショットが、B 退会後も残ってることを確認(MOD-03 の sentinel)。Lolipop SSH で SQL 実行:
  ```bash
  cd ~/web/tamabox.emomie.com
  /usr/local/php/8.3/bin/php -r "echo getenv('DATABASE_URL');"  # 接続情報確認
  mysql -u LA71012316 -p -h mysql327.phy.lolipop.lan LA71012316-tamabox -e \
    "SELECT sender_handle_snapshot, sender_avatar_url_snapshot, sender_profile_url_snapshot FROM messages WHERE sender_user_id='<B-user-id>'\G"
  ```
  期待: 行数は B の送信回数のまま不変、かつ snapshot 値が NULL じゃなくて B の送信時データのまま残ってる。
  > 厳密にやるなら Step 8 の前に B からのメッセージを最低 1 件 A に送っておくこと。Step 8 の退会で B のスナップショットも消えてないことを確認したい。

---

## Phase 2 / Phase 3 から繰越した human 項目

(Phase 2 / Phase 3 の verify-phase で `human_needed` として deferred になってたもの。Phase 4 ローンチで消化する取り決め(CONTEXT D-39))

- [ ] **(10) 実 Bluesky AS との handshake** (Phase 2 verifier 項目) — 上の Step (1) のサインアップが暗黙的にこれを exercise してる。実 PAR + DPoP + private_key_jwt + token exchange + getProfile を `bsky.social` 相手に通すフロー。Step (1) が成功してれば本項目クリア。
  > サインアップが途中で失敗したら、エラー URL と POST / redirect の HAR をキャプチャして失敗ログに記録。

- [ ] **(11) ログアウト時のクッキー破棄** (Phase 2 verifier 項目) — デバイス 1、アカウント A でログイン中の状態 → `/oauth/logout` (ダッシュボードのログアウトボタンで POST) → セッション cookie が unset されてることを確認(ブラウザ DevTools > Application > Cookies で CakePHP セッション cookie が消えてる) → `/dashboard` を再アクセス → `/` (LP) にリダイレクトされる。
  > Step (8) 退会の前にやっておくと最もクリーンな検証になる(退会後だとセッションがどっちで切れたか曖昧になる)。または Step (8) 後に新規サインアップして再検証。

- [ ] **(12) ハンドル変更の再ログイン同期** (Phase 3 verifier 項目) — bsky.app の handle 設定でアカウント A の Bluesky ハンドルを変更 → tamabox からログアウト → 再ログイン → ダッシュボードヘッダが新ハンドルに更新されてる + `/<a-slug>` が新しい slug (新ハンドルから自動派生) に解決される + 旧 slug は新 slug に 301 リダイレクトされる(Phase 3 D-04 で 1 世代だけ grace 期間あり)。

---

## 失敗時の記録テンプレート

失敗した step ごとに以下を埋める:

```
Step #: __
期待値 (Expected): __
実際 (Actual): __
再現手順 (Reproduction): __
ワークアラウンド / 次の手 (Workaround / next-action): __
スクショ: <パス or imgur リンク>
```

すべての失敗を `.planning/phases/04-moderation-production-launch/VERIFICATION.md` にまとめて、判断:

- **Block-launch (ローンチ阻害)**: ロールバック実施(LAUNCH-RUNBOOK.md の Rollback procedure 参照) + `/gsd-plan-phase --gaps 4` で gap-closure plan を起こす。
- **Non-blocking (緊急ではない)**: `STATE.md` の Open Todos に積んで次マイルストーンへ。

---

*出典: .planning/phases/04-moderation-production-launch/04-CONTEXT.md D-35 + Phase 2/3 verifier の human_needed 繰越項目(STATE.md Research Flags)。最終更新: Phase 4 plan 04-03、Japanese 翻訳: post-launch hotfix。*
