-- Migration: Add Bluesky OAuth columns and auditing fields
-- Run this after deploying Bluesky OAuth support.

ALTER TABLE `member_identities`
    ADD COLUMN `oauth_provider` varchar(64) NULL AFTER `verification_method`,
    ADD COLUMN `oauth_scopes` varchar(255) NULL AFTER `oauth_provider`,
    ADD COLUMN `oauth_access_token` longtext NULL AFTER `refresh_jwt`,
    ADD COLUMN `oauth_refresh_token` longtext NULL AFTER `oauth_access_token`,
    ADD COLUMN `oauth_token_expires_at` datetime NULL AFTER `oauth_refresh_token`,
    ADD COLUMN `oauth_metadata` longtext NULL AFTER `oauth_token_expires_at`,
    ADD COLUMN `oauth_connected_at` datetime NULL AFTER `oauth_metadata`,
    ADD COLUMN `needs_reauth` tinyint(1) NOT NULL DEFAULT 0 AFTER `oauth_connected_at`,
    ADD COLUMN `oauth_last_error` varchar(255) NULL AFTER `needs_reauth`;

ALTER TABLE `member_identities`
    ADD INDEX `idx_member_identities_oauth_provider` (`oauth_provider`);

