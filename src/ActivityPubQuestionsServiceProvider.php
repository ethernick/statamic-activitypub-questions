<?php

namespace Ethernick\ActivityPubQuestions;

use Illuminate\Support\ServiceProvider;
use Ethernick\ActivityPubCore\Services\ActivityPubTypes;
use Ethernick\ActivityPubQuestions\Http\Controllers\QuestionController;
use Ethernick\ActivityPubCore\Services\ActivityDispatcher;

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
                ['polls']
            );
        }
    }

    public function register()
    {
        //
    }
}
