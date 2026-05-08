<?php

use Illuminate\Support\Facades\Route;
use Ethernick\ActivityPubQuestions\Http\Controllers\WebVoteController;

Route::post('activitypub/polls/{id}/vote', [WebVoteController::class, 'store'])
    ->name('activitypub.polls.vote.web')
    ->middleware('web');
