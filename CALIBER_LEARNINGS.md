# Caliber Learnings

Accumulated patterns and anti-patterns from development sessions.
Auto-managed by [caliber](https://github.com/caliber-ai-org/ai-setup) — do not edit manually.

- **[gotcha:project]** `tests/ciscoTest.php::setUp()` calls `require_once __DIR__.'/../class.cisco.php'` which does not exist — running `vendor/bin/phpunit tests/` will fail immediately with a fatal error before any test executes. The fix is to replace the `setUp` body with `$this->object = new \Detain\CiscoParser\CiscoParser();` (remove the `require_once` entirely — the Composer autoloader handles it). This is the first thing to fix before any test run.
- **[env:project]** No `vendor/` directory is committed to the repo — `composer install` must be run before `vendor/bin/phpunit` exists. Always run `composer install` as the first step when setting up or running tests in this project.
- **[gotcha:project]** `bin/cisco_parser.php` line 4 has `include 'class.cisco.php'` which does not exist. This script cannot be executed in its current state. When updating it, replace the include with `require __DIR__ . '/../vendor/autoload.php'` and change `new cisco()` to `new \Detain\CiscoParser\CiscoParser()`.
- **[pattern:project]** For code-audit-and-fix tasks in this repo, after reading the four main files (src/CiscoParser.php, src/CiscoLoader.php, tests/ciscoTest.php, bin/cisco_parser.php), move directly to edits — do not re-read the same files in a second pass. The entire codebase is ~1,200 lines and fits in a single read round.
- **[gotcha:project]** The CLAUDE.md test commands omit `--bootstrap vendor/autoload.php` but that flag is required — without it PHPUnit cannot load classes and fails with autoload errors. The correct command is `vendor/bin/phpunit --bootstrap vendor/autoload.php tests/ -v`. The `.scrutinizer.yml` file has the authoritative form.
