
---

## Proposal: Reframe Bluesky OAuth as Identity & Delegation for Invitations

### 0. Context

Where we are now:

* We have a **working Bluesky OAuth client**:

  * Authorization code + DPoP + nonce + PAR all working.
  * Tokens are issued and stored per member, tied to their DID/handle.
  * The app can successfully **connect a member to a Bluesky DID**.

* Where things break:

  * `com.atproto.repo.createRecord` (write endpoint) rejects OAuth tokens with
    `InvalidToken: Bad token scope` even when the token scope is `atproto transition:generic`.
  * The same account **can** post using legacy app-password auth.

So: the *OAuth side* works for identity and graph. The **repo write API is not yet compatible with those tokens**.

We need a design that:

* Gets real traction and invitations **now**.
* Uses OAuth where it’s solid (identity + graph).
* Avoids being blocked by Bluesky’s broken write scopes.
* Can flip to “pure OAuth writes” later without reworking everything again.

---

## 1. Goals

**Business goals**

1. Any Elonara member can connect their Bluesky identity.
2. Hosts can invite their Bluesky follows/followers into **events** and **communities**.
3. Invites feel personal (from the host), not random spam from a platform bot.
4. We reduce long-term dependence on app passwords, but don’t block on Bluesky fixes.

**Technical goals**

1. **Use OAuth for identity + graph** (who is this person, who do they follow).
2. Introduce an internal **BlueskyAgent** abstraction for *sending* invites.
3. Under the hood, BlueskyAgent can:

   * Use legacy app-password / relay now.
   * Switch to OAuth write tokens later, without changing callers.
4. Keep config and feature flags explicit so we can migrate safely.

---

## 2. Strategy Overview

Instead of trying to push everything through Bluesky’s incomplete OAuth write path:

* **We keep OAuth as the official identity + consent layer**:

  * Member proves they are `did:plc:... / handle`.
  * We store and use that DID/handle for invitations and mapping.

* **We introduce a BlueskyAgent service**:

  * One interface: “post this invite / message / mention”.
  * Two possible backends:

    * **LegacyWriteClient** (using app-password / relay account).
    * **OAuthWriteClient** (using per-member OAuth tokens) — **disabled by default** until Bluesky fixes scope handling.
  * The invitation feature talks to the Agent, not directly to the ATProto write endpoint.

* **We still use OAuth tokens for reading graph data** (follows/followers) where it works, so invitation targeting can be personalized per member.

This way, you still get the *practical* value of OAuth (identity + graph + consent), and you’re not blocked by the write bug.

---

## 3. Design for Codex

### 3.1 Bluesky OAuth Identity & Token Storage (stabilize what we have)

**Intent:** Make per-member Bluesky identity + OAuth token a first-class, reliable thing in the data model.

Tasks:

1. Ensure `member_identities` (or equivalent) has at least:

   * `member_id`
   * `bsky_did`
   * `bsky_handle`
   * `bsky_access_token_enc`
   * `bsky_refresh_token_enc`
   * `bsky_scope`
   * `bsky_token_expires_at`

2. Confirm `BlueskyOAuthService`:

   * On callback:

     * Validates the `code` and `state`.
     * Exchanges `code` → token via `/oauth/token`.
     * Reads `scope` from the response.
     * Encrypts and stores tokens tied to the **current logged-in member**, not just a single global account.
   * On “Reauthorize”:

     * Re-runs the flow and overwrites token + scope for that member.

3. Add helpers:

   * `BlueskyOAuthService::getIdentityForMember($memberId)` → returns DID, handle, and decrypted token or null.
   * `BlueskyOAuthService::isConnected($memberId)` → boolean.

Result: any code can say “for member X, what is their Bluesky identity and token?” and get a stable answer.

---

### 3.2 BlueskyAgent: one interface, pluggable backends

**Intent:** Decouple “how we post to Bluesky” from invitations, and hide the “OAuth vs app-password vs relay” detail behind an interface.

**Interface (pseudo-PHP):**

```php
interface BlueskyAgentInterface
{
    public function createPostForMember(int $memberId, array $record): BlueskyResult;
}
```

Where `BlueskyResult` is a simple value object:

```php
class BlueskyResult
{
    public bool $success;
    public ?string $errorMessage;
    public ?array $responsePayload;
}
```

**Implementations:**

1. `LegacyBlueskyAgent` (active **now**)

   * Uses either:

     * The member’s stored app-password credentials (if still allowed), or
     * A single **relay account** credential.
   * Calls `com.atproto.repo.createRecord` using app-password auth (the way that currently works).
   * Ignores OAuth tokens.

2. `OAuthBlueskyAgent` (implemented but **feature-flagged off**)

   * Uses `BlueskyOAuthService::getIdentityForMember($memberId)` to obtain:

     * DID
     * Access token
     * Scope
   * If token present and scope includes `atproto`, it:

     * Builds the DPoP + client assertion.
     * Calls `com.atproto.repo.createRecord` with OAuth token.
   * For now, we expect this to fail with `InvalidToken: Bad token scope`, so this implementation is parked behind a feature flag until Bluesky fixes their side.

