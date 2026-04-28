-- やまふじ農園 お米 注文予測 - プッシュ通知用スキーマ
-- 既存の DB に対して追加で実行してください（phpMyAdmin の SQL タブから）。

SET NAMES utf8mb4;

-- ---------------------------------------------
-- 担当者マスタ
-- ---------------------------------------------
CREATE TABLE IF NOT EXISTS staff (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name         VARCHAR(100) NOT NULL,
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_staff_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------
-- プッシュ通知の購読情報
--   端末ごとに 1 レコード。endpoint がユニーク。
--   staff_id は「この端末は誰のもの？」という紐付け。
-- ---------------------------------------------
CREATE TABLE IF NOT EXISTS push_subscriptions (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    staff_id      INT UNSIGNED NULL,
    endpoint      TEXT NOT NULL,
    endpoint_hash CHAR(64) NOT NULL,
    p256dh        VARCHAR(255) NOT NULL,
    auth          VARCHAR(64) NOT NULL,
    user_agent    VARCHAR(255) DEFAULT NULL,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_used_at  DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_endpoint_hash (endpoint_hash),
    KEY idx_staff (staff_id),
    CONSTRAINT fk_push_staff FOREIGN KEY (staff_id) REFERENCES staff(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------
-- purchases に「誰が記録したか」を追加
-- ---------------------------------------------
ALTER TABLE purchases
    ADD COLUMN staff_id INT UNSIGNED NULL AFTER customer_id,
    ADD KEY idx_staff (staff_id),
    ADD CONSTRAINT fk_purchase_staff
        FOREIGN KEY (staff_id) REFERENCES staff(id) ON DELETE SET NULL;
