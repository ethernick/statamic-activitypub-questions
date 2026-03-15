<?php

declare(strict_types=1);

namespace Ethernick\ActivityPubQuestions\Jobs;

use Ethernick\ActivityPubCore\Contracts\InboxActivityHandlerInterface;
use Ethernick\ActivityPubCore\Services\ActivityPubUtils;
use Ethernick\ActivityPubCore\Services\ThreadService;
use Statamic\Facades\Entry;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class QuestionInboxHandler implements InboxActivityHandlerInterface
{
    public function handle(array $payload, array $object, mixed $localActor, mixed $externalActor): bool
    {
        $actorId = $payload['actor'] ?? 'Unknown';
        
        // 1. Federated Check
        if (!ActivityPubUtils::isFederated('polls')) {
            Log::info("QuestionInboxHandler: Dropping Question because polls collection is not federated.");
            return false;
        }

        // 2. Resolve Relationships
        $following = $localActor->get('following_actors', []) ?: [];
        $to = $object['to'] ?? [];
        $cc = $object['cc'] ?? [];
        $addressed = array_merge((array)$to, (array)$cc);
        $myApId = $localActor->get('activitypub_id') ?: $localActor->absoluteUrl();
        $isMentioned = in_array($myApId, $addressed);

        $inReplyTo = $object['inReplyTo'] ?? null;
        $isReplyToKnown = false;
        if ($inReplyTo) {
            $isReplyToKnown = Entry::query()
                ->whereIn('collection', ['notes', 'polls'])
                ->where('activitypub_id', $inReplyTo)
                ->exists()
                || ActivityPubUtils::findLocalEntryByUrl($inReplyTo);
        }

        // 3. Authorization Check
        if (in_array($externalActor->id(), $following) || $isMentioned || $isReplyToKnown) {
            $this->createPollEntry($object, $externalActor);
            return true;
        }

        Log::info("QuestionInboxHandler: Ignoring Question from non-followed/irrelevant actor $actorId");
        return false;
    }

    protected function createPollEntry(array $object, mixed $authorActor): mixed
    {
        $id = $object['id'] ?? null;
        if ($id) {
            $existing = Entry::query()->where('collection', 'polls')->where('activitypub_id', $id)->first();
            if ($existing)
                return $existing;
        }

        $uuid = (string) Str::uuid();
        $content = $object['content'] ?? '';
        $title = $object['name'] ?? strip_tags($content) ?: $uuid;

        $dateStr = $object['published'] ?? $object['updated'] ?? null;
        $date = $dateStr ? Carbon::parse($dateStr) : now();
        $published = $date->toIso8601String();

        $endTimeStr = $object['endTime'] ?? null;
        $endTime = $endTimeStr ? Carbon::parse($endTimeStr) : null;

        $closed = $object['closed'] ?? null;
        $isClosed = ($endTime && $endTime->isPast()) || $closed;

        // Options parsing
        $options = [];
        $isMultipleChoice = isset($object['anyOf']);
        $oneOf = $object['anyOf'] ?? $object['oneOf'] ?? [];

        foreach ($oneOf as $opt) {
            $name = $opt['name'] ?? 'Option';
            $replies = $opt['replies'] ?? [];
            $count = is_array($replies) ? ($replies['totalItems'] ?? 0) : 0;
            $options[] = [
                'name' => $name,
                'count' => $count
            ];
        }

        $poll = Entry::make()
            ->collection('polls')
            ->id($uuid)
            ->slug($uuid)
            ->date($date)
            ->data([
                'title' => $title,
                'content' => $content,
                'actor' => $authorActor->id(),
                'date' => $published,
                'activitypub_id' => $id,
                'activitypub_json' => json_encode($object),
                'is_internal' => false,
                'sensitive' => $object['sensitive'] ?? false,
                'summary' => (!empty($object['summary'])) ? $object['summary'] : (
                    ($object['sensitive'] ?? false) ? 'Sensitive Content' : null
                ),
                'options' => $options,
                'multiple_choice' => $isMultipleChoice,
                'voters_count' => $object['votersCount'] ?? 0,
                'end_time' => $endTime ? $endTime->toIso8601String() : null,
                'closed' => $isClosed
            ]);

        // Mentions
        $mentioned = [];
        if (isset($object['tag']) && is_array($object['tag'])) {
            foreach ($object['tag'] as $tag) {
                if (($tag['type'] ?? '') === 'Mention' && isset($tag['href'])) {
                    $mentioned[] = $tag['href'];
                }
            }
        }
        if (!empty($mentioned)) {
            $poll->set('mentioned_urls', array_values(array_unique($mentioned)));
        }

        $poll->set('title', $title);
        $poll->save();
        return $poll;
    }
}
