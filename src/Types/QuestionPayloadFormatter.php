<?php

declare(strict_types=1);

namespace Ethernick\ActivityPubQuestions\Types;

use Ethernick\ActivityPubCore\Contracts\PayloadFormatterInterface;
use Statamic\Entries\Entry;
use Carbon\Carbon;

class QuestionPayloadFormatter implements PayloadFormatterInterface
{
    public function format(array $data, Entry $entry): array
    {
        $options = $entry->get('options', []);
        $isMultiple = $entry->get('multiple_choice', false);
        $apOptions = [];

        foreach ($options as $opt) {
            $apOptions[] = [
                'type' => 'Note',
                'name' => $opt['name'],
                'replies' => [
                    'type' => 'Collection',
                    'totalItems' => (int) ($opt['count'] ?? 0)
                ]
            ];
        }

        if ($isMultiple) {
            $data['anyOf'] = $apOptions;
        } else {
            $data['oneOf'] = $apOptions;
        }

        if ($endTime = $entry->get('end_time')) {
            $data['endTime'] = Carbon::parse($endTime)->toIso8601String();
        }

        if ($entry->get('closed')) {
            $data['closed'] = ($entry->date() ?: now())->toIso8601String();
        }

        return $data;
    }
}
