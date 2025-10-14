-- Migration: Add alt-text columns for images
-- Date: 2025-10-09
-- Purpose: Support accessibility by adding alt-text for all images

-- Add alt-text for community featured images
ALTER TABLE `vt_communities`
ADD COLUMN `featured_image_alt` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT '' AFTER `featured_image`;

-- Add alt-text for conversation featured images
ALTER TABLE `vt_conversations`
ADD COLUMN `featured_image_alt` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT '' AFTER `featured_image`;

-- Add alt-text for event featured images
ALTER TABLE `vt_events`
ADD COLUMN `featured_image_alt` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT '' AFTER `featured_image`;

-- Add alt-text for user profile and cover images
ALTER TABLE `vt_user_profiles`
ADD COLUMN `profile_image_alt` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT '' AFTER `profile_image`,
ADD COLUMN `cover_image_alt` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT '' AFTER `cover_image`;
