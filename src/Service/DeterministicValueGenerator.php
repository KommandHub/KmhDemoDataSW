<?php

declare(strict_types=1);

namespace Kommandhub\DemoData\Service;

/**
 * Reproducible pseudo-randomness.
 *
 * Demo data has to look varied but be identical on every run, on every machine
 * — otherwise a second run "differs" and the idempotency check is meaningless.
 * Every varying value is therefore a pure function of a string key.
 */
final class DeterministicValueGenerator
{
    private const SCALE = 4294967295;

    public function int(string $key, int $min, int $max): int
    {
        if ($min >= $max) {
            return $min;
        }

        return $min + ($this->raw($key) % (($max - $min) + 1));
    }

    public function float(string $key, float $min, float $max, int $precision = 2): float
    {
        if ($min >= $max) {
            return round($min, $precision);
        }

        return round($min + (($max - $min) * ($this->raw($key) / self::SCALE)), $precision);
    }

    public function bool(string $key, int $percentTrue): bool
    {
        return $this->int($key, 1, 100) <= $percentTrue;
    }

    /**
     * @template T
     *
     * @param array<int, T> $values
     *
     * @return T
     */
    public function pick(string $key, array $values)
    {
        $values = array_values($values);

        if ($values === []) {
            throw new \InvalidArgumentException('Cannot pick from an empty list.');
        }

        return $values[$this->int($key, 0, \count($values) - 1)];
    }

    /**
     * Picks `$count` distinct values by scoring each one and taking the lowest —
     * a stable shuffle-and-slice that does not depend on PHP's sort being
     * stable, because every score is unique to the value's own key.
     *
     * @template T
     *
     * @param array<int, T> $values
     *
     * @return array<int, T>
     */
    public function pickMany(string $key, array $values, int $count): array
    {
        $values = array_values($values);
        $count = min($count, \count($values));

        if ($count <= 0) {
            return [];
        }

        $scores = [];

        foreach ($values as $index => $value) {
            $scores[$index] = $this->raw($key . '#' . $index . '#' . $this->stringify($value));
        }

        asort($scores);

        return array_map(
            static fn (int $index) => $values[$index],
            \array_slice(array_keys($scores), 0, $count)
        );
    }

    private function raw(string $key): int
    {
        return (int)hexdec(substr(hash('sha256', $key), 0, 8));
    }

    private function stringify(mixed $value): string
    {
        return \is_scalar($value) ? (string)$value : '';
    }
}
