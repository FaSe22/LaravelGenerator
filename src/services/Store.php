<?php

namespace Fase\LaravelGenerator\Services;

class Store
{
    public static Store $instance;
    public array $fields;

    public static function getInstance(): self
    {
        return static::$instance = static::$instance ?? new Store();
    }

    public function setFields(array $fields): void
    {
        static::$instance->fields = $fields;
    }
}
