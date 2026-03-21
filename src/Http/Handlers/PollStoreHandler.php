<?php

declare(strict_types=1);

namespace Ethernick\ActivityPubQuestions\Http\Handlers;

use Ethernick\ActivityPubCore\Contracts\StoreHandlerInterface;
use Illuminate\Http\Request;
use Statamic\Contracts\Entries\Entry as EntryContract;
use Statamic\Facades\Entry;
use Statamic\Facades\File;
use Statamic\Facades\YAML;
use Ethernick\ActivityPubCore\Services\ActivityPubUtils;

class PollStoreHandler implements StoreHandlerInterface
{
    public function store(Request $request): EntryContract
    {
        $request->validate([
            'content' => 'required|string',
            'actor' => 'required|string',
            'options' => 'required|array|min:2',
            'options.*' => 'required|string',
            'multiple_choice' => 'boolean',
            'duration' => 'nullable|integer',
            'date' => 'nullable|string',
            'content_warning' => 'nullable|string',
            'tags' => 'nullable|array',
        ]);

        $actor = Entry::find($request->input('actor'));
        if (!$actor) {
            throw new \Exception('Actor not found');
        }

        // Handle Date and Duration
        $date = \Illuminate\Support\Carbon::parse($request->input('date', now()));
        $duration = $request->input('duration', 10080);
        $endTime = (clone $date)->addMinutes((int) $duration);

        // Format options for storage
        $options = collect($request->input('options'))->map(function ($optionText) {
            return [
                'name' => $optionText,
                'count' => 0,
            ];
        })->all();

        $path = ActivityPubUtils::settingsPath();
        $settings = File::exists($path) ? YAML::parse(File::get($path)) : [];
        $hashtagField = $settings['hashtags']['field'] ?? 'tags';

        $entry = Entry::make()
            ->collection('polls')
            ->published(true)
            ->data([
                'content' => $request->input('content'),
                'actor' => [$actor->id()],
                'date' => $date->format('Y-m-d H:i'),
                'options' => $options,
                'multiple_choice' => $request->boolean('multiple_choice', false),
                'end_time' => $endTime->toIso8601String(),
                'closed' => false,
                'voters_count' => 0,
                'sensitive' => $request->filled('content_warning'),
                'summary' => $request->input('content_warning'),
                $hashtagField => $request->input('tags', []),
                'is_internal' => true,
            ]);

        $entry->save();

        return $entry;
    }
}
