<?php

namespace Fase\LaravelGenerator\Commands;

use Fase\LaravelGenerator\Services\Generator;
use Illuminate\Console\Command;

class GenerateFactory extends Command
{
    protected $signature = 'generate:factory {name} {--attributes=*}';
    protected $description = 'Generate a factory with dynamic key-value pairs';

    public function handle()
    {
        $name = $this->argument('name');
        $attributes = $this->option('attributes');
        $attributesArray = $this->parseAttributes($attributes);
        $this->info((new Generator(
            $name,
            'Database\Factories',
            __DIR__ . '/../stubs/factoryfields.stub',
            database_path('factories/' . $name . 'Factory.php'),
            $this->formatAttributes($attributesArray),
        ))->run());


        $this->info('Factory created successfully.');
    }


    protected function parseAttributes(array $attributes): array
    {
        $parsed = [];
        foreach ($attributes as $attribute) {
            [$key, $value] = explode('=', $attribute);
            $parsed[$key] = $value;
        }
        return $parsed;
    }


    protected function formatAttributes(array $attributes): string
    {
        $formatted = [];
        foreach ($attributes as $key => $value) {
            $formatted[] = "'$key' => $value";
        }
        return implode("," . PHP_EOL . "            ", $formatted);
    }
}
