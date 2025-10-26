-- Add avatar_alt column to users table
-- Migration: 014-add-avatar-alt-column
-- Date: 2025-10-26

ALTER TABLE `users`
ADD COLUMN `avatar_alt` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
AFTER `avatar_preference`;
