---
title: "Bluesky OAuth Integration Proposal"
project: "Elonara Social"
version: "1.1"
status: "Draft"
author: "Elonara Social Team"
date: "2025-11-07"
---

# Bluesky OAuth Integration Proposal

**Title:** From connected accounts to verified identity  
**Scope:** social.elonara.com  
**Integration:** Bluesky OAuth (AT Protocol) replacing legacy app-password authentication  
**Objective:** Deliver seamless invitation acceptance, trustworthy identity, and renewable credentials

---

## 0. Executive Summary

Elonara Social already talks to Bluesky for follower sync and directed invitation posts, but identity still hinges on static app-passwords. This proposal secures those touchpoints with OAuth, captures DID-backed identity during invite acceptance, and keeps existing app-password sessions alive until members migrate. By freezing current follower-fetch and invitation-post code paths while we retrofit OAuth, we retain stability and layer on verified identity, better UX, and measurable conversion improvements.

---

## 0.1 Current Implementation Status (Jan 2025 checkpoint)

| Area | Status | Notes |
|------|--------|-------|
| OAuth service & encryption | ✅ Done | `BlueskyOAuthService` issues tokens, refreshes, encrypts at rest, and flags `needs_reauth`; `BlueskyService` now prefers OAuth tokens with legacy fallback. |
| Config & secrets | ✅ Done | `config/config.php` mirrors sample; `.env` placeholders for `BLUESKY_CLIENT_*` variables + `BLUESKY_TOKEN_KEY`. |
| Profile UX | ✅ Done | “Authorize/Reauthorize via Bluesky” CTAs live behind `BLUESKY_OAUTH_ENABLED`; legacy app-password form remains for migration. |
| Invite/RSVP flows | 🚧 In progress | Session storage + OAuth redirect scaffolding exists, but callback auto-accept/`accepted_via` logic still pending. Non-Bluesky invites untouched. |
| Background jobs | 🚧 In progress | Follower/invite posting still use legacy JWTs unless OAuth tokens exist; need refresh-aware worker + telemetry. |
| Real OAuth redirect test | ❌ Not yet | Must deploy to staging/production host to run the actual Bluesky redirect loop once remaining work is complete. |

Use this table after a restart to see what’s landed versus what still blocks staging/production rollout.

---

## 1. Context & Problem Statement

### 1.1 Current Integration Surfaces

| Capability | Current Mechanism |
|------------|-------------------|
| Connect Bluesky account | Member provides handle + app-password; DID stored in `app_member_identities`. |
| Fetch followers / follows | Background jobs call Bluesky APIs using stored app-password credentials. |
| Send invitations | Directed posts issued with the member’s credentials and link to `/rsvp/{token}` or `/invitation/accept`. |
| Accept invitations | Existing members land on the invite page and RSVP; anonymous users must create an account manually. |

### 1.2 Gaps

1. No verified identity for anonymous invitees; trust stops at a pasted handle.  
2. App-passwords never expire, are stored long term, and cannot be revoked centrally.  
3. Invitation acceptance lacks session-aware redirects and Bluesky-aware onboarding.  
4. No “Authorize via Bluesky” UX or reauthorization path after tokens expire.  
5. Metrics do not track OAuth success, invite conversions, or token refresh health.

---

## 2. Goals & Benefits

1. **Secure OAuth tokens** replace app-passwords and live encrypted in `app_member_identities`.  
2. **Authorize via Bluesky** button (with a Reauthorize fallback) becomes the canonical member action.  
3. **Invitation acceptance** auto-detects session state, stores invite tokens in session, and finishes enrollment after OAuth callback.  
4. **App-password compatibility** remains during rollout; invitations and follower sync stay stable.  
5. **Monitoring** captures OAuth login success, invite conversions, and refresh reliability.

**Benefits:** decreased credential risk, verified DID identity, faster invite conversions, and a tested bridge toward AT Protocol-wide federation.

---

## 3. User Journeys

### 3.1 Member re-connects via OAuth

