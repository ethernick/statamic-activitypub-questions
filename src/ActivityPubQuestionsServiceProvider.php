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

        Statamic::script('ethernick/activitypub-questions', 'cp');

        // Register Events
        \Illuminate\Support\Facades\Event::listen(
            \Statamic\Events\EntrySaved::class,
            \Ethernick\ActivityPubQuestions\Listeners\PollVoteListener::class
        );
    }

    public function register()
    {
        //
    }
}
