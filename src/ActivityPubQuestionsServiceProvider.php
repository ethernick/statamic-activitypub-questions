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
    }

    protected function registerAssets(): void
    {
        $packageName = 'ethernick/activitypub-questions';
        
        // Detection logic for dist path (Statamic 5 vs 6)
        $version = Statamic::version();
        $isV6 = version_compare($version, '6.0.0', '>=');
        $distPath = $isV6 ? 'v6' : 'v5';
        $distDir = __DIR__ . "/../dist/{$distPath}";

        if (is_dir($distDir)) {
            $this->publishes([
                "$distDir/js/cp.js" => public_path("vendor/$packageName/js/cp.js"),
            ], 'activitypub-questions');
        }

        Statamic::script($packageName, 'cp');
    }

    public function register()
    {
        //
    }
}
