# Phase 3: Inbox, Message & SSR Reveal — Context

**Gathered:** 2026-04-26
**Status:** Ready for planning

<domain>
## Phase Boundary

受け手が自分の inbox(SNS handle 由来 slug + SSR 確率カスタマイズ + welcome_message + is_accepting)を持ち、Bluesky OAuth 済みの送り手が **同意 UI を経て** 送信、送信時に `is_ssr` / `ssr_probability_at_send` / `ssr_seed` / sender snapshot が確定し、受け手が `/dashboard` で受信一覧を見て **段階的開示 UX(本文 → 開封ボタン → SSR 結果)** で SSR hit/miss を確認できる **コア体験 E2E** を成立させる。

**Requirements mapped to this phase:** AUTH-03, INBOX-01, INBOX-02, INBOX-03, INBOX-06, MSG-01, MSG-02, MSG-03, MSG-04, MSG-05, MSG-06, MSG-07 (12 件)

**Not in this phase:**
- ブロック機能 (INBOX-04 / INBOX-05) → Phase 4
- 通報機能 (MOD-01〜04) → Phase 4(ただし Phase 3 で UI に通報ボタンと `/report` route を 501 stub で予約)
- 論理削除 (MSG-08) → Phase 4
- 退会 flow / 退会後の slug 凍結 (MOD-03 含む) → Phase 4
- token refresh の call site と `UserIdentitiesTable::refreshTokenIfExpired()` 実装 → Phase 4(Phase 2 sticky note 5 を defer。Phase 3 は cached snapshot のみで成立)
- 本番デプロイ (`tamabox.emomie.com` / debug=false) (INFRA-01 / INFRA-06) → Phase 4
- E2E 実 Bluesky AS 接続 verify → Phase 4 デプロイ後 human-needed(Phase 2 verify-phase の前例踏襲)

</domain>

<scope_changes>
## Scope Changes (上位ドキュメント書き換えが必要)

Area 1 の決定 (slug = SNS handle 自動導出、改名追従、tamabox 単独編集なし) に伴い、以下を **CONTEXT.md commit と同一 commit で書き換える**:

### PROJECT.md (Active 認証 / アイデンティティ + 受信箱 セクション)
- 「受け手は自分の **安定 slug(SNS handle と非連動)** で inbox URL を持つ」
  → 「受け手は **SNS handle から自動導出された slug** で inbox URL を持つ。SNS 改名時は slug が追従する」
- Key Decisions に 1 行追加: 「slug は SNS handle 由来で自動付与・改名追従。tamabox 単独で slug を変更する機能はない」(根拠: 受け手と送り手が共通の SNS identity 表示を持つコア体験の一貫性)

### REQUIREMENTS.md
- **INBOX-01** 改定:「受け手はサインアップ時にカスタム slug(SNS handle と非連動)を選んで inbox URL (`/<slug>`) を作成できる」
  → 「受け手のサインアップ時に SNS handle から導出した slug が自動付与され、受け手は inbox URL (`/<slug>`) を持つ。衝突時は `-2` / `-3` の suffix が自動で付く」
- **INBOX-06** 改定:「受け手は自分の slug / display_name を後から変更できる」
  → 「受け手の SNS handle 改名時に inbox の slug と display_name が自動追従する。tamabox 単独で slug / display_name を変更する機能はない」

### ROADMAP.md (Phase 3 Success Criteria)
- 旧 #1 「受け手はサインアップ時に任意の slug を選び `/<slug>` で inbox URL を持てる。後から slug / display_name を変更できる」
  → 新 #1 「受け手はサインアップ時に SNS handle 由来の slug が自動付与され `/<slug>` で inbox URL を持つ。SNS 改名時は slug と display_name が自動追従する」

</scope_changes>

<decisions>
## Implementation Decisions

### Slug & Identity (Area 1 — コンセプト整合)

