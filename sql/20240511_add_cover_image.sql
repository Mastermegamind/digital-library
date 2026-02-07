-- Migration: add cover image support to resources table

-- SQLite
ALTER TABLE resources ADD COLUMN cover_image_path TEXT;

-- MySQL / MariaDB
-- ALTER TABLE `resources`
--   ADD COLUMN `cover_image_path` VARCHAR(255) NULL AFTER `file_path`;
