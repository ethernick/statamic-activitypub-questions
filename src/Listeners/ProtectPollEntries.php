<?php

declare(strict_types=1);

namespace Ethernick\ActivityPubQuestions\Listeners;

use Ethernick\ActivityPubCore\Events\EntryCleaning;
use Statamic\Facades\Entry;

class ProtectPollEntries
{
    public function handle(EntryCleaning $event): bool
    {
        $entry = $event->entry;

        // 1. Protect all entries in the 'polls' collection
        if ($entry->collection()->handle() === 'polls') {
            return true;
        }

        // 2. Protect notes that are replies to polls
        if ($entry->collection()->handle() === 'notes') {
            $inReplyTo = $entry->get('in_reply_to');
            if ($inReplyTo) {
                // If the parent is a poll, protect this reply
                $parent = Entry::query()->where('collection', 'polls')->where('activitypub_id', $inReplyTo)->exists()
                    || Entry::find($inReplyTo);

                if ($parent) {
                    return true;
                }
            }
        }

        return false;
    }
}
