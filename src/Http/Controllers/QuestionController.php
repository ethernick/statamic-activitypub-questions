<?php

declare(strict_types=1);

namespace Ethernick\ActivityPubQuestions\Http\Controllers;

use Ethernick\ActivityPubCore\Contracts\ActivityHandlerInterface;
use Ethernick\ActivityPubCore\Http\Controllers\BaseObjectController;
use Statamic\Facades\Entry;
use Ethernick\ActivityPubCore\Services\ActorResolver;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class QuestionController extends BaseObjectController implements ActivityHandlerInterface
{
    public static function getHandledActivityTypes(): array
    {
        return [
            'Create:Question',
            'Update:Question',
            'Delete:Question',
        ];
    }

    protected function getCollectionSlug(): ?string
    {
        return 'polls';
    }

    protected function returnIndexView(\Statamic\Contracts\Entries\Entry $actor): mixed
    {
        return null;
    } // TODO: Implement if needed
    protected function returnShowView(\Statamic\Contracts\Entries\Entry $actor, \Statamic\Contracts\Entries\Entry $item): mixed
    {
        return null; // Polls usually displayed inline
    } // TODO: Implement

    public function handleCreate(array $payload, mixed $localActor, mixed $externalActor): bool
    {
        Log::info("QuestionController: Handling Create Question");
        $object = $payload['object'] ?? null;
        if (!$object)
            return false;

        if ($externalActor && !$externalActor->id())
            $externalActor->save();

        // Stray Check
        $following = $localActor->get('following_actors', []) ?: [];
        $to = $object['to'] ?? [];
        $cc = $object['cc'] ?? [];
        if (!is_array($to))
            $to = [$to];
        if (!is_array($cc))
            $cc = [$cc];
        $addressed = array_merge($to, $cc);
        $myApId = $localActor->get('activitypub_id') ?: $localActor->absoluteUrl();
        $isMentioned = in_array($myApId, $addressed);

        // Replies to polls? Rare but possible.
        $inReplyTo = $object['inReplyTo'] ?? null;
        $isReplyToKnown = false;
        if ($inReplyTo) {
            if (is_array($inReplyTo))
                $inReplyTo = $inReplyTo['id'] ?? $inReplyTo['url'] ?? $inReplyTo[0] ?? null;
            if (is_string($inReplyTo)) {
                $isReplyToKnown = Entry::query()->whereIn('collection', ['notes', 'polls'])->where('activitypub_id', $inReplyTo)->exists()
                    || Entry::find($inReplyTo);
            }
        }

        if (in_array($externalActor->id(), $following) || $isMentioned || $isReplyToKnown) {
            $this->createPollEntry($object, $externalActor);
            return true;
        }

        return false;
    }

    public function handleUpdate(array $payload, mixed $localActor, mixed $externalActor): bool
    {
        Log::info("QuestionController: Handling Update Question");
        $object = $payload['object'] ?? null;
        if (!$object)
            return false;

        $id = $object['id'] ?? null;
        if (!$id)
            return false;

        $following = $localActor->get('following_actors', []) ?: [];
        $followedBy = $localActor->get('followed_by_actors', []) ?: [];
        $isConnected = in_array($externalActor->id(), $following) || in_array($externalActor->id(), $followedBy);

        $existing = Entry::query()->where('collection', 'polls')->where('activitypub_id', $id)->first();

        if ($isConnected || $existing) {
            // For polls, update mainly voters count or status
            if ($existing) {
                if (isset($object['votersCount'])) {
                    $existing->set('voters_count', $object['votersCount']);
                }
                if (isset($object['closed'])) {
                    $existing->set('closed', $object['closed']);
                }

                // Update options counts if present
                $oneOf = $object['oneOf'] ?? $object['anyOf'] ?? null;
                if ($oneOf && is_array($oneOf)) {
                    $options = [];
                    foreach ($oneOf as $opt) {
                        $name = $opt['name'] ?? 'Option';
                        $replies = $opt['replies'] ?? [];
                        $count = is_array($replies) ? ($replies['totalItems'] ?? 0) : 0;
                        $options[] = ['name' => $name, 'count' => $count];
                    }
                    $existing->set('options', $options);
                }

                $existing->save();
            }
            return true;
        }
        return false;
    }

    public function handleDelete(array $payload, mixed $localActor, mixed $externalActor): bool
    {
        $object = $payload['object'] ?? null;
        $objectId = is_string($object) ? $object : ($object['id'] ?? null);
        if (!$objectId)
            return false;

        $existing = Entry::query()->where('collection', 'polls')->where('activitypub_id', $objectId)->first();
        if ($existing) {
            $existingActor = $existing->get('actor');
            if (is_array($existingActor))
                $existingActor = $existingActor[0] ?? null;

            if ($existingActor === $externalActor->id()) {
                $existing->delete();
                return true;
            }
        }
        return false;
    }

    public function vote(\Illuminate\Http\Request $request)
    {
        $request->validate([
            'poll' => 'required|string',
            'choices' => 'required|array',
            'actor' => 'required|string',
        ]);

        $pollId = $request->input('poll');
        $choices = $request->input('choices');
        $actorId = $request->input('actor');

        $poll = Entry::find($pollId);
        if (!$poll || $poll->collection()->handle() !== 'polls') {
            return response()->json(['error' => 'Poll not found'], 404);
        }

        $actor = Entry::find($actorId);
        if (!$actor || $actor->collection()->handle() !== 'actors') {
            return response()->json(['error' => 'Actor not found'], 404);
        }

        // Check if already voted
        $alreadyVoted = Entry::query()
            ->where('collection', 'notes')
            ->where('in_reply_to', $poll->id())
            ->where('actor', $actor->id())
            ->exists();

        if ($alreadyVoted) {
            return response()->json(['error' => 'Already voted'], 422);
        }

        // Create a local vote note
        $voteNote = Entry::make()
            ->collection('notes')
            ->data([
                'content' => implode(', ', $choices),
                'in_reply_to' => $poll->id(),
                'actor' => $actor->id(),
                'is_internal' => true,
            ]);
        $voteNote->save();

        // Update poll counts
        $options = $poll->get('options', []);
        foreach ($choices as $choice) {
            foreach ($options as &$opt) {
                if ($opt['name'] === $choice) {
                    $opt['count'] = ($opt['count'] ?? 0) + 1;
                }
            }
        }
        $poll->set('options', $options);
        $poll->set('voters_count', ($poll->get('voters_count', 0) + 1));
        $poll->save();

        return response()->json(['success' => true]);
    }

    // Logic migrated from NoteController/InboxHandler
    protected function createPollEntry($object, $authorActor)
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
        $date = $dateStr ? \Illuminate\Support\Carbon::parse($dateStr) : now();
        $published = $date->toIso8601String();

        $endTimeStr = $object['endTime'] ?? null;
        $endTime = $endTimeStr ? \Illuminate\Support\Carbon::parse($endTimeStr) : null;
        $isClosed = ($object['closed'] ?? false) || ($endTime && $endTime->isPast());

        $options = [];
        $isMultipleChoice = isset($object['anyOf']);
        $oneOf = $object['oneOf'] ?? $object['anyOf'] ?? [];

        foreach ($oneOf as $opt) {
            $name = $opt['name'] ?? 'Option';
            $replies = $opt['replies'] ?? [];
            $count = is_array($replies) ? ($replies['totalItems'] ?? 0) : 0;
            $options[] = ['name' => $name, 'count' => $count];
        }

        $summary = $object['summary'] ?? null;
        $sensitive = $object['sensitive'] ?? false;
        if (empty($summary) && $sensitive) {
            $summary = 'Sensitive Content';
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
                'sensitive' => $sensitive,
                'summary' => $summary,
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
