# ActivityPub Polls for Statamic

An ActivityPub Core Extention to add federated polls to Statamic

## 🌟 What This Will Do

- ✅ **Publish** polls to Mastodon, Pixelfed, and the entire fediverse
- ✅ **Accept** responsese from any ActivityPub server
- ✅ **Federate** automatically with HTTP signatures, and activity delivery
- ✅ **Moderate** with built-in blocking and content filtering

## 📋 Requirements

- Statamic 5.x or higher
- PHP 8.2+ (8.3 recommended)
- Laravel 12.x
- Public domain with HTTPS (required for federation)
- Queue worker running (Laravel queue)
- SQLite or MySQL database

## 🎯 Use Cases

- **Personal Blog** → Auto-post articles to Mastodon followers
- **Community Site** → Enable members to interact with fediverse
- **News Site** → Broadcast updates to federated network
- **Portfolio** → Share work with decentralized audience

## Installation

### WIP: Add Repository to Composer.json

Until this is registered, add the repository to composer.json

```json
"repositories": [
    {
        "type": "vcs",
        "url": "https://worktree.ca/ethernick/statamic-activitypub-questions.git"
    }
]
```

Once added you can run the following

```bash
$ composer require ethernick/statamic-activitypub-questions:dev
```

Note: if you want to install a specific branch, fee free. Update "main" with the target branch name. Where the full version format is dev-<Branch Name>
Run Install

```bash
$ php artisan actvitiypub:questions:install
```

This install will:

- setup polls
- create/associate the poll collection

## Antlers Tags
- `{{ activitypub_poll:form }}`: On it's own, renders a pre-built HTML form for voting. If the poll is closed, it automatically renders the results. Or for more Advanced users, you can use `{{ activitypub_poll:form }}{{ /activitypub_poll:form }}` to customize the form template.
  - `{{ action }}`, `{{ method }}`, `{{ id }}`: for `<form>`
  - `{{ options }}`: available poll options
    - `{{ name }}`: available option name
    - `{{ type }}`: radio/checkbox depending on if poll only allow single, or multiple values
    - `{{ ref }}`: variable name reference to submit value
  - `{{ closed }}`: boolean indicating if poll is closed
  - `{{ total_votes }}`: current total votes
- `{{ activitypub_poll:results }}`: design the post-submit or closed-state results view.
  - `{{ options }}`: available poll options
    - `{{ name }}`: name of the response
    - `{{ percentage }}`: whole number representing %
    - `{{ votes }}`: vote count for this specific option
  - `{{ total_votes }}`: total vote count across all options
  - `{{ message }}...{{ /message }}`: Renders only after a successful form submission (e.g. "Thanks for voting!")
- `{{ activitypub_poll:script }}`: Injects a lightweight JS script to handle the form submission asynchronously via AJAX and swap the form for results.

Simple Example:
```antlers
<div class="poll">
    <h3>{{ title }}</h3>
    {{ activitypub_poll:form }}
</div>
{{ activitypub_poll:script }}
```

Advanced Example:
        {{ activitypub_poll:form }}
        <form action="{{ action }}" method="{{ method }}" data-poll-id="{{ id }}">
            {{ csrf }}
            {{ options }}
            <div>
                <input type="{{ type }}" name="{{ ref }}" value="{{ name }}">
                <label>{{ name }}</label>
            </div>
            {{ /options }}
            <button type="submit">Vote</button>
        </form>
        {{ /activitypub_poll:form }}

        {{ activitypub_poll:results }}
            <h2>Results</h2>
            <ul>
            {{ options }}
                <li>{{ name }} - {{ percentage }}% ({{ votes }} votes)</li>
            {{ /options }}
            </ul>
            Total number of votes: {{ total_votes }}
        {{ /activitypub_poll:results }}
    </div>
</div>
{{ activitypub_poll:script }}

### Context Awareness
All tags automatically detect the id of the current entry if it matches the expected collection (polls or places). You can also pass an explicit `:id="my_id"` if needed.

## 🤖 AI Disclosure

This was written with the help of AI agents including Gemini & Claude. 