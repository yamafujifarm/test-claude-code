-- やまふじ農園 お米 注文予測 - 顧客の主担当者紐付けマイグレーション
-- 既存 DB に対して phpMyAdmin から実行してください。

SET NAMES utf8mb4;

ALTER TABLE customers
    ADD COLUMN primary_staff_id INT UNSIGNED NULL AFTER category,
    ADD KEY idx_primary_staff (primary_staff_id),
    ADD CONSTRAINT fk_customer_primary_staff
        FOREIGN KEY (primary_staff_id) REFERENCES staff(id) ON DELETE SET NULL;
