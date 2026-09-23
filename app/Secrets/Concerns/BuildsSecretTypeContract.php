<?php

namespace App\Secrets\Concerns;

trait BuildsSecretTypeContract
{
    /**
     * @param  list<array{name: string, required: bool, widget: string, label: string, hint?: string|null}>  $fields
     * @return array{
     *     slug: string,
     *     label: string,
     *     fields: list<array{name: string, required: bool, widget: string, label: string, hint: string|null}>
     * }
     */
    protected function makeContract(array $fields): array
    {
        return [
            'slug' => $this->type()->value,
            'label' => $this->type()->label(),
            'fields' => array_map(
                fn (array $field): array => [
                    'name' => $field['name'],
                    'required' => $field['required'],
                    'widget' => $field['widget'],
                    'label' => $field['label'],
                    'hint' => $field['hint'] ?? null,
                ],
                $fields,
            ),
        ];
    }
}
