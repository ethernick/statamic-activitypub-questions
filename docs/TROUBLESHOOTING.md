# Troubleshooting & Diagnostics: ActivityPubQuestions

This guide provides targeted diagnostics, log interpretation, and resolution workflows for issues encountered while federating polls and processing votes.

---

## 1. Quick Diagnostic Flowchart

```
Issue Detected
  ├── Outbound Poll Not Appearing on Fediverse?
  │     ├── Check activitypub-outbox queue: `php artisan queue:monitor activitypub-outbox`
  │     └── Verify `endTime` is ISO 8601 UTC in payload inspector
  ├── Votes Cast on Mastodon Not Incrementing Local Tally?
  │     ├── Inspect `inbox` logs for incoming `Create(Note)` replies
  │     └── Verify note `name` matches exact poll option string
  ├── Poll Never Closes After Expiration?
  │     ├── Verify cron schedule: `php artisan schedule:list`
  │     └── Run manual closer: `php artisan activitypub:polls-close -v`
  └── Duplicate Vote Errors or Double-Counting?
        └── Check `voters` array in `content/collections/polls/{slug}.md`
```

---

## 2. Common Issues & Root Causes

### Issue A: Remote Votes Not Registering in Statamic

**Symptoms:**
- Remote Mastodon/Pleroma users select an option and vote.
- The Statamic CP or front-end tally remains at 0 votes.

**Root Causes & Resolution:**
1. **ActivityPub Vote Representation**:
   - Mastodon expresses votes as an incoming `Create` activity wrapping a `Note` object. The `Note` has an `inReplyTo` pointing to the poll URI and sets `name: "Selected Option Label"`.
   - If the remote server sends HTML in `content` but omits `name`, verify `PollVoteListener.php` fallback logic.
2. **Actor Array vs String TypeError**:
   - In earlier versions, actor fields stored as arrays caused `strtolower()` type errors during Stache query evaluation. Ensure you are on latest `ActivityPubQuestions` where actor IDs are dereferenced safely.
3. **Queue Delay**:
   - If queue workers are stalled, `InboxHandler` jobs accumulate. Verify worker status:
     ```bash
     php artisan queue:work --queue=activitypub-inbox,default
     ```

---

### Issue B: Outgoing Polls Displayed as Plain Text on Remote Servers

**Symptoms:**
- Outbound poll renders on Mastodon as a regular text post without interactive radio/checkbox options.

**Root Causes & Resolution:**
1. **Missing or Corrupted `oneOf` / `anyOf` Schema**:
   - ActivityPub Core delegates question formatting to `QuestionPayloadFormatter`.
   - The formatter requires `options` array items containing `name` and `replies: { type: "Collection", totalItems: 0 }`.
2. **Missing `endTime` Attribute**:
   - Mastodon drops interactive poll widgets if `endTime` is missing or in the past. Ensure your entry has an `end_time` in future UTC.

---

### Issue C: Polls Remain Open Indefinitely

**Symptoms:**
- The expiration time passes, but remote users can still cast ballots and Statamic status is still "Open".

**Root Causes & Resolution:**
1. **Scheduler Not Configured**:
   - ActivityPub is passive; it requires a local worker to detect elapsed time and trigger federation.
   - Run the diagnostic command manually:
     ```bash
     php artisan activitypub:polls-close --verbose
     ```
2. **Broadcast Update Failure**:
   - When a poll closes, an `Update(Question)` activity is sent to all followers and previous voters. If HTTP signatures fail or the outbox queue is blocked, remote servers do not know the poll is closed.

---

### Issue D: Public Web Voting Rejection (CSRF or 403)

**Symptoms:**
- Web users submitting the public ballot encounter `419 Page Expired` or `403 Forbidden`.

**Root Causes & Resolution:**
1. **Missing CSRF Token**:
   - Verify `{{ csrf_field }}` is present inside `<form method="POST" action="/!/activitypub-questions/vote">`.
2. **Double Voting Check**:
   - If local session IP/voter tracking is enabled and the visitor has already voted, the controller rejects additional ballots with HTTP 422.

---

## 3. Useful Artisan CLI Commands

| Command | Purpose |
| :--- | :--- |
| `php artisan activitypub:questions:install` | Re-run collection/blueprint scaffolding safely |
| `php artisan activitypub:polls-close` | Scan and close all expired polls now |
| `php artisan queue:failed` | Inspect failed outbound/inbound poll jobs |
| `php artisan queue:retry all` | Retry failed federation jobs |

---

## 4. Logging & Diagnostics

Enable verbose activitypub logging in `.env`:

```env
ACTIVITYPUB_LOG_LEVEL=debug
```

Monitor incoming vote events in real-time:
```bash
tail -f storage/logs/laravel.log | grep -E "PollVoteListener|QuestionInboxHandler|activitypub:polls-close"
```
