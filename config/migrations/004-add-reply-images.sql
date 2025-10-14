-- Migration 004: Add image support to conversation replies
-- Adds image_url and image_alt columns for reply attachments

ALTER TABLE vt_conversation_replies
ADD COLUMN image_url VARCHAR(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci DEFAULT NULL AFTER content,
ADD COLUMN image_alt VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci DEFAULT NULL AFTER image_url;