- **D-01**: **slug は Bluesky handle 由来で自動導出**。`handle_cached` の **ドメイン前部分** を採用 (`satie.bsky.social` → `satie`、`you.example.com` → `you`)。tamabox 単独で slug を変更する UI / API は提供しない。コンセプト: 受け手と送り手が共通の SNS identity 表示を持つ世界観。送り手は SSR 抽選で自分の SNS が暴露されるスリルを楽しむ。
- **D-02**: **正規化後の衝突は `-2` / `-3` 自動 suffix 付番**。先勝ち。`alice` 取得済みのとき、後から来た別 DID の `alice.*` ハンドル → `alice-2` を自動付与。`alice-2` も埋まっていれば `alice-3`、と順次。最大 N (= 100 程度) まで試して全埋まりなら `<prefix>-<did_hash8>` フォールバック(realistic には起きない)。
- **D-03**: **handle 改名追従は毎ログイン時の `upsertBlueskyIdentity` で発火**。Phase 2 で実装済の `upsertBlueskyIdentity` が `handle_cached` を更新する際、`inboxes.slug` も再計算して UPDATE。slug 衝突のチェックも同時に走る。トランザクション境界は `users` + `user_identities` + `inboxes` 一括。
- **D-04**: **古い slug URL のフォールバック解決**。改名後に `/oldslug` にアクセスがあったとき、最小限の追加 DDL を planner 判断で導入(候補: `inboxes.slug_previous VARCHAR(32) NULL` 1 列、または `inbox_slug_history` 薄いテーブル)。改名 1 世代分は 301 リダイレクトで救う(複数世代追跡は MVP 範囲外)。実装は Phase 3 で導入する。
- **D-05**: **`display_name` も handle に固定追従**。tamabox 上での独立編集機能は提供しない。`users.display_name` は `handle_cached` のドメイン前部分(slug と同じ正規化規則)を毎ログイン同期。
- **D-06**: **衝突 suffix 付与時の通知**。dashboard ログイン後の最初の表示で「あなたの slug: `alice-2` になりました(`alice` は他のユーザに使われていたため)」を flash で 1 度だけ表示。

### SSR 確率 UI + 実装 (Area 2)

- **D-07**: **確率設定 UI = 数値入力(0〜100 整数)+ 横並びスライダ**(刻み 1%、`<input type="range" step="1" min="0" max="100">` + `<input type="number" min="0" max="100" step="1">` を双方向バインド)。受信箱設定フォーム内に組み込む。
- **D-08**: **DB 格納は `DECIMAL(4,3)` の 1% 刻み**(0.000 〜 1.000、下 1 桁は常に 0)。`ssr_probability` 列の精度は活かさず、UI 側で 1% 単位を強制。
- **D-09**: **SSR 判定アルゴリズム**: `hexdec(substr(ssr_seed, 0, 8)) / 0xFFFFFFFF < ssr_probability` で `[0, 1)` の浮動小数点判定。`ssr_seed` は送信時に `sha256($server_secret . $message_id . $created_at_microsecond_str)` で計算した hex 64 桁。判定は決定的なので、運営側は `ssr_seed` と `server_secret` から再検証可能(F2 仮説の監査性)。
- **D-10**: **0% 設定時 UX**:値そのものは許可(匿名箱モード)。設定保存時に確認ダイアログ「0% にするとコア体験(送信者開示の楽しみ)が失われますが、それでも設定しますか?」。JavaScript の `confirm()` で十分。
- **D-11**: **100% 設定時 UX**:値そのものは許可(全開示モード)。設定保存時に確認ダイアログ「全てのメッセージで送信者が開示されます — 本当に?」。`confirm()`。
- **D-12**: **確率変更時の既存メッセージへの遡及なし**。`ssr_probability` を UPDATE しても、既存 `messages.ssr_probability_at_send` と `is_ssr` は不変。新規 INSERT のみ新確率を `ssr_probability_at_send` に焼き付ける(MSG-02 の「送信時確定」契約維持、F2 監査性)。

### 送信フォーム (Area 3)

