<?php

namespace Fase\LaravelGenerator\Commands;

use Fase\LaravelGenerator\Services\Generator;
use Fase\LaravelGenerator\Services\Store;
use Illuminate\Console\Command;

class GenerateModel extends Command
{
    protected $signature = 'generate:model {name} {--attributes=*}';
    protected $description = 'Generate a model with relations';

    public function handle()
    {
        $name = $this->argument('name');
        $attributes = empty($this->option('attributes')) ? static::getRelations(Store::getInstance()->fields) : $this->option('attributes');
        $parsed = $this->parseAttributes($attributes);
        $this->info((new Generator(
            $name,
            'App\Models',
            __DIR__ . '/../stubs/modelfields.stub',
            app_path('Models/' . $name . '.php'),
            $this->formatAttributes($parsed),
        ))->run());


        $this->info('Model created successfully.');
    }

    protected function parseAttributes(array $attributes): array
    {
        return array_map(fn ($attribute) => explode(":", $attribute), $attributes);
    }

    protected function formatAttributes(array $attributes): string
    {
        $res = '';
        array_map(function ($field) use (&$res) {
            $res .= PHP_EOL . "\t" . 'public function ' . $field[0] . '() ' . PHP_EOL .
                "\t{" . PHP_EOL .
                "\t\t" . 'return $this->' . $field[1] . '(' . $field[2] . '::class);' . PHP_EOL .
                "\t}" . PHP_EOL;
        }, $attributes);

        return $res;
    }

    /**
     * Adds columns to the migration file for a given type/model.
     *
     *  @param string $typeName The name of the type/model
     *  @param array<object> $fields An array containing the fields to write as columns
     *  @throws RuntimeException if read or write operations fail
     */
    private static  function getRelations(array $fields): array
    {
        $fields = array_filter($fields, function ($val) {
            return in_array($val->directive, ['belongsTo', 'hasMany', 'hasOne', 'belongsToMany']);
        });
        $res = [];
        array_map(function ($field) use (&$res) {
            $res[] = $field->name . ":" . $field->directive . ":" . $field->type;
        }, $fields);

        return $res;
    }
}
