<?php

declare(strict_types=1);

namespace Ethernick\ActivityPubQuestions\Http\Handlers;

use Ethernick\ActivityPubCore\Contracts\OutboxHandlerInterface;
use Statamic\Entries\Entry;
use Illuminate\Support\Carbon;

class PollOutboxHandler implements OutboxHandlerInterface
{
    public function format(array $data, Entry $entry): array
    {
        if ($endTime = $entry->get('end_time')) {
            $data['endTime'] = Carbon::parse($endTime)->toIso8601String();
        }

        if ($entry->get('closed')) {
            $data['closed'] = Carbon::parse($entry->get('date'))->addDays(1)->toIso8601String();
        }

        $options = collect($entry->get('options', []))->map(function ($option) {
            return [
                'type' => 'Note',
                'name' => $option['name'],
                'replies' => [
                    'type' => 'Collection',
                    'totalItems' => (int) ($option['count'] ?? 0),
                ]
            ];
        })->all();

        if ($entry->get('multiple_choice')) {
            $data['anyOf'] = $options;
        } else {
            $data['oneOf'] = $options;
        }

        return $data;
    }
}
