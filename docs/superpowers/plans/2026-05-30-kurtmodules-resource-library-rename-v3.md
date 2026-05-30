# `ozankurt/laravel-modules-resource-library` v3.0 — Rename Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Rename the existing `ozankurt/laravel-modules-library` v2 package to `ozankurt/laravel-modules-resource-library` v3.0.0 — namespace, composer name, provider class, config key, console signatures, and table prefix all change from `library*` to `resource_library*`. No functional behavior changes.

**Architecture:** Mechanical rename across the entire repo via a sequence of search-and-replace passes + one new migration that runs `Schema::rename(...)` on every existing table. The repo on GitHub keeps its current URL (`KurtModules-Library`) — only the composer name, classes, configs, and tables change.

**Tech Stack:** PHP 8.4, Laravel 12, Pest 3 + Testbench, PHPStan 8, Pint. Same stack as v2 — no dep changes.

**Spec:** [2026-05-30-kurtmodules-resource-library-rename-spec.md](../specs/2026-05-30-kurtmodules-resource-library-rename-spec.md)

**Working directory:** `D:\Code\Projects\KurtModules-Library`. Branch `v3.0` off `master`.

---

## File-by-file rename inventory

Before writing tasks, here's exactly what each file becomes:

```
composer.json                                          (edit: name, autoload, provider)
config/library.php                            ->       config/resource-library.php
src/Providers/LibraryServiceProvider.php      ->       src/Providers/ResourceLibraryServiceProvider.php
src/Facades/Library.php                        ->       src/Facades/ResourceLibrary.php   (if it exists)
src/Console/Commands/RecountCommand.php                (edit: $signature 'library:recount' -> 'resource-library:recount')
src/Console/Commands/PruneVersionsCommand.php          (edit: 'library:prune-versions' -> 'resource-library:prune-versions')
src/Console/Commands/RebuildPathsCommand.php           (edit: 'library:rebuild-paths' -> 'resource-library:rebuild-paths')
src/Console/Commands/DemoCommand.php                   (edit: 'library:demo' -> 'resource-library:demo')
src/<every PHP file>                                   (namespace: Kurt\Modules\Library\ -> Kurt\Modules\ResourceLibrary\)
database/factories/<all>                               (namespace: Database\Factories\Kurt\Modules\Library -> Database\Factories\Kurt\Modules\ResourceLibrary)
tests/<every PHP file>                                 (namespace + assertion strings)
tests/TestCase.php                                     (provider FQCN)
tests/Pest.php                                         (TestCase FQCN)
README.md                                              (rewrite for v3)
CHANGELOG.md                                           (prepend v3.0.0 entry)
UPGRADE-3.0.md                                         (new — consumer migration steps)

database/migrations/2026_05_30_NNNNNN_rename_library_to_resource_library.php   (new migration)
```

---

## Task 0: Branch setup

**Files:**
- None created/modified yet. Just branch ops.

- [ ] **Step 1: Create v3.0 branch off master**

```bash
cd D:/Code/Projects/KurtModules-Library
git fetch --all --prune
git switch master
git pull
git switch -c v3.0
```

- [ ] **Step 2: Verify v2.0.0 tag exists (sanity)**

```bash
git tag -l v2.0.0
```
Expected: `v2.0.0` is listed.

---

## Task 1: composer.json rename

**Files:**
- Modify: `composer.json`

- [ ] **Step 1: Read current composer.json to find replacement targets**

```bash
cat composer.json
```

- [ ] **Step 2: Apply rename edits**

Replace these exact strings:

- `"ozankurt/laravel-modules-library"` → `"ozankurt/laravel-modules-resource-library"`
- `"description"` value updated: `"SaaS resource library: nested folders with per-folder permissions, versioned items (video link, file, document, URL)."` (description stays — already accurate).
- In `autoload.psr-4`: `"Kurt\\Modules\\Library\\": "src/"` → `"Kurt\\Modules\\ResourceLibrary\\": "src/"`
- In `autoload-dev.psr-4`: 
  - `"Kurt\\Modules\\Library\\Tests\\": "tests/"` → `"Kurt\\Modules\\ResourceLibrary\\Tests\\": "tests/"`
  - `"Database\\Factories\\Kurt\\Modules\\Library\\": "database/factories/"` → `"Database\\Factories\\Kurt\\Modules\\ResourceLibrary\\": "database/factories/"`
