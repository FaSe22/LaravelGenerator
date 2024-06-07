<?php

namespace Fase\LaravelGenerator\Commands;

use Fase\LaravelGenerator\Services\Generator;
use Fase\LaravelGenerator\Services\Store;
use Illuminate\Console\Command;
use Illuminate\Support\Pluralizer;

class GenerateMigration extends Command
{
    protected $signature = 'generate:migration  {name} {--attributes=*}';
    protected $description = 'Generate a migration with dynamic attributes';

    public function handle()
    {
        $typename = strtolower(Pluralizer::plural($this->argument('name')));
        $attributes = empty($this->option('attributes')) ? static::getCols(Store::getInstance()->fields) : $this->option('attributes');
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
    /**
     * TODO: Move this to generate migration command
     * Adds columns to the migration file for a given type/model.
     *
     *  @param string $typeName The name of the type/model
     *  @param array<object> $fields An array containing the fields to write as columns
     *  @throws RuntimeException if read or write operations fail
     */
    private static  function getCols(array $fields): array
    {

        $fields = array_filter($fields, function ($key) {
            return !in_array($key, config('generator.migration.ignore_fields'));
        }, ARRAY_FILTER_USE_KEY);


        $res = [];
        array_map(function ($field) use (&$res) {
            list($type, $name) = !$field->directive ?
                [strtolower($field->type), $field->name] : ($field->directive == 'belongsTo' ?
                    ["foreignId", $field->name . "_id"] :
                    [null, null]);

            //TODO: mappings, z.b. int -> integer

            if ($type && $name) {
                $res[] = $field->is_required
                    ? "\$table->$type('" . $name . "');"
                    : "\$table->$type('" . $name . "')->nullable();";
            }
        }, $fields);

        return $res;
    }
}
