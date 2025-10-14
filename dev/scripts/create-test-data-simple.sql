-- Simple Test Data Creation Using Direct SQL
-- Creates test users, communities, and conversations to demonstrate Circles of Trust

-- Create test users with bcrypt hashed 'password123'
-- Hash: $2y$12$sjU3fNqOFElpU/ApwVYdG.Zi0NtD6/kP6.z8oCGPGHeGROWXhujlC

INSERT INTO vt_users (username, email, password_hash, display_name, created_at, updated_at) VALUES
('lonn', 'lonn@test.com', '$2y$12$sjU3fNqOFElpU/ApwVYdG.Zi0NtD6/kP6.z8oCGPGHeGROWXhujlC', 'Lonn Holiday', NOW(), NOW()),
('alice', 'alice@test.com', '$2y$12$sjU3fNqOFElpU/ApwVYdG.Zi0NtD6/kP6.z8oCGPGHeGROWXhujlC', 'Alice Chen', NOW(), NOW()),
('bob', 'bob@test.com', '$2y$12$sjU3fNqOFElpU/ApwVYdG.Zi0NtD6/kP6.z8oCGPGHeGROWXhujlC', 'Bob Smith', NOW(), NOW()),
('carol', 'carol@test.com', '$2y$12$sjU3fNqOFElpU/ApwVYdG.Zi0NtD6/kP6.z8oCGPGHeGROWXhujlC', 'Carol Martinez', NOW(), NOW()),
('dave', 'dave@test.com', '$2y$12$sjU3fNqOFElpU/ApwVYdG.Zi0NtD6/kP6.z8oCGPGHeGROWXhujlC', 'Dave Johnson', NOW(), NOW()),
('eve', 'eve@test.com', '$2y$12$sjU3fNqOFElpU/ApwVYdG.Zi0NtD6/kP6.z8oCGPGHeGROWXhujlC', 'Eve Williams', NOW(), NOW()),
('frank', 'frank@test.com', '$2y$12$sjU3fNqOFElpU/ApwVYdG.Zi0NtD6/kP6.z8oCGPGHeGROWXhujlC', 'Frank Brown', NOW(), NOW());

SELECT '✓ Users created' as status;

-- Get user IDs
SET @lonn = (SELECT id FROM vt_users WHERE username = 'lonn');
SET @alice = (SELECT id FROM vt_users WHERE username = 'alice');
SET @bob = (SELECT id FROM vt_users WHERE username = 'bob');
SET @carol = (SELECT id FROM vt_users WHERE username = 'carol');
SET @dave = (SELECT id FROM vt_users WHERE username = 'dave');
SET @eve = (SELECT id FROM vt_users WHERE username = 'eve');
SET @frank = (SELECT id FROM vt_users WHERE username = 'frank');

-- Create communities for INNER CIRCLE
INSERT INTO vt_communities (name, slug, description, visibility, creator_id, creator_email, type, is_active, created_by, member_count, created_at, updated_at) VALUES
('Viva Events', 'viva-events', 'Main event planning community', 'public', @lonn, 'lonn@test.com', 'regular', 1, @lonn, 3, NOW(), NOW()),
('SF Tech Meetup', 'sf-tech-meetup', 'San Francisco tech professionals', 'public', @alice, 'alice@test.com', 'regular', 1, @alice, 3, NOW(), NOW());

SELECT '✓ Inner circle communities created' as status;

SET @viva_events = (SELECT id FROM vt_communities WHERE slug = 'viva-events');
SET @sf_tech = (SELECT id FROM vt_communities WHERE slug = 'sf-tech-meetup');

-- Add members to INNER communities
-- Viva Events: Lonn (admin), Alice, Bob
INSERT INTO vt_community_members (community_id, user_id, email, display_name, role, status, joined_at) VALUES
(@viva_events, @lonn, 'lonn@test.com', 'Lonn Holiday', 'admin', 'active', NOW()),
(@viva_events, @alice, 'alice@test.com', 'Alice Chen', 'member', 'active', NOW()),
(@viva_events, @bob, 'bob@test.com', 'Bob Smith', 'member', 'active', NOW());