1. Member opens profile → sees “Authorize via Bluesky” (or “Reauthorize” if expired).  
2. Clicking launches `/auth/bluesky/start`, storing PKCE + CSRF state.  
3. After consenting on Bluesky, callback updates/creates the OAuth token set, encrypted at rest with `token_expires_at`.  
4. Jobs that fetch followers or post invitations now use OAuth tokens transparently.

### 3.2 Invitee who is already a member

1. Invitation link hits `/rsvp/{token}` or `/invitation/accept`.  
2. Session detected → invitation attaches immediately, mirroring current behavior.  
3. If member still on app-password, prompt suggests “Reconnect via OAuth” but does not block acceptance.

### 3.3 Invitee who is not a member

1. Landing on invite route without a session stores the invite token and target type in session.  
2. Visitor is redirected to OAuth (Continue with Bluesky).  
3. Callback resolves the DID, locates or creates a member, migrates identity records, and auto-accepts the invitation.  
4. Session persists through redirects to confirm RSVP completion.

### 3.4 Token expiry or revocation

1. API calls detect HTTP 401/403 or elapsed `token_expires_at`.  
2. Member UI surfaces “Reauthorize via Bluesky” (profile banner + CTA).  
3. Until renewed, background jobs pause follower fetch/posting for that member but keep invitation history intact.

---

## 4. Implementation Roadmap

1. **Audit & freeze existing Bluesky code paths**  
   - Snapshot follower-fetch and invitation-post services, add feature flags, and block unreviewed changes during the migration.
2. **Preserve legacy app-password flows**  
   - Keep current credentials working; gate OAuth-only routes behind a feature toggle until adoption >80%.  
3. **Implement OAuth foundation**  
   - Add `/auth/bluesky/start` and `/auth/bluesky/callback`, PKCE + DPoP, encrypted token storage, and refresh handling.  
   - Serve `public/oauth/oauth-client-metadata.json` for dynamic registration.
4. **Update member profile UX** *(status: DONE in dev)*  
   - Profile edit page now shows “Authorize via Bluesky” / “Reauthorize” buttons gated by `BLUESKY_OAUTH_ENABLED`, while the legacy app-password form remains for the freeze period.  
   - Next: add banner when `needs_reauth` is set and hide the legacy form once adoption passes the target threshold.
5. **Invitation flow integration** *(status: IN PROGRESS)*  
   - OAuth endpoints exist and tokens can survive redirects, but `/invitation/accept` + `/rsvp/{token}` still rely on legacy DID checks.  
   - Remaining work: force anonymous Bluesky invitees through OAuth, auto-accept on callback, add `accepted_via = 'oauth'`, and keep email/link invites unchanged.
6. **Token migration & cleanup** *(status: PARTIAL)*  
   - `BlueskyOAuthService` now encrypts and refreshes tokens, marks `needs_reauth`, and `BlueskyService` prefers OAuth tokens while falling back to app-passwords.  
   - Still needed: background job/metrics plus UI prompt to nudge remaining users, then sunset app-password creation.
7. **Testing & rollout**  
   - Execute automated + manual flows (see Section 9).  
   - Deploy in phases: staging, limited production cohort, full rollout, app-password sunset.  
8. **Monitoring & refinement**  
   - Capture metrics, analyze invitation acceptance logs, and adjust UX or retry logic as needed.

---

## 5. Architecture & Data Model

### 5.1 `app_member_identities`

| Field | Type | Notes |
|-------|------|-------|
| `id` | BIGINT | PK |
| `member_id` | BIGINT | FK → `app_members` |
| `provider` | VARCHAR(64) | `'bluesky-oauth'` or legacy identifiers |
| `did` | VARCHAR(255) | Verified identifier (unique with provider) |
| `handle` | VARCHAR(255) | Canonical Bluesky handle |
| `pds_host` | VARCHAR(255) | Resolved host |
| `access_jwt` / `refresh_jwt` | TEXT | Legacy app-password tokens (kept until sunset) |
| `oauth_provider` | VARCHAR(64) | `'bluesky-oauth'` |
| `oauth_access_token` / `oauth_refresh_token` | LONGTEXT | AES-256-GCM encrypted |
| `oauth_token_expires_at` | DATETIME | UTC expiry for OAuth access token |
| `oauth_scopes` | VARCHAR(255) | Stored for audits |
| `needs_reauth` | TINYINT(1) | Flag when refresh fails or tokens revoked |
| `oauth_last_error` | VARCHAR(255) | Last refresh error summary |
| `created_at` / `updated_at` | DATETIME | Timestamps |

