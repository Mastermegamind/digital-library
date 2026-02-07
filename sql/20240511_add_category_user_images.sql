-- Migration: add cover images for categories and profile images for users

-- SQLite
ALTER TABLE categories ADD COLUMN cover_image_path TEXT;
ALTER TABLE users ADD COLUMN profile_image_path TEXT;

-- MySQL / MariaDB
-- ALTER TABLE `categories`
--   ADD COLUMN `cover_image_path` VARCHAR(255) NULL AFTER `name`;
-- ALTER TABLE `users`
--   ADD COLUMN `profile_image_path` VARCHAR(255) NULL AFTER `password_hash`;
