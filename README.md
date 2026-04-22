# tamabox — SSR箱

確率で送信者のSNSハンドル・アイコン・プロフリンクが露出する匿名メッセージ箱。
X・Bluesky等のクリエイター向け。

**Production**: `https://tamabox.emomie.com`
**Stage**: 初期実装（discovery フェーズ完了、build フェーズ開始）

---

## 概要

- 受け手は Bluesky OAuth で認証、カスタマイズ可能な受信箱を持つ
- 送信者も SNS OAuth 必須（完全匿名不可）
- メッセージごとに確率（デフォルト10%）で送信者 identity が開示される「SSR」仕組み
- 確率0%設定で「普通の匿名箱」としても機能
- ブロック・通報機能、論理削除

コアメカニクス: 悪意送信者への抑止力 + 好意送信者の祝福体験。

---

## 技術スタック

| 項目 | 採用 |
|---|---|
| 言語 / フレームワーク | PHP / CakePHP 4.5 |
| DB | MySQL 8.0 |
| ホスティング | ロリポップ（共有レンタルサーバー） |
| 認証 | Bluesky (AT Protocol) OAuth 2.0、後続で X 追加 |
| ID | UUID (CHAR(36)) |

---

## 設計ドキュメント

**すべての設計判断は [ssr-box-discovery](https://github.com/emomie/ssr-box-discovery) リポジトリに記録**。

- [ASSUMPTIONS.md](https://github.com/emomie/ssr-box-discovery/blob/main/ASSUMPTIONS.md) — 8カテゴリ仮説棚卸し
- [DESIGN.md](https://github.com/emomie/ssr-box-discovery/blob/main/DESIGN.md) — Q1-Q5 基本設計決定・技術スタック
- [DB-SCHEMA.md](https://github.com/emomie/ssr-box-discovery/blob/main/DB-SCHEMA.md) — MySQL 8.0 向けスキーマ v0.2
- [AUTH-FLOW.md](https://github.com/emomie/ssr-box-discovery/blob/main/AUTH-FLOW.md) — Bluesky OAuth 認証フロー設計 v0.1

---

## セットアップ

### 前提

- PHP 7.4+（本番ロリポップは 8.0+ 想定）
- Composer
- MySQL 8.0

### 初回セットアップ

```bash
# 依存インストール
composer install

# 環境変数ファイル
cp config/app_local.example.php config/app_local.php
# config/.env を作成して秘匿値を設定

# ES256 鍵ペア生成（OAuth 用、.gitignore 済）
openssl ecparam -genkey -name prime256v1 -noout -out config/keys/private.key
openssl ec -in config/keys/private.key -pubout -out config/keys/public.key
chmod 600 config/keys/private.key
chmod 644 config/keys/public.key

# DB マイグレーション
bin/cake migrations migrate
```

### ローカル開発サーバ

```bash
bin/cake server
```

---

## ディレクトリ構成

```
tamabox/
├── config/
│   ├── keys/           # OAuth 用 ES256 鍵ペア（.gitignore 済）
│   ├── .env            # 環境変数（.gitignore 済）
│   ├── app.php         # 公開設定
│   └── app_local.php   # 環境依存設定（.gitignore 済）
├── src/
│   ├── Controller/
│   ├── Model/
│   ├── Service/        # ビジネスロジック（OAuth、SSR判定等）
│   └── ...
├── templates/
├── webroot/            # ← ロリポップで公開されるのはここだけ
│   └── oauth/          # OAuth 公開エンドポイント
└── tests/
```

---

## デプロイ

ロリポップの Git deploy を使用予定。本番反映は `main` への push をトリガー。

---

## ライセンス

※未定（公開範囲・商用利用方針は discovery 側 Vi2 仮説検証後に決定）
