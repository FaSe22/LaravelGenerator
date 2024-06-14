<?php

namespace Fase\LaravelGenerator\Services;

use Illuminate\Contracts\Filesystem\FileNotFoundException;

class GraphqlSchemaParser
{
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
    public static function parse(string $filePath): array
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
}
