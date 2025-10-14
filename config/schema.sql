
/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `vt_ai_interactions` (
  `id` mediumint NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `event_id` mediumint DEFAULT NULL,
  `interaction_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `prompt_text` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci,
  `response_text` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci,
  `tokens_used` int DEFAULT '0',
  `cost_cents` int DEFAULT '0',
  `provider` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci DEFAULT 'openai',
  `model` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci DEFAULT '',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `event_id` (`event_id`),
  KEY `interaction_type` (`interaction_type`),
  KEY `created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `vt_analytics` (
  `id` mediumint NOT NULL AUTO_INCREMENT,
  `event_id` mediumint NOT NULL,
  `metric_name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `metric_value` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `metric_date` date NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `event_id` (`event_id`),
  KEY `metric_name` (`metric_name`),
  KEY `metric_date` (`metric_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `vt_at_protocol_sync` (
  `id` mediumint NOT NULL AUTO_INCREMENT,
  `entity_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `entity_id` mediumint NOT NULL,
  `sync_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `at_protocol_uri` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci DEFAULT '',
  `sync_status` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci DEFAULT 'pending',
  `sync_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci,
  `error_message` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci,
  `attempts` int DEFAULT '0',
  `last_attempt_at` datetime DEFAULT NULL,
  `synced_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `entity_type` (`entity_type`),
  KEY `entity_id` (`entity_id`),
  KEY `sync_type` (`sync_type`),
  KEY `sync_status` (`sync_status`),
  KEY `at_protocol_uri` (`at_protocol_uri`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `vt_at_protocol_sync_log` (
  `id` mediumint NOT NULL AUTO_INCREMENT,
  `entity_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `entity_id` mediumint NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `action` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `at_uri` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci DEFAULT '',
  `success` tinyint(1) DEFAULT '0',
  `error_message` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `entity_type` (`entity_type`),
  KEY `entity_id` (`entity_id`),
  KEY `user_id` (`user_id`),
  KEY `action` (`action`),
  KEY `success` (`success`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `vt_communities` (
  `id` mediumint NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `slug` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci,
  `type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci DEFAULT 'standard',
  `privacy` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci DEFAULT 'public',
  `personal_owner_user_id` bigint unsigned DEFAULT NULL,
  `member_count` int DEFAULT '0',
  `event_count` int DEFAULT '0',
  `creator_id` bigint unsigned NOT NULL,
  `creator_email` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `featured_image` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci DEFAULT '',
  `featured_image_alt` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT '',
  `settings` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci,
  `at_protocol_did` varchar(255) COLLATE utf8mb4_unicode_520_ci DEFAULT NULL,
  `at_protocol_handle` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci DEFAULT '',
  `at_protocol_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci,
  `is_active` tinyint(1) DEFAULT '1',
  `requires_approval` tinyint(1) DEFAULT '0',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by` bigint unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`),
  UNIQUE KEY `at_protocol_did` (`at_protocol_did`),
  KEY `creator_id` (`creator_id`),
  KEY `privacy` (`privacy`),
  KEY `type` (`type`),
  KEY `is_active` (`is_active`),
  KEY `personal_owner_user_id` (`personal_owner_user_id`),
  KEY `created_by` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `vt_community_events` (
  `id` mediumint NOT NULL AUTO_INCREMENT,
  `community_id` mediumint NOT NULL,
  `event_id` mediumint NOT NULL,
  `organizer_member_id` mediumint NOT NULL,
  `visibility` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci DEFAULT 'community',
  `member_permissions` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci DEFAULT 'view_rsvp',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_community_event` (`community_id`,`event_id`),
  KEY `community_id` (`community_id`),
  KEY `event_id` (`event_id`),
  KEY `organizer_member_id` (`organizer_member_id`),
  KEY `visibility` (`visibility`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `vt_community_invitations` (
  `id` mediumint NOT NULL AUTO_INCREMENT,
  `community_id` mediumint NOT NULL,
  `invited_by_member_id` mediumint NOT NULL,
  `invited_email` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `invited_user_id` bigint unsigned DEFAULT NULL,
  `invitation_token` varchar(64) COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `message` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci,
  `status` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci DEFAULT 'pending',
  `expires_at` datetime NOT NULL,
  `responded_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `accepted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `invitation_token` (`invitation_token`),
  KEY `community_id` (`community_id`),
  KEY `invited_by_member_id` (`invited_by_member_id`),
  KEY `invited_email` (`invited_email`),
  KEY `invited_user_id` (`invited_user_id`),
  KEY `status` (`status`),
  KEY `expires_at` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `vt_community_members` (
  `id` mediumint NOT NULL AUTO_INCREMENT,
  `community_id` mediumint NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `email` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `display_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `role` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci DEFAULT 'member',
  `permissions` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci,
  `status` enum('active','pending','blocked') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT 'active',
  `at_protocol_did` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci DEFAULT '',
  `joined_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `last_seen_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `invitation_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_member` (`community_id`,`user_id`,`email`),
  UNIQUE KEY `unique_membership` (`community_id`,`user_id`),
  UNIQUE KEY `community_user` (`community_id`,`user_id`),
  KEY `community_id` (`community_id`),
  KEY `user_id` (`user_id`),
  KEY `email` (`email`),
  KEY `role` (`role`),
  KEY `status` (`status`),
  KEY `at_protocol_did` (`at_protocol_did`),
  KEY `community_user_status` (`community_id`,`user_id`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `vt_config` (
  `option_name` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `option_value` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `autoload` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT 'yes',
  PRIMARY KEY (`option_name`),
  KEY `autoload` (`autoload`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `vt_conversation_follows` (
  `id` mediumint NOT NULL AUTO_INCREMENT,
  `conversation_id` mediumint NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `email` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `last_read_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `notification_frequency` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci DEFAULT 'immediate',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_follow` (`conversation_id`,`user_id`,`email`),
  KEY `conversation_id` (`conversation_id`),
  KEY `user_id` (`user_id`),
  KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `vt_conversation_replies` (
  `id` mediumint NOT NULL AUTO_INCREMENT,
  `conversation_id` mediumint NOT NULL,
  `parent_reply_id` mediumint DEFAULT NULL,
  `content` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `image_url` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci DEFAULT NULL,
  `image_alt` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci DEFAULT NULL,
  `author_id` bigint unsigned NOT NULL,
  `author_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `author_email` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `depth_level` int DEFAULT '0',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `conversation_id` (`conversation_id`),
  KEY `parent_reply_id` (`parent_reply_id`),
  KEY `author_id` (`author_id`),
  KEY `created_at` (`created_at`),
  KEY `conversation_created_at` (`conversation_id`,`created_at`),
  KEY `conversation_created` (`conversation_id`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `vt_conversation_topics` (
  `icon` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci DEFAULT '',
  `id` mediumint NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `slug` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci,
  `sort_order` int DEFAULT '0',
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`),
  KEY `sort_order` (`sort_order`),
  KEY `is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `vt_conversations` (
  `id` mediumint NOT NULL AUTO_INCREMENT,
  `event_id` mediumint DEFAULT NULL,
  `title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `slug` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `content` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `author_id` bigint unsigned NOT NULL,
  `author_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `author_email` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `is_pinned` tinyint(1) DEFAULT '0',
  `is_locked` tinyint(1) DEFAULT '0',
  `reply_count` int DEFAULT '0',
  `last_reply_date` datetime DEFAULT CURRENT_TIMESTAMP,
  `last_reply_author` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci DEFAULT '',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `community_id` mediumint DEFAULT NULL,
  `privacy` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci DEFAULT 'public',
  `featured_image` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci DEFAULT '',
  `featured_image_alt` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT '',
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`),
  KEY `event_id` (`event_id`),
  KEY `author_id` (`author_id`),
  KEY `is_pinned` (`is_pinned`),
  KEY `last_reply_date` (`last_reply_date`),
  KEY `community_id` (`community_id`),
  KEY `community_created_at` (`community_id`,`created_at`),
  KEY `community_created` (`community_id`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `vt_event_invitations` (
  `id` mediumint NOT NULL AUTO_INCREMENT,
  `event_id` mediumint NOT NULL,
  `invited_by_user_id` bigint unsigned NOT NULL,
  `invited_email` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `invited_user_id` bigint unsigned DEFAULT NULL,
  `invitation_token` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `message` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci,
  `status` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci DEFAULT 'pending',
  `custom_message` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci,
  `expires_at` datetime NOT NULL,
  `responded_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `invitation_token` (`invitation_token`),
  UNIQUE KEY `unique_event_invitation` (`event_id`,`invited_email`,`status`),
  KEY `event_id` (`event_id`),
  KEY `invited_by_user_id` (`invited_by_user_id`),
  KEY `invited_email` (`invited_email`),
  KEY `invited_user_id` (`invited_user_id`),
  KEY `status` (`status`),
  KEY `expires_at` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `vt_events` (
  `id` mediumint NOT NULL AUTO_INCREMENT,
  `title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `slug` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `description` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci,
  `excerpt` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci,
  `event_date` datetime DEFAULT NULL,
  `event_time` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci DEFAULT '',
  `end_date` datetime DEFAULT NULL,
  `all_day` tinyint(1) DEFAULT '0',
  `recurrence_type` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci DEFAULT 'none',
  `recurrence_interval` int DEFAULT '1',
  `recurrence_days` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci DEFAULT '',
  `monthly_type` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci DEFAULT 'date',
  `monthly_week` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci DEFAULT '',
  `monthly_day` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci DEFAULT '',
  `guest_limit` int DEFAULT '0',
  `venue_info` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci,
  `host_email` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci DEFAULT '',
  `host_notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci,
  `ai_plan` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci,
  `event_status` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci DEFAULT 'active',
  `author_id` bigint unsigned DEFAULT '1',
  `post_id` bigint unsigned NOT NULL DEFAULT '0',
  `community_id` mediumint DEFAULT NULL,
  `featured_image` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci DEFAULT '',
  `featured_image_alt` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT '',
  `meta_title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci DEFAULT '',
  `meta_description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `delivery_orders` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci,
  `privacy` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci DEFAULT 'public',
  `location` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci DEFAULT NULL,
  `address` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci,
  `max_guests` int DEFAULT NULL,
  `created_by` bigint unsigned NOT NULL,
  `status` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci DEFAULT 'active',
  `visibility` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci DEFAULT 'public',
  `rsvp_deadline` datetime DEFAULT NULL,
  `allow_plus_ones` tinyint(1) DEFAULT '1',
  `auto_approve_rsvps` tinyint(1) DEFAULT '1',
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`),
  KEY `event_date` (`event_date`),
  KEY `event_status` (`event_status`),
  KEY `author_id` (`author_id`),
  KEY `privacy` (`privacy`),
  KEY `community_id` (`community_id`),
  KEY `created_by` (`created_by`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `vt_guests` (
  `id` mediumint NOT NULL AUTO_INCREMENT,
  `rsvp_token` varchar(64) COLLATE utf8mb4_unicode_520_ci DEFAULT '',
  `temporary_guest_id` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci DEFAULT '',
  `converted_user_id` bigint unsigned DEFAULT NULL,
  `event_id` mediumint NOT NULL,
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `email` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `phone` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci DEFAULT '',
  `status` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci DEFAULT 'pending',
  `invitation_source` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci DEFAULT 'direct',
  `dietary_restrictions` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci,
  `plus_one` tinyint(1) DEFAULT '0',
  `plus_one_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci DEFAULT '',
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci,
  `rsvp_date` datetime DEFAULT CURRENT_TIMESTAMP,
  `reminder_sent` tinyint(1) DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_guest_event` (`event_id`,`email`),
  KEY `event_id` (`event_id`),
  KEY `email` (`email`),
  KEY `status` (`status`),
  KEY `rsvp_token` (`rsvp_token`),
  KEY `temporary_guest_id` (`temporary_guest_id`),
  KEY `converted_user_id` (`converted_user_id`),
  KEY `invitation_source` (`invitation_source`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `vt_member_identities` (
  `id` mediumint NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `email` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `display_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci DEFAULT '',
  `at_protocol_did` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT '',
  `at_protocol_handle` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci DEFAULT '',
  `at_protocol_pds` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci DEFAULT '',
  `at_protocol_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci,
  `public_key` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci,
  `private_key_encrypted` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci,
  `cross_site_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci,
  `is_verified` tinyint(1) DEFAULT '0',
  `last_sync_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `did` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci DEFAULT '',
  `handle` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci DEFAULT '',
  `access_jwt` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci,
  `refresh_jwt` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci,
  `pds_url` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci DEFAULT '',
  `profile_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_id` (`user_id`),
  UNIQUE KEY `email` (`email`),
  UNIQUE KEY `at_protocol_did` (`at_protocol_did`),
  KEY `at_protocol_handle` (`at_protocol_handle`),
  KEY `is_verified` (`is_verified`),
  KEY `did` (`did`),
  KEY `handle` (`handle`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `vt_post_images` (
  `id` mediumint NOT NULL AUTO_INCREMENT,
  `event_id` mediumint NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `filename` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `original_filename` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT '',
  `file_url` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `file_path` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `file_size` bigint DEFAULT '0',
  `mime_type` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci DEFAULT '',
  `width` int DEFAULT '0',
  `height` int DEFAULT '0',
  `caption` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci,
  `alt_text` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci DEFAULT '',
  `sort_order` int DEFAULT '0',
  `is_featured` tinyint(1) DEFAULT '0',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `image_url` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `thumbnail_url` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci DEFAULT '',
  `display_order` int DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `event_id` (`event_id`),
  KEY `user_id` (`user_id`),
  KEY `sort_order` (`sort_order`),
  KEY `is_featured` (`is_featured`),
  KEY `created_at` (`created_at`),
  KEY `display_order` (`display_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `vt_search` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `entity_type` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `entity_id` bigint unsigned NOT NULL,
  `title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `content` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `url` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `owner_user_id` bigint unsigned NOT NULL DEFAULT '0',
  `community_id` bigint unsigned NOT NULL DEFAULT '0',
  `event_id` bigint unsigned NOT NULL DEFAULT '0',
  `visibility_scope` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT 'public',
  `last_activity_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `entity_unique` (`entity_type`,`entity_id`),
  KEY `entity_type_idx` (`entity_type`),
  KEY `community_idx` (`community_id`),
  KEY `event_idx` (`event_id`),
  KEY `visibility_idx` (`visibility_scope`),
  KEY `owner_idx` (`owner_user_id`),
  FULLTEXT KEY `ft_search` (`title`,`content`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `vt_sessions` (
  `id` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `guest_token` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `data` longtext COLLATE utf8mb4_unicode_ci,
  `expires_at` datetime NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `guest_token` (`guest_token`),
  KEY `expires_at` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `vt_social` (
  `id` mediumint NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `guest_id` mediumint DEFAULT NULL,
  `at_protocol_handle` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci DEFAULT '',
  `bluesky_did` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci DEFAULT '',
  `connection_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci,
  `connection_status` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci DEFAULT 'active',
  `last_sync` datetime DEFAULT CURRENT_TIMESTAMP,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `guest_id` (`guest_id`),
  KEY `at_protocol_handle` (`at_protocol_handle`),
  KEY `connection_status` (`connection_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `vt_user_activity_tracking` (
  `id` mediumint NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `activity_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `item_id` mediumint NOT NULL,
  `last_seen_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_tracking` (`user_id`,`activity_type`,`item_id`),
  KEY `user_id` (`user_id`),
  KEY `activity_type` (`activity_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `vt_user_profiles` (
  `id` mediumint NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `display_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci DEFAULT '',
  `bio` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci,
  `location` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci DEFAULT '',
  `profile_image` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci DEFAULT '',
  `profile_image_alt` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT '',
  `cover_image` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci DEFAULT '',
  `cover_image_alt` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT '',
  `website_url` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci DEFAULT '',
  `social_links` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci,
  `hosting_preferences` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci,
  `notification_preferences` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci,
  `privacy_settings` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci,
  `events_hosted` int DEFAULT '0',
  `events_attended` int DEFAULT '0',
  `host_rating` decimal(3,2) DEFAULT '0.00',
  `host_reviews_count` int DEFAULT '0',
  `available_times` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci,
  `dietary_restrictions` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci,
  `accessibility_needs` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci,
  `is_verified` tinyint(1) DEFAULT '0',
  `is_active` tinyint(1) DEFAULT '1',
  `last_active` datetime DEFAULT CURRENT_TIMESTAMP,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `avatar_source` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci DEFAULT 'gravatar',
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_id` (`user_id`),
  KEY `display_name` (`display_name`),
  KEY `location` (`location`),
  KEY `is_verified` (`is_verified`),
  KEY `is_active` (`is_active`),
  KEY `last_active` (`last_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `vt_users` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `username` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password_hash` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `display_name` varchar(250) COLLATE utf8mb4_unicode_ci NOT NULL,
  `bio` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `avatar_url` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cover_url` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cover_alt` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT 'active',
  `role` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT 'member',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `last_login_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  UNIQUE KEY `username` (`username`),
  KEY `status` (`status`),
  KEY `role` (`role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `vt_password_reset_tokens` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `token` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `expires_at` datetime NOT NULL,
  `used_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `token` (`token`),
  KEY `user_id` (`user_id`),
  KEY `expires_at` (`expires_at`),
  KEY `used_at` (`used_at`),
  CONSTRAINT `vt_password_reset_tokens_user_fk` FOREIGN KEY (`user_id`) REFERENCES `vt_users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `vt_email_verification_tokens` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `email` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `expires_at` datetime NOT NULL,
  `verified_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `token` (`token`),
  KEY `user_id` (`user_id`),
  KEY `email` (`email`),
  KEY `expires_at` (`expires_at`),
  KEY `verified_at` (`verified_at`),
  CONSTRAINT `vt_email_verification_tokens_user_fk` FOREIGN KEY (`user_id`) REFERENCES `vt_users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