### 5.2 `app_invitations`

- Add `accepted_member_id` FK.  
- Persist `accepted_via` enum (`legacy`, `oauth`).  
- Store `pending_token` in session to resume after OAuth.

---

## 6. OAuth Flow

1. **Discovery** – Resolve handle → DID → PDS host → fetch `/.well-known/oauth-authorization-server` metadata.  
2. **Client metadata** – Hosted at `https://social.elonara.com/oauth/oauth-client-metadata.json` with redirect URI `https://social.elonara.com/auth/bluesky/callback`, scopes `atproto transition:generic`, grant types `authorization_code` + `refresh_token`, `token_endpoint_auth_method` = `private_key_jwt`.  
3. **Authorization start** – `/auth/bluesky/start` stores PKCE verifier, state, invite context, and optional return URL in session, then redirects to provider.  
4. **Callback** – Validate `state`, exchange `code` using PKCE + DPoP, decrypt/store tokens with expiry, and emit domain events for downstream services.  
5. **Member linking** – Lookup by DID; update tokens if found, else create member record + identity row.  
6. **Invitation continuation** – If session tracked an invite token, call `InvitationService::attachMemberToInvite()` and redirect to the success page.  
7. **Token refresh** – `BlueskyOAuthService::getAccessToken()` already refreshes and sets `needs_reauth` when refresh fails; remaining work is to run a cron/queue worker plus telemetry so expirations are handled proactively.

---

## 7. Key Services & Routes

- `BlueskyOAuthService` – Discovery, authorization URL generation, callback handling, refresh, and revocation.  
- `AuthController` – Routes `/auth/bluesky/start`, `/auth/bluesky/callback`, `/auth/bluesky/reauthorize`.  
- `MemberProfileController` – Surfaces authorize/reauthorize CTAs and “Reconnect via OAuth” prompts.  
- `InvitationController` – Updated `/rsvp/{token}` and `/invitation/accept` flows with session-aware redirects.  
- Background jobs – follower fetch, invitation posting, token refresh scheduler.

---

## 8. Security, Migration, and Sunset

1. Encrypt all tokens with existing Keyring helper; never log raw values.  
2. Mask secrets in error telemetry; use structured context for traceability.  
3. Mark legacy identities (`provider = 'bluesky-app-password'`) as read-only; continue honoring them until OAuth adoption threshold reached.  
4. Provide “Reconnect via OAuth” prompts plus email notifications; once majority migrated, disable UI to create app-password credentials and delete remaining secrets.  
5. Add `needs_reauth` flag to pause background jobs when tokens expire, surfacing the Reauthorize button prominently.

---

## 9. Testing Plan

1. **Unit tests** – Mock OAuth discovery and token exchange, ensuring encryption wrappers invoked.  
2. **Feature tests** – Simulate `/rsvp/{token}` and `/invitation/accept` flows for (a) logged-in member, (b) anonymous invitee who becomes a member via OAuth, ensuring session persistence across redirects.  
3. **Regression tests** – Validate follower-fetch and invitation-post jobs continue to operate under OAuth tokens and still respect app-password identities until sunset.  
4. **Manual QA** – End-to-end scenarios for authorize, reauthorize, expired token recovery, invite acceptance, and background job refresh failures.

---

## 10. Metrics & Monitoring

| Metric | Description | Target |
|--------|-------------|--------|
| OAuth login success rate | Successful callbacks / starts | ≥ 98% |
| Invite conversion rate | Invites accepted / invite clicks | +30% vs baseline |
| Token refresh success | Successful refreshes / attempts | ≥ 99% |
| Reauthorize prompts cleared | Members resolving `needs_reauth` | 90% within 48h |
| Invitation acceptance patterns | Logged DID + invitation type trends | Reviewed weekly |

