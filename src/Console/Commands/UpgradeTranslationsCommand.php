<?php

declare(strict_types=1);

namespace Kurt\Modules\Blog\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class UpgradeTranslationsCommand extends Command
{
    protected $signature = 'blog:upgrade-translations {--locale=en}';

    protected $description = 'Wrap legacy scalar string columns into JSON translation arrays.';

    /** @var array<string, list<string>> */
    private array $targets = [
        'blog_posts' => ['title', 'excerpt', 'body', 'meta_title', 'meta_description'],
        'blog_categories' => ['name', 'description'],
        'blog_tags' => ['name', 'description'],
    ];

    public function handle(): int
    {
        $option = $this->option('locale');
        $locale = is_string($option) ? $option : 'en';

        foreach ($this->targets as $table => $columns) {
            foreach ($columns as $column) {
                $this->upgradeColumn($table, $column, $locale);
            }
        }

        return self::SUCCESS;
    }

    private function upgradeColumn(string $table, string $column, string $locale): void
    {
        $rows = DB::table($table)->select(['id', $column])->whereNotNull($column)->get();

        foreach ($rows as $row) {
            $value = $row->{$column};

            if ($value === null || $value === '' || $this->looksLikeJson((string) $value)) {
                continue;
            }

            DB::table($table)
                ->where('id', $row->id)
                ->update([$column => json_encode([$locale => $value], JSON_UNESCAPED_UNICODE)]);
        }

        $this->info("Upgraded {$table}.{$column}");
    }

    private function looksLikeJson(string $value): bool
    {
        $trimmed = ltrim($value);

        return str_starts_with($trimmed, '{') || str_starts_with($trimmed, '[');
    }
}
