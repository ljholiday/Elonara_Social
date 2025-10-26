-- Migration: Add avatar_preference column to users table
-- Date: 2025-10-25
-- Description: Adds avatar_preference field to allow users to explicitly choose between custom avatar and Gravatar

ALTER TABLE `users`
ADD COLUMN `avatar_preference` VARCHAR(20) DEFAULT 'auto' COMMENT 'Avatar source preference: auto, custom, gravatar'
AFTER `avatar_url`;

-- Update existing users:
-- Users with custom avatars default to 'custom'
-- Users without avatars default to 'auto' (will use gravatar)
UPDATE `users`
SET `avatar_preference` = 'custom'
WHERE `avatar_url` IS NOT NULL AND `avatar_url` != '';

-- Add index for faster queries
CREATE INDEX `idx_avatar_preference` ON `users` (`avatar_preference`);
