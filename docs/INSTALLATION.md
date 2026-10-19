# Installation & Deployment Guide: ActivityPubQuestions

This guide details the complete installation, automated configuration, environment verification, and production operational practices for the **ActivityPubQuestions** addon (`ethernick/activitypub-questions`).

---

## 1. System Requirements

Ensure the target Statamic deployment meets the following baseline dependencies:

| Component | Requirement | Notes |
| :--- | :--- | :--- |
| **PHP** | `^8.2` | With `ext-ctype`, `ext-json`, `ext-curl`, and `ext-openssl` enabled |
| **Statamic CMS** | `^5.0 \| ^6.0` | Compatible with Statamic 5.x and 6.x |
| **ActivityPubCore** | `^1.0 \|\| dev-main` | Required foundation addon (`ethernick/activitypub-core`) |
| **Queue Worker** | Running (redis / database) | Processes outgoing federation and vote tally updates asynchronously |
| **Cron / Scheduler** | `* * * * * php artisan schedule:run` | Required to automatically close expired polls via `activitypub:polls-close` |

---

## 2. Composer Installation

Install the package via Composer into your Statamic root directory:

```bash
composer require ethernick/activitypub-questions
```

Composer will detect the Statamic addon and auto-register `ActivityPubQuestionsServiceProvider`.

---

## 3. Automated Setup Command

Run the interactive installer to generate the `polls` content collection and blueprint if they do not already exist:

```bash
php artisan activitypub:questions:install
```

### What the Installer Does:
1. **Creates Collection**: Adds `content/collections/polls.yaml` with ordered entries, routing (`/polls/{slug}`), and default author/date taxonomies.
2. **Generates Blueprint**: Creates `resources/blueprints/collections/polls/poll.yaml` defining:
   - `question` (Text field, markdown allowed)
   - `poll_type` (Select: `oneOf` for single choice, `anyOf` for multiple choice)
   - `options` (Grid / Table of poll options with label and tally)
   - `end_time` (Date/Time picker for automatic expiration)
   - `closed` (Toggle switch to lock voting manually or automatically)
   - `voters` (Array of federated actor URIs who have cast ballots)
3. **Registers Handlers**: Injects `QuestionPayloadFormatter` and `QuestionInboxHandler` into Core's `ActivityPubTypes` registry.
4. **CP Navigation**: Appends **Polls** to the CP **ActivityPub** navigation menu.

---

## 4. Operational Requirements: Expired Polls Worker

ActivityPub polls specify an `endTime`. Once expired, polls must be marked `closed` and broadcast an `Update(Question)` activity to all remote inboxes so Mastodon and fediverse clients freeze ballot inputs.

Add the poll closing worker to your server scheduler or Laravel `routes/console.php` (or `app/Console/Kernel.php` on Laravel 10/11):

```php
use Illuminate\Support\Facades\Schedule;

Schedule::command('activitypub:polls-close')->everyFiveMinutes();
```

To run manually or test expiration logic:
```bash
php artisan activitypub:polls-close
```

---

## 5. Frontend Integration (Antlers)

Display active and closed polls on your website using native Statamic Antlers tags.

### Complete Poll Card Example (`resources/views/polls/show.antlers.html`):

```antlers
<article class="poll-entry">
    <header>
        <h2>{{ question }}</h2>
        <p class="poll-meta">
            Posted by {{ author:name or "Local Node" }} &bull;
            {{ if closed }}
                <strong class="status-closed">Closed</strong>
            {{ else }}
                <span class="status-open">Closes in {{ end_time | relative }}</span>
            {{ /if }}
        </p>
    </header>

    {{ if closed }}
        {{# Display Final Results #}}
        <div class="poll-results">
            {{ options }}
                <div class="result-row">
                    <div class="result-label">
                        <span>{{ label }}</span>
                        <span>{{ votes or 0 }} votes ({{ percent }}%)</span>
                    </div>
                    <progress value="{{ percent }}" max="100">{{ percent }}%</progress>
                </div>
            {{ /options }}
            <p class="voter-tally"><small>{{ voters_count or 0 }} total voters</small></p>
        </div>
    {{ else }}
        {{# Public Web Ballot Form #}}
        <form method="POST" action="/!/activitypub-questions/vote" class="poll-form">
            {{ csrf_field }}
            <input type="hidden" name="poll_id" value="{{ id }}" />

            <fieldset>
                {{ options }}
                    <label class="poll-option">
                        <input 
                            type="{{ poll_type == 'anyOf' ? 'checkbox' : 'radio' }}" 
                            name="options[]" 
                            value="{{ key }}" 
                            required
                        />
                        <span>{{ label }}</span>
                    </label>
                {{ /options }}
            </fieldset>

            <button type="submit">Submit Vote</button>
        </form>
    {{ /if }}
</article>
```


---

## 6. Verification Checklist

Execute these checks after installation to ensure full operability:

- [ ] Run `php artisan activitypub:questions:install` without errors.
- [ ] Confirm `content/collections/polls.yaml` exists and is readable.
- [ ] Verify `Polls` appears in the CP sidebar under `ActivityPub`.
- [ ] Create a test poll with an expiration date 10 minutes in the future.
- [ ] Confirm outbound `Create(Question)` activity is queued in `activitypub-outbox`.
- [ ] Cast a vote from a remote Mastodon client and inspect `PollVoteListener` updates.
- [ ] Execute `php artisan activitypub:polls-close` to verify expiration handling.