- **D-13**: **未認証時の送信フォーム挙動**:本文 `<textarea>` は未認証でも入力可能。送信ボタンは未認証時 「Bluesky でログインして送信」ラベルに切り替え、押下で `/login/bluesky` へ POST(本文を session(`pending_message_body`)に保持)→ OAuth 完了後に callback で session 値を復元 → 送信フォームに戻して再表示。
- **D-14**: **同意 UI**:送信ボタン上部にチェックボックス必須:「[ ] 確率で送信者が開示されることに同意する」。未チェックでは submit 不可(HTML `required` + サーバ側でも検証)。
- **D-15**: **同意文言のトーン**:直感的。固定文言「このメッセージは抽選で送信者の Bluesky アカウントが開示される可能性があります(現在の確率: **X%**)」(X はその inbox の `ssr_probability * 100`)。
- **D-16**: **本文最大長 = 2000 文字**(`mb_strlen()` 基準)。サーバ側バリデーションで `body_length > 2000` は 422。`messages.body_length` 列には UTF-8 文字数を保存。
- **D-17**: **本文 HTML / Markdown 扱い = プレーンテキスト固定**。表示時は `h()` (CakePHP HTML エスケープ) を必ず通し、改行 `\n` のみ `<br>` 化(`nl2br()`)。Markdown / URL 自動リンク化は MVP 範囲外。
- **D-18**: **送信成功後の画面**:固定文言「送信しました。受け手が開封したとき、抽選次第であなたのアカウントが開示されます。」+「同じ受信箱に再送する」ボタン(`/<slug>` 送信フォームに戻る)+「他の受信箱を見る」リンク(LP `/`)。
- **D-19**: **送り手は自分の SSR 結果を永遠に知らない**(MVP 簡略化)。送信完了画面に SSR hit/miss は表示しない。受け手側のみが結果を見る非対称設計で送り手のドキドキを残す。送信履歴閲覧 UI も Phase 3 範囲外。

### 受信ダッシュボード + 開封 UX (Area 4)

- **D-20**: **ダッシュボード URL = `/dashboard`** 単一ページに全機能集約(受信一覧 + 受信箱設定 + 自分の slug 通知)。1 user 1 inbox なので分割しない。
- **D-21**: **受信一覧レイアウト = 縦リスト形式**。1 行 = 送信時刻 + 本文先頭 N 文字(N = 80 文字程度)+ 開封状態 icon。SNS DM 風の情報密度。
- **D-22**: **未開封 / 開封済みの視覚区分**:同一リスト内で混在表示。未開封 = 太字 + 左に「●」(open icon)、開封済み = 通常テキスト + 「✓」(checkmark)。タブ分離は採用しない。
- **D-23**: **ソート = 新しい順(送信日時 DESC)固定**。`idx_messages_inbox_created` の自然順。ユーザ切替不可。
- **D-24**: **ページング = ページネーション**(`?page=2` クエリ、CakePHP `Paginator` 標準、20 件/ページ)。無限スクロール不採用(Lolipop 共有鯖の安定性 + SEO/履歴整合)。
- **D-25**: **開封 UX = 段階的開示**。リスト行クリック → 本文を展開表示(まだ `opened_at` は更新しない)。本文展開ペインの下に「開封する」ボタン → 押下で `opened_at` UPDATE + SSR 結果セクションを追加で展開。これによりコア体験のドキドキを最大化(本文を読んでから抽選結果を見る順序)。
- **D-26**: **SSR 露出時の表示強度**:hit 時は控えめバナー + 送信者カード(avatar 64px + handle linked to `https://bsky.app/profile/<handle>` + profile_url ボタン)。miss 時は「★ 抽選 miss(送信者は匿名のまま)」程度の控えめテキスト。CSS は `tamabox.css` に追記(Phase 2 ベース)、派手なアニメは入れない。
- **D-27**: **既開封メッセージの再閲覧時挙動**:結果まで全部表示済の状態で見える(段階的開示は初回のみ、`opened_at` は再更新しない)。シンプル。
- **D-28**: **受信箱設定 UI 統合**:`/dashboard/settings`(POST `/dashboard/settings`)に 1 フォームで:
  - `ssr_probability` (number + range スライダ、Area 2 既決)
  - `welcome_message` (textarea, NULL 許可、最大 1000 文字程度)
  - `is_accepting` (checkbox, デフォルト ON、OFF 時は受信フォームで「現在この受信箱は受け付けていません」表示)