- In `extra.laravel.providers`: `"Kurt\\Modules\\Library\\Providers\\LibraryServiceProvider"` → `"Kurt\\Modules\\ResourceLibrary\\Providers\\ResourceLibraryServiceProvider"`

- [ ] **Step 3: Validate composer.json**

```bash
C:/laragon/bin/php/php-8.4.5-nts-Win32-vs17-x64/php.exe composer.phar validate --strict
```
Expected: `./composer.json is valid`.

- [ ] **Step 4: Regenerate autoload**

```bash
C:/laragon/bin/php/php-8.4.5-nts-Win32-vs17-x64/php.exe composer.phar dump-autoload
```

- [ ] **Step 5: Commit**

```bash
git add composer.json composer.lock
git commit -m "chore: rename composer package to ozankurt/laravel-modules-resource-library"
```

---

## Task 2: PHP namespace rename across `src/`

**Files:**
- Modify: every file under `src/` (mass `sed`).

- [ ] **Step 1: Confirm current namespace shape**

```bash
grep -rl "Kurt\\\\Modules\\\\Library" src/
```
Expected: list of all PHP files under `src/`.

- [ ] **Step 2: Mass replace namespace declarations**

Run from repo root:

```bash
find src -name '*.php' -exec sed -i 's/Kurt\\\\Modules\\\\Library/Kurt\\\\Modules\\\\ResourceLibrary/g' {} +
find src -name '*.php' -exec sed -i 's/namespace Kurt\\\\Modules\\\\Library/namespace Kurt\\\\Modules\\\\ResourceLibrary/g' {} +
```

PowerShell alternative if `sed` unavailable:
```powershell
Get-ChildItem -Path src -Recurse -Filter *.php | ForEach-Object {
    (Get-Content $_.FullName -Raw) -replace 'Kurt\\Modules\\Library', 'Kurt\Modules\ResourceLibrary' | Set-Content $_.FullName -NoNewline
}
```

- [ ] **Step 3: Verify replacement was clean**

```bash
grep -r "Kurt\\\\Modules\\\\Library" src/ || echo "clean"
```
Expected: `clean` (or no matches).

- [ ] **Step 4: Commit**

```bash
git add src
git commit -m "chore: rename namespace Kurt\Modules\Library to Kurt\Modules\ResourceLibrary in src/"
```

---

## Task 3: Service provider class rename

**Files:**
- Rename: `src/Providers/LibraryServiceProvider.php` → `src/Providers/ResourceLibraryServiceProvider.php`
- Modify: the class name inside

- [ ] **Step 1: Verify file exists**

```bash
ls src/Providers/LibraryServiceProvider.php
```

- [ ] **Step 2: Git rename the file**

```bash
git mv src/Providers/LibraryServiceProvider.php src/Providers/ResourceLibraryServiceProvider.php
```

- [ ] **Step 3: Update class name inside**

Replace `class LibraryServiceProvider` with `class ResourceLibraryServiceProvider` in the renamed file.

Also update the Spatie package name passed to `configurePackage`:

```php
// before
$package->name('laravel-modules-library')
// after
$package->name('laravel-modules-resource-library')
```

And the `hasConfigFile()` argument:
```php
// before
->hasConfigFile()                  // implies config/library.php
// after
->hasConfigFile('resource-library') // explicitly load config/resource-library.php
```

- [ ] **Step 4: Commit**

```bash
git add src/Providers
git commit -m "chore: rename LibraryServiceProvider to ResourceLibraryServiceProvider"
```

---

## Task 4: Config file rename

**Files:**
- Rename: `config/library.php` → `config/resource-library.php`
- Modify: model FQCNs inside (already done in Task 2 via namespace sed, but double-check)

