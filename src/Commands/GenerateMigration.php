<?php

namespace Fase\LaravelGenerator\Commands;

use Fase\LaravelGenerator\Services\Generator;
use Illuminate\Console\Command;
use Illuminate\Support\Pluralizer;

class GenerateMigration extends Command
{
    protected $signature = 'generate:migration  {name} {--attributes=*}';
    protected $description = 'Generate a migration with dynamic attributes';

    public function handle()
    {
        $typename = strtolower(Pluralizer::plural($this->argument('name')));
        $attributes = $this->option('attributes');
        $attributesArray = $this->parseAttributes($attributes);
        $this->info((new Generator(
            $typename,
            'Database\Migrations',
            __DIR__ . '/../stubs/migrationfields.stub',
            database_path('migrations/' . date('Y_m_d_His') . '_create_' . $typename . '_table.php'),
            $this->formatAttributes($attributesArray),
        ))->run());

        $this->info('Migration created successfully.');
    }

    protected function parseAttributes(array $attributes): array
    {
        $parsed = [];
        foreach ($attributes as $attribute) {
            $parsed[] = preg_replace("/\(([a-zA-Z,_-]+)\)/", "('$1')", $attribute);
        }
        return $parsed;
    }

    protected function formatAttributes(array $attributes): string
    {
        return implode("\n            ", $attributes);
    }
}
