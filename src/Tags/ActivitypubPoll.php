<?php

declare(strict_types=1);

namespace Ethernick\ActivityPubQuestions\Tags;

use Statamic\Tags\Tags;
use Statamic\Facades\Entry;

class ActivitypubPoll extends Tags
{
    /**
     * The {{ activitypub_poll }} tag
     * Returns the poll data variables so they can be output directly.
     */
    protected static $handle = 'activitypub_poll';

    public function index()
    {
        $poll = $this->getPoll();

        if (!$poll) {
            return null;
        }

        $totalVotes = (int) $poll->get('voters_count', 0);

        return [
            'id' => $poll->id(),
            'title' => $poll->get('title'),
            'options' => $this->transformOptions($poll->get('options', []), $totalVotes),
            'total_votes' => $totalVotes,
            'closed' => $this->isClosed($poll),
            'end_time' => $poll->get('end_time'),
            'content' => $poll->get('content'),
        ];
    }

    protected function isClosed($poll)
    {
        if ($poll->get('closed')) {
            return true;
        }

        if ($endTime = $poll->get('end_time')) {
            if (\Illuminate\Support\Carbon::parse($endTime)->isPast()) {
                return true;
            }
        }

        return false;
    }

    protected function transformOptions($options, $totalVotes)
    {
        return collect($options)->map(function ($opt) use ($totalVotes) {
            $votes = (int) ($opt['count'] ?? $opt['tally'] ?? 0);
            return [
                'name' => $opt['name'] ?? '',
                'votes' => $votes,
                'percentage' => $totalVotes > 0 ? round(($votes / $totalVotes) * 100) : 0,
            ];
        })->all();
    }

    /**
     * The {{ poll:form }} tag
     */
    public function form()
    {
        $poll = $this->getPoll();
        if (!$poll) {
            return null;
        }

        if ($this->isClosed($poll)) {
            // If it's a pair tag, we return nothing so the form disappears.
            // The results tag will handle rendering the results inline.
            if ($this->isPair) {
                return '';
            }
            // If it's a single tag, we show the default results.
            return $this->renderResults($poll);
        }

        $data = $this->generateFormData($poll);

        if ($this->isPair) {
            return $this->parse($data);
        }

        return $data;
    }

    /**
     * The {{ poll:results }} tag
     */
    public function results()
    {
        $poll = $this->getPoll();
        if (!$poll || !$this->isPair)
            return null;

        $id = $poll->id();
        
        // When results tag is used, we provide the data for it
        $totalVotes = (int) $poll->get('voters_count', 0);
        $isClosed = $this->isClosed($poll);
        $data = [
            'total_votes' => $totalVotes,
            'options' => $this->transformOptions($poll->get('options', []), $totalVotes),
            'closed' => $isClosed,
            'message' => null,
        ];

        // If the poll is open (template mode), we need to protect the message block
        // from being stripped by Antlers. We wrap it in a hidden div that JS can show.
        if (!$isClosed) {
            $this->content = preg_replace('/\{\{\s*message\s*\}\}/', '<div class="activitypub-poll-message" style="display:none">', $this->content);
            $this->content = preg_replace('/\{\{\s*\/message\s*\}\}/', '</div>', $this->content);
        }

        $content = $this->parse($data);
        if (is_array($content)) {
            $content = ''; 
        }

        // If the poll is closed, we render results inline immediately.
        if ($this->isClosed($poll)) {
            return $content;
        }

        // If it's open, return the template wrapped for JS success state
        return "<template class='activitypub-results-template' data-poll-id='{$id}'>" . $content . "</template>";
    }

    protected function renderResults($poll)
    {
        $id = $poll->id();
        $totalVotes = (int) $poll->get('voters_count', 0);
        $data = [
            'id' => $id,
            'total_votes' => $totalVotes,
            'options' => $this->transformOptions($poll->get('options', []), $totalVotes),
            'closed' => true,
        ];

        if ($this->isPair) {
            $content = $this->parse($data);
            return is_array($content) ? '' : $content;
        }

        return $this->generateDefaultResults($data);
    }

    /**
     * The {{ activitypub_poll:script }} tag
     */
    public function script()
    {
        // var_dump("DEBUG: script() called");
        return $this->generateScript();
    }

    /**
     * Internal form data generator
     */
    protected function generateFormData($poll = null)
    {
        $poll = $poll ?? $this->getPoll();

        if (!$poll) {
            return null;
        }

        $id = $poll->id();
        $totalVotes = (int) $poll->get('voters_count', 0);
        $isMultiple = (bool) $poll->get('multiple_choice', false);
        
        $options = collect($poll->get('options', []))->map(function ($opt) use ($isMultiple) {
            return [
                'name' => $opt['name'] ?? '',
                'type' => $isMultiple ? 'checkbox' : 'radio',
                'ref' => $isMultiple ? 'option[]' : 'option',
            ];
        })->all();

        $data = [
            'action' => route('activitypub.polls.vote.web', ['id' => $id]),
            'method' => 'POST',
            'csrf' => csrf_field(),
            'id' => $id,
            'options' => $options,
            'total_votes' => $totalVotes,
            'closed' => false,
        ];

        if (!$this->isPair && $this->method === 'form') {
            return $this->generateDefaultForm($data);
        }

        return $data;
    }

