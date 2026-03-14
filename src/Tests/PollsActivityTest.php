<?php

namespace Ethernick\ActivityPubQuestions\Tests;

use Tests\TestCase;
use Statamic\Facades\Entry;

class PollsActivityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['statamic.editions.pro' => true]);

        // Write config file expected by listener
        $path = resource_path('settings/activitypub.yaml');
        if (!\Statamic\Facades\File::exists(resource_path('settings'))) {
            \Statamic\Facades\File::makeDirectory(resource_path('settings'));
        }
        \Statamic\Facades\Collection::make('actors')->save();
        \Statamic\Facades\Collection::make('activities')->dated(true)->save();
        \Statamic\Facades\Collection::make('polls')->dated(true)->save();

        \Statamic\Facades\File::put($path, "polls:\n  type: Question\n  federated: true\n");

        $this->cleanup();
    }

    protected function tearDown(): void
    {
        $this->cleanup();

        // Clean up config
        $path = resource_path('settings/activitypub.yaml');
        if (\Statamic\Facades\File::exists($path)) {
            \Statamic\Facades\File::delete($path);
        }

        parent::tearDown();
    }

    protected function cleanup()
    {
        \Statamic\Facades\Entry::query()
            ->whereIn('collection', ['polls', 'activities', 'actors'])
            ->get()
            ->filter(fn($entry) => str_contains($entry->slug(), 'test-poll-gen'))
            ->each->delete();
    }

    public function test_creating_poll_generates_question_activity_summary()
    {
        // 1. Create a local actor
        $actor = Entry::make()
            ->collection('actors')
            ->slug('test-poll-gen-actor')
            ->data([
                'title' => 'Poll Tester',
                'is_internal' => true,
            ]);
        $actor->save();

        // 2. Create a Poll
        // 'polls' collection is configured with type: Question in activitypub.yaml
        $poll = Entry::make()
            ->collection('polls')
            ->slug('test-poll-gen-entry')
            ->data([
                'title' => 'What is your favorite color?',
                'content' => 'Please vote.',
                'actor' => $actor->id(),
                'is_internal' => true,
                'published' => true,
            ]);

        $poll->save();

        // 3. Find the generated activity
        $activity = Entry::query()
            ->where('collection', 'activities')
            ->where('slug', 'like', 'activity-%')
            ->get()
            ->first(function ($entry) use ($poll) {
                $object = $entry->get('object');
                return $object && in_array($poll->id(), $object);
            });

        $this->assertNotNull($activity, 'Activity should be generated for the poll.');

        // 4. Verify Summary uses dynamic type 'question'
        // Expected: "Poll Tester created a question"
        // Old logic would have resulted in "Poll Tester created an article"
        $this->assertStringContainsString('created a question', $activity->get('content'));
        $this->assertEquals('Create', $activity->get('type'));
    }
}
