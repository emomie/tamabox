# Phase 2: Bluesky OAuth & Identity — Discussion Log

> **Audit trail only.** Do not use as input to planning, research, or execution agents.
> Decisions are captured in CONTEXT.md — this log preserves the alternatives considered.

**Date:** 2026-04-23
**Phase:** 02-bluesky-oauth-identity
**Areas discussed:** JWT/OAuth ライブラリ選定 / Phase 2-3 境界 (inbox 作成の扱い)、および altotoo 参照実装から派生した Layer 構造 / Endpoint 解決 / Scope / Handle 入力 UX の 4 サブエリア

---

## Gray Area 選定

| Option | Description | Selected |
|---|---|---|
| 1. JWT/OAuth ライブラリ選定 | `web-token/jwt-framework` vs `firebase/php-jwt` vs 自前実装 vs altotoo 踏襲 | ✓ |
| 2. マルチプロバイダ抽象化のタイミング | 最初から `OAuth\Provider\{Bluesky,Twitter}` interface 前提 vs Bluesky 後追い refactor | |
| 3. Handle resolution DNS 戦略 | DNS TXT + DoH fallback 最初から vs `dns_get_record` 先行 | |
| 4. Phase 2 / Phase 3 の境界 (inbox 作成の扱い) | 厳密分離 vs 自動仮作成 vs オンボーディング先食い | ✓ |
| 5. OAuth エラー UX / 失敗モード | 失敗モードごとの露出度設計 | |

**ユーザ選択:** 1, 4

---

## Area 1: JWT/OAuth ライブラリ選定

### 初回提示

| Option | Pros | Cons | Selected |
|---|---|---|---|
| (A) `web-token/jwt-framework` | フル機能、JWK/DPoP/private_key_jwt を一貫 API で扱える。AUTH-FLOW.md 第1候補 | symfony 系 deps が重い | |
| (B) `firebase/php-jwt` + 手書き JWK/DPoP | 軽量・安定 | DPoP/JWK 変換は自前、仕様追従コストあり | |
| (C) ほぼ自前 | 依存最小 | DPoP nonce 周りの罠を全て自分で踏む | |
| (D) altotoo に合わせる | 運用実績を拾える | altotoo コード要確認 | ✓ |

**ユーザ選択:** (D) altotoo 踏襲
**付帯条件:** altotoo 確認手段についてフォローアップ

### Followup: altotoo 参照手段

| Option | Description | Selected |
|---|---|---|
| (i) VPS に SSH 認証情報を預けて俺が fetch | Claude が直接 altotoo から取得 | |
| (ii) 後日 PC からコピペで渡す | 一旦 discuss を中断 | |
| (iii) 暫定で (A) を採用し plan 前に altotoo 確認 | discuss を止めない | |
| (iv) ユーザがスマホで altotoo から DL | (本セッションで実施) | ✓ |

**実際の進行:** ユーザが altotoo から 3 ファイルをスマホで DL → Discord 経由で Claude に共有。
- `BlueskyOauthComponent.php` (24KB) — OAuth ロジック本体
- `LoginController.php` (7KB) — OAuth 開始 + callback
- `app_local.php` (3KB) — Lolipop DB 接続 (参考)

**判明事項:** altotoo は **JWT ライブラリを使わず、`openssl_sign` + DER→Raw 変換 + 手書き base64url の純自前実装**。約 660 行 (投稿機能含む) で、OAuth 認証部分は ~300 行。

**結論:** **(C) ほぼ自前**。altotoo のコードをそのまま参照実装として踏襲する形で、本番実績ある自前コードをコピペ移植する。`.planning/references/altotoo/` に 3 ファイルを永続保存。

**User's choice:** C (altotoo 踏襲による自前)
**Notes:** altotoo の `BlueskyOauthComponent.php` は Controller Component 配置だが、tamabox では Service 層に分離する (Area 1-a 参照)

---

## Area 4: Phase 2 / Phase 3 の境界

