<?php

namespace Fase\LaravelGenerator\Commands;

use Fase\LaravelGenerator\Services\Generator;
use Illuminate\Console\Command;
use Illuminate\Support\Pluralizer;

class GenerateTestCase extends Command
{

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'make:testcase {name}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Make an Test Class';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $typename = $this->argument('name');
        $this->info((new Generator(
            $typename,
            'Tests\Feature',
            __DIR__ . '/../stubs/testcase.stub',
            base_path('tests/Feature/' . ucwords(Pluralizer::singular($typename)) . '/'   . ucwords(Pluralizer::singular($typename)) . 'TestCase.php')
        ))->run());
    }
}
