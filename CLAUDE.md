# cisco_parser

Cisco IOS communications and configuration parsing library. PHP >= 7.4, PSR-4, PHPUnit.

## Commands

```bash
composer install
vendor/bin/phpunit tests/ -v
vendor/bin/phpunit tests/ -v --coverage-clover coverage.xml --whitelist src/
```

## Architecture

- **Namespace**: `Detain\CiscoParser\` → `src/`
- **Core**: `src/CiscoParser.php` — recursive config parser (`parse_cisco_children`, `get_space_depth`)
- **Loader**: `src/CiscoLoader.php` — connection/communication layer (SSH/telnet via `ext-ssh2`)
- **CLI**: `bin/cisco_parser.php` — reads a config file, invokes `parse_cisco_children`
- **Tests**: `tests/ciscoTest.php` extends `PHPUnit\Framework\TestCase`
- **CI**: `.scrutinizer.yml` · `.travis.yml` · coverage via clover to `coverage.xml`
- **IDE**: `.idea/` — PhpStorm project configuration (`inspectionProfiles/`, `cisco_parser.iml`, `deployment.xml`)

## Conventions

- Tabs for indentation (see `.scrutinizer.yml` `use_tabs: true`)
- `camelCase` properties and parameters; `UPPER_CASE` constants
- One class per file; no closing `?>`
- PHPDoc on all public methods (`@param`, `@return` required)
- Tests: extend `PHPUnit\Framework\TestCase`; bootstrap via Composer autoloader
- `parse_cisco_children(array $lines, int $x, int $depth)` returns nested `['command', 'arguments'?, 'children'?]` arrays
- `get_space_depth(array $lines, int $x)` returns leading-space count as depth

## Coding Patterns

```php
namespace Detain\CiscoParser;

class CiscoParser {
	public function myMethod(array $lines, $x = 0) {
		// use mb_strlen / mb_substr / mb_strpos for multibyte safety
		$depth = $this->get_space_depth($lines, $x);
		$command = ltrim($lines[$x]);
		return [];
	}
}
```

```php
// tests/ciscoTest.php pattern
use PHPUnit\Framework\TestCase;
class myFeatureTest extends TestCase {
	protected function setUp() {
		$this->object = new \Detain\CiscoParser\CiscoParser();
	}
	public function testMyMethod() {
		$this->assertEquals($expected, $this->object->myMethod($lines));
	}
}
```

```bash
# run single test
vendor/bin/phpunit tests/ciscoTest.php --filter testMyMethod -v
```

## Notes

- `ext-ssh2` is suggested, not required; guard SSH calls with `extension_loaded('ssh2')`
- Commit messages: lowercase, descriptive (`fix depth parsing`, `add vlan parser`)

<!-- caliber:managed:pre-commit -->
## Before Committing

Run `caliber refresh` before creating git commits to keep docs in sync with code changes.
After it completes, stage any modified doc files before committing:

```bash
caliber refresh && git add CLAUDE.md .claude/ .cursor/ .github/copilot-instructions.md AGENTS.md CALIBER_LEARNINGS.md 2>/dev/null
```
<!-- /caliber:managed:pre-commit -->

<!-- caliber:managed:learnings -->
## Session Learnings

Read `CALIBER_LEARNINGS.md` for patterns and anti-patterns learned from previous sessions.
These are auto-extracted from real tool usage — treat them as project-specific rules.
<!-- /caliber:managed:learnings -->

<!-- caliber:managed:model-config -->
## Model Configuration

Recommended default: `claude-sonnet-4-6` with high effort (stronger reasoning; higher cost and latency than smaller models).
Smaller/faster models trade quality for speed and cost — pick what fits the task.
Pin your choice (`/model` in Claude Code, or `CALIBER_MODEL` when using Caliber with an API provider) so upstream default changes do not silently change behavior.

<!-- /caliber:managed:model-config -->

<!-- caliber:managed:sync -->
## Context Sync

This project uses [Caliber](https://github.com/caliber-ai-org/ai-setup) to keep AI agent configs in sync across Claude Code, Cursor, Copilot, and Codex.
Configs update automatically before each commit via `caliber refresh`.
If the pre-commit hook is not set up, run `/setup-caliber` to configure everything automatically.
<!-- /caliber:managed:sync -->
