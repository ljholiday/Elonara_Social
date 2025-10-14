-- Wipe All Test Data
-- WARNING: This deletes ALL users, communities, conversations, and related data
-- Only use in development environment

-- Disable foreign key checks temporarily
SET FOREIGN_KEY_CHECKS = 0;

-- Delete all conversation-related data
DELETE FROM vt_conversation_replies;
DELETE FROM vt_conversation_follows;
DELETE FROM vt_conversations;
DELETE FROM vt_conversation_topics;

-- Delete all community-related data
DELETE FROM vt_community_members;
DELETE FROM vt_community_invitations;
DELETE FROM vt_community_events;
DELETE FROM vt_communities;

-- Delete all event-related data
DELETE FROM vt_event_invitations;
DELETE FROM vt_guests;
DELETE FROM vt_events;

-- Delete all user-related data
DELETE FROM vt_sessions;
DELETE FROM vt_user_profiles;
DELETE FROM vt_member_identities;
DELETE FROM vt_user_activity_tracking;
DELETE FROM vt_users;

-- Delete analytics and search data
DELETE FROM vt_analytics;
DELETE FROM vt_search;
DELETE FROM vt_ai_interactions;

-- Delete AT Protocol sync data
DELETE FROM vt_at_protocol_sync_log;
DELETE FROM vt_at_protocol_sync;

-- Delete social data
DELETE FROM vt_social;

-- Delete post images
DELETE FROM vt_post_images;

-- Re-enable foreign key checks
SET FOREIGN_KEY_CHECKS = 1;

-- Reset auto-increment counters
ALTER TABLE vt_users AUTO_INCREMENT = 1;
ALTER TABLE vt_communities AUTO_INCREMENT = 1;
ALTER TABLE vt_conversations AUTO_INCREMENT = 1;
ALTER TABLE vt_events AUTO_INCREMENT = 1;

SELECT 'All test data wiped successfully' as result;
