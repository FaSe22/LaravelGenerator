<?php

namespace Fase\LaravelGenerator\Services;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class FileFinder
{
    /**
     * @param string $directory The directory to search for files
     * @param array $excludeDirs The directories to exclude from search
     * @param array $excludeFiles The files to exclude from search
     * @return array All .graphql filepaths found
     */
    public static function getPaths(string $directory, string $filetype, array $excludeDirs = [], array $excludeFiles = []): array
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
}
