<?php

namespace Fase\LaravelGenerator\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Pluralizer;

class GenerateMigration extends Command
{
    protected $signature = 'generate:migration  {table} {--attributes=*}';
    protected $description = 'Generate a migration with dynamic attributes';

    public function handle()
    {
        $table = strtolower(Pluralizer::plural($this->argument('table')));
        $attributes = $this->option('attributes');

        $attributesArray = $this->parseAttributes($attributes);
        $stub = File::get($this->getStubPath());

        $stub = str_replace(
            ['{{ table }}', '{{ attributes }}', '{{ drop }}'],
            [$table, $this->formatAttributes($attributesArray), $this->formatDropAttributes($attributesArray)],
            $stub
        );

        $filePath = database_path('migrations/' . date('Y_m_d_His') . '_create_' . $table . '_table.php');
        File::put($filePath, $stub);

        $this->info('Migration created successfully.');
    }

    /**
     * Return the stub file path
     * @return string
     *
     */
    public function getStubPath()
    {
        return __DIR__ . '/../stubs/migrationfields.stub';
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

    protected function formatDropAttributes(array $attributes): string
    {
        $formatted = [];
        foreach ($attributes as $column => $type) {
            $formatted[] = "\$table->dropColumn('$column');";
        }
        return implode("\n            ", $attributes);
    }
}