- [ ] **Step 1: Git rename**

```bash
git mv config/library.php config/resource-library.php
```

- [ ] **Step 2: Verify model FQCNs were updated by the namespace sed**

```bash
grep "Kurt\\\\Modules\\\\Library" config/resource-library.php || echo "clean"
```
Expected: clean (Task 2 already replaced these).

- [ ] **Step 3: Commit**

```bash
git add config
git commit -m "chore: rename config/library.php to config/resource-library.php"
```

---

## Task 5: Console command signatures

**Files:**
- Modify: every command class under `src/Console/Commands/`

- [ ] **Step 1: List all commands + current signatures**

```bash
grep -rE "protected \$signature = 'library:" src/Console/Commands/
```

- [ ] **Step 2: Replace `library:` prefix with `resource-library:` in every signature**

Bash:
```bash
find src/Console/Commands -name '*.php' -exec sed -i "s/protected \\\$signature = 'library:/protected \\\$signature = 'resource-library:/g" {} +
```

PowerShell:
```powershell
Get-ChildItem -Path src/Console/Commands -Filter *.php | ForEach-Object {
    (Get-Content $_.FullName -Raw) -replace "protected \`$signature = 'library:", "protected `$signature = 'resource-library:" | Set-Content $_.FullName -NoNewline
}
```

- [ ] **Step 3: Verify no `library:` signatures remain**

```bash
grep "protected \$signature = 'library:" src/ || echo "clean"
```

- [ ] **Step 4: Commit**

```bash
git add src/Console
git commit -m "chore: rename console command signatures from library:* to resource-library:*"
```

---

## Task 6: Facade rename (if facade exists)

**Files:**
- Rename: `src/Facades/Library.php` → `src/Facades/ResourceLibrary.php` (if present)

- [ ] **Step 1: Check whether a facade exists**

```bash
ls src/Facades 2>&1 || echo "no facades dir"
```

- [ ] **Step 2: If `src/Facades/Library.php` exists, rename + update class name**

```bash
if [ -f src/Facades/Library.php ]; then
  git mv src/Facades/Library.php src/Facades/ResourceLibrary.php
fi
```

Inside the renamed file (if it existed), update `class Library extends Facade` to `class ResourceLibrary extends Facade`.

If no facade existed in v2, this task is a no-op.

- [ ] **Step 3: Commit (skip if no-op)**

```bash
git add src/Facades 2>/dev/null
if ! git diff --cached --quiet; then
  git commit -m "chore: rename Library facade to ResourceLibrary"
