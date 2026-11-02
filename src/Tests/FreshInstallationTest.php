<?php

declare(strict_types=1);

namespace Ethernick\ActivityPubQuestions\Tests;

use Tests\TestCase;
use Ethernick\ActivityPubCore\Tests\Concerns\ProvidesSandbox;
use Ethernick\ActivityPubCore\Services\ActivityPubUtils;
use Statamic\Facades\Collection;
use Statamic\Facades\File;
use Statamic\Facades\YAML;
use PHPUnit\Framework\Attributes\Test;

class FreshInstallationTest extends TestCase
{
    use ProvidesSandbox;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setupSandbox();
    }

    protected function tearDown(): void
    {
        $this->teardownSandbox();
        parent::tearDown();
    }

    #[Test]
    public function it_executes_questions_install_command_in_isolation(): void
    {
        $settingsPath = ActivityPubUtils::settingsPath();
        if (File::exists($settingsPath)) {
            File::delete($settingsPath);
        }

        $blueprintPath = ActivityPubUtils::blueprintPath('collections/polls/poll.yaml');
        if (File::exists($blueprintPath)) {
            File::delete($blueprintPath);
        }

        $this->artisan('activitypub:questions:install')
            ->expectsOutput('Installing ActivityPub Questions...')
            ->expectsOutput('ActivityPub Questions installation complete!')
            ->assertExitCode(0);

        // Verify polls collection exists
        $collection = Collection::findByHandle('polls');
        $this->assertNotNull($collection);
        $this->assertEquals('Polls', $collection->title());

        // Verify blueprint template was installed
        $this->assertFileExists($blueprintPath);
        $blueprintData = YAML::file($blueprintPath)->parse();
        $this->assertArrayHasKey('tabs', $blueprintData);

        // Verify activitypub.yaml has polls configured
        $this->assertFileExists($settingsPath);
        $settings = YAML::file($settingsPath)->parse();
        $this->assertArrayHasKey('polls', $settings);
        $this->assertTrue($settings['polls']['enabled'] ?? false);
        $this->assertTrue($settings['polls']['federated'] ?? false);
        $this->assertEquals('Question', $settings['polls']['type'] ?? null);
    }
}
