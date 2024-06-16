<?php

namespace Fase\LaravelGenerator\Services;

use Illuminate\Support\Pluralizer;

class GraphqlSchemaExtender
{
    public static function generateGraphQLSchema($typeName, $fields): void
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
        $newContent .= "\n\n extend type Mutation {\n" . $definitions['mutations'] . "\n}\n";
        $newContent .= "\nextend type Query {\n" . $definitions['queries'] . "\n}\n";
        $newContent = str_replace("\u{00A0}", " ", $newContent);
        file_put_contents($filepath, $content . $newContent);
    }
}
