<?php

namespace Fase\LaravelGenerator\Commands;

use Fase\LaravelGenerator\Services\FileFinder;
use Fase\LaravelGenerator\Services\GraphqlSchemaExtender;
use Fase\LaravelGenerator\Services\GraphqlSchemaParser;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use RuntimeException;

class GenerateCode extends Command
{
    protected $signature = 'generate:code';

    protected $description = 'Command description';


    public function handle()
    {

        $choice = $this->choice(
            'what do you want to generate?',
            ['CRUD', 'Model,Factory,Migration', 'Policy', 'tests'],
            null,
            null,
            true
        );

        $files = FileFinder::getPaths(base_path(), "graphql", config('generator.graphql.exclude_dirs'), config('generator.graphql.exclude_files'));

        $types = [];

        foreach ($files as $file) {
            array_push($types, GraphqlSchemaParser::parse($file));
        }
        $flattenedTypes = array_merge(...$types);
        $flattenedTypes = array_unique($flattenedTypes, SORT_REGULAR);

        array_walk($flattenedTypes, function ($fields, $type) use ($choice) {
            if (in_array('CRUD', $choice)) GraphqlSchemaExtender::generateGraphQLSchema($type, $fields);
            if (in_array('Model,Factory,Migration', $choice)) {
                Artisan::call("make:model $type");
                $args = implode(" --attributes=", static::getArgs($fields));
                Artisan::call("generate:factory $type --attributes=" . $args);
                $columns = implode(' --attributes=', static::getCols($fields));
                dump($columns);
                Artisan::call("generate:migration $type --attributes=" . $columns);
                //static::addColumns($type, $fields);
                static::addRelations($type, $fields);
            }
            if (in_array('Policy', $choice)) Artisan::call("make:policy " . $type . "Policy --model=$type");
            if (in_array('tests', $choice)) {
                Artisan::call('make:testcase ' . $type);
                Artisan::call('make:crudtest ' . $type);
            }
        });
    }

    /**
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
                    ? "\$table->$type('" . $name . "');" . PHP_EOL
                    : "\$table->$type('" . $name . "')->nullable();" . PHP_EOL;
            }
        }, $fields);

        return $res;
    }

    /**
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

                array_push($res, "'" . $value . "'");
            }
        }, $fields);

        return $res;
    }


    /**
     * Adds columns to the migration file for a given type/model.
     *
     *  @param string $typeName The name of the type/model
     *  @param array<object> $fields An array containing the fields to write as columns
     *  @throws RuntimeException if read or write operations fail
     */
    private static  function addRelations(string $typeName, array $fields): void
    {
        $modelFilePath = app_path("models/" . $typeName . ".php");
        $content = file_get_contents($modelFilePath);
        throw_if($content === false, new RuntimeException("Failed to read migration file: $modelFilePath."));

        $fields = array_filter($fields, function ($val) {
            return in_array($val->directive, ['belongsTo', 'hasMany', 'hasOne', 'belongsToMany']);
        });


        $relations = array_reduce($fields, function ($relations, $field) {
            $relations .= PHP_EOL . "\t" . 'public function ' . $field->name . '() ' . PHP_EOL .
                "\t{" . PHP_EOL .
                "\t\t" . 'return $this->' . $field->directive . '(' . $field->type . '::class);' . PHP_EOL .
                "\t}" . PHP_EOL;
            return $relations;
        }, '');

        $relations .= '}';

        $generatedContent = str_replace('}',  $relations, $content);

        throw_if(file_put_contents($modelFilePath, $generatedContent) === false, new RuntimeException("Failed to write migration file: $modelFilePath."));
    }
}
