-- Migration: Backfill Bluesky DIDs from community_members and community_invitations
-- Purpose: Migrate existing Bluesky invitees who joined before the member_identities tracking was implemented
-- Date: 2025-10-21

-- Insert or update member_identities for users with DIDs in community_members
INSERT INTO member_identities (user_id, email, did, at_protocol_did, verification_method, is_verified, created_at, updated_at)
SELECT DISTINCT
    cm.user_id,
    u.email,
    cm.at_protocol_did,
    cm.at_protocol_did,
    'invitation_linked',
    0,
    NOW(),
    NOW()
FROM community_members cm
INNER JOIN users u ON u.id = cm.user_id
WHERE cm.at_protocol_did IS NOT NULL
  AND cm.at_protocol_did != ''
  AND NOT EXISTS (
      SELECT 1 FROM member_identities mi
      WHERE mi.user_id = cm.user_id
  )
ON DUPLICATE KEY UPDATE
    did = VALUES(did),
    at_protocol_did = VALUES(at_protocol_did),
    verification_method = CASE
        WHEN verification_method = 'oauth' THEN 'oauth'
        ELSE 'invitation_linked'
    END,
    updated_at = NOW();

-- Also backfill from community_invitations for accepted invitations
INSERT INTO member_identities (user_id, email, did, at_protocol_did, verification_method, is_verified, created_at, updated_at)
SELECT
    ci.invited_user_id,
    u.email,
    SUBSTRING(ci.invited_email, 6),
    SUBSTRING(ci.invited_email, 6),
    'invitation_linked',
    0,
    NOW(),
    NOW()
FROM community_invitations ci
INNER JOIN users u ON u.id = ci.invited_user_id
WHERE ci.invited_email LIKE 'bsky:did:%'
  AND ci.invited_user_id IS NOT NULL
  AND ci.status = 'accepted'
  AND NOT EXISTS (
      SELECT 1 FROM member_identities mi
      WHERE mi.user_id = ci.invited_user_id
  )
ON DUPLICATE KEY UPDATE
    did = VALUES(did),
    at_protocol_did = VALUES(at_protocol_did),
    verification_method = CASE
        WHEN verification_method = 'oauth' THEN 'oauth'
        ELSE 'invitation_linked'
    END,
    updated_at = NOW();
