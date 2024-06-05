<?php

namespace Fase\LaravelGenerator\Services;

use Illuminate\Filesystem\Filesystem;

class Generator
{
    /**
     * Filesystem instance
     * @var Filesystem
     */
    protected $files;
    protected $namespace;
    protected $typename;
    protected $stub;
    protected $path;
    protected $attributes;

    public function __construct(
        string $typename,
        string $namespace,
        string $stub,
        string $path,
        ?string $attributes = null,
    ) {
        $this->files = new Filesystem;
        $this->typename = $typename;
        $this->namespace = $namespace;
        $this->path = $path;
        $this->stub = $stub;
        $this->attributes = $attributes;
    }

    public  function run()
    {

        $this->makeDirectory(dirname($this->path));

        $contents = $this->getSourceFile();

        if (!$this->files->exists($this->path)) {
            $this->files->put($this->path, $contents);
            return ("File : {$this->path} created");
        } else {
            return ("File : {$this->path} already exits");
        }
    }


    /**
     **
     * Map the stub variables present in stub to its value
     *
     * @return array
     *
     */
    public function getStubVariables()
    {
        return [
            'NAMESPACE' => $this->namespace,
            'TYPE_NAME' => $this->typename,
            'ATTRIBUTES' => $this->attributes,
        ];
    }

    /**
     * Get the stub path and the stub variables
     *
     * @return bool|mixed|string
     *
     */
    public function getSourceFile()
    {
        return $this->getStubContents($this->stub, $this->getStubVariables());
    }


    /**
     * Replace the stub variables(key) with the desire value
     *
     * @param $stub
     * @param array $stubVariables
     * @return bool|mixed|string
     */
    public function getStubContents($stub, $stubVariables = [])
    {
        $contents = file_get_contents($stub);

        foreach ($stubVariables as $search => $replace) {
            $contents = str_replace('$' . $search . '$', $replace, $contents);
        }

        return $contents;
    }

    /**
     * Build the directory for the class if necessary.
     *
     * @param  string  $path
     * @return string
     */
    protected function makeDirectory($path)
    {
        if (!$this->files->isDirectory($path)) {
            $this->files->makeDirectory($path, 0777, true, true);
        }

        return $path;
    }
}
