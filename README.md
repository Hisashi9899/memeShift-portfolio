# memeShift

介護施設向けのシフト自動作成 Web アプリケーションです。
スタッフの希望休・勤務条件・必要人数・相性ルールを入力すると、夜勤→明け→休みの連鎖や連勤上限といった現場固有の制約を考慮した勤務表を生成します。

このリポジトリは技術評価用のサニタイズ済みスナップショットです。実運用の設定・ドメイン・認証情報・個人情報は含まれていません。

---

## 主な機能

| 機能 | 概要 |
|------|------|
| スタッフ管理 | 勤務可能日、雇用形態、スキル、ソート順の一括編集 |
| シフト種別 | 日勤・早番・遅番・夜勤・明けなどの時刻・色定義 |
| 必要人数設定 | シフト種別 × 曜日/日付ごとの必要人数、日別上書き |
| 希望休・希望シフト | スタッフごとの休み希望・固定シフト |
| 相性ルール | 同時勤務の可否（組み合わせ禁止） |
| 自動生成 | 制約充足 + 公平性評価による月次勤務表の生成 |
| 自動修正 | 違反箇所の局所修復、崩しルートの探索・適用 |
| 分析 | サマリ、タイムライン、制約違反一覧、年次・成長統計 |
| エクスポート | CSV / PDF（IPA フォントによる日本語出力） |
| 失敗理由の説明 | 生成不可時に不足条件を自然言語で提示 |

---

## アーキテクチャ

```
┌──────────────┐   HTTP    ┌──────────────┐   FastCGI   ┌──────────────────┐
│  Next.js 14  │ ────────▶ │    Nginx     │ ──────────▶ │  Laravel 10      │
│  (TypeScript)│           │              │             │  (PHP 8.2)       │
└──────────────┘           │              │             │  Interface層      │
                           │              │             │  Service層        │
                           │              │             └────────┬─────────┘
                           │              │                      │
                           │              │             ┌────────▼─────────┐
                           │              │             │  MySQL 8         │
                           └──────────────┘             └──────────────────┘
                                        │
                              ┌─────────▼──────────┐
                              │ logic (PHP CLI)     │
                              │ 依存ゼロの生成エンジン │
                              └────────────────────┘
```