fi
```

---

## Task 7: Tests namespace + assertion fixups

**Files:**
- Modify: every PHP file under `tests/` (namespace + table-name assertions)

- [ ] **Step 1: Mass replace namespace in tests/**

Bash:
```bash
find tests -name '*.php' -exec sed -i 's/Kurt\\\\Modules\\\\Library/Kurt\\\\Modules\\\\ResourceLibrary/g' {} +
```

PowerShell:
```powershell
Get-ChildItem -Path tests -Recurse -Filter *.php | ForEach-Object {
    (Get-Content $_.FullName -Raw) -replace 'Kurt\\Modules\\Library', 'Kurt\Modules\ResourceLibrary' | Set-Content $_.FullName -NoNewline
}
```

- [ ] **Step 2: Update `Database\Factories\Kurt\Modules\Library` namespace in factory references inside tests**

Same sed/PowerShell pattern targeting `Database\\Modules\\Library` patterns.

- [ ] **Step 3: Update test assertion strings that reference table names**

```bash
grep -rn "'library_" tests/ src/
```
Note any references to literal table names `library_folders`, `library_items`, etc. **Do NOT replace these yet** — Task 9 introduces the migration that renames the tables. Until then, tests continue running against the old table names.

Specifically: tests SHOULD continue asserting on `library_*` table names through Task 8, and switch to `resource_library_*` table names in Task 9 once the rename migration lands.

- [ ] **Step 4: Run tests to confirm namespace updates compile**

```bash
C:/laragon/bin/php/php-8.4.5-nts-Win32-vs17-x64/php.exe vendor/bin/pest --no-coverage
```

Expected: tests run. Some may fail because the service provider's table prefix expectations don't match yet — that's fine for this task; Task 9 reconciles.

If tests don't even load (PHP fatal), check namespace clean-up before continuing.

- [ ] **Step 5: Commit**

```bash
git add tests
git commit -m "chore: rename namespace Kurt\Modules\Library to Kurt\Modules\ResourceLibrary in tests/"
```

---

## Task 8: Factories namespace

**Files:**
- Modify: every PHP file under `database/factories/`

- [ ] **Step 1: Replace factory namespace**

Bash:
```bash
find database/factories -name '*.php' -exec sed -i 's/Database\\\\Factories\\\\Kurt\\\\Modules\\\\Library/Database\\\\Factories\\\\Kurt\\\\Modules\\\\ResourceLibrary/g' {} +
find database/factories -name '*.php' -exec sed -i 's/Kurt\\\\Modules\\\\Library/Kurt\\\\Modules\\\\ResourceLibrary/g' {} +
```

PowerShell:
```powershell
Get-ChildItem -Path database/factories -Recurse -Filter *.php | ForEach-Object {
    $content = Get-Content $_.FullName -Raw
    $content = $content -replace 'Database\\Factories\\Kurt\\Modules\\Library', 'Database\Factories\Kurt\Modules\ResourceLibrary'
    $content = $content -replace 'Kurt\\Modules\\Library', 'Kurt\Modules\ResourceLibrary'
    Set-Content $_.FullName $content -NoNewline
}
```

- [ ] **Step 2: Regenerate autoload**

```bash
C:/laragon/bin/php/php-8.4.5-nts-Win32-vs17-x64/php.exe composer.phar dump-autoload
```

- [ ] **Step 3: Commit**

```bash
git add database/factories
git commit -m "chore: rename namespace Database\Factories\Kurt\Modules\Library to Kurt\Modules\ResourceLibrary"
```

---

## Task 9: Rename all `library_*` tables → `resource_library_*` via new migration

**Files:**
- Create: `database/migrations/2026_05_30_000100_rename_library_to_resource_library.php`

- [ ] **Step 1: Write the rename migration**

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /** @var array<int, array{0: string, 1: string}> */
    private array $tables = [
        ['library_access_log', 'resource_library_access_log'],
        ['library_folder_permissions', 'resource_library_folder_permissions'],
        ['library_item_tag', 'resource_library_item_tag'],
        ['library_tags', 'resource_library_tags'],
        ['library_item_versions', 'resource_library_item_versions'],
        ['library_items', 'resource_library_items'],
        ['library_folders', 'resource_library_folders'],
    ];

    public function up(): void
    {
        foreach ($this->tables as [$from, $to]) {
            if (Schema::hasTable($from) && ! Schema::hasTable($to)) {
                Schema::rename($from, $to);
            }
        }
    }

    public function down(): void
    {
        foreach (array_reverse($this->tables) as [$from, $to]) {
            if (Schema::hasTable($to) && ! Schema::hasTable($from)) {
                Schema::rename($to, $from);
            }
        }
    }
};
```

Order matters: rename leaf tables before parents to avoid FK constraint issues on some DB engines. The list above renames tables with FK dependencies (access_log, permissions, pivot, item_tag, versions) before their parents (items, folders).

- [ ] **Step 2: Update existing migrations' `Schema::create` calls so a fresh install creates tables with the new names**

For every file under `database/migrations/2026_05_28_030001_create_library_folders_table.php`-style:

```bash
find database/migrations -name "2026_05_28_*_create_library_*_table.php" -exec sed -i "s/Schema::create('library_/Schema::create('resource_library_/g" {} +
find database/migrations -name "2026_05_28_*_create_library_*_table.php" -exec sed -i "s/Schema::dropIfExists('library_/Schema::dropIfExists('resource_library_/g" {} +
find database/migrations -name "2026_05_28_*_create_library_*_table.php" -exec sed -i "s/constrained('library_/constrained('resource_library_/g" {} +
```

