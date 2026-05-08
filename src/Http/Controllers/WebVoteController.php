<?php

declare(strict_types=1);

namespace Ethernick\ActivityPubQuestions\Http\Controllers;

use Statamic\Facades\Entry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class WebVoteController
{
    public function store(Request $request, string $id)
    {
        $request->validate([
            'option' => 'required|string',
        ]);

        $poll = Entry::find($id);

        if (!$poll || $poll->collection()->handle() !== 'polls') {
            return response()->json(['error' => 'Poll not found.'], 404);
        }

        if ($poll->get('closed')) {
            return response()->json(['error' => 'Poll is closed.'], 400);
        }

        $options = $poll->get('options', []);
        $option = $request->input('option');

        if (!in_array($option, array_column($options, 'name'))) {
            return response()->json(['error' => 'Invalid option.'], 400);
        }

        $ip = $request->ip();
        $sessionId = app()->runningUnitTests() ? 'test-session' : $request->session()->getId();

        $cacheKey = "poll_vote_{$id}_{$ip}_{$sessionId}";
        $isLocal = app()->environment('dev');

        if (Cache::has($cacheKey) && !$isLocal) {
            return response()->json(['error' => 'You have already voted.'], 403);
        }

        // Increment count
        $updatedOptions = array_map(function ($opt) use ($option) {
            if ($opt['name'] === $option) {
                $opt['count'] = ($opt['count'] ?? 0) + 1;
            }
            return $opt;
        }, $options);

        $poll->set('options', $updatedOptions);
        $poll->set('voters_count', ((int) $poll->get('voters_count', 0)) + 1);
        $poll->save();

        // Mark as voted for this IP/Session (cache for 30 days)
        Cache::put($cacheKey, true, now()->addDays(30));

        // The EntrySaved listener in ActivityPubCore should automatically dispatch an Update activity
        // to the network since the content of the Poll has changed.

        // If the request expects JSON, return JSON, otherwise redirect back
        if ($request->expectsJson()) {
            $totalVotes = (int) $poll->get('voters_count');
            return response()->json([
                'success' => true,
                'message' => 'Thanks for your vote!',
                'total_votes' => $totalVotes,
                'options' => collect($updatedOptions)->map(function ($opt) use ($totalVotes) {
                    $votes = (int) ($opt['count'] ?? 0);
                    return [
                        'name' => $opt['name'],
                        'votes' => $votes,
                        'percentage' => $totalVotes > 0 ? round(($votes / $totalVotes) * 100) : 0,
                    ];
                })->all(),
            ]);
        }

        return redirect()->back()->with('success', 'Vote recorded successfully.');
    }
}
