<?php

namespace Fase\LaravelGenerator\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\File;

class GenerateFactory extends Command
{
    protected $signature = 'generate:factory {name} {--attributes=*}';
    protected $description = 'Generate a factory with dynamic key-value pairs';

    public function handle()
    {
        $name = $this->argument('name');
        $attributes = $this->option('attributes');

        $attributesArray = $this->parseAttributes($attributes);

        $stub = File::get($this->getStubPath());

        $stub = str_replace(
            ['{{ factoryNamespace }}', '{{ namespacedModel }}', '{{ factory }}', '{{ attributes }}'],
            [$this->getFactoryNamespace(), $this->getNamespacedModel($name), $name, $this->formatAttributes($attributesArray)],
            $stub
        );

        $filePath = database_path('factories/' . $name . 'Factory.php');
        File::put($filePath, $stub);

        $this->info('Factory created successfully.');
    }

    /**
     * Return the stub file path
     * @return string
     *
     */
    public function getStubPath()
    {
        return __DIR__ . '/../stubs/factoryfields.stub';
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

    protected function getFactoryNamespace(): string
    {
        return 'Database\Factories';
    }

    protected function getNamespacedModel(string $name): string
    {
        return 'App\\Models\\' . Str::singular($name);
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