PowerShell equivalent:
```powershell
Get-ChildItem -Path database/migrations -Filter "2026_05_28_*_create_library_*_table.php" | ForEach-Object {
    $content = Get-Content $_.FullName -Raw
    $content = $content -replace "Schema::create\('library_", "Schema::create('resource_library_"
    $content = $content -replace "Schema::dropIfExists\('library_", "Schema::dropIfExists('resource_library_"
    $content = $content -replace "constrained\('library_", "constrained('resource_library_"
    Set-Content $_.FullName $content -NoNewline
}
```

Now a fresh install will create `resource_library_*` tables directly, while existing installs that already have `library_*` tables will get them renamed by the new migration.

- [ ] **Step 3: Also rename the migration filenames themselves for cleanliness**

```bash
for f in database/migrations/2026_05_28_*_create_library_*_table.php; do
  new="${f//create_library_/create_resource_library_}"
  git mv "$f" "$new"
done
```

PowerShell:
```powershell
Get-ChildItem -Path database/migrations -Filter "2026_05_28_*_create_library_*_table.php" | ForEach-Object {
    $newName = $_.Name -replace 'create_library_', 'create_resource_library_'
    git mv $_.FullName "database/migrations/$newName"
}
```

- [ ] **Step 4: Update models' `$table` properties**

Every model under `src/Models/` has a `protected $table = 'library_XYZ';`. Mass replace:

```bash
find src/Models -name '*.php' -exec sed -i "s/protected \\\$table = 'library_/protected \\\$table = 'resource_library_/g" {} +
```

PowerShell:
```powershell
Get-ChildItem -Path src/Models -Filter *.php | ForEach-Object {
    (Get-Content $_.FullName -Raw) -replace "protected `\\\$table = 'library_", "protected `$table = 'resource_library_" | Set-Content $_.FullName -NoNewline
}
```

- [ ] **Step 5: Update test assertions referencing old table names**

```bash
find tests -name '*.php' -exec sed -i "s/'library_/'resource_library_/g" {} +
```

Be careful — this also touches any non-table-name string starting with `library_`. Verify after:

```bash
grep -rn "'library_" tests/
```

If false positives (rare), restore those manually.

- [ ] **Step 6: Update `hasMigrations(...)` list in the service provider**

Open `src/Providers/ResourceLibraryServiceProvider.php`. Its `configurePackage()` calls `->hasMigrations([...])` with file names. Update each entry from `create_library_*_table` to `create_resource_library_*_table`.

- [ ] **Step 7: Run full test suite**

```bash
C:/laragon/bin/php/php-8.4.5-nts-Win32-vs17-x64/php.exe vendor/bin/pest
```

Expected: all tests pass.

- [ ] **Step 8: Run pint + phpstan**

```bash
C:/laragon/bin/php/php-8.4.5-nts-Win32-vs17-x64/php.exe vendor/bin/pint --test
C:/laragon/bin/php/php-8.4.5-nts-Win32-vs17-x64/php.exe vendor/bin/phpstan analyse --memory-limit=2G
```

Both must be clean.

- [ ] **Step 9: Commit**

```bash
git add database/migrations src/Models src/Providers tests
git commit -m "feat(resource-library): rename all library_* tables to resource_library_* via new migration + update existing create migrations + model \$table props"
```

---

## Task 10: Docs — README + CHANGELOG + UPGRADE-3.0

**Files:**
- Modify: `README.md`
- Modify: `CHANGELOG.md`
- Create: `UPGRADE-3.0.md`

- [ ] **Step 1: Rewrite README.md**

Replace title heading from `# laravel-modules-library` to `# laravel-modules-resource-library`. Update the install command to `composer require ozankurt/laravel-modules-resource-library`. Update artisan command examples to `resource-library:*`. Note in a brief "v3.0 rename" section that this replaces the v2 `ozankurt/laravel-modules-library` package.

- [ ] **Step 2: Prepend CHANGELOG.md entry**

