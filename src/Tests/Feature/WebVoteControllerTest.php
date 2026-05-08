<?php

namespace Ethernick\ActivityPubQuestions\Tests\Feature;

use Illuminate\Support\Facades\Event;
use Statamic\Facades\Entry;
use Tests\TestCase;
use Ethernick\ActivityPubCore\Tests\Concerns\ProvidesSandbox;
use Illuminate\Foundation\Testing\RefreshDatabase;

class WebVoteControllerTest extends TestCase
{
    use ProvidesSandbox;

    protected function setUp(): void
    {
        parent::setUp();
        // Assume Sandbox handles isolation, though we should make sure we have a 'polls' collection in our test environment
        // if not, tests might fail on collection() handle check, but often TestCase sets this up.
    }

    public function test_can_vote_on_poll()
    {
        Event::fake();

        $poll = Entry::make()
            ->collection('polls')
            ->slug('test-poll-1')
            ->data([
                'title' => 'Favorite Color?',
                'options' => [
                    ['name' => 'Red', 'count' => 0],
                    ['name' => 'Blue', 'count' => 0],
                ],
                'closed' => false,
                'voters_count' => 0,
            ]);
        $poll->save();

        $response = $this->postJson(route('activitypub.polls.vote.web', ['id' => $poll->id()]), [
            'option' => 'Red',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'total_votes' => 1,
            'options' => [
                ['name' => 'Red', 'votes' => 1, 'percentage' => 100],
                ['name' => 'Blue', 'votes' => 0, 'percentage' => 0],
            ]
        ]);

        $freshPoll = Entry::find($poll->id());
        $this->assertEquals(1, $freshPoll->get('voters_count'));
        $this->assertEquals(1, $freshPoll->get('options')[0]['count']);
    }

    public function test_cannot_vote_twice()
    {
        $poll = Entry::make()
            ->collection('polls')
            ->slug('test-poll-2')
            ->data([
                'title' => 'Favorite Color?',
                'options' => [
                    ['name' => 'Red', 'count' => 0],
                    ['name' => 'Blue', 'count' => 0],
                ],
                'closed' => false,
                'voters_count' => 0,
            ]);
        $poll->save();

        // First vote
        $response1 = $this->postJson(route('activitypub.polls.vote.web', ['id' => $poll->id()]), [
            'option' => 'Red',
        ]);
        $response1->assertStatus(200);

        // Second vote
        $response2 = $this->postJson(route('activitypub.polls.vote.web', ['id' => $poll->id()]), [
            'option' => 'Red',
        ]);

        $response2->assertStatus(403);
        $response2->assertJson(['error' => 'You have already voted.']);
    }

    public function test_cannot_vote_on_closed_poll()
    {
        $poll = Entry::make()
            ->collection('polls')
            ->slug('test-poll-3')
            ->data([
                'title' => 'Favorite Color?',
                'options' => [
                    ['name' => 'Red', 'count' => 0],
                ],
                'closed' => true,
            ]);
        $poll->save();

        $response = $this->postJson(route('activitypub.polls.vote.web', ['id' => $poll->id()]), [
            'option' => 'Red',
        ]);

        $response->assertStatus(400);
        $response->assertJson(['error' => 'Poll is closed.']);
    }

    public function test_can_vote_multiple_times_in_dev_environment()
    {
        // Mock environment as 'dev'
        app()['env'] = 'dev';

        $poll = Entry::make()
            ->collection('polls')
            ->slug('test-poll-dev')
            ->data([
                'title' => 'Favorite Color?',
                'options' => [
                    ['name' => 'Red', 'count' => 0],
                ],
                'closed' => false,
            ]);
        $poll->save();

        // First vote
        $this->postJson(route('activitypub.polls.vote.web', ['id' => $poll->id()]), ['option' => 'Red'])->assertStatus(200);
        
        // Second vote (should still pass in dev)
        $this->postJson(route('activitypub.polls.vote.web', ['id' => $poll->id()]), ['option' => 'Red'])->assertStatus(200);

        $freshPoll = Entry::find($poll->id());
        $this->assertEquals(2, $freshPoll->get('voters_count'));

        // Reset env
        app()['env'] = 'testing';
    }
}