**Factory/Config:**

In config:

```php
'bluesky' => [
    'writes' => [
        'mode' => 'legacy', // 'legacy' or 'oauth'
    ],
    // existing keys...
];
```

Factory:

```php
class BlueskyAgentFactory
{
    public static function make(): BlueskyAgentInterface
    {
        $mode = app_config('bluesky.writes.mode');

        if ($mode === 'oauth') {
            return new OAuthBlueskyAgent(/* dependencies */);
        }

        return new LegacyBlueskyAgent(/* dependencies */);
    }
}
```

Result: all invitation-related code calls `BlueskyAgentFactory::make()` and doesn’t care which underlying method is used.

---

### 3.3 Invitation flows: use OAuth for graph, Agent for posts

**Intent:** Deliver the *actual* feature: letting members invite Bluesky follows/followers to events/communities.

**Read side (graph):**

* `BlueskyGraphService` uses the **member’s OAuth token** to call:

  * `app.bsky.graph.getFollowers`
  * `app.bsky.graph.getFollows`
* The token and DPoP setup already exist; reuse them.
* Expose methods:

  * `getFollowers($memberId, $cursor = null)`
  * `getFollows($memberId, $cursor = null)`

**Write side (invites):**

* `BlueskyInvitationService`:

  * Given `memberId` and a specific follower DID or handle, constructs the `app.bsky.feed.post` record for an invite.

  * Calls:

    ```php
    $agent = BlueskyAgentFactory::make();
    $result = $agent->createPostForMember($memberId, $record);
    ```

  * Logs success/failure and exposes a clean result to the UI.

The *only* change needed when Bluesky fixes OAuth writes is to:

* Flip `bluesky.writes.mode` from `'legacy'` → `'oauth'`.

No changes to invitation UI, flows, or event/community code.

---

### 3.4 Internal “Elonara token” (optional, but forward-looking)

This is optional right now and can be tackled later, but:

* When a member connects Bluesky, we can also issue an **Elonara JWT** that encapsulates:

  * `member_id`
  * `bsky_did`
  * Permissions inside Elonara
* The front-end uses that token to call Elonara APIs; all Bluesky interaction happens server-side.

This keeps all actual Bluesky tokens off the front-end and makes it easier to support future ATProto PDS integrations.

For now, this is “Phase 2+”; Codex doesn’t need to implement it before finishing the Agent and invitation wiring.

---

## 4. Concrete Tasks for Codex

You can give Codex this exact checklist.

1. **Stabilize per-member OAuth storage**

   * Ensure `BlueskyOAuthService` stores tokens per `member_id` with DID/handle/scope.
   * Add `getIdentityForMember($memberId)` and `isConnected($memberId)` helpers.
   * Confirm the OAuth status card uses these helpers.

2. **Introduce `BlueskyAgentInterface` and factory**

   * Define `BlueskyAgentInterface::createPostForMember(int $memberId, array $record): BlueskyResult`.
   * Implement `LegacyBlueskyAgent` using existing app-password or relay posting code.
   * Implement `BlueskyAgentFactory` with config-based `mode`.

3. **Refactor invitation posting to use BlueskyAgent**

   * Locate existing `BlueskyInvitationService` / `createPost` calls.
   * Replace direct repo calls with `BlueskyAgentFactory::make()->createPostForMember(...)`.

4. **Wire graph reads through OAuth**

   * Implement `BlueskyGraphService` using the member’s OAuth token.
   * Replace any existing follower/follow graph reads that were using legacy auth.

5. **Config + Feature flags**

   * In `config/config.php`, add:

     ```php
     'bluesky' => [
         // ...
         'writes' => [
             'mode' => 'legacy', // 'legacy' now, 'oauth' later
         ],
     ];
     ```

   * Make sure this is the single source of truth for which Agent implementation is used.

6. **Prepare for future switch to OAuth writes**

   * Ensure `OAuthBlueskyAgent` compiles and is testable, but leave `mode=legacy` in production.
   * Once Bluesky fixes the scope bug, switching to OAuth writes should be:

     * Change `mode` to `'oauth'`.
     * Reauthorize tokens for key hosts.
     * Test invites.

---

## 5. Out of Scope (explicitly)

Codex should **not** do these right now:

* Don’t remove app-password support.
* Don’t attempt to hack around Bluesky’s scope bug by spoofing scopes or bypassing DPoP.
* Don’t attempt to run a full ATProto PDS in this sprint.
* Don’t expose raw Bluesky access tokens to the browser.

---


> “Implement this design. Use OAuth for identity + graph, introduce BlueskyAgent with legacy writes now, and keep an OAuth write backend ready behind a feature flag. The goal is: any member can connect Bluesky and invite followers, even while Bluesky’s token scopes are broken for writes.”

