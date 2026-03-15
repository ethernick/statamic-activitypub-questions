<?php

declare(strict_types=1);

namespace Ethernick\ActivityPubQuestions\Listeners;

use Statamic\Events\EntrySaving;
use Statamic\Entries\Entry;

class EnsurePollIdIsSlug
{
    public function handle(EntrySaving $event): void
    {
        /** @var Entry $entry */
        $entry = $event->entry;

        if ($entry->collectionHandle() !== 'polls') {
            return;
        }

        // If ID is missing, generate it.
        if (!$entry->id()) {
            $entry->id((string) \Illuminate\Support\Str::uuid());
        }

        // Ensure slug matches ID
        if ($entry->slug() !== $entry->id()) {
            $entry->slug($entry->id());
        }

        // Ensure title matches ID (overwriting blueprint default)
        // This follows the 'Note' behavior as requested by the user.
        if ($entry->get('title') !== $entry->id()) {
            $entry->set('title', $entry->id());
        }

        // Fix legacy data: Statamic Date fieldtype expects a Carbon instance
        // or a specific format. If it's a string, cast it.
        $date = $entry->get('date');
        if (is_string($date)) {
            $entry->set('date', \Illuminate\Support\Carbon::parse($date));
        }
    }
}
