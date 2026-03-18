<?php

namespace Ethernick\ActivityPubQuestions\Tests;

use Tests\TestCase;
use Statamic\Facades\Entry;

class PollsActivityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        
        $this->setupCollections(['actors', 'activities', 'polls']);

        // Setup ActivityPub config in sandbox
        \Statamic\Facades\File::put(
            \Ethernick\ActivityPubCore\Services\ActivityPubUtils::settingsPath(), 
            "polls:\n  type: Question\n  federated: true\n"
        );
    }

    protected function tearDown(): void
    {
        parent::tearDown();
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