### 送信時 profile snapshot (Area 5)

- **D-29**: **送信時の sender profile = `user_identities.*_cached` を読んでスナップショット**。`getProfile` 再呼びはしない。`last_synced_at` は毎ログイン更新されるので fresh と見なす。`messages.sender_handle_snapshot` / `sender_avatar_url_snapshot` / `sender_profile_url_snapshot` に焼き付け。
- **D-30**: **token refresh は Phase 3 で実装しない**。Phase 2 sticky note 5 (`UserIdentitiesTable::refreshTokenIfExpired()`) は Phase 4 へ defer。Phase 3 のメッセージ送信は外部 API を一切呼ばないため、token 期限切れの問題は発生しない。
- **D-31**: **avatar dead link フォールバック**:`<img src="..." onerror="this.src='/img/default-avatar.svg'">` で HTML 標準の `onerror` 属性で fallback。`webroot/img/default-avatar.svg` を Phase 3 で追加(シンプルな匿名アバター SVG、Inkscape 不要、手書き SVG で十分)。
- **D-32**: **profile_url 生成方針 = `https://bsky.app/profile/<handle>`** 固定(Bluesky の場合)。`user_identities.profile_url_cached` も同じ規則で生成して保存。message snapshot にも同じ規則で焼き付け。
- **D-33**: **自己送信(自分の inbox に自分で送る)= 許可**。送信フォーム側でも特別扱いせず、通常の送信 flow を通す。SSR 抽選もそのまま走る(自分の identity が自分に開示される、コア体験的には特殊事象だが MVP では制限しない)。
- **D-34**: **handle 改名で過去の `*_snapshot` も追従させない**。MSG-04 要件通り、送信時点の値で固定。改名後にダッシュボードで過去メッセージを見ても、当時の handle / avatar / profile_url を表示する(監査性 + V1 仮説の「逃げ得防止」根拠)。

### Phase 境界設計 (Area 6)

- **D-35**: **Phase 4 機能の Phase 3 stub 化**:
  - 通報ボタン:受信メッセージ展開時の SSR 結果セクション付近に「通報」ボタンを表示。押下で `POST /report/<message_id>` → `OauthController` のように `MessagesController::report()` に 501 ハンドラを置く。Phase 4 でこのメソッド本体を埋める契約。
  - ブロックボタン:SSR hit 時の送信者カード上に「このユーザーをブロック」ボタン。押下で `POST /block/<sender_user_id>` → 501 ハンドラ。Phase 4 で実装。
  - Phase 2 D-DEF-01 / callback 501 stub と同じ予約パターン(callback stub の置き換えに対応する integration test を Phase 4 で更新する)。
- **D-36**: **不存在 slug アクセス = 404**(`NotFoundException`)。CakePHP 標準のエラーテンプレート(`templates/Error/error400.php`、Phase 1 で確認済)。専用ランディングは MVP 過剰。
- **D-37**: **退会ユーザの slug は Phase 3 では考慮しない**。退会 flow 自体が Phase 4 範疇(MOD-03)。Phase 3 では `users.deleted_at IS NULL` の WHERE 条件を slug ルックアップに入れる必要なし(そもそも `users.deleted_at` 列は schema にあるが Phase 3 の機能では UPDATE しない)。Phase 4 で退会 flow 設計時に同時にハンドル。
- **D-38**: **`/<slug>` URL の挙動分岐**:
  - 認証済 + 自分の inbox(`$identity->user_id == $inbox->user_id`)= 送信フォーム表示 + ヘッダに「これはあなたの受信箱です(/dashboard で受信一覧)」リンク表示。送信は許可(D-33)。
  - 認証済 + 他人の inbox = 通常の送信フォーム。
  - 未認証 = 通常の送信フォーム(D-13 の挙動)。
