<?php

declare(strict_types=1);

namespace Kommandhub\DemoData\Tests\Unit\Service;

use Kommandhub\DemoData\Blueprint\DemoBlueprint;
use Kommandhub\DemoData\Service\DeterministicValueGenerator;
use Kommandhub\DemoData\Service\ProductReviewBuilder;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Uuid\Uuid;

class ProductReviewBuilderTest extends TestCase
{
    private ProductReviewBuilder $builder;

    protected function setUp(): void
    {
        $this->builder = new ProductReviewBuilder(new DeterministicValueGenerator());
    }

    public function testReviewsAreStableForTheSameProduct(): void
    {
        $this->assertEquals($this->build('product-a'), $this->build('product-a'));
    }

    public function testDifferentProductsGetDifferentReviews(): void
    {
        $this->assertNotEquals($this->build('product-a'), $this->build('product-b'));
    }

    public function testEveryReviewIsCompleteEnoughToStore(): void
    {
        foreach ($this->someReviews() as $review) {
            $this->assertTrue(Uuid::isValid((string)$review['id']));
            $this->assertSame('product-id', $review['productId']);
            $this->assertSame('channel-id', $review['salesChannelId']);
            $this->assertNotSame('', $review['title']);
            $this->assertNotSame('', $review['content']);
            $this->assertIsBool($review['status']);
            $this->assertGreaterThanOrEqual(1.0, $review['points']);
            $this->assertLessThanOrEqual(5.0, $review['points']);
        }
    }

    public function testReviewIdsAreUniqueWithinAProduct(): void
    {
        $ids = array_column($this->someReviews(), 'id');

        $this->assertSame($ids, array_unique($ids));
    }

    /**
     * The point of banding the copy: a one-star review must not read like a
     * five-star one, or every rating filter in the shop demos badly.
     */
    public function testCopyMatchesTheRating(): void
    {
        $copy = DemoBlueprint::reviewCopy();
        $positive = array_column($copy['positive'], 'title');
        $neutral = array_column($copy['neutral'], 'title');
        $negative = array_column($copy['negative'], 'title');

        $seen = ['positive' => 0, 'neutral' => 0, 'negative' => 0];

        foreach ($this->manyReviews() as $review) {
            $points = (int)$review['points'];
            $title = (string)$review['title'];

            if ($points >= 4) {
                $this->assertContains($title, $positive, 'high rating with non-positive copy');
                ++$seen['positive'];
            } elseif ($points === 3) {
                $this->assertContains($title, $neutral, 'middling rating with non-neutral copy');
                ++$seen['neutral'];
            } else {
                $this->assertContains($title, $negative, 'low rating with non-negative copy');
                ++$seen['negative'];
            }
        }

        // All three bands must actually occur, or the assertions above are vacuous.
        $this->assertGreaterThan(0, $seen['positive']);
        $this->assertGreaterThan(0, $seen['neutral']);
        $this->assertGreaterThan(0, $seen['negative']);
    }

    public function testRatingsSkewPositiveTheWayRealCataloguesDo(): void
    {
        $reviews = $this->manyReviews();
        $high = \count(array_filter($reviews, static fn (array $r): bool => (float)$r['points'] >= 4.0));

        $this->assertGreaterThan(0.6 * \count($reviews), $high, 'ratings should skew to 4-5 stars');
    }

    public function testSomeProductsHaveNoReviewsAtAll(): void
    {
        $empty = 0;

        for ($i = 0; $i < 200; ++$i) {
            if ($this->build('product-' . $i) === []) {
                ++$empty;
            }
        }

        $this->assertGreaterThan(0, $empty);
        $this->assertLessThan(100, $empty, 'most of the catalogue should still carry reviews');
    }

    public function testReviewCountStaysWithinBounds(): void
    {
        for ($i = 0; $i < 120; ++$i) {
            $this->assertLessThanOrEqual(14, \count($this->build('product-' . $i)));
        }
    }

    public function testSomeReviewsArePendingModerationAndSomeHaveReplies(): void
    {
        $reviews = $this->manyReviews();

        $pending = \count(array_filter($reviews, static fn (array $r): bool => $r['status'] === false));
        $replied = \count(array_filter($reviews, static fn (array $r): bool => isset($r['comment'])));

        $this->assertGreaterThan(0, $pending, 'the admin moderation queue would be empty');
        $this->assertLessThan(\count($reviews), $pending);
        $this->assertGreaterThan(0, $replied);
    }

    public function testReviewsAreDatedInThePast(): void
    {
        $now = new \DateTimeImmutable();

        foreach ($this->someReviews() as $review) {
            $written = new \DateTimeImmutable((string)$review['createdAt']);

            $this->assertLessThan($now, $written);
            $this->assertGreaterThan($now->modify('-3 years'), $written);
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function build(string $productKey): array
    {
        return $this->builder->build($productKey, 'product-id', 'channel-id');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function someReviews(): array
    {
        for ($i = 0; $i < 50; ++$i) {
            $reviews = $this->build('product-' . $i);

            if ($reviews !== []) {
                return $reviews;
            }
        }

        self::fail('no product produced reviews');
    }

    /**
     * A wide sample, so distribution assertions are not reading one product's
     * luck.
     *
     * @return array<int, array<string, mixed>>
     */
    private function manyReviews(): array
    {
        $all = [];

        for ($i = 0; $i < 120; ++$i) {
            $all = [...$all, ...$this->build('sample-product-' . $i)];
        }

        $this->assertNotEmpty($all);

        return $all;
    }
}
