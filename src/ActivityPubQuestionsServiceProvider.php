<?php

namespace Ethernick\ActivityPubQuestions;

use Archetype\Endpoints\PHP\Make;
use Statamic\Providers\AddonServiceProvider;
use Ethernick\ActivityPubCore\Services\ActivityPubTypes;
use Ethernick\ActivityPubQuestions\Http\Controllers\QuestionController;
use Ethernick\ActivityPubCore\Services\ActivityDispatcher;
use Statamic\Statamic;


class ActivityPubQuestionsServiceProvider extends AddonServiceProvider
{
    protected $slug = 'activitypub-questions';

    protected $commands = [
        \Ethernick\ActivityPubQuestions\Console\Commands\ActivityPubPollsClose::class ,
    ];

    protected $routes = [
        'cp' => __DIR__ . '/routes/cp.php',
    ];

    public function boot(): void
    {
        parent::boot();

        // Register ActivityPub Type
        if (class_exists(ActivityPubTypes::class)) {
            ActivityPubTypes::register(
                'Question',
                'Question',
                null,
                QuestionController::class ,
            ['polls'],
                \Ethernick\ActivityPubQuestions\Http\Handlers\PollStoreHandler::class ,
                \Ethernick\ActivityPubQuestions\Http\Handlers\PollOutboxHandler::class ,
                \Ethernick\ActivityPubQuestions\Jobs\QuestionInboxHandler::class
            );

            $this->registerInboxHooks();
        }

        $this->registerNav();

        $this->registerAssets();

        // Register Events
        \Illuminate\Support\Facades\Event::listen(
            \Statamic\Events\EntrySaving::class ,
            \Ethernick\ActivityPubQuestions\Listeners\EnsurePollIdIsSlug::class
        );

        \Illuminate\Support\Facades\Event::listen(
            \Statamic\Events\EntrySaved::class ,
            \Ethernick\ActivityPubQuestions\Listeners\PollVoteListener::class
        );

        \Illuminate\Support\Facades\Event::listen(
            \Ethernick\ActivityPubCore\Events\EntryCleaning::class ,
            \Ethernick\ActivityPubQuestions\Listeners\ProtectPollEntries::class
        );

        if ($this->app->runningInConsole()) {
            $this->commands([
                \Ethernick\ActivityPubQuestions\Console\Commands\ActivityPubQuestionsInstall::class ,
            ]);
        }

        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'activitypub-questions');
    }

    protected function schedule(\Illuminate\Console\Scheduling\Schedule $schedule): void
    {
        $schedule->command('activitypub:polls-close')->everyMinute()->withoutOverlapping();
    }

    protected function registerNav(): void
    {
        $icon = @file_get_contents(__DIR__ . '/../resources/svg/charts.svg') ?: 'charts';

        \Ethernick\ActivityPubCore\Services\ActivityPubNav::register('polls', [
            'label' => 'Polls',
            'route' => 'activitypub.polls.index',
            'icon' => $icon,
            'order' => 20, // After Inbox (10)
            'section' => 'ActivityPub',
        ]);
    }

    protected function registerInboxHooks(): void
    {
        // 1. Register Preload Hook (Batch fetch votes)
        ActivityPubTypes::registerPreloadHook(function ($items, $userActors) {
            $pollIds = [];
            $pollUrls = [];

            foreach ($items as $item) {
                if ($item->collection()->handle() === 'polls') {
                    $pollIds[] = $item->id();
                    if ($url = $item->get('activitypub_id')) {
                        $pollUrls[$item->id()] = $url;
                    }
                }
            }

            if (empty($pollIds) || empty($userActors))
                return;

            $allPollIdentifiers = [];
            foreach ($pollIds as $pollId) {
                $allPollIdentifiers[] = (string)$pollId;
                if (isset($pollUrls[$pollId])) {
                    $allPollIdentifiers[] = (string)$pollUrls[$pollId];
                }
            }

            $votes = \Statamic\Facades\Entry::query()
                ->where('collection', 'notes')
                ->whereIn('in_reply_to', $allPollIdentifiers)
                ->whereIn('actor', $userActors)
                ->get();

            $voteCache = [];
            foreach ($pollIds as $pollId) {
                $pollIdentifiers = [(string)$pollId];
                if (isset($pollUrls[$pollId])) {
                    $pollIdentifiers[] = (string)$pollUrls[$pollId];
                }

                $pollVotes = $votes->filter(function ($vote) use ($pollIdentifiers) {
                            $inReplyTo = $vote->get('in_reply_to');
                            return in_array($inReplyTo, $pollIdentifiers);
                        }
                        );

                        $voteCache[$pollId] = [
                            'has_voted' => $pollVotes->isNotEmpty(),
                            'voted_options' => $pollVotes->map(fn($v) => $v->get('content'))->unique()->values()->all(),
                        ];
                    }

                    // Store in static cache for transform hook
                    \Ethernick\ActivityPubQuestions\Http\Controllers\PollAnalyticsController::$voteCache = $voteCache;
                });

        // 2. Register Transform Hook (Enrich transformed array)
        ActivityPubTypes::registerTransformHook(function ($entry, &$data) {
            if ($entry->collection()->handle() !== 'polls')
                return;

            $data['type'] = 'question';
            $data['options'] = $entry->get('options', []);
            $data['voters_count'] = (int)$entry->get('voters_count', 0);
            $data['end_time'] = $entry->get('end_time');
            $data['closed'] = (bool)$entry->get('closed', false);

            // Access static cache from preload hook
            $pollId = $entry->id();
            $cache = \Ethernick\ActivityPubQuestions\Http\Controllers\PollAnalyticsController::$voteCache[$pollId] ?? null;

            $data['has_voted'] = $cache['has_voted'] ?? false;
            $data['voted_options'] = $cache['voted_options'] ?? [];

            // Add custom action for editing
            $data['actions']['edit'] = 'activitypub:inbox:edit-poll';

            // Add vote URL
            $data['vote_url'] = cp_route('activitypub.polls.vote');
        });
    }

    protected function registerAssets(): void
    {
        $packageName = 'ethernick/activitypub-questions';
        $version = \Statamic\Statamic::version();
        $isV6 = version_compare($version, '6.0.0', '>=');
        $distSubdir = $isV6 ? 'v6' : 'v5';

        $distDir = __DIR__ . "/../dist/{$distSubdir}";
        if (is_dir($distDir)) {
            $this->publishes([
                "$distDir/js/cp.js" => public_path("vendor/$packageName/js/cp.js"),
            ], 'activitypub');
        }

        \Statamic\Statamic::script($packageName, 'cp.js');
    }

    public function register()
    {
    //
    }
}