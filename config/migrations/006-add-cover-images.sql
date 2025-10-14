-- Migration 006: Add cover image support
-- Adds cover_url and cover_alt columns to vt_users
-- Events and communities already have featured_image/featured_image_alt which serve as covers

ALTER TABLE vt_users
ADD COLUMN cover_url VARCHAR(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci DEFAULT NULL AFTER avatar_url,
ADD COLUMN cover_alt VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci DEFAULT NULL AFTER cover_url;
