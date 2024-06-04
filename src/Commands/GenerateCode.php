<?php

namespace Fase\LaravelGenerator\Commands;

use Illuminate\Console\Command;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Pluralizer;
use Illuminate\Support\Str;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
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

        $files = static::getPaths(base_path(), "graphql", config('generator.graphql.exclude_dirs'), config('generator.graphql.exclude_files'));

        $types = [];

        foreach ($files as $file) {
            array_push($types, static::parse($file));
        }
        $flattenedTypes = array_merge(...$types);
        $flattenedTypes = array_unique($flattenedTypes, SORT_REGULAR);

        array_walk($flattenedTypes, function ($fields, $type) use ($choice) {
            if (in_array('CRUD', $choice)) static::generateGraphQLSchema($type, $fields);
            if (in_array('Model,Factory,Migration', $choice)) {
                Artisan::call("make:model $type -fm");
                static::addColumns($type, $fields);
                static::addRelations($type, $fields);
                static::addFakeData($type, $fields);
            }
            if (in_array('Policy', $choice)) Artisan::call("make:policy " . $type . "Policy --model=$type");
            if (in_array('tests', $choice)) {
                static::generateGraphQLTestClass($type);
                static::generateGraphQLTest($type);
            }
        });
    }

    /**
     * @param string $directory The directory to search for files
     * @param array $excludeDirs The directories to exclude from search
     * @param array $excludeFiles The files to exclude from search
     * @return array All .graphql filepaths found
     */
    private static function getPaths(string $directory, string $filetype, array $excludeDirs = [], array $excludeFiles = []): array
    {
        $files = [];

        $excludeDirPaths = array_map('realpath', $excludeDirs);

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($iterator as $file) {

            //coniinue if filetype doesnt match
            if (pathinfo($file, PATHINFO_EXTENSION) !== $filetype) continue;

            $realFilePath = realpath($file->getPathname());

            if (
                //continue if file is in the list of exluded files
                in_array($realFilePath, $excludeFiles)
                //continue if file is in the list of exluded directories
                || count(array_filter($excludeDirPaths, function ($excludeDirPath) use ($realFilePath) {
                    return strpos($realFilePath, $excludeDirPath) === 0;
                })) > 0
            ) {
                continue;
            }

            $files[] = $realFilePath;
        }
        return $files;
    }

    /**
     * @param string $filePath path to the .graphql-file to be parsed
     * @throws FileNotFoundException if no .graphql-file found
     * @return array an array of objects
     * @example ["User" => [
     *      "id" => {[
     *        "type" => "ID",
     *        "is_required" => true,
     *        "is_array" => false,
     *        "directive" => null,
     *      ]},
     *       // More fields..
     *  ]
     *]
     */
    private static function parse(string $filePath): array
    {
        $fileContent = file_get_contents($filePath);
        throw_if(!$fileContent, new FileNotFoundException("File not found or unreadable."));
        $pattern = '/\btype\s+([^\s{]+)\s*{([^}]+)/';
        preg_match_all($pattern, $fileContent, $matches, PREG_SET_ORDER);
        $types = [];
        foreach ($matches as $match) {
            $typeName = trim($match[1]);

            if (in_array($typeName, ['Query', 'Mutation']) || str_starts_with($typeName, 'input')) continue;

            $pattern = '/\b(\w+)\s*:\s*([^\n]+)/';
            preg_match_all($pattern, $match[2], $matches, PREG_SET_ORDER);

            $fields = [];

            foreach ($matches as $match) {
                $fieldName = trim($match[1]);
                $fieldDefinition = trim($match[2]);

                // Extract directive if present
                $directive = null;
                if (strpos($fieldDefinition, '@') !== false) {
                    [$fieldType, $directive] = explode('@', $fieldDefinition);
                    $directive = trim($directive);
                } else {
                    $fieldType = $fieldDefinition;
                }

                //Sanitize String
                $fieldType = trim(str_replace("\u{00A0}", "", $fieldType));

                // Check if the field is required
                $is_required = false;
                if (str_ends_with($fieldType, '!')) {
                    $is_required = true;
                    $fieldType = rtrim($fieldType, '!');
                }

                // Check if the field is an array
                $is_array = false;
                if (preg_match('/\[(.+)\]/', $fieldType, $arrayMatch)) {
                    $is_array = true;
                    $fieldType = $arrayMatch[1];
                }

                // Construct the field array
                $fields[$fieldName] = (object)([
                    'name' => $fieldName,
                    'type' => rtrim(rtrim($fieldType), "!"), #TODO: workaround to remove unwanted exclamation marks
                    'is_required' => $is_required,
                    'is_array' => $is_array,
                    'directive' => $directive
                ]);
            }

            $types[$typeName] = $fields;
        }
        return $types;
    }

    /**
     * Adds columns to the migration file for a given type/model.
     *
     *  @param string $typeName The name of the type/model
     *  @param array<object> $fields An array containing the fields to write as columns
     *  @throws RuntimeException if read or write operations fail
     */
    private static  function addColumns(string $typeName, array $fields): void
    {
        $migrationName = 'create_' . strtolower(Str::plural(Str::snake($typeName))) . '_table';
        $migrationFilePath = glob(database_path("migrations/*_$migrationName.php"))[0] ?? throw new RuntimeException("Migration file not found for $migrationName.");
        $content = file_get_contents($migrationFilePath);
        throw_if($content === false, new RuntimeException("Failed to read migration file: $migrationFilePath."));

        $fields = array_filter($fields, function ($key) {
            return !in_array($key, config('generator.migration.ignore_fields'));
        }, ARRAY_FILTER_USE_KEY);


        $columns = array_reduce($fields, function ($columns, $field) {
            list($type, $name) = !$field->directive ?
                [strtolower($field->type), $field->name] : ($field->directive == 'belongsTo' ?
                    ["foreignId", $field->name . "_id"] :
                    [null, null]);

            //TODO: mappings, z.b. int -> integer

            if ($type && $name) {
                $columns .= $field->is_required
                    ? "\$table->$type('$name');\n          "
                    : "\$table->$type('$name')->nullable();\n          ";
            }
            return $columns;
        }, '');

        $generatedContent = str_replace('$table->id();',  $columns, $content);


        throw_if(file_put_contents($migrationFilePath, $generatedContent) === false, new RuntimeException("Failed to write migration file: $migrationFilePath."));
    }

    /**
     * Adds columns to the migration file for a given type/model.
     *
     *  @param string $typeName The name of the type/model
     *  @param array<object> $fields An array containing the fields to write as columns
     *  @throws RuntimeException if read or write operations fail
     */
    private static  function addFakeData(string $typeName, array $fields): void
    {
        $migrationName = ucfirst(Str::camel($typeName)) . 'Factory.php';
        $migrationFilePath = database_path('factories/' . $migrationName);
        $content = file_get_contents($migrationFilePath);
        throw_if($content === false, new RuntimeException("Failed to read migration file: $migrationFilePath."));

        $fields = array_filter($fields, function ($key) {
            return !in_array($key, config('generator.migration.ignore_fields'));
        }, ARRAY_FILTER_USE_KEY);


        $columns = array_reduce($fields, function ($columns, $field) {
            list($type, $name) = !$field->directive ?
                [strtolower($field->type), $field->name] :
                [null, null];

            if ($type && $name && $type != 'id') {
                switch ($type) {
                    case 'string':
                        $columns .= "'" . $name . "' => fake()->name()," . PHP_EOL;
                        break;
                    case 'int':
                        $columns .= "'" . $name . "' => fake()->numberBetween(0,100)," . PHP_EOL;
                        break;
                    case 'float':
                        $columns .= "'" . $name . "' => fake()->numberBetween(0,100)," . PHP_EOL;
                        break;
                    default:
                        # code...
                        break;
                }
            }
            return $columns;
        }, '');

        $generatedContent = str_replace('//',  $columns, $content);


        throw_if(file_put_contents($migrationFilePath, $generatedContent) === false, new RuntimeException("Failed to write migration file: $migrationFilePath."));
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


    private static function generateGraphQLSchema($typeName, $fields): void
    {
        $inputFields = array_filter($fields, function ($key) {
            return $key !== 'id' && $key !== 'created_at' && $key !== 'updated_at';
        }, ARRAY_FILTER_USE_KEY);

        $inputType = "input Create{$typeName}Input {\n";
        $updateInputType = "input Update{$typeName}Input {\n";

        foreach ($inputFields as  $field) {
            switch ($field->directive) {
                case 'belongsTo':
                    $val = "    $field->name: Create" . $field->type . "BelongsTo" . "\n";
                    break;
                case 'belongsToMany':
                    $val = "    $field->name: Create" . Pluralizer::plural($field->type) . "BelongsToMany" . "\n";
                    break;
                case 'hasMany':
                    $val = "    $field->name: Create" . Pluralizer::plural($field->type) . "HasMany" . "\n";
                    break;
                default:
                    $val = "    $field->name: $field->type\n";
                    break;
            }
            $inputType .= $val;
            $updateFieldType = str_replace("Create", "Update", str_replace("!", "", $field->type));
            $updateInputType .= "    $field->name: $updateFieldType\n";
        }

        $inputType .= "}\n";
        $updateInputType .= "}\n";

        $mutations = "\tcreate{$typeName}(input: Create{$typeName}Input! @spread): $typeName @create" . PHP_EOL .
            "\tupdate{$typeName}(id: ID!, input: Update{$typeName}Input! @spread): $typeName @update" . PHP_EOL .
            "\tdelete{$typeName}(id: ID! @whereKey): $typeName @delete";

        $queries = "\t" . strtolower($typeName) . "(id: ID! @whereKey): $typeName @find" . PHP_EOL . "\t" . strtolower($typeName) . "s: [{$typeName}!] @all";

        $definitions = [
            'inputType' => $inputType,
            'updateInputType' => $updateInputType,
            'mutations' => $mutations,
            'queries' => $queries,
        ];
        $filepath = base_path('/graphql/' . strtolower($typeName) . '.graphql');
        $content = file_get_contents($filepath);

        $newContent = "\n\n" . $definitions['inputType'] . $definitions['updateInputType'];
        $newContent .= "\n\ntype Mutation {\n" . $definitions['mutations'] . "\n}\n";
        $newContent .= "\nextend type Query {\n" . $definitions['queries'] . "\n}\n";
        $newContent = str_replace("\u{00A0}", " ", $newContent);
        file_put_contents($filepath, $content . $newContent);
    }

    public static function generateGraphQLTestClass($className)
    {
        Artisan::call('make:testcase ' . $className);
    }
    public static function generateGraphQLTest($className)
    {
        Artisan::call('make:crudtest ' . $className);
    }
}
