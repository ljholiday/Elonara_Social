-- Migration: Rename login field to username in vt_users table
-- Date: 2024-12-19
-- Description: Establish username/display_name convention

-- Check if the login column exists and username doesn't
-- If login exists, rename it to username
ALTER TABLE vt_users
CHANGE COLUMN login username varchar(60) NOT NULL DEFAULT '';

-- Update the unique key constraint
DROP INDEX login;
CREATE UNIQUE INDEX username ON vt_users (username);