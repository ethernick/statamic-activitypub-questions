<?php

declare(strict_types=1);

namespace Ethernick\ActivityPubQuestions\Console\Commands;

use Illuminate\Console\Command;
use Statamic\Facades\Entry;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class ActivityPubPollsClose extends Command
{
    protected $signature = 'activitypub:polls-close';
    protected $description = 'Find and close expired polls';

    public function handle(): int
    {
        $this->info('Checking for expired polls...');

        $now = Carbon::now();

        $expiredPolls = Entry::query()
            ->where('collection', 'polls')
            ->where('closed', '!=', true)
            ->whereNotNull('end_time')
            ->get()
            ->filter(function ($poll) use ($now) {
                return Carbon::parse($poll->get('end_time'))->isPast();
            });

        if ($expiredPolls->isEmpty()) {
            $this->info('No expired polls found.');
            return 0;
        }

        foreach ($expiredPolls as $poll) {
            $this->info("Closing poll: {$poll->get('title')} ({$poll->id()})");
            $poll->set('closed', true);
            
            // Saving will trigger AutoGenerateActivityListener in Core, 
            // which sends an Update activity with the finalized tallies.
            $poll->save();
            
            Log::info("ActivityPubPollsClose: Closed expired poll {$poll->id()}");
        }

        $this->info("Closed {$expiredPolls->count()} polls.");
        return 0;
    }
}
