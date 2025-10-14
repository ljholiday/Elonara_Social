-- Migration: Add role column to vt_users table
-- Date: 2025-10-09
-- Purpose: Support user role management (member, admin, super_admin)

ALTER TABLE `vt_users`
ADD COLUMN `role` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT 'member' AFTER `status`,
ADD INDEX `role` (`role`);