| Option | Description | Selected |
|---|---|---|
| (A) 厳密分離 | Phase 2 は users + user_identities のみ、OAuth 成功後は `/dashboard` プレースホルダ、inbox slug 選択は Phase 3 | ✓ |
| (B) 自動仮作成 | Phase 2 で handle ベースの slug で inbox 自動生成、Phase 3 で slug 変更 UI | |
| (C) オンボーディング先食い | Phase 2 に slug 選択 UI だけ含める、ダッシュボードは Phase 3 | |

**User's choice:** A
**Notes:** Phase 2 success criteria 7 項目に inbox の話が含まれないため、要件トレーサビリティ上 A が自然。Phase 2 verify は「OAuth が通って DB に users + user_identities 行がある」で完結できる。

---

## altotoo 実装から派生した 4 サブエリア

altotoo のソース (BlueskyOauthComponent + LoginController) と AUTH-FLOW.md の仕様に齟齬があったため、追加で 4 つの決定をまとめて行う。

### Q-a: Layer 構造

| Option | Description | Selected |
|---|---|---|
| (a1) altotoo 準拠 = Component 一枚 | `Controller/Component/BlueskyOauthComponent` に全部 | |
| (a2) Service 層分離 | `src/Service/OAuth/Bluesky/` 配下に分割、Controller は薄く | ✓ |

**User's choice:** a2 (おまかせ → 推奨採用)
**Rationale:** 単体テスト容易、AUTH-06 interface 抽象化がやりやすい、DB 永続層 (altotoo と違い tamabox は DB 保存) を差し込みやすい

### Q-b: Endpoint 解決

| Option | Description | Selected |
|---|---|---|
| (b1) 静的 (altotoo 準拠) | `.env` ハードコード、bsky.social 前提 | ✓ |
| (b2) 動的 (AUTH-FLOW.md 準拠) | PDS → AS metadata 動的 resolve、third-party PDS 対応 | |

**User's choice:** b1 (おまかせ → 推奨採用)
**Rationale:** MVP は bsky.social ユーザ前提。third-party PDS 対応は Out of Scope 境界に近い

### Q-c: Scope

| Option | Description | Selected |
|---|---|---|
| (c1) `atproto transition:generic` | altotoo 同じ、legacy API も叩ける | ✓ |
| (c2) `atproto` のみ | 認証のみ最小権限 | |

**User's choice:** c1 (ユーザ明示決定: 「bsky への投稿機能、あとから生やしたいから c1 で」)
**Rationale:** 将来 Bluesky 投稿機能を追加する可能性。scope を後から拡張するには全ユーザ再認可が必要なので baking-in する。投稿機能自体は Phase 2 では実装せず DEFERRED。

### Q-d: Handle 入力 UX

| Option | Description | Selected |
|---|---|---|
| (d1) altotoo 準拠 = tamabox 側で聞かない | `login_hint` なし、Bluesky AS 画面でユーザが handle 入力 | ✓ |
| (d2) AUTH-FLOW.md 準拠 = 自前で聞く | DNS TXT / well-known 解決、`login_hint` 付与 | |

**User's choice:** d1 (おまかせ → 推奨採用)
**Rationale:** altotoo 運用実績あり、DNS 解決の罠 (Lolipop `dns_get_record` 動作不確実) を回避、実装 ~100 行削減。UX もシンプル (ボタン 1 → Bluesky 画面 → 戻る)。

---

## Claude's Discretion (CONTEXT.md に記載)

- エラー UX の具体文言・再試行導線
- `config/bluesky.php` の具体構造
- Flash message / View プレースホルダ文言
- Test fixture 構成
- `bin/cake oauth:setup_keys` CLI ツールの有無
- Controller / Service 内の細かい命名・DI パターン

---

## Deferred Ideas (CONTEXT.md `<deferred>` に記載)

- Bluesky 投稿機能 (scope は確保、実装は v1.1+)
- X (Twitter) OAuth 追加 (v2)
- third-party PDS / did:web 対応
- TOKEN_ENC_KEY ローテーション (`token_enc_key_version` カラム)
- Bluesky 側トークン revoke on logout
- Refresh token 自動更新・並行リクエスト保護 (Phase 3 で)
- AS metadata 動的解決
- Handle → DID 自前解決 (必要になった時点で復活)
- `bin/cake oauth:setup_keys` CLI ツール
