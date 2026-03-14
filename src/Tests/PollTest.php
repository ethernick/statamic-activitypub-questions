<?php

namespace Ethernick\ActivityPubQuestions\Tests;

use Tests\TestCase;
use Statamic\Facades\Entry;
use Statamic\Facades\User;
use Ethernick\ActivityPubCore\Jobs\InboxHandler;

class PollTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();

        \Statamic\Facades\Collection::make('actors')->save();
        \Statamic\Facades\Collection::make('activities')->dated(true)->save();
        \Statamic\Facades\Collection::make('notes')->dated(true)->save();
        \Statamic\Facades\Collection::make('polls')->dated(true)->save();

        // Setup ActivityPub config
        $path = resource_path('settings/activitypub.yaml');
        if (!\Statamic\Facades\File::exists(resource_path('settings'))) {
            \Statamic\Facades\File::makeDirectory(resource_path('settings'));
        }
        \Statamic\Facades\File::put($path, "polls:\n  type: Question\n  federated: true\nnotes:\n  type: Note\n  federated: true\n");

        \Statamic\Facades\Blink::flush();

        Entry::query()->whereIn('collection', ['activities', 'actors', 'notes', 'polls'])->get()->each->delete();
    }

    public function tearDown(): void
    {
        // Clean up config
        $path = resource_path('settings/activitypub.yaml');
        if (\Statamic\Facades\File::exists($path)) {
            \Statamic\Facades\File::delete($path);
        }

        parent::tearDown();
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_creates_poll_from_question_activity()
    {
        $this->actingAs(User::make()->id('admin')->makeSuper()->save());

        $localActor = Entry::make()->collection('actors')->slug('me')->data(['title' => 'Me']);
        $localActor->save();

        $externalActor = Entry::make()->collection('actors')->slug('sender')->data(['title' => 'Sender', 'activitypub_id' => 'https://example.com/sender']);
        $externalActor->save();

        $localActor->set('following_actors', [$externalActor->id()]);
        $localActor->save();

        $payload = [
            'id' => 'https://example.com/activity/1',
            'type' => 'Create',
            'actor' => 'https://example.com/sender',
            'object' => [
                'id' => 'https://example.com/poll/1',
                'type' => 'Question',
                'content' => 'Favorite Color?',
                'oneOf' => [
                    ['type' => 'Note', 'name' => 'Red', 'replies' => ['type' => 'Collection', 'totalItems' => 0]],
                    ['type' => 'Note', 'name' => 'Blue', 'replies' => ['type' => 'Collection', 'totalItems' => 0]],
                ],
                'endTime' => now()->addDay()->toIso8601String(),
                'votersCount' => 0,
                'attributedTo' => 'https://example.com/sender',
                'published' => now()->toIso8601String(),
            ],
            'published' => now()->toIso8601String(),
        ];

        $handler = new InboxHandler();
        $handler->handle($payload, $localActor, $externalActor);

        $poll = Entry::query()->where('collection', 'polls')->where('activitypub_id', 'https://example.com/poll/1')->first();

        $this->assertNotNull($poll);
        // Title might be handled via title_format, so we check content which is the source of truth
        $this->assertEquals('Favorite Color?', $poll->get('content'));
        $this->assertEquals(0, $poll->get('voters_count'));
        $this->assertCount(2, $poll->get('options'));
        $this->assertEquals('Red', $poll->get('options')[0]['name']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_updates_poll_counts()
    {
        $this->actingAs(User::make()->id('admin')->makeSuper()->save());

        $localActor = Entry::make()->collection('actors')->slug('me')->data(['title' => 'Me']);
        $localActor->save();
        $externalActor = Entry::make()->collection('actors')->slug('sender')->data(['title' => 'Sender', 'activitypub_id' => 'https://example.com/sender']);
        $externalActor->save();
        $localActor->set('following_actors', [$externalActor->id()]);
        $localActor->save();

        // 1. Create Poll
        $payload = [
            'id' => 'https://example.com/activity/1',
            'type' => 'Create',
            'actor' => 'https://example.com/sender',
            'object' => [
                'id' => 'https://example.com/poll/1',
                'type' => 'Question',
                'content' => 'Favorite Color?',
                'oneOf' => [
                    ['type' => 'Note', 'name' => 'Red', 'replies' => ['type' => 'Collection', 'totalItems' => 0]],
                    ['type' => 'Note', 'name' => 'Blue', 'replies' => ['type' => 'Collection', 'totalItems' => 0]],
                ],
                'votersCount' => 0,
                'attributedTo' => 'https://example.com/sender',
            ],
        ];

        $handler = new InboxHandler();
        $handler->handle($payload, $localActor, $externalActor);

        // 2. Update Poll (Vote received)
        $updatePayload = [
            'id' => 'https://example.com/activity/2',
            'type' => 'Update',
            'actor' => 'https://example.com/sender',
            'object' => [
                'id' => 'https://example.com/poll/1',
                'type' => 'Question',
                'votersCount' => 5,
                'oneOf' => [
                    ['type' => 'Note', 'name' => 'Red', 'replies' => ['type' => 'Collection', 'totalItems' => 3]],
                    ['type' => 'Note', 'name' => 'Blue', 'replies' => ['type' => 'Collection', 'totalItems' => 2]],
                ],
            ],
        ];

        $handler->handle($updatePayload, $localActor, $externalActor);

        $poll = Entry::query()->where('collection', 'polls')->where('activitypub_id', 'https://example.com/poll/1')->first();

        $this->assertEquals(5, $poll->get('voters_count'));
        $options = $poll->get('options');
        $this->assertEquals(3, $options[0]['count']);
        $this->assertEquals(2, $options[1]['count']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_increments_reply_count_on_vote()
    {
        $this->actingAs(User::make()->id('admin')->makeSuper()->save());
        $localActor = Entry::make()->collection('actors')->slug('me')->data(['title' => 'Me']);
        $localActor->save();

        // 1. Create Poll
        $payload = [
            'id' => 'https://example.com/activity/poll',
            'type' => 'Create',
            'actor' => 'https://example.com/sender',
            'object' => [
                'id' => 'https://example.com/poll/reply-test',
                'type' => 'Question',
                'content' => 'Question?',
                'oneOf' => [
                    ['type' => 'Note', 'name' => 'A', 'replies' => ['type' => 'Collection', 'totalItems' => 0]],
                    ['type' => 'Note', 'name' => 'B', 'replies' => ['type' => 'Collection', 'totalItems' => 0]],
                ],
                'attributedTo' => 'https://example.com/sender',
            ],
        ];

        $handler = new InboxHandler();
        // Mock external actor
        $externalActor = Entry::make()->collection('actors')->slug('sender')->data(['title' => 'Sender', 'activitypub_id' => 'https://example.com/sender']);
        $externalActor->save();
        $localActor->set('following_actors', [$externalActor->id()]);
        $localActor->save();

        $handler->handle($payload, $localActor, $externalActor);

        // 2. Reply to Poll (Vote as Note)
        $replyPayload = [
            'id' => 'https://example.com/activity/vote-note',
            'type' => 'Create',
            'actor' => 'https://example.com/sender',
            'object' => [
                'id' => 'https://example.com/note/vote',
                'type' => 'Note',
                'content' => 'A',
                'inReplyTo' => 'https://example.com/poll/reply-test',
                'attributedTo' => 'https://example.com/sender',
            ]
        ];

        $handler->handle($replyPayload, $localActor, $externalActor);

        // 3. Verify Poll Reply Count
        $poll = Entry::query()->where('collection', 'polls')->where('activitypub_id', 'https://example.com/poll/reply-test')->first();
        $this->assertEquals(1, $poll->get('reply_count'));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_checks_if_user_voted()
    {
        $user = User::make()->id('admin')->makeSuper()->save();
        $this->actingAs($user);

        // 1. Setup Actors
        // Ensure local actor is linked to user
        $localActor = Entry::make()->collection('actors')->slug('me')->data(['title' => 'Me', 'user' => 'admin']);
        $localActor->save();

        $externalActor = Entry::make()->collection('actors')->slug('sender')->data(['title' => 'Sender', 'activitypub_id' => 'https://example.com/sender']);
        $externalActor->save();
        $localActor->set('following_actors', [$externalActor->id()]);
        $localActor->save();

        // 2. Create Poll via InboxHandler
        $payload = [
            'id' => 'https://example.com/activity/poll-v',
            'type' => 'Create',
            'actor' => 'https://example.com/sender',
            'object' => [
                'id' => 'https://example.com/poll/vote-check',
                'type' => 'Question',
                'content' => 'Poll?',
                'oneOf' => [
                    ['type' => 'Note', 'name' => 'Yes', 'replies' => ['type' => 'Collection', 'totalItems' => 0]],
                    ['type' => 'NoTe', 'name' => 'No', 'replies' => ['type' => 'Collection', 'totalItems' => 0]],
                ],
                'attributedTo' => 'https://example.com/sender',
            ],
        ];

        $handler = new InboxHandler();
        $handler->handle($payload, $localActor, $externalActor);

        $poll = Entry::query()->where('collection', 'polls')->where('activitypub_id', 'https://example.com/poll/vote-check')->first();
        $this->assertNotNull($poll);

        // Ensure user has the actor linked for InboxController checks
        $user = User::find('admin');
        $user->set('actors', [$localActor->id()])->save();
        $this->actingAs($user);

        // 3. Check API - Should be false
        $request = \Illuminate\Http\Request::create(cp_route('activitypub.inbox.api'), 'GET');

        $controller = app(\Ethernick\ActivityPubCore\Http\Controllers\CP\InboxController::class);
        $response = $controller->api($request);
        // api returns JsonResponse
        $data = $response->getData(true); // as array

        $pollData = collect($data['data'])->firstWhere('id', $poll->id());
        $this->assertFalse($pollData['has_voted'] ?? false);

        // 4. Vote (Create local reply Note)
        $voteNote = Entry::make()
            ->collection('notes')
            ->slug('my-vote')
            ->data([
                'content' => 'Yes',
                'in_reply_to' => $poll->id(), // Link to Statamic ID of poll
                // Ensure actor is correct ID. 
                // InboxController::getVotedOptions uses: whereIn('actor', collect($actors)->pluck('id'))
                // $actors comes from User::current()->activityPubActors().
                // We need to ensure localActor is returned by that.
                // In Setup, we set 'user' => 'admin'.
                'actor' => $localActor->id(),
                'date' => now(),
            ]);
        $voteNote->save();

        // 5. Check API - Should be true
        // Re-resolve or just re-call
        $response = $controller->api($request);
        $data = $response->getData(true);

        $pollData = collect($data['data'])->firstWhere('id', $poll->id());

        $this->assertTrue($pollData['has_voted']);
        $this->assertContains('Yes', $pollData['voted_options']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_tallies_incoming_vote_note()
    {
        $this->actingAs(User::make()->id('admin')->makeSuper()->save());
        $localActor = Entry::make()->collection('actors')->slug('me')->data(['title' => 'Me']);
        $localActor->save();
        $externalActor = Entry::make()->collection('actors')->slug('voter')->data(['title' => 'Voter', 'activitypub_id' => 'https://example.com/voter']);
        $externalActor->save();
        $localActor->set('following_actors', [$externalActor->id()]);
        $localActor->save();

        // 1. Create Local Poll
        $poll = Entry::make()
            ->collection('polls')
            ->slug('my-poll')
            ->data([
                'title' => 'My Poll',
                'content' => 'What is your favorite?',
                'options' => [
                    ['name' => 'Option A', 'count' => 0],
                    ['name' => 'Option B', 'count' => 0],
                ],
                'actor' => $localActor->id(),
                'is_internal' => true,
                'activitypub_id' => 'https://example.com/poll/my-poll',
            ]);
        $poll->save();

        $this->assertEquals(0, $poll->get('voters_count'));

        // 2. Vote via Note Reply from external actor
        $votePayload = [
            'id' => 'https://example.com/activity/vote-1',
            'type' => 'Create',
            'actor' => 'https://example.com/voter',
            'object' => [
                'id' => 'https://example.com/note/vote-reply-1',
                'type' => 'Note',
                'content' => 'Option A',
                'inReplyTo' => 'https://example.com/poll/my-poll',
                'attributedTo' => 'https://example.com/voter',
            ]
        ];

        $handler = new InboxHandler();
        $handler->handle($votePayload, $localActor, $externalActor);

        // 3. Verify Tally
        $poll = $poll->fresh();
        $this->assertEquals(1, $poll->get('voters_count'));
        $options = $poll->get('options');
        $this->assertEquals(1, $options[0]['count']);
        
        // Assert Deduplication
        $handler->handle($votePayload, $localActor, $externalActor);
        $poll = $poll->fresh();
        $this->assertEquals(1, $poll->get('voters_count'), 'Should not double-count votes from the same actor');

        // 4. Verify Update Activity was generated (by AutoGenerateActivityListener)
        $updateActivity = Entry::query()->where('collection', 'activities')->where('type', 'Update')->first();
        $this->assertNotNull($updateActivity, 'An Update activity should have been generated for the poll counts update');
        $this->assertContains($poll->id(), $updateActivity->get('object'));
    }
}
