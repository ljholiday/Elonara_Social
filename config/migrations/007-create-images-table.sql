-- Migration 007: Create images table for scalable image tracking
-- Purpose: Track all uploaded images with ownership and context allocation
-- Date: 2025-10-18

CREATE TABLE IF NOT EXISTS `images` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uploader_id` bigint unsigned NOT NULL COMMENT 'User who uploaded this image',
  `image_type` varchar(20) COLLATE utf8mb4_unicode_520_ci NOT NULL COMMENT 'profile, cover, featured, post, reply',
  `urls` longtext COLLATE utf8mb4_unicode_520_ci COMMENT 'JSON object with all size variant URLs',
  `alt_text` varchar(500) COLLATE utf8mb4_unicode_520_ci NOT NULL COMMENT 'Required alt text for accessibility',
  `file_path` varchar(500) COLLATE utf8mb4_unicode_520_ci NOT NULL COMMENT 'Primary file path for reference',
  `file_size` int unsigned DEFAULT NULL COMMENT 'File size in bytes',
  `mime_type` varchar(50) COLLATE utf8mb4_unicode_520_ci DEFAULT NULL,
  `width` int unsigned DEFAULT NULL COMMENT 'Original image width',
  `height` int unsigned DEFAULT NULL COMMENT 'Original image height',

  -- Context allocation: where this image is used
  `community_id` bigint unsigned DEFAULT NULL COMMENT 'Community this image belongs to',
  `event_id` bigint unsigned DEFAULT NULL COMMENT 'Event this image belongs to',
  `conversation_id` bigint unsigned DEFAULT NULL COMMENT 'Conversation this image belongs to',
  `reply_id` bigint unsigned DEFAULT NULL COMMENT 'Conversation reply this image belongs to',

  -- Active usage flags
  `is_community_cover` tinyint(1) DEFAULT 0 COMMENT 'Is this the active community cover?',
  `is_event_cover` tinyint(1) DEFAULT 0 COMMENT 'Is this the active event featured image?',
  `is_profile_image` tinyint(1) DEFAULT 0 COMMENT 'Is this the active user profile image?',
  `is_cover_image` tinyint(1) DEFAULT 0 COMMENT 'Is this the active user cover image?',

  -- Timestamps
  `created_at` datetime NOT NULL,
  `deleted_at` datetime DEFAULT NULL COMMENT 'Soft delete timestamp',
  `is_active` tinyint(1) DEFAULT 1 COMMENT 'Quick filter for non-deleted images',

  PRIMARY KEY (`id`),
  KEY `idx_uploader` (`uploader_id`, `created_at`),
  KEY `idx_community` (`community_id`, `created_at`),
  KEY `idx_event` (`event_id`, `created_at`),
  KEY `idx_conversation` (`conversation_id`, `created_at`),
  KEY `idx_community_cover` (`is_community_cover`, `community_id`),
  KEY `idx_event_cover` (`is_event_cover`, `event_id`),
  KEY `idx_profile_image` (`is_profile_image`, `uploader_id`),
  KEY `idx_cover_image` (`is_cover_image`, `uploader_id`),
  KEY `idx_active` (`is_active`, `created_at`),

  CONSTRAINT `fk_images_uploader` FOREIGN KEY (`uploader_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;