-- SF Tech: Alice (admin), Lonn, Bob
INSERT INTO vt_community_members (community_id, user_id, email, display_name, role, status, joined_at) VALUES
(@sf_tech, @alice, 'alice@test.com', 'Alice Chen', 'admin', 'active', NOW()),
(@sf_tech, @lonn, 'lonn@test.com', 'Lonn Holiday', 'member', 'active', NOW()),
(@sf_tech, @bob, 'bob@test.com', 'Bob Smith', 'member', 'active', NOW());

SELECT '✓ Inner circle members added' as status;

-- Create communities for TRUSTED CIRCLE (created by Alice & Bob)
INSERT INTO vt_communities (name, slug, description, visibility, creator_id, creator_email, type, is_active, created_by, member_count, created_at, updated_at) VALUES
('Book Club', 'book-club', 'Monthly book discussions', 'public', @alice, 'alice@test.com', 'regular', 1, @alice, 2, NOW(), NOW()),
('Sports Team', 'sports-team', 'Weekend sports activities', 'public', @bob, 'bob@test.com', 'regular', 1, @bob, 2, NOW(), NOW());

SELECT '✓ Trusted circle communities created' as status;

SET @book_club = (SELECT id FROM vt_communities WHERE slug = 'book-club');
SET @sports_team = (SELECT id FROM vt_communities WHERE slug = 'sports-team');

-- Add members to TRUSTED communities
INSERT INTO vt_community_members (community_id, user_id, email, display_name, role, status, joined_at) VALUES
(@book_club, @alice, 'alice@test.com', 'Alice Chen', 'admin', 'active', NOW()),
(@book_club, @carol, 'carol@test.com', 'Carol Martinez', 'member', 'active', NOW()),
(@sports_team, @bob, 'bob@test.com', 'Bob Smith', 'admin', 'active', NOW()),
(@sports_team, @dave, 'dave@test.com', 'Dave Johnson', 'member', 'active', NOW());

SELECT '✓ Trusted circle members added' as status;

-- Create communities for EXTENDED CIRCLE (created by Carol & Dave)
INSERT INTO vt_communities (name, slug, description, visibility, creator_id, creator_email, type, is_active, created_by, member_count, created_at, updated_at) VALUES
('Art Gallery', 'art-gallery', 'Local art and exhibitions', 'public', @carol, 'carol@test.com', 'regular', 1, @carol, 2, NOW(), NOW()),
('Gaming Group', 'gaming-group', 'Video game enthusiasts', 'public', @dave, 'dave@test.com', 'regular', 1, @dave, 2, NOW(), NOW());

SELECT '✓ Extended circle communities created' as status;

SET @art_gallery = (SELECT id FROM vt_communities WHERE slug = 'art-gallery');
SET @gaming_group = (SELECT id FROM vt_communities WHERE slug = 'gaming-group');

-- Add members to EXTENDED communities
INSERT INTO vt_community_members (community_id, user_id, email, display_name, role, status, joined_at) VALUES
(@art_gallery, @carol, 'carol@test.com', 'Carol Martinez', 'admin', 'active', NOW()),
(@art_gallery, @eve, 'eve@test.com', 'Eve Williams', 'member', 'active', NOW()),
(@gaming_group, @dave, 'dave@test.com', 'Dave Johnson', 'admin', 'active', NOW()),
(@gaming_group, @frank, 'frank@test.com', 'Frank Brown', 'member', 'active', NOW());

SELECT '✓ Extended circle members added' as status;

