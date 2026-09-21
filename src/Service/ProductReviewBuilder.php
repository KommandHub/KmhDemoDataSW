<?php

declare(strict_types=1);

namespace Kommandhub\DemoData\Service;

use Kommandhub\DemoData\Blueprint\DemoBlueprint;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * Builds a product's reviews.
 *
 * Two things make generated reviews look generated, and both are avoided here.
 * The first is a uniform rating spread: real catalogues are heavily skewed to
 * four and five stars, so a shop where every product averages 3.0 makes every
 * sorting and filtering demo meaningless. The second is copy that contradicts
 * its own score, so the rating decides which pool the text comes from.
 *
 * Pure, like the payload builder — reviews are derived entirely from the
 * product's key, so the same product always has the same reviews.
 */
class ProductReviewBuilder
{
    /**
     * Share of products carrying no reviews at all. A catalogue where every
     * single item has been reviewed is its own kind of obviously-fake.
     */
    private const UNREVIEWED_SHARE_PERCENT = 25;

    private const MAX_REVIEWS = 14;

    /** Share of reviews still awaiting moderation, so the admin queue is not empty. */
    private const PENDING_SHARE_PERCENT = 12;

    /** Share of reviews the shop has replied to. */
    private const REPLY_SHARE_PERCENT = 20;

    /**
     * Rating distribution, as cumulative weights out of 100. Roughly what a
     * healthy catalogue actually looks like.
     *
     * @var array<int, array{0: int, 1: int}> [cumulative weight, points]
     */
    private const RATING_WEIGHTS = [
        [52, 5],
        [78, 4],
        [89, 3],
        [95, 2],
        [100, 1],
    ];

    public function __construct(private readonly DeterministicValueGenerator $values)
    {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function build(string $productKey, string $productId, string $salesChannelId): array
    {
        if ($this->values->bool($productKey . '|unreviewed', self::UNREVIEWED_SHARE_PERCENT)) {
            return [];
        }

        $copy = DemoBlueprint::reviewCopy();
        $count = $this->values->int($productKey . '|review-count', 1, self::MAX_REVIEWS);
        $reviews = [];

        for ($i = 1; $i <= $count; ++$i) {
            $key = $productKey . '|review|' . $i;
            $points = $this->points($key);
            $text = $this->values->pick($key . '|text', $this->pool($copy, $points));
            $reviewer = $this->reviewer($copy, $key);

            $review = [
                'id' => Uuid::fromStringToHex($key),
                'productId' => $productId,
                'productVersionId' => Defaults::LIVE_VERSION,
                'salesChannelId' => $salesChannelId,
                'languageId' => Defaults::LANGUAGE_SYSTEM,
                'externalUser' => $reviewer['name'],
                'externalEmail' => $reviewer['email'],
                'title' => $text['title'],
                'content' => $text['content'],
                'points' => (float)$points,
                // A pending review is invisible in the storefront but shows up
                // in the admin's moderation list, which is worth demonstrating.
                'status' => !$this->values->bool($key . '|pending', self::PENDING_SHARE_PERCENT),
                'createdAt' => $this->writtenAt($key),
            ];

            if ($this->values->bool($key . '|reply', self::REPLY_SHARE_PERCENT)) {
                $review['comment'] = $this->values->pick($key . '|reply-text', $copy['replies']);
            }

            $reviews[] = $review;
        }

        return $reviews;
    }

    private function points(string $key): int
    {
        $roll = $this->values->int($key . '|points', 1, 100);
        $fallback = 5;

        foreach (self::RATING_WEIGHTS as [$threshold, $points]) {
            if ($roll <= $threshold) {
                $fallback = $points;

                break;
            }
        }

        return $fallback;
    }

    /**
     * @param array{positive: array<int, array{title: string, content: string}>, neutral: array<int, array{title: string, content: string}>, negative: array<int, array{title: string, content: string}>} $copy
     *
     * @return array<int, array{title: string, content: string}>
     */
    private function pool(array $copy, int $points): array
    {
        return match (true) {
            $points >= 4 => $copy['positive'],
            $points === 3 => $copy['neutral'],
            default => $copy['negative'],
        };
    }

    /**
     * @param array{reviewers: array<int, string>, surnames: array<int, string>} $copy
     *
     * @return array{name: string, email: string}
     */
    private function reviewer(array $copy, string $key): array
    {
        $first = $this->values->pick($key . '|first-name', $copy['reviewers']);
        $last = $this->values->pick($key . '|last-name', $copy['surnames']);

        return [
            'name' => $first . ' ' . $last,
            // example.com is reserved for documentation, so a generated address
            // can never reach a real mailbox.
            'email' => strtolower($first . '.' . $last) . '@example.com',
        ];
    }

    /**
     * Spread over the last two years so "most recent" and date sorting have
     * something to work with.
     */
    private function writtenAt(string $key): string
    {
        return (new \DateTimeImmutable('today'))
            ->modify(\sprintf('-%d days', $this->values->int($key . '|written', 1, 730)))
            ->modify(\sprintf('+%d hours', $this->values->int($key . '|hour', 0, 23)))
            ->format(\DATE_ATOM);
    }
}
