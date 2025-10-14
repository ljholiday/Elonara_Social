-- Migration 005: Add profile fields to users table
-- Adds bio and avatar_url columns for user profiles

ALTER TABLE vt_users
ADD COLUMN bio TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci DEFAULT NULL AFTER display_name,
ADD COLUMN avatar_url VARCHAR(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci DEFAULT NULL AFTER bio;