```markdown
## [3.0.0] - 2026-05-30

### Changed
- **BREAKING:** Composer package renamed from `ozankurt/laravel-modules-library` to `ozankurt/laravel-modules-resource-library`.
- **BREAKING:** PHP namespace renamed from `Kurt\Modules\Library\` to `Kurt\Modules\ResourceLibrary\`.
- **BREAKING:** Config key renamed from `library` to `resource-library`. Update `config('library.foo')` → `config('resource-library.foo')`.
- **BREAKING:** Service provider class renamed from `LibraryServiceProvider` to `ResourceLibraryServiceProvider`.
- **BREAKING:** Artisan signatures renamed from `library:*` to `resource-library:*` (`library:recount` → `resource-library:recount`, etc.).
- **BREAKING:** Database tables renamed from `library_*` to `resource_library_*` via a single auto-migration. Run `php artisan migrate` after upgrading.

### Why

The new `ozankurt/laravel-modules-media-library` package introduces a WordPress-style media bucket. To prevent confusion, the previous Library module — which is a SaaS *resource* library for sharing videos/files/documents with folder ACL — is renamed to ResourceLibrary.

### Compatibility

No functional behavior changes. All model methods, scopes, policy gates, and event class signatures remain identical. The repo URL on GitHub is unchanged.

See [`UPGRADE-3.0.md`](./UPGRADE-3.0.md) for migration steps.
```

- [ ] **Step 3: Write UPGRADE-3.0.md**

```markdown
# Upgrade Guide — v2.x → v3.0

The 3.0 release is a rename: composer name, namespace, config key, console signatures, and table prefix all change. No functional behavior changes.

## 1. Composer

```diff
-"ozankurt/laravel-modules-library": "^2.0"
+"ozankurt/laravel-modules-resource-library": "^3.0"
```

Run `composer update`.

## 2. PHP namespace

Find-and-replace across your `app/` directory:

| Find | Replace |
|---|---|
| `Kurt\Modules\Library\` | `Kurt\Modules\ResourceLibrary\` |
| `Kurt\\Modules\\Library\\` | `Kurt\\Modules\\ResourceLibrary\\` |

(VS Code regex: `\bKurt\\Modules\\Library\b` → `Kurt\Modules\ResourceLibrary`.)

## 3. Config key

```diff
-config('library.media.disk')
+config('resource-library.media.disk')
```

If you've published the config file:

```bash
mv config/library.php config/resource-library.php
```

## 4. Artisan commands

| Old | New |
|---|---|
| `library:recount` | `resource-library:recount` |
| `library:prune-versions` | `resource-library:prune-versions` |
| `library:rebuild-paths` | `resource-library:rebuild-paths` |
| `library:demo` | `resource-library:demo` |

Update any cron entries and scheduler bindings.

## 5. Database tables

Run pending migrations:

```bash
php artisan migrate
```

The `2026_05_30_000100_rename_library_to_resource_library` migration renames every `library_*` table to `resource_library_*`. Existing data is preserved.

For new installations, fresh installs create the `resource_library_*` tables directly.

## 6. Service provider

If your app's `config/app.php` lists providers manually:

```diff
-Kurt\Modules\Library\Providers\LibraryServiceProvider::class,
+Kurt\Modules\ResourceLibrary\Providers\ResourceLibraryServiceProvider::class,
```

If you rely on Laravel's package auto-discovery, no change needed.

## 7. Facades

If you import the facade:

```diff
-use Kurt\Modules\Library\Facades\Library;
+use Kurt\Modules\ResourceLibrary\Facades\ResourceLibrary;

-Library::createFolder(...);
+ResourceLibrary::createFolder(...);
```

## 8. Verify

After upgrade:

```bash
php artisan migrate:status   # confirms the rename migration ran
php artisan resource-library:demo   # smoke test
```
```

- [ ] **Step 4: Commit**

```bash
git add README.md CHANGELOG.md UPGRADE-3.0.md
git commit -m "docs: rewrite README, prepend CHANGELOG entry, add UPGRADE-3.0 for v3 rename"
```