-- Create INNER CIRCLE conversations (6 total)
INSERT INTO vt_conversations (title, slug, content, author_id, author_name, author_email, community_id, privacy, created_at, updated_at) VALUES
('Welcome to Viva Events', 'welcome-to-viva-events', 'Let''s plan amazing gatherings together!', @lonn, 'Lonn Holiday', 'lonn@test.com', @viva_events, 'public', NOW(), NOW()),
('Summer BBQ Planning', 'summer-bbq-planning', 'Who wants to organize a summer BBQ?', @alice, 'Alice Chen', 'alice@test.com', @viva_events, 'public', NOW(), NOW()),
('Pool Party Ideas', 'pool-party-ideas', 'Thinking about hosting a pool party next month.', @bob, 'Bob Smith', 'bob@test.com', @viva_events, 'public', NOW(), NOW()),
('New Tech Trends', 'new-tech-trends', 'What tech trends are you following?', @alice, 'Alice Chen', 'alice@test.com', @sf_tech, 'public', NOW(), NOW()),
('Startup Opportunities', 'startup-opportunities', 'Anyone interested in startup ideas?', @lonn, 'Lonn Holiday', 'lonn@test.com', @sf_tech, 'public', NOW(), NOW()),
('DevOps Best Practices', 'devops-best-practices', 'Let''s discuss DevOps tooling.', @bob, 'Bob Smith', 'bob@test.com', @sf_tech, 'public', NOW(), NOW());

SELECT '✓ Inner circle conversations created (6)' as status;

-- Create TRUSTED CIRCLE conversations (4 more = 10 total)
INSERT INTO vt_conversations (title, slug, content, author_id, author_name, author_email, community_id, privacy, created_at, updated_at) VALUES
('This Month: Dune', 'this-month-dune', 'What did everyone think of Dune?', @alice, 'Alice Chen', 'alice@test.com', @book_club, 'public', NOW(), NOW()),
('Next Book Suggestions', 'next-book-suggestions', 'What should we read next?', @carol, 'Carol Martinez', 'carol@test.com', @book_club, 'public', NOW(), NOW()),
('Weekend Soccer Game', 'weekend-soccer-game', 'Who''s up for soccer this weekend?', @bob, 'Bob Smith', 'bob@test.com', @sports_team, 'public', NOW(), NOW()),
('Team Jersey Ideas', 'team-jersey-ideas', 'Should we get matching jerseys?', @dave, 'Dave Johnson', 'dave@test.com', @sports_team, 'public', NOW(), NOW());

SELECT '✓ Trusted circle conversations created (+4 = 10 total)' as status;

-- Create EXTENDED CIRCLE conversations (4 more = 14 total)
INSERT INTO vt_conversations (title, slug, content, author_id, author_name, author_email, community_id, privacy, created_at, updated_at) VALUES
('Modern Art Exhibition', 'modern-art-exhibition', 'Check out the new modern art exhibit downtown.', @carol, 'Carol Martinez', 'carol@test.com', @art_gallery, 'public', NOW(), NOW()),
('Photography Workshop', 'photography-workshop', 'Hosting a photography workshop next week.', @eve, 'Eve Williams', 'eve@test.com', @art_gallery, 'public', NOW(), NOW()),
('New RPG Release', 'new-rpg-release', 'The new RPG just dropped, who''s playing?', @dave, 'Dave Johnson', 'dave@test.com', @gaming_group, 'public', NOW(), NOW()),
('Gaming Tournament', 'gaming-tournament', 'Let''s organize a tournament!', @frank, 'Frank Brown', 'frank@test.com', @gaming_group, 'public', NOW(), NOW());

SELECT '✓ Extended circle conversations created (+4 = 14 total)' as status;

SELECT '
================================================================================
TEST DATA CREATED SUCCESSFULLY
================================================================================

Test User: lonn (password: password123)

Expected Circle Results:
  • INNER Circle:    6 conversations (Viva Events + SF Tech Meetup)
  • TRUSTED Circle: 10 conversations (+ Book Club + Sports Team)
  • EXTENDED Circle: 14 conversations (+ Art Gallery + Gaming Group)

Network Structure:
  Lonn → Member of: Viva Events, SF Tech Meetup
  Alice → Creates: Book Club (Trusted for Lonn)
  Bob → Creates: Sports Team (Trusted for Lonn)
  Carol → Creates: Art Gallery (Extended for Lonn)
  Dave → Creates: Gaming Group (Extended for Lonn)

Login at http://localhost:8081/login
Username: lonn
Password: password123

================================================================================' as instructions;
