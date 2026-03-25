<?php

declare(strict_types=1);

namespace Ethernick\ActivityPubQuestions\Http\Controllers;

use Statamic\Http\Controllers\CP\CpController;
use Illuminate\Http\Request;
use Statamic\Facades\Entry;
use Illuminate\Support\Carbon;

class PollAnalyticsController extends CpController
{
    public static array $voteCache = [];

    public function index()
    {
        return view('activitypub-questions::cp.polls.index', [
            'title' => 'Poll Analytics',
        ]);
    }

    public function metrics()
    {
        $polls = Entry::query()->where('collection', 'polls')->get();

        $totalPolls = $polls->count();
        $activePolls = $polls->where('closed', false)->count();
        $totalVotes = (int) $polls->sum('voters_count');

        // Fetch local actors for permission checks in JS
        $actors = Entry::query()
            ->where('collection', 'actors')
            ->get()
            ->map(function ($actor) {
                return [
                    'id' => $actor->id(),
                    'name' => $actor->get('title') ?: $actor->slug(),
                    'handle' => $actor->get('activitypub_handle'),
                    'url' => $actor->get('activitypub_id'),
                ];
            });

        return [
            'rollup' => [
                'total_polls' => $totalPolls,
                'active_polls' => $activePolls,
                'total_votes' => $totalVotes,
            ],
            'chart' => $this->getChartData($polls),
            'actors' => $actors,
            'permissions' => [
                'manage_polls' => true, // Simple CP permission for now
            ],
            'polls' => $polls->map(function ($poll) {
                return [
                    'id' => $poll->id(),
                    'slug' => $poll->slug(),
                    'title' => $poll->get('title'),
                    'content' => $poll->get('content'),
                    'actor' => $poll->get('actor'),
                    'options' => $poll->get('options', []),
                    'voters_count' => (int) $poll->get('voters_count', 0),
                    'closed' => (bool) $poll->get('closed', false),
                    'is_internal' => (bool) $poll->get('is_internal', false),
                    'created_at' => $poll->date() ? $poll->date()->toIso8601String() : $poll->created_at->toIso8601String(),
                ];
            }),
        ];
    }

    public function voters($pollId)
    {
        $poll = Entry::find($pollId);
        if (!$poll || $poll->collectionHandle() !== 'polls') {
            return response()->json(['error' => 'Poll not found'], 404);
        }

        $voterIds = $poll->get('voters', []) ?: [];
        $voters = Entry::query()
            ->where('collection', 'actors')
            ->whereIn('id', $voterIds)
            ->get()
            ->map(function ($actor) {
                return [
                    'id' => $actor->id(),
                    'name' => $actor->get('title') ?: $actor->slug(),
                    'handle' => $actor->get('activitypub_handle'),
                    'avatar' => $actor->get('activitypub_avatar') ?: '/vendor/statamic/cp/img/default-avatar.png',
                    'url' => cp_route('activitypub.actor-lookup.index', ['url' => $actor->get('activitypub_id')]),
                ];
            });

        return response()->json([
            'voters' => $voters,
        ]);
    }

    public function close($pollId)
    {
        $poll = Entry::find($pollId);
        if (!$poll || $poll->collectionHandle() !== 'polls') {
            return response()->json(['error' => 'Poll not found'], 404);
        }

        $poll->set('closed', true);
        $poll->save();

        return response()->json([
            'message' => 'Poll closed successfully',
            'poll' => [
                'id' => $poll->id(),
                'closed' => true,
            ],
        ]);
    }

    public function update(Request $request, $pollId)
    {
        $poll = Entry::find($pollId);
        if (!$poll || $poll->collectionHandle() !== 'polls') {
            return response()->json(['error' => 'Poll not found'], 404);
        }

        $request->validate([
            'content' => 'required|string',
            'options' => 'nullable|array',
            'multiple_choice' => 'nullable|boolean',
            'date' => 'nullable|string',
        ]);

        $poll->set('title', strip_tags($request->input('content')));
        $poll->set('content', $request->input('content'));
        
        if ($request->has('multiple_choice')) {
            $poll->set('multiple_choice', $request->input('multiple_choice'));
        }

        if ($request->has('options')) {
            $newOptions = [];
            foreach ($request->input('options') as $optName) {
                if (empty($optName)) continue;
                // Preserve counts if matching names
                $existingOptions = $poll->get('options', []);
                $count = 0;
                foreach ($existingOptions as $oldOpt) {
                    if ($oldOpt['name'] === $optName) {
                        $count = $oldOpt['count'] ?? 0;
                        break;
                    }
                }
                $newOptions[] = ['name' => $optName, 'count' => $count];
            }
            $poll->set('options', $newOptions);
        }

        $poll->save();

        return response()->json([
            'success' => true,
            'message' => 'Poll updated successfully',
            'poll' => [
                'id' => $poll->id(),
                'title' => $poll->get('title'),
            ],
        ]);
    }

    protected function getChartData($polls)
    {
        $pollIds = $polls->pluck('activitypub_id')->filter()->toArray();
        $localPollIds = $polls->pluck('id')->toArray();
        
        // Find notes that are replies to these polls
        $votes = Entry::query()
            ->where('collection', 'notes')
            ->where('is_internal', false)
            ->whereIn('in_reply_to', array_merge($pollIds, $localPollIds))
            ->get();

        $data = $votes->map(function ($vote) {
            return [
                'poll' => $vote->get('in_reply_to'),
                'date' => $vote->date() ? $vote->date()->format('Y-m-d') : $vote->created_at->format('Y-m-d'),
            ];
        })->groupBy('date')->sortKeys();

        $labels = $data->keys()->toArray();
        $datasets = [];

        foreach ($polls as $poll) {
            $apId = $poll->get('activitypub_id');
            $localId = $poll->id();
            
            $counts = [];
            $cumulative = 0;
            
            foreach ($labels as $date) {
                $dayVotes = $data->get($date) ?? collect();
                $voteCount = $dayVotes->whereIn('poll', [$apId, $localId])->count();
                $cumulative += $voteCount;
                $counts[] = $cumulative;
            }

            if ($cumulative > 0) {
                $datasets[] = [
                    'label' => $poll->get('title'),
                    'data' => $counts,
                ];
            }
        }

        return [
            'labels' => $labels,
            'datasets' => $datasets,
        ];
    }
}
