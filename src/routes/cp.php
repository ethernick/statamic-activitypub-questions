<?php

use Illuminate\Support\Facades\Route;

Route::namespace('Ethernick\ActivityPubQuestions\Http\Controllers')->group(function () {
    Route::get('activitypub/polls', 'PollAnalyticsController@index')->name('activitypub.polls.index');
    Route::get('activitypub/polls/metrics', 'PollAnalyticsController@metrics')->name('activitypub.polls.metrics');
    Route::get('activitypub/polls/{poll}/voters', 'PollAnalyticsController@voters')->name('activitypub.polls.voters');
    Route::post('activitypub/polls/{poll}/close', 'PollAnalyticsController@close')->name('activitypub.polls.close');
    Route::put('activitypub/polls/{poll}', 'PollAnalyticsController@update')->name('activitypub.polls.update');
    Route::post('activitypub/polls/vote', 'QuestionController@vote')->name('activitypub.polls.vote');
});
