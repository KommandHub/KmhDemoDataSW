<?php

declare(strict_types=1);

namespace Kommandhub\DemoData\Service;

use Kommandhub\DemoData\Blueprint\DemoBlueprint;

/**
 * Decides which products a leaf category carries.
 *
 * A catalogue big enough to be worth demonstrating cannot be hand-written, and
 * a catalogue of "Product 1 … Product 24" is not worth demonstrating. The
 * compromise is how real ranges are actually named: a line name and a model
 * descriptor. "Halo" × "Over-Ear ANC" reads like a product because that is how
 * products are named.
 *
 * The blueprint's hand-written names always come first and are never displaced;
 * the generated tail fills the rest. That ordering is what makes the catalogue
 * size a knob rather than a migration — `pickMany` scores every candidate and
 * takes the lowest, so raising the target appends names without disturbing the
 * ones already chosen, and every existing product keeps its id.
 */
class ProductNameGenerator
{
    public function __construct(private readonly DeterministicValueGenerator $values)
    {
    }

    /**
     * @param array<string, mixed> $spec leaf category definition
     *
     * @return array<int, string>
     */
    public function names(string $leafKey, array $spec, int $target): array
    {
        $explicit = $this->strings($spec['products'] ?? null);

        if (\count($explicit) >= $target) {
            return \array_slice($explicit, 0, $target);
        }

        // Two tiers, best first. "Halo Over-Ear ANC" reads better than "Halo
        // Over-Ear ANC Select", so editions are only reached for once every
        // line x model pairing in the category has been spent. A tier-1 name
        // always carries a suffix, so it can never collide with a tier-0 one.
        //
        // The tier-0 key is deliberately unsuffixed. It is the key that was in
        // use before editions existed, and changing it would re-pick every
        // generated name in the shop — renaming live products and orphaning
        // their reviews and cross-selling.
        $tiers = [
            '|generated-names' => $this->pairings($spec, $explicit),
            '|generated-names|editions' => $this->editions($spec, $explicit),
        ];

        $names = $explicit;

        foreach ($tiers as $suffix => $candidates) {
            $missing = $target - \count($names);

            if ($missing <= 0) {
                break;
            }

            $names = [...$names, ...$this->values->pickMany($leafKey . $suffix, $candidates, $missing)];
        }

        return $names;
    }

    /**
     * Every line × model pairing the leaf allows, minus names already taken —
     * otherwise a leaf ends up with two products of the same name and, because
     * ids are keyed on the name, one row.
     *
     * @param array<string, mixed> $spec
     * @param array<int, string> $taken
     *
     * @return array<int, string>
     */
    private function pairings(array $spec, array $taken): array
    {
        $series = $this->strings($spec['series'] ?? null);
        $models = $this->strings($spec['models'] ?? null);

        if ($series === [] || $models === []) {
            return [];
        }

        $candidates = [];

        foreach ($series as $line) {
            foreach ($models as $model) {
                $candidates[] = $line . ' ' . $model;
            }
        }

        return array_values(array_diff(array_unique($candidates), $taken));
    }

    /**
     * The second tier: every pairing again, suffixed with an edition. Multiplies
     * the pool by the size of the edition list, which is what lets a category
     * carry fifty products without fifty hand-written names.
     *
     * @param array<string, mixed> $spec
     * @param array<int, string> $taken
     *
     * @return array<int, string>
     */
    private function editions(array $spec, array $taken): array
    {
        $editions = $this->strings($spec['editions'] ?? null) ?: DemoBlueprint::editions();
        $candidates = [];

        foreach ($this->pairings($spec, []) as $pairing) {
            foreach ($editions as $edition) {
                $candidates[] = $pairing . ' ' . $edition;
            }
        }

        return array_values(array_diff(array_unique($candidates), $taken));
    }

    /**
     * How many distinct names a leaf can produce at most — the check that stops
     * a category quietly coming up short.
     *
     * @param array<string, mixed> $spec
     */
    public function capacity(array $spec): int
    {
        $pairings = \count($this->pairings($spec, []));
        $editions = \count($this->strings($spec['editions'] ?? null) ?: DemoBlueprint::editions());

        return \count($this->strings($spec['products'] ?? null)) + $pairings + ($pairings * $editions);
    }

    /**
     * @return array<int, string>
     */
    private function strings(mixed $value): array
    {
        if (!\is_array($value)) {
            return [];
        }

        return array_values(array_filter($value, static fn (mixed $item): bool => \is_string($item)));
    }
}