Log anonymized invitation acceptance journeys to verify traction goals and feed product analytics.

---

## 11. Deployment Phases

1. **Phase 1 – Staging**: OAuth endpoints, metadata hosting, and invitation session handling behind feature flag.  
2. **Phase 2 – Member migration**: Surface “Authorize via Bluesky” + “Reauthorize”; monitor adoption.  
3. **Phase 3 – Invite OAuth default**: Anonymous invitees forced through OAuth; ensure automatic account creation.  
4. **Phase 4 – App-password sunset**: Disable new app-password connections and purge remaining credentials.  
5. **Phase 5 – Optimization**: Tune UX based on telemetry, iterate on refresh heuristics, and expand scopes if Bluesky introduces richer permissions.

---

## 12. Implementation Backlog (Next Iteration)

### 12.1 Invitation OAuth Completion

- **Session capture + redirect**:  
  - Add `PendingInviteSessionStore` helper around `SessionStorageService` so `/invitation/accept` and `/rsvp/{token}` can persist `invite_token`, `invite_channel`, and `post_auth_redirect`.  
  - Apply middleware that checks for `bluesky_invite_required` and routes anonymous users through `/auth/bluesky/start` with the invite context embedded in `state`.
- **Callback attach + metrics**:  
  - Extend `AuthController::blueskyCallback()` to detect pending invite context, call `InvitationService::attachMemberToInvite()`, set `accepted_via = 'oauth'`, and emit `invitation.accepted` with DID + channel metadata.  
  - Log structured analytics (`invite_token`, `member_id`, `did`, `accepted_via`) for conversion dashboards.
- **RSVP UX polish**:  
  - Update the invite success template to acknowledge verified identity (handle + DID) and surface reauthorize prompts if `needs_reauth` flagged during callback.  
  - Add resilience copy for fallback paths (email invites or Bluesky outage) so users know how to complete acceptance manually.

### 12.2 Background Refresh & Worker Resilience

- **Token refresh worker**:  
  - `dev/scripts/refresh-bluesky-tokens.php` scans identities with `oauth_token_expires_at < NOW() + 2h` (override with `--window`) and forces a refresh via `BlueskyOAuthService::getAccessToken($userId, true)`, logging successes/failures plus `needs_reauth`. Wire this into cron/systemd with a 30-minute cadence and scrape logs for metrics.  
  - When refresh fails, `needs_reauth = 1` is already set and `oauth_last_error` doubles as a human-readable summary; enqueue member notification events based on the script’s `needs_reauth` count.
- **Job integration**:  
  - Update follower/invitation workers to short-circuit when `needs_reauth` is true, incrementing `worker.bluesky_paused` metrics instead of retrying blindly.  
  - Ensure legacy app-password fallback is only attempted when no OAuth tokens exist, avoiding thrash between credential types.

### 12.3 Observability & Tooling

- **Dashboards**: Create Grafana/Looker tiles for OAuth start vs callback counts, refresh success %, invite conversions, and paused worker totals.  
- **Alerting**: Page on consecutive refresh failures per member (`>=3` within 24h) and on OAuth callback error rate >2% (5 min rolling window).  
- **Runbooks**: Document recovery steps (rotate client keys, manually clear `needs_reauth`, re-trigger invite) in `../social_elonara-docs/bluesky-oauth.md`.

### 12.4 Definition of Done

1. Anonymous invitee can accept purely via OAuth across staging + prod behind flag.  
2. Refresh worker keeps `needs_reauth` population <5% of connected members.  
3. Dashboards reflect same-day metrics with alerts wired to on-call rotation.  
4. Legacy app-password flow remains available but no longer the default path for new invites.

---

## End State

Every Bluesky-linked account on Elonara uses encrypted, renewable OAuth tokens; invitations automatically attach to verified DIDs; sessions persist across redirects; and legacy app-passwords are retired with measurable improvements in security and invite conversion performance.
