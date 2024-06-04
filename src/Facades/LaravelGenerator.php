<?php

namespace Fase\LaravelGenerator\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Fase\LaravelGenerator\LaravelGenerator
 */
class LaravelGenerator extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Fase\LaravelGenerator\LaravelGenerator::class;
    }
}
