<?php

namespace Fase\LaravelGenerator\Commands;

use Illuminate\Console\Command;

class LaravelGeneratorCommand extends Command
{
    public $signature = 'laravelgenerator';

    public $description = 'My command';

    public function handle(): int
    {
        $this->comment('All done');

        return self::SUCCESS;
    }
}
