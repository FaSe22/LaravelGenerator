<?php

namespace Fase\LaravelGenerator\Commands;

use Fase\LaravelGenerator\Services\FileFinder;
use Fase\LaravelGenerator\Services\GraphqlSchemaExtender;
use Fase\LaravelGenerator\Services\GraphqlSchemaParser;
use Fase\LaravelGenerator\Services\Store;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

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
            Store::getInstance()->setFields($fields);
            if (in_array('CRUD', $choice)) GraphqlSchemaExtender::generateGraphQLSchema($type, $fields);
            if (in_array('Model,Factory,Migration', $choice)) {
                Artisan::call("generate:model $type ");
                Artisan::call("generate:factory $type");
                Artisan::call("generate:migration $type");
            }
            if (in_array('Policy', $choice)) Artisan::call("make:policy " . $type . "Policy --model=$type");
            if (in_array('tests', $choice)) {
                Artisan::call('make:testcase ' . $type);
                Artisan::call('make:crudtest ' . $type);
            }
        });
    }
}
