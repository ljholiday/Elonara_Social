-- Migration 010: Ensure community creators keep admin role
-- Purpose: restore admin role for community creators that were downgraded
-- Date: 2025-10-19

UPDATE community_members cm
JOIN communities c ON c.id = cm.community_id AND c.creator_id = cm.user_id
SET cm.role = 'admin'
WHERE cm.role <> 'admin';
