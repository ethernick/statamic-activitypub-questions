<?php

declare(strict_types=1);

namespace Ethernick\ActivityPubQuestions\Console\Commands;

use Illuminate\Console\Command;
use Statamic\Facades\Collection;
use Statamic\Facades\File;
use Statamic\Facades\YAML;

class ActivityPubQuestionsInstall extends Command
{
    protected $signature = 'activitypub:questions:install';
    protected $description = 'Install and configure ActivityPub Questions and Polls.';

    public function handle(): void
    {
        $this->info('Installing ActivityPub Questions...');

        $this->ensurePollsCollection();
        $this->configureActivityPubSettings();
        $this->publishAssets();

        $this->info('ActivityPub Questions installation complete!');
    }

    protected function ensurePollsCollection(): void
    {
        $handle = 'polls';
        $title = 'Polls';

        if (Collection::findByHandle($handle)) {
            $this->comment("✓ Collection [{$handle}] already exists.");
        } else {
            $this->info("Creating collection: {$title} ({$handle})...");
            Collection::make($handle)
                ->title($title)
                ->save();
        }

        $this->ensureBlueprint($handle);
    }

    protected function ensureBlueprint(string $handle): void
    {
        $blueprintDir = resource_path("blueprints/collections/{$handle}");
        if (!File::exists($blueprintDir)) {
            File::makeDirectory($blueprintDir, 0755, true);
        }

        $blueprintPath = "{$blueprintDir}/poll.yaml";
        $stubPath = __DIR__ . '/../../../resources/blueprints/templates/collections/polls.yaml';

        if (!File::exists($stubPath)) {
            $this->error("Blueprint template for Polls not found at [{$stubPath}]");
            return;
        }

        $targetFields = YAML::parse(File::get($stubPath));

        if (!File::exists($blueprintPath)) {
            $this->info("Creating default blueprint for Polls at [{$blueprintPath}]...");
            File::put($blueprintPath, YAML::dump($targetFields));
            return;
        }

        // Standardize existing blueprint
        $this->info("Updating existing blueprint at [{$blueprintPath}]...");
        $blueprint = YAML::parse(File::get($blueprintPath));

        // For simplicity, we'll ensure title and slug are configured as requested
        $this->standardizeField($blueprint, 'title', [
            'type' => 'hidden',
            'required' => false,
            'visibility' => 'hidden',
            'default' => 'Generating...',
        ]);

        $this->standardizeField($blueprint, 'slug', [
            'type' => 'slug',
            'localizable' => true,
            'visibility' => 'hidden',
            'read_only' => true,
            'validate' => 'max:200',
        ]);

        // Merge in other essential fields from stub if missing
        // (Simplified merge for now)
        
        File::put($blueprintPath, YAML::dump($blueprint));
        $this->info("Blueprint updated.");
    }

    protected function standardizeField(array &$blueprint, string $handle, array $config): void
    {
        foreach ($blueprint['tabs'] ?? [] as &$tab) {
            foreach ($tab['sections'] ?? [] as &$section) {
                foreach ($section['fields'] ?? [] as &$field) {
                    if (($field['handle'] ?? '') === $handle) {
                        $field['field'] = array_merge($field['field'] ?? [], $config);
                        return;
                    }
                }
            }
        }

        // If not found, add to main tab first section
        $blueprint['tabs']['main']['sections'][0]['fields'][] = [
            'handle' => $handle,
            'field' => $config,
        ];
    }

    protected function configureActivityPubSettings(): void
    {
        $settingsPath = \Ethernick\ActivityPubCore\Services\ActivityPubUtils::settingsPath();
        $settingsDir = dirname($settingsPath);

        if (!File::exists($settingsDir)) {
            File::makeDirectory($settingsDir, 0755, true);
        }

        if (!File::exists($settingsPath)) {
            $this->info("Creating ActivityPub settings at [{$settingsPath}]...");
            File::put($settingsPath, YAML::dump([]));
        }

        $settings = YAML::parse(File::get($settingsPath));
        
        if (isset($settings['polls'])) {
            $this->comment("✓ Polls already configured in activitypub.yaml.");
        } else {
            $this->info("Enabling Polls in activitypub.yaml...");
            $settings['polls'] = [
                'enabled' => true,
                'federated' => true,
                'type' => 'Question',
            ];
            File::put($settingsPath, YAML::dump($settings));
        }

        // Update polls.yaml content collection settings to ensure date: true and route
        $collectionPath = \Ethernick\ActivityPubCore\Services\ActivityPubUtils::collectionPath('polls');
        if (File::exists($collectionPath)) {
            $config = YAML::parse(File::get($collectionPath));
            $config['date'] = true;
            $config['route'] = '/polls/{slug}';
            $config['blueprints'] = ['poll'];
            File::put($collectionPath, YAML::dump($config));
        }
    }

    protected function publishAssets(): void
    {
        $this->info('Publishing ActivityPub Questions assets...');
        $this->call('vendor:publish', [
            '--provider' => 'Ethernick\ActivityPubQuestions\ActivityPubQuestionsServiceProvider',
            '--tag' => 'activitypub-questions',
            '--force' => true,
        ]);
    }
}
