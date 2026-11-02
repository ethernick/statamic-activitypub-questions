# Developer Extension & Hooks Guide: ActivityPubQuestions

This guide details how developers can extend the **ActivityPubQuestions** addon via Laravel events, Core payload hooks, and Control Panel Vue extension points.

---

## 1. Backend Hooks: Outbound Question Payloads

You can alter the outbound ActivityStreams 2.0 JSON payload before it is signed and broadcast by registering a filter or listener in your application service provider.

### Intercepting Outbound Question Data:

```php
namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Statamic\Events\EntrySaving;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Example: Add custom tags or tracking metadata to outbound polls
        \Event::listen('activitypub.question.formatting', function ($payload, $entry) {
            $payload['tag'] = [
                [
                    'type' => 'Hashtag',
                    'href' => url('/tags/survey'),
                    'name' => '#survey',
                ]
            ];
            return $payload;
        });
    }
}
```

---

## 2. Inbound Vote Event Hooks

Whenever an inbound vote is verified and counted, an event is triggered allowing you to notify authors, run real-time analytics, or log actions to external monitoring tools.

### Listening for Votes:

```php
namespace App\Listeners;

use Ethernick\ActivityPubQuestions\Events\PollVoteRecorded;

class NotifyPollAuthorOnVote
{
    public function handle(PollVoteRecorded $event): void
    {
        $poll = $event->poll;          // Statamic Entry instance
        $optionKey = $event->optionKey; // e.g., 'opt_2'
        $voterActor = $event->actorId;  // URI of voter actor

        \Log::info("Vote recorded on poll {$poll->get('title')} for option {$optionKey} by {$voterActor}");
    }
}
```

Register this in your `EventServiceProvider`:
```php
protected $listen = [
    \Ethernick\ActivityPubQuestions\Events\PollVoteRecorded::class => [
        \App\Listeners\NotifyPollAuthorOnVote::class,
    ],
];
```

---

## 3. Frontend & Control Panel Extension (Vue Hooks)

`ActivityPubQuestions` interacts with `ActivityPubCore`'s Vue 2 / Vue 3 hook system. Developers can inject additional cards, buttons, or drawers into the CP Polls dashboard.

### Available UI Extension Points:

| Hook ID | Placement | Purpose |
| :--- | :--- | :--- |
| `inbox-actions` | Inbox post action bar | Renders the "New Poll" / "Poll Details" modal triggers |
| `inbox-note-content` | Note feed body | Injects the embedded `PollBox.vue` card inside poll notes |
| `polls-dashboard-header` | Top of Polls Dashboard | Inject custom analytics widgets or export buttons |
| `poll-stack-tabs` | Inside Poll slide-over stack | Add custom voter auditing tabs or export panels |

### Registering a Custom Vue CP Component:

```javascript
// resources/js/cp.js
Statamic.booting(() => {
    if (window.Statamic && window.Statamic.$activitypub && window.Statamic.$activitypub.hooks) {
        window.Statamic.$activitypub.hooks.register('poll-stack-tabs', {
            id: 'custom-export-tab',
            priority: 50,
            component: 'CustomPollExportTab',
        });
    }
});
```

---

## 4. Custom Antlers Tags

You can query poll statistics directly in your frontend Antlers templates using custom modifiers or collection tags:

```antlers
{{# Fetch the 3 most popular polls #}}
{{ collection:polls sort="voters_count:desc" limit="3" }}
    <div class="trending-poll">
        <h4>{{ title }}</h4>
        <p>{{ voters_count }} people voted</p>
    </div>
{{ /collection:polls }}
```
