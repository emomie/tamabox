# tamabox — v1 Roadmap

**Granularity**: coarse (4 phases)
**Coverage**: 34/34 v1 requirements mapped
**Created**: 2026-04-22

## Milestone Goal

「確率で名前がバレる」メッセージ箱を `tamabox.emomie.com` で稼働させ、受け手が inbox をカスタマイズし、Bluesky OAuth 経由で認証された送り手が送信し、SSR 開封体験と通報/ブロック運用が成立するところまで。Core Value (V1 仮説) の検証に足る最小セットをロリポップ共有鯖上で運用可能にする。

## Phases

- [ ] **Phase 1: Foundation & Schema** — CakePHP skeleton 衛生化、`.env` ローダ有効化、PHP 8.0 整合、httpoxy 対策、MySQL 8.0 スキーマ migration 一式
- [ ] **Phase 2: Bluesky OAuth & Identity** — ES256 confidential client で PAR+DPoP+PKCE の OAuth 認証フロー、マルチプロバイダ抽象化、token 暗号化
- [ ] **Phase 3: Inbox, Message & SSR Reveal** — inbox 作成/設定、送信フォーム(OAuth 必須 + 同意 UI)、SSR 送信時確定、開封ガチャ演出
- [ ] **Phase 4: Moderation & Production Launch** — ブロック、通報、論理削除、退会時 snapshot 保持、本番デプロイ (`tamabox.emomie.com` + debug=false)

## Phase Details

### Phase 1: Foundation & Schema

**Goal**: ロリポップ共有鯖で安全に CakePHP 4.5 を本番運用するための土台と、v1 で必要な全テーブル(users / user_identities / inboxes / messages / reports / blocks)を migrate 可能な状態にする。
**Depends on**: Nothing (first phase)
**Requirements**: INFRA-02, INFRA-03, INFRA-04, INFRA-05, INFRA-07
**Success Criteria** (what must be TRUE):
  1. `composer.json` の PHP 要件が `^8.0` に整合し、PHP 8.0+ 環境で `composer install` が通る
  2. `.env` ローダが有効化され、`config/.env` から `SECURITY_SALT` / `DATABASE_URL` / `SERVER_SECRET` 等の秘匿値が CakePHP `env()` 経由で読める(コメントアウトが解除されている)
  3. `.htaccess` の httpoxy ブロック(`RequestHeader unset Proxy`)が有効化されている
  4. `bin/cake migrations migrate` を実行すると v1 スキーマ(users / user_identities / inboxes / messages / reports / blocks)が MySQL 8.0 上に UUID CHAR(36) PK + `utf8mb4_0900_ai_ci` 付きで作成される
  5. 上記スキーマに対応する `*Table` クラスがすべて明示的に `src/Model/Table/` 配下に存在し、`allowFallbackClass(false)` 下でも `TableLocator` から解決できる
**Plans**: 4 plans (4 waves)
  - [x] 01-01-infra-hygiene-PLAN.md — composer ^8.0 / .env loader / httpoxy / server_secret wiring / dev scripts (INFRA-02, INFRA-03, INFRA-05) [wave 1] — **done 2026-04-22**
  - [x] 01-02a-schema-root-PLAN.md — env bootstrap + MySQL 8.0.16 gate + 3 Phinx migrations (users / user_identities / inboxes) with UUID PK, CHECK constraints, FK cascade per DB-SCHEMA.md v0.2 (INFRA-04) [wave 2] — **done 2026-04-22**
  - [x] 01-02b-schema-dependents-PLAN.md — 3 Phinx migrations (messages / blocks / reports) + `bin/cake migrations migrate` + INFORMATION_SCHEMA introspection + rollback sanity (INFRA-04) [wave 3] — **done 2026-04-22**
  - [x] 01-03-table-classes-PLAN.md — bake 6 Table + Entity + Fixture + Test classes, fix UUID @property types, TableLocator smoke test under allowFallbackClass(false) (INFRA-07) [wave 4] — **done 2026-04-22**

### Phase 2: Bluesky OAuth & Identity

**Goal**: 受け手・送り手ともに Bluesky OAuth (AT Protocol, ES256 confidential client, PAR+DPoP+PKCE) で本人確認でき、1 ユーザー = 1 SNS アカウント制約が DB レベルで守られ、アクセストークンは暗号化 DB 格納される。
**Depends on**: Phase 1
**Requirements**: AUTH-01, AUTH-02, AUTH-04, AUTH-05, AUTH-06, AUTH-07, AUTH-08, AUTH-09
**Success Criteria** (what must be TRUE):
  1. 未登録ユーザーが Bluesky OAuth を経由してサインアップでき、`users` + `user_identities` 行が生成されてセッションが確立する
  2. 既存ユーザーが同じ Bluesky ハンドルで再ログインすると、最新の handle / avatar / profile_url が `user_identities` に同期される
  3. ログアウトを実行するとセッションが破棄され、保護リソースへの再アクセスでログイン画面に戻る
  4. `users` × `user_identities` の DB 制約で 1 ユーザーが同一プロバイダで複数 identity を持てない(ユニーク制約違反で拒否される)
  5. `/oauth/jwks.json` と `/oauth/client-metadata.json` が公開され、Bluesky AS から参照可能な JWKS/metadata を返す
  6. OAuth アクセス/リフレッシュトークンが `*_enc` 列に AES-GCM 暗号化で保存され、平文が DB に残らない
  7. OAuth プロバイダが interface 抽象化され、Bluesky 以外(将来の X)を追加するときに既存コードを書き換えずに追加クラスで実装できる構造になっている
