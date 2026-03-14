<?php

declare(strict_types=1);

namespace Ethernick\ActivityPubQuestions\Listeners;

use Statamic\Events\EntrySaved;
use Statamic\Facades\Entry;
use Illuminate\Support\Facades\Log;

class PollVoteListener
{
    public function handle(EntrySaved $event): void
    {
        $entry = $event->entry;

        // 1. Filter for coming notes (external)
        if ($entry->collection()->handle() !== 'notes' || $entry->get('is_internal') !== false) {
            return;
        }

        // 2. Check if it's a reply to a poll
        $inReplyTo = $entry->get('in_reply_to');
        if (!$inReplyTo) {
            return;
        }

        // Resolve parent poll
        $poll = $this->resolvePoll($inReplyTo);
        if (!$poll) {
            return;
        }

        // 3. Match content to options
        $this->processVote($entry, $poll);
    }

    protected function resolvePoll(string $urlOrId): ?\Statamic\Entries\Entry
    {
        // Try direct ID
        $poll = Entry::find($urlOrId);
        if ($poll && $poll->collection()->handle() === 'polls') {
            return $poll;
        }

        // Try ActivityPub ID
        $poll = Entry::query()
            ->where('collection', 'polls')
            ->where('activitypub_id', $urlOrId)
            ->first();

        return $poll;
    }

    protected function processVote(\Statamic\Entries\Entry $voteNote, \Statamic\Entries\Entry $poll): void
    {
        $actorId = $voteNote->get('actor');
        if (is_array($actorId)) {
            $actorId = $actorId[0] ?? null;
        }

        if (!$actorId) {
            return;
        }

        // Deduplication: Check if actor already voted
        $voters = $poll->get('voters', []);
        if (!is_array($voters)) {
            $voters = [];
        }

        if (in_array($actorId, $voters)) {
            Log::info("PollVoteListener: Actor {$actorId} already voted on poll {$poll->id()}");
            return;
        }

        // Match content
        $content = strip_tags((string) $voteNote->get('content'));
        $options = $poll->get('options', []);
        $matched = false;

        foreach ($options as &$option) {
            if (trim(strtolower($option['name'])) === trim(strtolower($content))) {
                $option['count'] = ($option['count'] ?? 0) + 1;
                $matched = true;
                break;
            }
        }

        if ($matched) {
            $poll->set('options', $options);
            $poll->set('voters_count', ($poll->get('voters_count') ?? 0) + 1);
            
            // Add to voters list
            $voters[] = $actorId;
            $poll->set('voters', $voters);

            // Save the poll. This will trigger AutoGenerateActivityListener in Core
            // which will send an Update:Question activity to followers.
            $poll->save();

            Log::info("PollVoteListener: Tallied vote for option '{$content}' on poll {$poll->id()} by actor {$actorId}");
        } else {
            Log::info("PollVoteListener: Note content '{$content}' did not match any options for poll {$poll->id()}");
        }
    }
}
