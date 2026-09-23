<?php

namespace App\Secrets;

use App\Enums\SecretType;
use App\Secrets\Contracts\SecretTypeStrategy;
use InvalidArgumentException;

final class SecretTypeRegistry
{
    /** @var array<string, SecretTypeStrategy> */
    private array $strategies = [];

    /**
     * @param  iterable<SecretTypeStrategy>  $strategies
     */
    public function __construct(iterable $strategies)
    {
        foreach ($strategies as $strategy) {
            $this->strategies[$strategy->type()->value] = $strategy;
        }
    }

    public function get(SecretType $type): SecretTypeStrategy
    {
        $strategy = $this->strategies[$type->value] ?? null;

        if ($strategy === null) {
            throw new InvalidArgumentException("Не зарегистрирована strategy для типа «{$type->value}»");
        }

        return $strategy;
    }

    /**
     * @return list<array{
     *     slug: string,
     *     label: string,
     *     fields: list<array{name: string, required: bool, widget: string, label: string, hint: string|null}>
     * }>
     */
    public function contracts(): array
    {
        return array_values(array_map(
            fn (SecretTypeStrategy $strategy) => $strategy->contract(),
            $this->strategies,
        ));
    }
}