- **D-39**: **Phase 3 verify-phase の human-needed 項目**:Phase 2 と同様、「実際の Bluesky アカウントから tamabox.emomie.com に送信して開封 → SSR 開示」までの E2E は Phase 4 デプロイ後に持ち越す(`status: human_needed`)。Phase 3 内では HTTP mock + DB 検証 + integration test での `Client::addMockResponse()` パターンで code-level 7-truth verify を狙う(Phase 2 verifier で確立した Level 4 data-flow trace パターン踏襲)。
- **D-40**: **要件書き換え (PROJECT.md / REQUIREMENTS.md / ROADMAP.md) は CONTEXT.md と同一 commit**。traceability 一貫性のため。commit message: `docs(03): capture phase context + scope rewrite (slug = SNS handle auto-derive)`.

### Claude's Discretion

以下は Claude が実装時に判断する範囲:
- slug 衝突 suffix の上限値(`-2`〜`-100` 程度を想定、超過時の `<prefix>-<did_hash8>` フォールバック動作)
- `welcome_message` の最大長(1000 文字程度を想定するが厳密値は planner 判断)
- 送信フォームの session key 名(`pending_message_body`、`pending_message_inbox_id` 等)
- `/dashboard` 内のセクション分割粒度(タブ vs 縦並び、CSS で見せ方を整える)
- ページネーションのページサイズ(20 件 / ページを既定とするが planner / executor 判断で微調整)
- 開封ボタンクリックハンドラの JavaScript 実装(progressive enhancement: JS なしでもフォーム submit で動作するか / JS 必須 fetch ベースか)
- avatar SVG のデザイン(シンプルな匿名 silhouette、色は tamabox.css のニュートラル trim)
- 通報・ブロック 501 stub controller の class 配置(`MessagesController` / `ReportsController` / `BlocksController` のどれに置くか — Phase 4 plan-phase で決まる流れに合わせる)
- slug 履歴解決 (D-04) の DDL: `inboxes.slug_previous` 1 列 vs `inbox_slug_history` テーブル新設のどちら(planner 判断 — 改名 1 世代分救えば十分なら 1 列で済む)
- flash message の文言・タイミング(`Flash->success` / `Flash->error` の使い分け、CakePHP 標準パターンに従う)
- E2E test の HTTP mock fixture 構成(Phase 2 で確立した `Client::addMockResponse()` パターンの拡張)

</decisions>

<canonical_refs>
## Canonical References

**Downstream agents (researcher / planner / executor) MUST read these before planning or implementing.**

### Discovery (external repo, VPS 内 local clone)
- `/home/claude/projects/ssr-box-discovery/DB-SCHEMA.md` §3 (`inboxes`) / §4 (`messages`) — slug regex / ssr_probability range / sender_*_snapshot 列の DDL 確定仕様。Phase 3 plan の **single source of truth**。
- `/home/claude/projects/ssr-box-discovery/DESIGN.md` Q4 (SSR 仕様: 送信時確定) / Q5 (モデレーション方針) — Phase 3 の SSR メカニクス + 通報事後レビュー方針の根拠。
- `/home/claude/projects/ssr-box-discovery/ASSUMPTIONS.md` V1 (確率名前バレで悪意自己抑止) / E1 (送信前同意 UI で「確率名前バレ」明示) / F2 (乱数監査性) — Phase 3 の同意 UI と SSR 判定アルゴリズムの根拠。

