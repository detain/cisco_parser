---
name: phpunit-test
description: Writes a PHPUnit test in `tests/` extending `PHPUnit\Framework\TestCase` with `setUp` instantiating a `Detain\CiscoParser\` class. Use when user says 'write test', 'add test for', 'test this method', or 'implement test in ciscoTest.php'. Includes `@covers` annotations and `assertEquals`/`assertSame` patterns matching `tests/ciscoTest.php`. Do NOT use for integration tests, CLI tests, or SSH/connection tests requiring live devices.
---
# phpunit-test

## Critical

- All tests go in `tests/ciscoTest.php` — do NOT create new test files unless explicitly asked
- Bootstrap via Composer autoloader — never use `require_once` for class files in new tests
- Use tabs for indentation (project enforces `use_tabs: true` in `.scrutinizer.yml`)
- Do NOT test SSH/connection methods (`connect`, `exec`, `read`, `write`) — guard those with `markTestIncomplete`
- `setUp` must instantiate `\Detain\CiscoParser\CiscoParser` (not the legacy `cisco` class)
- Every test method needs a `@covers` PHPDoc annotation

## Instructions

1. **Read the target method** in `src/CiscoParser.php` to understand its signature, parameters, and return type before writing any test.

2. **Locate the test class** at `tests/ciscoTest.php`. Add new test methods inside the existing `ciscoTest` class — do not create a new class or file.

3. **Verify `setUp` uses the autoloader** (not a `require_once`). The correct pattern:
   ```php
   protected function setUp()
   {
       $this->object = new \Detain\CiscoParser\CiscoParser();
   }
   ```
   If `setUp` still references `class.cisco.php`, update it to the above before adding new tests.

4. **Add the test method** immediately before the closing `}` of the class. Follow this exact template:
   ```php
   /**
    * @covers \Detain\CiscoParser\CiscoParser::methodName
    */
   public function testMethodName()
   {
       $lines = ['line one', ' child line'];
       $expected = [['command' => 'line', 'arguments' => 'one', 'children' => [['command' => 'child', 'arguments' => 'line']]]];
       $this->assertEquals($expected, $this->object->methodName($lines));
   }
   ```

5. **Run the single test** to verify it passes:
   ```bash
   vendor/bin/phpunit tests/ciscoTest.php --filter testMethodName -v
   ```
   Fix any failures before considering the task done.

6. **Run the full suite** to confirm no regressions:
   ```bash
   vendor/bin/phpunit tests/ -v
   ```

## Examples

**User says:** "add test for `get_space_depth`"

**Actions taken:**
1. Read `src/CiscoParser.php` — `get_space_depth(array $lines, int $x): int` returns count of leading spaces.
2. Open `tests/ciscoTest.php`, add inside the class:

```php
/**
 * @covers \Detain\CiscoParser\CiscoParser::get_space_depth
 */
public function testGetSpaceDepth()
{
	$lines = ['no indent', '  two spaces', '    four spaces'];
	$this->assertEquals(0, $this->object->get_space_depth($lines, 0));
	$this->assertEquals(2, $this->object->get_space_depth($lines, 1));
	$this->assertEquals(4, $this->object->get_space_depth($lines, 2));
}
```

3. Run: `vendor/bin/phpunit tests/ciscoTest.php --filter testGetSpaceDepth -v`

**Result:** Test passes; `assertEquals` confirms depth integers for each line index.

## Common Issues

- **`Class 'cisco' not found`**: `setUp` still uses the legacy `require_once __DIR__.'/../class.cisco.php'`. Replace with `$this->object = new \Detain\CiscoParser\CiscoParser();` and remove the `require_once`.
- **`Call to undefined method ... markTestIncomplete`**: You forgot to extend `TestCase`. Confirm the class declaration is `class ciscoTest extends TestCase`.
- **`No tests executed`**: Filter string doesn't match — method must be named `test` + PascalCase (e.g., `testGetSpaceDepth`). Run without `--filter` to see all discovered tests.
- **Indentation errors from CS tools**: Use a literal tab character, not spaces. The `.scrutinizer.yml` enforces `use_tabs: true`.
- **`Cannot redeclare class ciscoTest`**: You created a second test file instead of adding to `tests/ciscoTest.php`. Remove the duplicate file.
