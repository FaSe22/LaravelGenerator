<?php

namespace Fase\LaravelGenerator\Commands;

use Fase\LaravelGenerator\Services\Generator;
use Fase\LaravelGenerator\Services\Store;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class GenerateFactory extends Command
{
    protected $signature = 'generate:factory {name} {--attributes=*}';
    protected $description = 'Generate a factory with dynamic key-value pairs';

    public function handle()
    {
        $name = $this->argument('name');
        $attributes = empty($this->option('attributes')) ? static::getArgs(Store::getInstance()->fields) : $this->option('attributes');
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

    /**
     * TODO:: move this to generate factory command
     * Adds columns to the migration file for a given type/model.
     *
     *  @param string $typeName The name of the type/model
     *  @param array<object> $fields An array containing the fields to write as columns
     *  @throws RuntimeException if read or write operations fail
     */
    private static  function getArgs(array $fields): array
    {

        $fields = array_filter($fields, function ($key) {
            return !in_array($key, config('generator.migration.ignore_fields'));
        }, ARRAY_FILTER_USE_KEY);


        $res = [];
        array_map(function ($field) use (&$res) {
            if (!$field->directive && $field->type != 'ID') {

                $value = "";

                switch ($field->type) {
                    case 'String':
                        if (Str::contains($field->name, 'name')) {
                            $value = "$field->name=fake()->name()";
                        } else if (Str::contains($field->name, 'gender')) {
                            $value = "$field->name=fake()->gender()";
                        } else if (Str::contains($field->name, 'address')) {
                            $value = "$field->name=fake()->address()";
                        } else {
                            $value = "$field->name=fake()->name()";
                        }
                        break;
                    case 'Int':
                        $value = "$field->name=fake()->numberBetween(1,1000)";
                        break;
                    case 'Float':
                        $value = "$field->name=fake()->randomFloat(2, 1,1000)";
                        break;
                    case 'DateTime':
                        $value = "$field->name=fake()->dateTime()";
                        break;
                    default:
                        # code...
                        break;
                }

                array_push($res,  $value);
            }
        }, $fields);

        return $res;
    }
}
