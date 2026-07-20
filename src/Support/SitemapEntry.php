<?php

declare(strict_types=1);

namespace Kurt\Modules\Blog\Support;

use Illuminate\Support\Carbon;

/**
 * A single sitemap URL entry (loc / lastmod / changefreq / priority), the
 * shape a consuming app feeds into its own sitemap generator.
 */
final readonly class SitemapEntry
{
    public function __construct(
        public string $loc,
        public ?Carbon $lastmod = null,
        public ?string $changefreq = null,
        public ?float $priority = null,
    ) {}

    /**
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return array_filter([
            'loc' => $this->loc,
            'lastmod' => $this->lastmod?->toAtomString(),
            'changefreq' => $this->changefreq,
            'priority' => $this->priority !== null ? number_format($this->priority, 1) : null,
        ], static fn (?string $value): bool => $value !== null && $value !== '');
    }
}
