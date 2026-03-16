<?php

namespace Ethernick\ActivityPubQuestions;

use Illuminate\Support\ServiceProvider;
use Ethernick\ActivityPubCore\Services\ActivityPubTypes;
use Ethernick\ActivityPubQuestions\Http\Controllers\QuestionController;
use Ethernick\ActivityPubCore\Services\ActivityDispatcher;
use Statamic\Statamic;


class ActivityPubQuestionsServiceProvider extends ServiceProvider
{
    public function boot()
    {
        // Register ActivityPub Type
        if (class_exists(ActivityPubTypes::class)) {
            ActivityPubTypes::register(
                'Question',
                'Question',
                QuestionController::class,
                null,
                ['polls'],
                \Ethernick\ActivityPubQuestions\Types\QuestionPayloadFormatter::class,
                \Ethernick\ActivityPubQuestions\Jobs\QuestionInboxHandler::class
            );
        }

        $this->registerAssets();

        // Register Events
        \Illuminate\Support\Facades\Event::listen(
            \Statamic\Events\EntrySaving::class,
            \Ethernick\ActivityPubQuestions\Listeners\EnsurePollIdIsSlug::class
        );

        \Illuminate\Support\Facades\Event::listen(
            \Statamic\Events\EntrySaved::class,
            \Ethernick\ActivityPubQuestions\Listeners\PollVoteListener::class
        );

        if ($this->app->runningInConsole()) {
            $this->commands([
                \Ethernick\ActivityPubQuestions\Console\Commands\ActivityPubQuestionsInstall::class,
            ]);
        }
    }

    protected function registerAssets(): void
    {
        $packageName = 'ethernick/activitypub-questions';
        
        foreach (['v5', 'v6'] as $version) {
            $distDir = __DIR__ . "/../dist/{$version}";
            if (is_dir($distDir)) {
                $this->publishes([
                    "$distDir/js/cp.js" => public_path("vendor/$packageName/js/cp.js"),
                ], 'activitypub-questions');
                break; // Use the first one found
            }
        }

        Statamic::script($packageName, 'cp');
    }

    public function register()
    {
        //
    }
}