- **frontend/** — Next.js 14 (App Router) + TypeScript + Tailwind CSS
- **backend/** — Laravel 10。`app/Interface/Controllers` に HTTP 層、`app/Services` にドメインサービスを配置
- **logic/** — Laravel に依存しない単体実行可能なスケジューリングエンジン（`logic/src/ScheduleEngine.php`）
- **shared/** — backend と logic が共有する DTO（Composer path repository）
- **docker/** — Laravel / Next.js / logic の Dockerfile と Nginx 設定

### ディレクトリ構成

```
.
├── backend/                 Laravel アプリケーション
│   ├── app/
│   │   ├── Interface/       Controller / Request / Resource
│   │   ├── Services/        生成・分析・修復・エクスポート
│   │   └── Models/          Eloquent モデル
│   ├── database/            migrations / seeders
│   ├── routes/api.php       REST API
│   └── tests/               PHPUnit
├── frontend/                Next.js アプリケーション
│   ├── src/app/             login / dashboard / settings / preferences / statistics / growth
│   ├── src/components/      UI コンポーネント
│   └── e2e/                 Playwright E2E
├── logic/                   スタンドアロン生成エンジン
│   ├── src/ScheduleEngine.php
│   └── tests/               等価性テスト
├── shared/                  共有 DTO
├── docker/                  Dockerfile / Nginx 設定
└── docker-compose.yml
```

---

## スケジューリングの設計

生成は「制約充足による構築」と「違反の局所修復」の二段構えです。

### 1. マスタデータの読み込み

スタッフ、シフト種別、必要人数、日別上書き、希望休、固定シフト、相性ルール、祝日数を一括で読み込みます（`MasterDataLoader`）。

### 2. 制約を意識した構築

- **希望休の反映** — 希望休は最初に確定し、以降の割当から除外
- **夜勤ブロック** — 夜勤を割り当てたら翌日は「明け」、その翌日は休みとしてブロックを形成
- **シフト割当** — 必要人数と勤務可能条件を満たす候補をスコアリングして割当（`ShiftAssigner`）
- **公休日の配分** — 月内の公休数が偏らないよう残りの休みを分散（`OffDayDistributor`）

### 3. 公平性と制約の評価

- 夜勤回数の均等化
- 連勤上限（既定 5 日）
- 夜勤→明け→休みの連鎖
- 遅番→早番、日勤→早番の禁止
- 同時勤務の相性ルール

違反は `ConstraintChecker` が検出し、`ConflictRepairer` が局所的に修復します。修復しきれない場合は `ScheduleFailureExplanationService` が「どの条件がどれだけ不足しているか」を説明します。

### 4. 自動修正（崩しルート）

`BreakRouteService` は、現在の勤務表に対する「崩し」の候補を生成し、違反数・公平性スコアの改善量で順位付けします。適用は明示的な操作（確認ボタン）でのみ実行される設計です。

> 生成ロジックは評価目的で公開しています。詳細は [LICENSE](LICENSE) を参照してください。

---

## セットアップ

### 前提

- Docker / Docker Compose
- （ローカルで直接動かす場合）PHP 8.2+、Composer 2、Node.js 20+

### 1. 環境変数

```bash
cp .env.example .env
```

`.env` の以下の値は **必ず変更** してください。

| 変数 | 説明 |
|------|------|
| `DB_ROOT_PASSWORD` / `DB_PASSWORD` | MySQL のパスワード |
| `APP_KEY` | `php artisan key:generate --show` で生成 |
| `ADMIN_USERNAME` / `ADMIN_PASSWORD` | 初回シードで作成する管理者。未設定なら管理者は作成されません |
| `APP_URL` / `SANCTUM_STATEFUL_DOMAINS` / `SESSION_DOMAIN` / `FRONTEND_URL` | 公開するドメインに合わせて変更 |

### 2. 起動（本番相当）

```bash
docker compose --profile prod up -d --build
docker compose exec laravel php artisan migrate --seed
```

`http://localhost` でアクセスできます。

### 3. 起動（開発）

```bash
docker compose -f docker-compose.yml -f docker-compose.dev.yml --profile dev up -d --build
docker compose exec laravel-dev php artisan migrate --seed
```

`http://localhost:8081` でアクセスできます（MySQL は `localhost:3307`）。

### 4. フロントエンドのみローカル実行

```bash
cd frontend
npm ci
npm run dev
```

---

## テスト

```bash
# backend
cd backend && composer install && php artisan test

# logic engine
cd logic && composer install && composer test

# frontend build
cd frontend && npm ci && npm run build

# E2E（認証情報は環境変数で渡す）
cd frontend
E2E_BASE_URL=http://localhost:8081 \
E2E_USERNAME=<username> \
E2E_PASSWORD=<password> \
npm run test:e2e
```

CI（`.github/workflows/deploy.yml`）では lint・PHPUnit・Next.js ビルドを実行します。デプロイジョブは `ENABLE_DEPLOY` リポジトリ変数を `true` にした場合のみ動作します。

---

## セキュリティに関する注記

- 認証は Laravel Sanctum のトークン方式。ログインにはレート制限（10 回/分）を設定
- 管理者アカウントの初期パスワードはリポジトリに含めず、環境変数からのみ作成
- DB のパスワードにコード上のフォールバック値を持たない
- `.env` は `.gitignore` / `.dockerignore` の両方で除外
- 本番プロファイルでは MySQL のポートをホストに公開しない（開発用 override のみ公開）

---

## ライセンス

本リポジトリは **評価目的（Evaluation Only）** で公開しています。商用利用・再配布・派生開発は許可されません。詳細は [LICENSE](LICENSE) を参照してください。

同梱の IPA フォントについては [THIRD_PARTY_NOTICES.md](THIRD_PARTY_NOTICES.md) を参照してください。
