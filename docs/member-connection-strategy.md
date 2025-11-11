# Member Connection Strategy

## Purpose
This document captures the current understanding of community membership, invitations, and circle logic so future work can resume without session context. Treat it as the single source of truth for what exists today, the gaps we observed, and the incremental plan for improving clarity around member relationships without a major rewrite.

## Current State (as of 2025-03-08)
- **Invitations:** `InvitationService` only sends email-based invitations (plus Bluesky follower posts). The database column `community_invitations.invited_user_id` is unused; controllers (`InvitationApiController`, event/community manage routes) accept email strings only. There is no workflow for inviting an existing Elonara member directly.
- **Join actions:** Public communities still lack a visible “Join” CTA in `templates/community-detail.php` or `templates/partials/entity-card.php`. The only in-app entry point is via invitation acceptance.
- **Circles:** `CircleService::buildContext` derives `inner/trusted/extended` solely from `user_links` (creator relationships) and caches that result. Community memberships are **not** considered when constructing these tiers; only hosts and creators influence circle scopes. `memberCommunities()` exists but is only used by access-control helpers.
- **Frontend invitation UI:** `templates/partials/invitation-section.php` and `public/assets/js/membership.js` still present a single email field plus optional Bluesky selector—no member picker, no join state messaging.
- **API responses:** Community list/detail endpoints do not expose `viewer_join_state`, so templates cannot distinguish “member vs invited vs guest.”
- **Testing:** No integration tests cover member-to-member invitations or join flows; existing coverage is limited to email invitations and RSVP guests.

## Discovered Gaps
1. We cannot invite existing members without retyping their email.
2. Users cannot see their current relationship (join state) when viewing a community card/detail page.
3. Circle context lacks awareness of actual community memberships, making “inner/trusted” semantics confusing.
4. There are no logs/audit trails tying invitation acceptance, auto-joins, and circle updates together.

## Proposal Overview
1. **Expose Viewer Join State**
   - Update `CommunityController::index/show` and `CommunityApiController` to return `viewer_join_state` (`guest`, `invited`, `member`, `host`) plus `viewer_member_id` when applicable.
   - Templates use this to render badges and toggle “Join”/“Leave” actions.
2. **Member-Friendly Invites**
   - Extend invitation APIs to accept either `email` or `user_id`. Persist `community_invitations.invited_user_id` when provided and auto-create `community_members` rows when the invitee already has an account.
   - Add a lightweight `/api/users/search` endpoint scoped to authorized community/event managers (backed by `UserService::listForAdmin`) for the new picker UI.
3. **Minimal Frontend Changes**
   - Add a “Join Community” button for public communities (if logged in + non-member) that calls the existing `/api/communities/{id}/join` endpoint. Reuse existing nonce from layout.
   - Redesign the invitation section to show both the email field and a member search box. `public/assets/js/membership.js` should manage selected users/emails and send a combined payload to `/api/invitations/...`.
4. **Circle Clarity**
   - Without rewriting BFS logic, enrich the cached circle payload with `member_community_ids` and update `CircleService::buildContext` to include `community_members` rows as part of hop distance 1. This keeps queries cheap while reflecting real memberships in “inner/trusted” views.
5. **Audit Logging**
   - Introduce a `membership_events` table (id, user_id, community_id, action, metadata, created_at) to record invitations, join actions, and promotions. Controllers append to it whenever membership state changes.

## Incremental Plan
1. **Data Layer**
   - Write a migration to add `membership_events`.
   - Backfill `community_invitations.invited_user_id` where email matches existing users (script under `dev/scripts/`).
2. **APIs**
   - Update `InvitationService` and `InvitationApiController` to accept `user_id`.
   - Add `viewer_join_state` to community APIs.
   - Create `/api/users/search` with pagination + minimal fields (id, display_name, email).
3. **Frontend**
   - Add join CTA to community detail/cards.
   - Enhance invitation section + JS to support multi-select of members.
4. **Circles**
   - Modify `CircleService::buildContext` to merge `memberCommunities()` into hop-1 results and cache `member_community_ids`.
5. **Testing & Docs**
   - Add integration tests covering: join public community, invite existing member, accept invite auto-join, circle resolution with memberships.
   - Update this document plus `docs/` rollout notes after each phase.

## Open Questions
- Should member-to-member invites trigger immediate joins or still require acceptance? (Current plan favors automatic membership for authorized managers inviting internal users.)
- How do we reconcile circle tiers when a user belongs to dozens of communities? (May require paging or limits on `member_community_ids` exposed.)

## Next Steps
\[ ] Draft SQL migration + data backfill scripts.  
\[ ] Implement viewer join state + join CTA.  
\[ ] Build member search endpoint and extend invite payloads.  
\[ ] Update CircleService caching logic.  
\[ ] Add integration tests and audit logging.  
\[ ] Document deployment checklist (cache clears, reauth requirements, data scripts).

Keep this file updated after each milestone so future contributors can see exactly what shipped and what remains.