**Plans**: TBD
**UI hint**: yes

### Phase 3: Inbox, Message & SSR Reveal

**Goal**: 受け手が自分の inbox を作って SSR 確率をカスタマイズし、Bluesky OAuth 済みの送り手が同意 UI を経て送信し、送信時に `is_ssr` と `ssr_seed` が確定、受け手の「開封」で SSR 露出が開示されるまでの **プロダクトのコア体験** をエンドツーエンドで成立させる。
**Depends on**: Phase 2
**Requirements**: AUTH-03, INBOX-01, INBOX-02, INBOX-03, INBOX-06, MSG-01, MSG-02, MSG-03, MSG-04, MSG-05, MSG-06, MSG-07
**Success Criteria** (what must be TRUE):
  1. 受け手はサインアップ時に任意の slug を選び `/<slug>` で inbox URL を持てる。後から slug / display_name を変更できる
  2. 受け手は自分の inbox の SSR 確率を 0〜100% のスライダ/数値で設定・保存でき、デフォルト 10% が適用される
  3. 未認証の訪問者が送信フォームにアクセスすると、Bluesky OAuth 同意を経なければ送信ボタンが押せない(`AUTH-03`)
  4. 送信フォームは「確率で名前がバレる可能性がある」旨を明示し、同意チェック/同意ボタンなしで送信 submit できない
  5. 送信成功時、`messages` 行には `is_ssr` と `ssr_seed = sha256(server_secret + message_id + created_at)` が刻まれ、送信者の handle / avatar / profile_url のスナップショットが保存される(絵文字含む本文も `utf8mb4_0900_ai_ci` で保存できる)
  6. 受け手のダッシュボードで自分の inbox の受信一覧が見え、未開封/開封済みが視覚的に区別される
  7. 未開封メッセージを「開封」操作すると `opened_at` が記録され、`is_ssr=true` のときだけ送信者 identity(handle / avatar / profile_url)が露出表示される
**Plans**: TBD
**UI hint**: yes

### Phase 4: Moderation & Production Launch

**Goal**: 通報・ブロック・論理削除・退会時 snapshot 保持の運用レーンを整え、`tamabox.emomie.com` 上で `debug=false` 固定の本番運用に乗せる。
**Depends on**: Phase 3
**Requirements**: INBOX-04, INBOX-05, MSG-08, MOD-01, MOD-02, MOD-03, MOD-04, INFRA-01, INFRA-06
**Success Criteria** (what must be TRUE):
  1. 受け手は任意の送信者 identity をブロックでき、ブロックされた送信者が同じ inbox に送信しようとすると送信フォームで「このユーザーには送信できません」とエラー表示される
  2. 受け手は任意の受信メッセージを `harassment` / `spam` / `illegal` / `other` の 4 カテゴリから通報でき、`reports` 行に記録される(送信時に AI 検閲や NG ワードフィルタはかからないことも確認できる)
  3. 受け手は任意の受信メッセージを論理削除でき、`deleted_at` がセットされて一覧から外れる(物理行は残る)
  4. 受け手ユーザーが退会(削除)しても、過去に送った message の送信者 snapshot(handle / avatar / profile_url)は DB 上に残る
  5. 通報された送信者でも、受け手側のブロックがない限り、別 inbox への送信は拒否されない(グローバル BAN は発生しない)
  6. `tamabox.emomie.com` で実サイトが稼働し、`debug=false` 固定 / DebugKit 無効化 / webroot 外 config / ES256 鍵は `config/keys/` に配置された状態で OAuth・送信・開封が本番から通る
**Plans**: TBD
**UI hint**: yes

## Progress

| Phase | Plans Complete | Status | Completed |
|-------|----------------|--------|-----------|
| 1. Foundation & Schema | 4/4 | Complete (01-01 ✓, 01-02a ✓, 01-02b ✓, 01-03 ✓); awaits verifier | 2026-04-22 |
| 2. Bluesky OAuth & Identity | 0/? | Not started | - |
| 3. Inbox, Message & SSR Reveal | 0/? | Not started | - |
| 4. Moderation & Production Launch | 0/? | Not started | - |

## Coverage Summary

| Category | Count | Phase(s) |
|----------|-------|----------|
| AUTH (9) | AUTH-01, 02, 04, 05, 06, 07, 08, 09 → P2 / AUTH-03 → P3 | 2, 3 |
| INBOX (6) | INBOX-01, 02, 03, 06 → P3 / INBOX-04, 05 → P4 | 3, 4 |
| MSG (8) | MSG-01〜07 → P3 / MSG-08 → P4 | 3, 4 |
| MOD (4) | MOD-01, 02, 03, 04 → P4 | 4 |
| INFRA (7) | INFRA-02, 03, 04, 05, 07 → P1 / INFRA-01, 06 → P4 | 1, 4 |

**Total**: 34/34 v1 requirements mapped, no orphans, no duplicates.

---
*Last updated: 2026-04-22 (Phase 1 wave 4 complete — all 4/4 plans done; awaits verifier)*
