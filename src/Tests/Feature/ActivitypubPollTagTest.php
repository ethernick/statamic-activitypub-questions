<?php

namespace Ethernick\ActivityPubQuestions\Tests\Feature;

use Statamic\Facades\Entry;
use Tests\TestCase;
use Mockery;
use Ethernick\ActivityPubQuestions\Tags\ActivitypubPoll;

class ActivitypubPollTagTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_poll_form_tag_renders_with_mocked_entry()
    {
        // Mock the Entry
        $mockEntry = Mockery::mock(\Statamic\Entries\Entry::class);
        $mockEntry->shouldReceive('id')->andReturn('mock-123');
        $mockEntry->shouldReceive('get')->with('options', Mockery::any())->andReturn([
            ['name' => 'Red', 'count' => 5],
            ['name' => 'Blue', 'count' => 10],
        ]);
        $mockEntry->shouldReceive('get')->with('voters_count', Mockery::any())->andReturn(15);
        $mockEntry->shouldReceive('get')->with('closed')->andReturn(false);
        $mockEntry->shouldReceive('get')->with('end_time')->andReturn(null);
        $mockEntry->shouldReceive('get')->with('multiple_choice', Mockery::any())->andReturn(false);
        
        $mockCollection = Mockery::mock();
        $mockCollection->shouldReceive('handle')->andReturn('polls');
        $mockEntry->shouldReceive('collection')->andReturn($mockCollection);

        // Mock the Facade
        Entry::shouldReceive('find')->with('mock-123')->andReturn($mockEntry);

        // Instantiate Tag directly
        $tag = new ActivitypubPoll();
        $tag->setContext([]);
        $tag->setParameters(['id' => 'mock-123']);
        $tag->method = 'form';
        $tag->isPair = false;

        $output = $tag->form();

        $this->assertStringContainsString('action=', $output);
        $this->assertStringContainsString('mock-123', $output);
    }

    public function test_poll_form_renders_empty_when_closed_pair()
    {
        // Mock the Entry
        $mockEntry = Mockery::mock(\Statamic\Entries\Entry::class);
        $mockEntry->shouldReceive('id')->andReturn('mock-closed');
        $mockEntry->shouldReceive('get')->with('options', Mockery::any())->andReturn([
            ['name' => 'Red', 'count' => 10],
            ['name' => 'Blue', 'count' => 0],
        ]);
        $mockEntry->shouldReceive('voters_count', Mockery::any())->andReturn(10);
        $mockEntry->shouldReceive('get')->with('voters_count', Mockery::any())->andReturn(10);
        $mockEntry->shouldReceive('get')->with('closed')->andReturn(true);
        $mockEntry->shouldReceive('get')->with('end_time')->andReturn(null);
        
        $mockCollection = Mockery::mock();
        $mockCollection->shouldReceive('handle')->andReturn('polls');
        $mockEntry->shouldReceive('collection')->andReturn($mockCollection);

        // Mock the Facade
        Entry::shouldReceive('find')->with('mock-closed')->andReturn($mockEntry);

        // Instantiate Tag directly
        $tag = new ActivitypubPoll();
        $tag->setContext([]);
        $tag->setParameters(['id' => 'mock-closed']);
        $tag->method = 'form';
        $tag->isPair = true;
        $tag->setContent('<form>This should be hidden</form>');

        $output = $tag->form();

        // Should be empty string
        $this->assertEquals('', $output);
    }

    public function test_poll_results_renders_inline_when_closed()
    {
        // Mock the Entry
        $mockEntry = Mockery::mock(\Statamic\Entries\Entry::class);
        $mockEntry->shouldReceive('id')->andReturn('mock-results-closed');
        $mockEntry->shouldReceive('get')->with('options', Mockery::any())->andReturn([
            ['name' => 'Red', 'count' => 10],
        ]);
        $mockEntry->shouldReceive('get')->with('voters_count', Mockery::any())->andReturn(10);
        $mockEntry->shouldReceive('get')->with('closed')->andReturn(true);
        $mockEntry->shouldReceive('get')->with('end_time')->andReturn(null);
        
        $mockCollection = Mockery::mock();
        $mockCollection->shouldReceive('handle')->andReturn('polls');
        $mockEntry->shouldReceive('collection')->andReturn($mockCollection);

        // Mock the Facade
        Entry::shouldReceive('find')->with('mock-results-closed')->andReturn($mockEntry);

        // Instantiate Tag directly
        $tag = new ActivitypubPoll();
        $tag->setContext([]);
        $tag->setParameters(['id' => 'mock-results-closed']);
        $tag->method = 'results';
        $tag->isPair = true;
        $tag->setContent('Votes: {{ total_votes }}');

        // In this test environment, parse() returns an array because there is no real Antlers engine.
        // Our tag logic converts this array to an empty string. 
        // We verify that results() doesn't return the <template> wrapper when closed.
        $output = (string) $tag->results();

        // Should NOT contain a template tag
        $this->assertStringNotContainsString('<template', $output);
    }

    public function test_poll_results_tag_renders_template_container_with_data()
    {
        // Mock the Entry
        $mockEntry = Mockery::mock(\Statamic\Entries\Entry::class);
        $mockEntry->shouldReceive('id')->andReturn('mock-456');
        $mockEntry->shouldReceive('get')->with('options', Mockery::any())->andReturn([
            ['name' => 'Red', 'count' => 10],
        ]);
        $mockEntry->shouldReceive('get')->with('voters_count', Mockery::any())->andReturn(10);
        $mockEntry->shouldReceive('get')->with('closed')->andReturn(false);
        $mockEntry->shouldReceive('get')->with('end_time')->andReturn(null);
        $mockEntry->shouldReceive('get')->with('multiple_choice', Mockery::any())->andReturn(false);
        
        $mockCollection = Mockery::mock();
        $mockCollection->shouldReceive('handle')->andReturn('polls');
        $mockEntry->shouldReceive('collection')->andReturn($mockCollection);

        // Mock the Facade
        Entry::shouldReceive('find')->with('mock-456')->andReturn($mockEntry);

        // Instantiate Tag directly
        $tag = new ActivitypubPoll();
        $tag->setContext([]);
        $tag->setParameters(['id' => 'mock-456']);
        $tag->method = 'results';
        $tag->isPair = true;
        // Content with standardized variables
        $tag->setContent('Total: {{ total_votes }}, Red: {{ options }}{{ votes }}{{ /options }}, Message: {{ if message }}Yes{{ else }}No{{ /if }}');

        $output = (string) $tag->results();

        $this->assertStringContainsString("data-poll-id='mock-456'", $output);
        // Message should be 'No' by default (null/false)
        // Note: Antlers engine is not real in this test, but the data is there.
    }

    public function test_poll_script_tag_renders()
    {
        // Instantiate Tag directly
        $tag = new ActivitypubPoll();
        $output = $tag->script();

        $this->assertStringContainsString('<script>', $output);
        $this->assertStringContainsString('activitypub-poll-form', $output);
    }
}
