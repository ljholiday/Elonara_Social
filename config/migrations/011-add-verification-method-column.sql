-- Migration: Add verification_method column to member_identities table
-- Purpose: Track how Bluesky identity was verified (none, self_reported, oauth)
-- Date: 2025-10-21

ALTER TABLE `member_identities`
ADD COLUMN `verification_method` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci DEFAULT 'none'
AFTER `is_verified`;
