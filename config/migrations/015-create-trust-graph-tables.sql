-- Migration 015: Create trust graph tables for circle-based visibility
-- Per dev/doctrine/trust.xml v1.1
-- Adds user_links (bidirectional trust graph) and user_circle_cache (computed hop layers)

CREATE TABLE IF NOT EXISTS `user_links` (
  `user_id` bigint unsigned NOT NULL,
  `peer_id` bigint unsigned NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`,`peer_id`),
  KEY `idx_peer` (`peer_id`,`user_id`),
  CONSTRAINT `fk_user_links_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_user_links_peer` FOREIGN KEY (`peer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Bidirectional user trust graph for circle relationships';

CREATE TABLE IF NOT EXISTS `user_circle_cache` (
  `user_id` bigint unsigned NOT NULL,
  `circle_json` json NOT NULL COMMENT 'Cached hop layers: {"inner":[2,3],"trusted":[4,5],"extended":[6]}',
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`),
  CONSTRAINT `fk_circle_cache_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Optional cache of computed hop-distance circles';
