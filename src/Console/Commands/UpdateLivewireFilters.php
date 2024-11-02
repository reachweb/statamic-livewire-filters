<?php

namespace Reach\StatamicLivewireFilters\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Statamic\Console\RunsInPlease;

class UpdateLivewireFilters extends Command
{
    use RunsInPlease;

    protected $signature = 'statamic-livewire-filters:update';

    protected $description = 'Needed upgrades for Livewire Filters';

    public function handle()
    {
        $this->updateConfigForCustomQueryString();
    }

    protected function updateConfigForCustomQueryString()
    {
        $configPath = config_path('statamic-livewire-filters.php');

        if (! File::exists($configPath)) {
            return;
        }

        // Read file content as string
        $contents = File::get($configPath);

        // Check if options already exist
        $hasCustomQueryString = preg_match("/['\"']custom_query_string['\"']\s*=>/", $contents);
        $hasAliases = preg_match("/['\"']custom_query_string_aliases['\"']\s*=>/", $contents);

        if ($hasCustomQueryString && $hasAliases) {
            return;
        }

        // New options using nowdoc for exact formatting
        $newOptions = <<<'EOT'

            // Enable custom query string
            'custom_query_string' => false,

            // Set the aliases for each custom query string parameter
            'custom_query_string_aliases' => [
                //
            ],

        EOT;

        // Find position of last closing bracket
        $pos = strrpos($contents, '];');

        if ($pos !== false) {
            // Insert new options before closing bracket
            $contents = substr_replace($contents, $newOptions, $pos, 0);
        }

        File::put($configPath, $contents);

        $this->info('Statamic Livewire Filters config file updated successfully.');
    }
}
