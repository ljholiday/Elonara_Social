-- Migration 008: Expand featured image columns for JSON payloads
-- Purpose: allow storing multi-variant image URL JSON strings
-- Date: 2025-10-19

ALTER TABLE `communities`
  MODIFY `featured_image` LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci DEFAULT NULL;

ALTER TABLE `events`
  MODIFY `featured_image` LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci DEFAULT NULL;

ALTER TABLE `conversations`
  MODIFY `featured_image` LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci DEFAULT NULL;