    /**
     * Default Form Generator
     */
    protected function generateDefaultForm($data)
    {
        $html = "<form action=\"{$data['action']}\" method=\"{$data['method']}\" class=\"activitypub-poll-form\" data-poll-id=\"{$data['id']}\">\n";
        $html .= $data['csrf'] . "\n";
        $html .= "<div class=\"activitypub-poll-options\">\n";

        foreach ($data['options'] as $option) {
            $name = htmlspecialchars($option['name']);
            $html .= "<label class=\"activitypub-poll-option\">\n";
            $html .= "<input type=\"radio\" name=\"option\" value=\"{$name}\" required>\n";
            $html .= "<span>{$name}</span>\n";
            $html .= "</label>\n";
        }

        $html .= "</div>\n";
        $html .= "<button type=\"submit\" class=\"activitypub-poll-submit\">Vote</button>\n";
        $html .= "</form>\n";

        return $html;
    }

    protected function generateDefaultResults($data)
    {
        $html = "<div class=\"activitypub-poll-results-container\" data-poll-id=\"{$data['id']}\">\n";
        $html .= "<p class=\"activitypub-poll-status\">" . ($data['closed'] ? 'Poll Closed' : 'Results') . "</p>\n";
        $html .= "<ul class=\"activitypub-poll-results\">\n";

        foreach ($data['options'] as $option) {
            $name = htmlspecialchars($option['name']);
            $votes = $option['votes'];
            $percentage = $option['percentage'];
            $html .= "<li>{$name}: <strong>{$percentage}%</strong> ({$votes} votes)</li>\n";
        }

        $html .= "</ul>\n";
        $html .= "<p class=\"activitypub-poll-total\">Total votes: {$data['total_votes']}</p>\n";
        $html .= "</div>\n";

        return $html;
    }

    /**
     * Default Script Generator
     */
    protected function generateScript()
    {
        return <<<'HTML'
<script>
document.addEventListener('DOMContentLoaded', function() {
    const forms = document.querySelectorAll('.activitypub-poll-form');
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(form);
            const submitBtn = form.querySelector('button[type="submit"]');
            const pollId = form.getAttribute('data-poll-id');
            // Look for template anywhere on the page that matches this poll ID
            const template = document.querySelector(`.activitypub-results-template[data-poll-id="${pollId}"]`);
            
            submitBtn.disabled = true;
            submitBtn.textContent = 'Voting...';

            fetch(form.action, {
                method: 'POST',
                body: formData,
                headers: {
                    'Accept': 'application/json'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    alert(data.error);
                    submitBtn.disabled = false;
                    submitBtn.textContent = 'Vote';
                } else {
                    if (template) {
                        renderCustomResults(form, template, data);
                    } else {
                        renderDefaultResults(form, data);
                    }
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while voting.');
                submitBtn.disabled = false;
                submitBtn.textContent = 'Vote';
            });
        });
    });

    function renderDefaultResults(form, data) {
        let total = data.total_votes || 0;
        let html = '<p>Thank you for voting!</p><ul class="activitypub-poll-results">';
        data.options.forEach(opt => {
            let votes = opt.votes || 0;
            let pct = opt.percentage || (total > 0 ? Math.round((votes / total) * 100) : 0);
            html += '<li>' + opt.name + ': ' + pct + '% (' + votes + ')</li>';
        });
        html += '</ul>';
        form.innerHTML = html;
    }

    function renderCustomResults(form, template, data) {
        let html = template.innerHTML;
        let total = data.total_votes || 0;
        
        // Simple variable replacement
        html = html.replace(/{{ total_votes }}/g, total);
        
        // Handle message block visibility
        if (data.message) {
            // Use a regex to handle browser normalization (quotes, semicolons, spacing)
            html = html.replace(/class=["']activitypub-poll-message["']\s+style=["']display:\s*none;?["']/gi, 'class="activitypub-poll-message"');
        }

        // Handle options loop
        const loopRegex = /{{ options }}([\s\S]*?){{ \/options }}/g;
        html = html.replace(loopRegex, function(match, content) {
            return data.options.map(opt => {
                let votes = opt.votes || 0;
                let pct = opt.percentage || (total > 0 ? Math.round((votes / total) * 100) : 0);
                return content
                    .replace(/{{ name }}/g, opt.name)
                    .replace(/{{ votes }}/g, votes)
                    .replace(/{{ percentage }}/g, pct);
            }).join('');
        });

        form.innerHTML = html;
    }
});
</script>
HTML;
    }

    protected function getPoll()
    {
        $id = $this->params->get('id');

        if (!$id && $this->context->get('id')) {
            $id = $this->context->get('id');
        }

        \Illuminate\Support\Facades\Log::debug("Tag getPoll: id=" . ($id ?? 'NULL'));

        if (!$id && $this->context->get('entry')) {
            $id = $this->context->get('entry')->id();
        }

        \Illuminate\Support\Facades\Log::debug("Tag getPoll: id=" . ($id ?? 'NULL'));

        if (!$id) {
            return null;
        }

        $poll = Entry::find($id);

        if ($poll && $poll->collection()->handle() === 'polls') {
            return $poll;
        }

        return null;
    }
}
