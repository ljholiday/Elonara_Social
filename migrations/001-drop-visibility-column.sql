-- Migration: Drop visibility column from vt_communities
-- Date: 2025-10-02
-- Description: Remove redundant 'visibility' column after standardizing on 'privacy'
--
-- IMPORTANT: Run these steps in order with verification between each step

-- ============================================================================
-- STEP 1: Data Migration (Run this FIRST, before deploying code changes)
-- ============================================================================
-- Copy all visibility values to privacy column to ensure no data loss
-- This is safe to run even if privacy column already has data

UPDATE vt_communities
SET privacy = visibility
WHERE privacy IS NULL
   OR privacy = ''
   OR privacy != visibility;

-- Verify the migration worked (should return 0 rows with mismatches)
SELECT id, name, privacy, visibility
FROM vt_communities
WHERE privacy != visibility
   OR privacy IS NULL;

-- ============================================================================
-- STEP 2: Deploy Code Changes
-- ============================================================================
-- At this point, deploy the code changes from commit 7e17e47
-- Code will now read from 'privacy' column instead of 'visibility'
-- Both columns still exist, so rollback is safe if needed

-- ============================================================================
-- STEP 3: Verification (Run after code deployment)
-- ============================================================================
-- Test in production:
-- 1. Create a new public community
-- 2. Create a new private community
-- 3. Edit existing community privacy settings
-- 4. View community lists and single community pages
-- 5. Check community search results
-- 6. Verify permission checks work (event creation, etc)

-- Verify all communities have valid privacy values
SELECT COUNT(*) as total_communities,
       SUM(CASE WHEN privacy = 'public' THEN 1 ELSE 0 END) as public_count,
       SUM(CASE WHEN privacy = 'private' THEN 1 ELSE 0 END) as private_count,
       SUM(CASE WHEN privacy NOT IN ('public', 'private') THEN 1 ELSE 0 END) as invalid_count
FROM vt_communities;

-- ============================================================================
-- STEP 4: Drop Old Column (Run ONLY after confirming everything works)
-- ============================================================================
-- WARNING: This is irreversible. Make a backup first.
-- Only run this after 24-48 hours of production verification

-- Optional: Create backup table first
CREATE TABLE vt_communities_backup_20251002 AS
SELECT * FROM vt_communities;

-- Drop the visibility column
ALTER TABLE vt_communities DROP COLUMN visibility;

-- Verify column is gone (should error: Unknown column 'visibility')
-- SELECT visibility FROM vt_communities LIMIT 1;

-- ============================================================================
-- ROLLBACK PLAN
-- ============================================================================
-- If issues are found after Step 2 but before Step 4:
-- 1. Revert code to previous commit
-- 2. Both columns still exist, so no data migration needed
-- 3. System continues using 'visibility' column as before
--
-- If issues are found after Step 4:
-- 1. Restore from backup table:
--    ALTER TABLE vt_communities ADD COLUMN visibility enum('public','private') DEFAULT 'public';
--    UPDATE vt_communities c
--    JOIN vt_communities_backup_20251002 b ON c.id = b.id
--    SET c.visibility = b.visibility;
-- 2. Revert code deployment
