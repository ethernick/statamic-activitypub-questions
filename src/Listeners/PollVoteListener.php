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

        Log::info("PollVoteListener: Incoming external note {$entry->id()} is a reply to {$inReplyTo}");

        // Resolve parent poll
        $poll = $this->resolvePoll($inReplyTo);
        if (!$poll) {
            Log::info("PollVoteListener: Failed to resolve poll for {$inReplyTo}");
            return;
        }

        Log::info("PollVoteListener: Successfully resolved poll {$poll->id()}");
        // 3. Match content to options
        $this->processVote($entry, $poll);
    }

    protected function resolvePoll(string $urlOrId): ?\Statamic\Entries\Entry
    {
        Log::info("PollVoteListener: Attempting to resolve poll: {$urlOrId}");
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

        if (!$poll) {
            // Try resolving local URI if it's a local poll
            $baseUrl = \Statamic\Facades\Site::selected()->absoluteUrl();
            if (\Illuminate\Support\Str::startsWith($urlOrId, $baseUrl)) {
                $uri = str_replace($baseUrl, '', $urlOrId);
                $uri = '/' . ltrim($uri, '/');
                
                // Parse /polls/{slug} manually to bypass Stache routing limitations
                $parts = explode('/', trim($uri, '/'));
                if (count($parts) === 2 && $parts[0] === 'polls') {
                    $poll = Entry::query()->where('collection', 'polls')->where('slug', $parts[1])->first();
                }

                if (!$poll) {
                    $localEntry = Entry::findByUri($uri, \Statamic\Facades\Site::selected()->handle());
                    if ($localEntry && $localEntry->collection()->handle() === 'polls') {
                        $poll = $localEntry;
                    }
                }
            }
        }

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

        // Match content or name (name is often used for votes)
        $content = strip_tags((string) $voteNote->get('content'));
        $name = (string) $voteNote->get('title'); // Statamic maps AP 'name' to 'title'
        
        // If title ended up as a UUID (Statamic fallback), check the raw JSON
        $apJson = $voteNote->get('activitypub_json');
        if ($apJson) {
            $apData = json_decode($apJson, true);
            if ($apData && isset($apData['name'])) {
                $name = $apData['name'];
            }
        }

        $options = $poll->get('options', []);
        $matched = false;

        foreach ($options as &$option) {
            $optName = trim(strtolower($option['name']));
            if ($optName === trim(strtolower($content)) || $optName === trim(strtolower($name))) {
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
