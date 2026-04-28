<?php
/**
 * やまふじ農園 お米 注文予測 - 設定ファイル
 *
 * さくらレンタルサーバへ配置する前に、以下の値を実環境に合わせて編集してください。
 * 編集後はこのファイルを Git で公開しないように注意してください。
 */

declare(strict_types=1);

// ---- データベース接続情報（さくらのコントロールパネル → データベース で確認） ----
const DB_HOST = 'mysql<NN>.db.sakura.ne.jp'; // 例: mysql1234.db.sakura.ne.jp
const DB_NAME = 'your_db_name';              // 例: your_account_rice
const DB_USER = 'your_db_user';              // 例: your_account
const DB_PASS = 'your_db_password';          // データベース作成時のパスワード
const DB_CHARSET = 'utf8mb4';

// ---- アプリ設定 ----
const APP_NAME = 'やまふじ農園 お米 注文予測';
const APP_TIMEZONE = 'Asia/Tokyo';

// ---- プッシュ通知 (VAPID) ----
// 初回のみ index.php?p=vapid_setup にアクセスして鍵を生成し、その値をここに貼り付けてください。
// 以降は鍵を変えないでください（既存端末の購読情報が無効になります）。
const VAPID_SUBJECT      = 'mailto:your-email@example.com'; // 連絡先メール or サイト URL
const VAPID_PUBLIC_KEY   = '';                              // base64url 87 文字程度
const VAPID_PRIVATE_KEY  = '';                              // base64url 43 文字程度

// ---- タイムゾーン設定 ----
date_default_timezone_set(APP_TIMEZONE);

// ---- エラー表示（本番では false 推奨） ----
const APP_DEBUG = false;
if (APP_DEBUG) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
    ini_set('display_errors', '0');
}