### Project-Level
- `.planning/PROJECT.md` — Core Value (V1 仮説) と Out of Scope。**本 Phase の commit で書き換える対象**(scope_changes セクション参照)。
- `.planning/REQUIREMENTS.md` — AUTH-03, INBOX-01..03, INBOX-06, MSG-01..07 の正規化。**本 Phase の commit で INBOX-01 / INBOX-06 を書き換える**。
- `.planning/ROADMAP.md` — Phase 3 success criteria 7 項目が verify-phase のチェックリスト。**本 Phase の commit で #1 を書き換える**。
- `.planning/STATE.md` — Phase 2 VERIFIED 確認、sticky notes 5 件(特に #5 の `refreshTokenIfExpired()` を Phase 4 へ defer する旨は本 CONTEXT.md D-30 で明記)。
- `.planning/phases/01-foundation-schema/01-CONTEXT.md` — Phase 1 で確定した schema decisions(messages テーブルに `updated_at` なし、`ssr_seed VARCHAR(64) NOT NULL` の制約)
- `.planning/phases/02-bluesky-oauth-identity/02-CONTEXT.md` — Phase 2 で確定した Authentication / OAuth client 構造、`upsertBlueskyIdentity` の挙動、501 stub パターン、`queryString/sessionString` helper 規約。

### Codebase State
- `.planning/codebase/STACK.md` — CakePHP 4.5 / PHP ^8.0 / MySQL 8.0 / `bin/cake` available
- `.planning/codebase/ARCHITECTURE.md` — Middleware pipeline (ErrorHandler → Asset → Routing → BodyParser → CSRF → Authentication)
- `.planning/codebase/CONVENTIONS.md` — PSR-4 `App\` → `src/`、naming 規約、`declare(strict_types=1)` 必須
- Phase 2 の Executor-discovered decisions(STATE.md `## Accumulated Context` 参照)— `protected array $fixtures` の typed-property 衝突、`$request->getQuery()` の string 化 helper、`Client::addMockResponse()` の HTTP mock パターン

</canonical_refs>

<code_context>
## Existing Code Insights

### Reusable Assets (Phase 2 完了時点)

- **`src/Controller/UsersController.php` `dashboard` action** — 現在は最小プレースホルダ(「ようこそ、〇〇 さん」相当)。Phase 3 で受信一覧 + 受信箱設定の本体に拡張。`templates/Users/dashboard.php` を本体化。
- **`src/Controller/AuthController.php`** — `startBluesky` / `logout` 既存。送信フォームの未認証時挙動 (D-13) は `startBluesky` を再利用、session に `pending_message_body` を入れる呼び出し形に拡張。
- **`src/Service/OAuth/Bluesky/BlueskyOAuthClient.php`** — `resolveProfile()` 既存。Phase 3 では使わない (D-29 で `*_cached` 利用)、ただし将来 token refresh + 再取得が必要になったら call site 用意可。
- **`src/Model/Table/UserIdentitiesTable.php` `upsertBlueskyIdentity`** — Phase 2 で `handle_cached` / `avatar_url_cached` / `profile_url_cached` / `last_synced_at` 更新ロジック実装済。Phase 3 では同 method を拡張して `inboxes.slug` の自動再計算(D-03)を追加。
- **`src/Model/Table/UsersTable.php` `findByDid`** — Phase 2 で実装済。Phase 3 inbox 作成時の用ユーザ参照に流用。
- **`src/Model/Entity/Inbox.php` + `src/Model/Table/InboxesTable.php`** — Phase 1 で bake 済。schema は揃っている(`slug` / `ssr_probability` / `welcome_message` / `is_accepting`)。Phase 3 で validation rules + `findBySlug` finder + slug 自動付与 logic を追加。
- **`src/Model/Entity/Message.php` + `src/Model/Table/MessagesTable.php`** — Phase 1 で bake 済(timestamp behavior は modified なし、`updated_at` 列なし)。Phase 3 で send-time logic(`is_ssr` / `ssr_probability_at_send` / `ssr_seed` / sender snapshot)を追加。
- **`webroot/css/tamabox.css`** — Phase 2 で 218 行ベース。Phase 3 で受信一覧、開封ボタン、SSR 結果バナー、送信フォーム、設定フォームのスタイルを追記。
- **`templates/Pages/home.php`** — Phase 2 で「Bluesky でログイン」CTA に書き換え済。Phase 3 では LP に「公開受信箱を作成しよう」「他人の受信箱に送信しよう」導線を追加(全件 listing は MVP 範囲外、URL 直打ち前提)。
- **CSRF middleware + Authentication component** — Phase 2 で wired 済。Phase 3 controllers は `$this->Authentication->getIdentity()` で認証ユーザを取得可能(sticky note #1)、CSRF も自動で kicks(送信フォーム POST には自動でフィールド埋め込み)。
- **`queryString` / `sessionString` helper pattern** — Phase 2 OauthController で確立。Phase 3 controllers でも `$request->getQuery()` / session 読み取り時に同じ pattern を踏襲(phpstan level 8 対応、sticky note #3)。
- **`Client::addMockResponse()` HTTP mock pattern** — Phase 2 verifier 確立。Phase 3 では使う場面ほぼなし(外部 API call が D-29 で消えた)、ただし profile snapshot 異常系の test で再利用余地。

### Established Patterns

- **Configure reads**: `Configure::read('Security.serverSecret')` を SSR seed 計算で利用 (D-09)。
- **Phinx migration**: D-04 の slug 履歴解決のため、Phase 3 で 1 件追加 migration が必要(`inboxes.slug_previous` 1 列 or `inbox_slug_history` テーブル、planner 判断)。
- **Test fixture**: `tests/Fixture/MessagesFixture.php` / `InboxesFixture.php` は Phase 1 で bake 済(schema-valid な手書き fixture に整形済、Phase 1 deviation #1)。Phase 3 で送信 / 開封 integration test 用に追加 records を入れる。
- **`TableLocator::allowFallbackClass(false)` 維持**:新規 Controller / Service には Table クラスを明示 inject する。

### Integration Points

- **`config/routes.php`**: 新規ルート 6 系統(planner で詳細詰める):
  - `GET /<slug>` → `MessagesController::send($slug)` (送信フォーム表示)
  - `POST /<slug>` → `MessagesController::send($slug)` (送信処理)
  - `GET /dashboard` → `UsersController::dashboard()` (受信一覧 + 設定)
  - `POST /dashboard/settings` → `InboxesController::update()` (受信箱設定保存)
  - `POST /dashboard/messages/<id>/open` → `MessagesController::open($id)` (開封操作)
  - `POST /report/<message_id>` → `MessagesController::report($id)` (501 stub)
  - `POST /block/<sender_user_id>` → `BlocksController::create($id)` (501 stub)
  - 既存 `/dashboard` (Phase 2) は同 URL のまま機能拡張。
- **`templates/`**:
  - `templates/Messages/send.php` (送信フォーム新規)
  - `templates/Messages/send_done.php` (送信完了画面新規)
  - `templates/Users/dashboard.php` (Phase 2 の最小版を機能拡張)
  - `templates/Inboxes/settings.php` (受信箱設定フォーム新規)
- **`webroot/img/default-avatar.svg`** 新規追加(D-31 fallback)。
- **`composer.json`**: 新規依存なし(Phase 2 で必要なものは揃っている)。

</code_context>

<specifics>
## Specific Ideas

- **送信フォーム未認証時の本文 session 保持** (D-13): session key は `pending_message_body` + `pending_message_inbox_id` の 2 つ。OAuth callback 完了後に `redirect /<slug>?restored=1` で送信フォームに戻し、`session->consume('pending_message_body')` で 1 度だけ復元(再ログインで増殖しないように consume パターン)。
- **段階的開示の DOM 構造** (D-25): `<div class="message-row" data-msg-id="...">` 内に `<div class="body-preview">` (常に表示)、`<div class="body-full" hidden>` (展開で表示)、`<button class="open-btn" data-action="open">` (クリックで `opened_at` UPDATE)、`<div class="ssr-reveal" hidden>` (SSR 結果)。プログレッシブ・エンハンスメントとして JS なしでも動く形を目指す(`<details>` タグ + `<form>` submit でフォールバック可能なら最良 — planner / executor 判断)。
- **slug 衝突 suffix の DB 戦略** (D-02): `inboxes` テーブルの `slug` UNIQUE 制約があるため、INSERT 時に `Cake\Database\Exception\DatabaseException` を catch して `-2`、`-3`... と suffix を増やしながら retry。Phase 2 の `upsertBlueskyIdentity` での DatabaseException catch パターンと同型。
- **Bluesky handle 正規化規則の境界ケース** (D-01): `did:plc:abc...` だけが返ってきて handle が空文字の場合(BlueskyAS の異常系)→ slug は `user-<did_hash8>` フォールバック。`handle_cached` が `_atproto.example.com` 的なドメイン専用 handle の場合 → 同様に `user-<did_hash8>`。実装は `BlueskyHandleSlugifier` 専用ヘルパー関数(`src/Service/Inbox/SlugDeriver.php` あたり、planner 判断)。
- **SSR 判定の re-verify CLI ツール候補**(F2 監査性):`bin/cake ssr:verify <message_id>` で DB の `ssr_seed` + `server_secret` から `is_ssr` を再計算して照合する CLI を Phase 3 で追加すると運用上強い。Claude's Discretion(planner 判断、外しても可)。
- **welcome_message の表示位置**:`/<slug>` 送信フォームの上部に「<受信者の display_name> から:」+ welcome_message 本文を表示。空 (NULL) のときは表示自体省略。XSS 防御は `h()` 必須(プレーンテキストのみ、Markdown 不可)。
- **is_accepting=false 時の挙動**:`/<slug>` で送信フォームを出さず、「現在この受信箱は受け付けていません」テキストのみ表示。POST 直接叩いてもサーバ側で 422 拒否。
- **テスト戦略**:Phase 3 で integration test ヘビー(controller test + ORM data-flow test)。Phase 2 の `Client::addMockResponse()` は不要だが、SSR 判定の deterministic テスト(`hexdec` ベースなので fixture seed で hit/miss を選べる)を unit test で重視。

</specifics>

<deferred>
## Deferred Ideas

- **`UserIdentitiesTable::refreshTokenIfExpired()` の実装と call site 配置** (Phase 2 sticky note 5) → Phase 4。Phase 3 では cached snapshot のみで成立するため不要。Phase 4 でブロック・通報の運用 UI を実装するときに Bluesky 側の最新プロフィール再取得が必要になれば call site を追加する。
- **退会(account deletion)flow 全般** (MOD-03 / `users.deleted_at` の UPDATE) → Phase 4。Phase 3 では `deleted_at IS NULL` の WHERE 条件は入れない(Phase 3 の機能で `deleted_at` を立てる導線がないため、結果として全行がクエリ対象)。
- **slug 改名複数世代追跡**:Phase 3 では 1 世代前の slug のみ救済(`slug_previous` 1 列または最近 1 件の history 行)。複数回改名した場合の N 世代前 slug は救わない(MVP 範囲外、改名再帰の悪用を避ける)。
- **送信履歴閲覧 UI(送り手側)**:送り手が自分が過去にどの受信箱に何を送ったかを一覧する機能 → 永遠に Out of Scope(送り手の identity が tamabox に蓄積されると別種のプロフィールサービス化する、コア体験のスコープを超える)。
- **送り手への SSR 結果通知**:メール / Push / Bluesky DM などで送り手に「あなたが送ったメッセージは SSR hit でした」を通知する機能 → v2 以降。Phase 3 で『送り手は永遠に知らない』(D-19) を選んだ世界観の前提になる。
- **無限スクロール / リアルタイム通知**:WebSocket / Server-Sent Events / Long polling は Lolipop 共有鯖で運用困難 → 永遠に Out of Scope。新着確認は手動リロードのみ。
- **通報事後レビュー管理画面 / 通報統計**:Phase 4 でも運用者向け管理画面は最小(直接 DB クエリ運用想定)、専用 admin UI は v2 以降。
- **welcome_message の Markdown / リンク化**:現状プレーンテキスト固定。Markdown / 自動リンクは Out of Scope(本文と同じ理由、XSS 簡略化)。
- **メッセージ送信のレート制限**:PROJECT.md Out of Scope 明記。MVP 不採用、運用上必要になったら別 Phase。
- **`bin/cake ssr:verify` CLI**:F2 監査性のための運用 CLI。Phase 3 で実装するか、運用時に手動 PHP で再計算するか — planner 判断。

</deferred>

---

*Phase: 03-inbox-message-ssr-reveal*
*Context gathered: 2026-04-26 (interactive discuss-phase via Discord, 6 areas, 40 decisions captured)*
