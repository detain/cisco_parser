---
name: cisco-parser-feature
description: Adds a new parse method to `src/CiscoParser.php` following the recursive `parse_cisco_children` / `get_space_depth` pattern. Use when user says 'add parser for', 'parse X output', 'new method', or 'extend CiscoParser'. Key capabilities: multibyte string handling, depth-based recursion, returns nested command/arguments/children arrays. Do NOT use for CLI scripts (`bin/`) or test files.
---
# cisco-parser-feature

## Critical

- All methods go in `src/CiscoParser.php` under namespace `Detain\CiscoParser`, class `CiscoParser`
- Use **tabs** for indentation (not spaces) — enforced by `.scrutinizer.yml`
- Always use `mb_strlen` / `mb_substr` / `mb_strpos` — never `strlen` / `substr` / `strpos`
- PHPDoc with `@param` and `@return` is required on every public method
- No closing `?>` at end of file
- Return arrays use the shape `['command' => string, 'arguments'? => string, 'children'? => array]`

## Instructions

1. **Open `src/CiscoParser.php`** and locate the end of the `CiscoParser` class (before the closing `}`).
   Verify the namespace declaration is `namespace Detain\CiscoParser;` before proceeding.

2. **Add the new method** using this exact boilerplate:
   ```php
   /**
    * @param array $lines  Raw IOS output lines
    * @param int   $x      Starting line index
    * @return array        Nested ['command', 'arguments'?, 'children'?] arrays
    */
   public function parse_<feature>($lines, $x = 0)
   {
   	$data = [];
   	for ($xMax = count($lines); $x < $xMax; $x++) {
   		$cdepth = $this->get_space_depth($lines, $x);
   		$command = ltrim($lines[$x]);
   		$arguments = '';
   		$spacepos = mb_strpos($command, ' ');
   		if ($spacepos !== false) {
   			$arguments = mb_substr($command, $spacepos + 1);
   			$command = mb_substr($command, 0, $spacepos);
   		}
   		$new_data = ['command' => $command];
   		if ($arguments != '') {
   			$new_data['arguments'] = trim($arguments);
   		}
   		if ($x + 1 < count($lines) && $this->get_space_depth($lines, $x + 1) > $cdepth) {
   			$new_data['children'] = $this->parse_cisco_children($lines, $x + 1, $this->get_space_depth($lines, $x + 1));
   			while ($x + 1 < count($lines) && $this->get_space_depth($lines, $x + 1) > $cdepth) {
   				++$x;
   			}
   		}
   		$data[] = $new_data;
   	}
   	return $data;
   }
   ```
   Name format: `parse_<feature>` using `snake_case` (e.g., `parse_vlan`, `parse_bgp_neighbors`).
   Verify method name does not already exist in the class.

3. **Add a PHPUnit test** in `tests/ciscoTest.php`:
   ```php
   /**
    * @covers \Detain\CiscoParser\CiscoParser::parse_<feature>
    */
   public function testParse_<feature>()
   {
   	$this->object = new \Detain\CiscoParser\CiscoParser();
   	$lines = [
   		'<command> <arguments>',
   		' <child-command> <child-arguments>',
   	];
   	$expected = [
   		['command' => '<command>', 'arguments' => '<arguments>', 'children' => [
   			['command' => '<child-command>', 'arguments' => '<child-arguments>'],
   		]],
   	];
   	$this->assertEquals($expected, $this->object->parse_<feature>($lines));
   }
   ```
   Note: `tests/ciscoTest.php` uses the global namespace — no `namespace` declaration.

4. **Run the test** to verify:
   ```bash
   vendor/bin/phpunit tests/ciscoTest.php --filter testParse_<feature> -v
   ```
   Verify output shows `OK (1 test, 1 assertion)` before committing.

## Examples

**User says:** "Add a parser for VLAN config blocks"

**Actions taken:**
1. Add `parse_vlan(array $lines, $x = 0)` to `src/CiscoParser.php` using pattern above
2. Add `testParse_vlan()` to `tests/ciscoTest.php` with fixture lines like `['vlan 10', ' name SERVERS']`
3. Run: `vendor/bin/phpunit tests/ciscoTest.php --filter testParse_vlan -v`

**Result:**
```php
// Input: ['vlan 10', ' name SERVERS']
// Output:
[
  ['command' => 'vlan', 'arguments' => '10', 'children' => [
    ['command' => 'name', 'arguments' => 'SERVERS'],
  ]],
]
```

## Common Issues

- **`Class 'Detain\CiscoParser\CiscoParser' not found`**: Run `composer install` to generate the autoloader. Verify `src/CiscoParser.php` declares `namespace Detain\CiscoParser;`.

- **`Call to undefined method ... parse_<feature>`**: You added the method outside the class braces. Check `src/CiscoParser.php` — the method must be inside `class CiscoParser { ... }`.

- **Off-by-one in children**: Children are detected by `get_space_depth($lines, $x + 1) > $cdepth`. If children are not captured, verify the fixture lines use actual spaces (not tabs) for indentation, since `get_space_depth` counts leading whitespace characters.

- **`arguments` key missing in expected output**: The method only sets `'arguments'` when the string is non-empty after `ltrim`. Fixture lines with no arguments should not include `'arguments'` in `$expected`.

- **PHPUnit reports `No tests executed`**: Confirm the test method is named `testParse_<feature>` (camelCase `test` prefix + exact method name) and the class extends `TestCase`.
