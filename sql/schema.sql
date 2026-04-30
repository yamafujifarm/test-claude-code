-- やまふじ農園 お米 注文予測 - スキーマ定義
-- さくらの phpMyAdmin から実行してください。
--
-- 既存の DB に対してプッシュ通知機能を後から追加する場合は、
-- schema.sql ではなく sql/schema_push.sql を実行してください。

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------
-- 顧客マスタ
-- ---------------------------------------------
CREATE TABLE IF NOT EXISTS customers (
    id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name             VARCHAR(100) NOT NULL,
    category         ENUM('business','regular','retail') NOT NULL,
    primary_staff_id INT UNSIGNED NULL,
    phone            VARCHAR(50) DEFAULT NULL,
    note             TEXT DEFAULT NULL,
    created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_category (category),
    KEY idx_name (name),
    KEY idx_primary_staff (primary_staff_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
-- 購入履歴
-- ---------------------------------------------
CREATE TABLE IF NOT EXISTS purchases (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    customer_id   INT UNSIGNED NOT NULL,
    staff_id      INT UNSIGNED NULL,
    purchased_at  DATETIME NOT NULL,
    quantity_kg   DECIMAL(8,2) NOT NULL,
    note          TEXT DEFAULT NULL,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_customer_date (customer_id, purchased_at),
    KEY idx_staff (staff_id),
    CONSTRAINT fk_purchases_customer
        FOREIGN KEY (customer_id) REFERENCES customers(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_purchases_staff
        FOREIGN KEY (staff_id) REFERENCES staff(id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 顧客の主担当者 FK は staff テーブル作成後に追加
ALTER TABLE customers
    ADD CONSTRAINT fk_customer_primary_staff
    FOREIGN KEY (primary_staff_id) REFERENCES staff(id) ON DELETE SET NULL;

-- ---------------------------------------------
-- プッシュ通知の購読情報（端末ごとに 1 レコード）
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

SET FOREIGN_KEY_CHECKS = 1;
