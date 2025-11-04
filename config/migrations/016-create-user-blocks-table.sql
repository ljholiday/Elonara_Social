-- Migration 016: Create user blocks table
-- Purpose: Allow users to block other members from interacting with them
-- Date: 2025-11-04

CREATE TABLE IF NOT EXISTS `user_blocks` (
  `id` mediumint NOT NULL AUTO_INCREMENT,
  `blocker_user_id` bigint unsigned NOT NULL,
  `blocked_user_id` bigint unsigned NOT NULL,
  `reason` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_block` (`blocker_user_id`, `blocked_user_id`),
  KEY `blocker_user_idx` (`blocker_user_id`),
  KEY `blocked_user_idx` (`blocked_user_id`),
  CONSTRAINT `fk_user_blocks_blocker` FOREIGN KEY (`blocker_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_user_blocks_blocked` FOREIGN KEY (`blocked_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;