---

## Task 11: Final verification

- [ ] **Step 1: Run the full local CI suite**

```bash
C:/laragon/bin/php/php-8.4.5-nts-Win32-vs17-x64/php.exe vendor/bin/pint --test
C:/laragon/bin/php/php-8.4.5-nts-Win32-vs17-x64/php.exe vendor/bin/phpstan analyse --memory-limit=2G
C:/laragon/bin/php/php-8.4.5-nts-Win32-vs17-x64/php.exe vendor/bin/pest
```

All three must be green. The same test count as v2 (pre-rename) should still pass.

- [ ] **Step 2: Verify the v3 migration is idempotent**

```bash
C:/laragon/bin/php/php-8.4.5-nts-Win32-vs17-x64/php.exe vendor/bin/pest tests/Feature/MigrationsTest.php
```
Expected: pass. If no such test exists, write a smoke test under `tests/Feature/RenameMigrationTest.php`:

```php
<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;

it('creates resource_library_* tables and not library_* tables on fresh install', function () {
    foreach ([
        'resource_library_folders', 'resource_library_items', 'resource_library_item_versions',
        'resource_library_tags', 'resource_library_item_tag', 'resource_library_folder_permissions',
        'resource_library_access_log',
    ] as $table) {
        expect(Schema::hasTable($table))->toBeTrue("missing {$table}");
    }
    foreach ([
        'library_folders', 'library_items', 'library_item_versions',
        'library_tags', 'library_item_tag', 'library_folder_permissions',
        'library_access_log',
    ] as $oldTable) {
        expect(Schema::hasTable($oldTable))->toBeFalse("legacy table {$oldTable} should not exist on fresh install");
    }
});
```

Commit the smoke test:

```bash
git add tests/Feature/RenameMigrationTest.php
git commit -m "test(resource-library): smoke test confirming fresh install uses resource_library_* tables"
```

---

## Task 12: Push + PR + merge + tag

- [ ] **Step 1: Push branch**

```bash
git push -u origin v3.0
```

- [ ] **Step 2: Open PR**

```bash
gh pr create --title "v3.0: rename ozankurt/laravel-modules-library to ozankurt/laravel-modules-resource-library" --body "$(cat <<'EOF'
## Summary

- Renames composer name, PHP namespace, service provider, config key, console signatures, and table prefix from `library*` to `resource_library*`.
- No functional behavior changes.
- New migration renames existing `library_*` tables to `resource_library_*` automatically.
- Disambiguates from the upcoming `ozankurt/laravel-modules-media-library` (WordPress-style media bucket).

See `UPGRADE-3.0.md` for the consumer migration steps.

## Test plan
- [x] vendor/bin/pint --test
- [x] vendor/bin/phpstan analyse
- [x] vendor/bin/pest (full suite — same count as v2)
- [ ] CI matrix green on this PR
EOF
)"
```

- [ ] **Step 3: Wait for CI to go green**

```bash
gh pr checks <PR_NUMBER> --watch
```

- [ ] **Step 4: Merge**

```bash
gh pr merge <PR_NUMBER> --merge
```

- [ ] **Step 5: Tag v3.0.0 + release**

```bash
git switch master
git pull
git tag -a v3.0.0 -m "v3.0.0"
git push origin v3.0.0
gh release create v3.0.0 --title "v3.0.0" --notes-file CHANGELOG.md
```

---

## Definition of done

- [ ] composer.json + namespace + provider + config + commands + tables all renamed.
- [ ] New `2026_05_30_000100_rename_library_to_resource_library` migration renames existing installs.
- [ ] Existing create migrations updated so fresh installs use `resource_library_*` tables directly.
- [ ] All v2 tests still pass.
- [ ] Pint + PHPStan level 8 clean.
- [ ] CI matrix green.
- [ ] README + CHANGELOG + UPGRADE-3.0 in place.
- [ ] Tagged `v3.0.0`; GitHub release published.
- [ ] Downstream module's VCS-repo references continue working (URL unchanged on GitHub).
