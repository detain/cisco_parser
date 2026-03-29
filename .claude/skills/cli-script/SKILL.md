---
name: cli-script
description: Creates a CLI script under `bin/` following the `bin/cisco_parser.php` pattern: reads `$_SERVER['argv']`, validates file arg, reads with `file_get_contents`, invokes `Detain\CiscoParser\CiscoParser` via autoloader. Use when user says 'add CLI', 'new bin script', or 'command-line tool'. Do NOT use for library class additions or test files.
---
# cli-script

## Critical

- **Always use the Composer autoloader** — `bin/cisco_parser.php` uses a legacy `include 'class.cisco.php'`; all new scripts must use the Composer autoloader instead.
- **Never use PDO or direct DB calls** — these are stateless CLI tools.
- **Validate `$_SERVER['argv'][1]` before doing anything** — die with a usage message if missing or invalid.
- **Use `mb_*` string functions** (`mb_strlen`, `mb_substr`, `mb_strpos`) for all string operations — the codebase enforces multibyte safety throughout.
- Tabs for indentation, no closing `?>`.

## Instructions

1. **Create the CLI script in `bin/`.**
   - File must start with `<?php` (no closing tag).
   - Require the Composer autoloader as the first statement.
   - Run `composer install` if the autoloader is missing.

2. **Validate the file argument.**
   ```php
   if (!isset($_SERVER['argv'][1]) || !file_exists($_SERVER['argv'][1])) {
   	die('Specify a (valid) file as the first argument to get it parsed');
   }
   ```
   Adjust the die message to describe your script's expected argument.

3. **Read and normalize the input file.**
   ```php
   $file  = str_replace("\r", '', file_get_contents($_SERVER['argv'][1]));
   $lines = explode("\n", $file);
   ```
   Always strip `\r` to handle Windows line endings.

4. **Instantiate `CiscoParser` and call the relevant method.**
   ```php
   $cisco  = new \Detain\CiscoParser\CiscoParser();
   $result = $cisco->parse_cisco_children($lines, 0);
   print_r($result);
   ```
   If you need to skip a header (e.g. `Building configuration...`), advance `$x` using `mb_substr` comparison before passing it:
   ```php
   $start = 'Building configuration...';
   $x = 0;
   while ($x < count($lines) && mb_substr($lines[$x], 0, mb_strlen($start)) !== $start) {
   	$x++;
   }
   $x += 3; // skip header lines
   $result = $cisco->parse_cisco_children($lines, $x);
   ```

5. **Output the result** using `print_r()` for structured data or `echo` for plain text.

6. **Run the script to verify.**
   Pass a valid config file as the first argument and confirm output is printed.

## Examples

**User says:** "Add a CLI script to dump VLAN config from a file."

**Actions taken:**
1. Create the vlan dump script in `bin/`
2. Require autoloader, validate argv, read file, strip `\r`, explode on `\n`
3. Instantiate `\Detain\CiscoParser\CiscoParser`, call `parse_cisco_children($lines, 0)`
4. Filter result for `command === 'vlan'` entries, `print_r`

**Result:**
```php
<?php
require __DIR__ . '/../vendor/autoload.php';

if (!isset($_SERVER['argv'][1]) || !file_exists($_SERVER['argv'][1])) {
	die('Specify a (valid) Cisco config file as the first argument');
}

$file  = str_replace("\r", '', file_get_contents($_SERVER['argv'][1]));
$lines = explode("\n", $file);
$cisco  = new \Detain\CiscoParser\CiscoParser();
$parsed = $cisco->parse_cisco_children($lines, 0);
$vlans  = array_filter($parsed, fn($entry) => ($entry['command'] ?? '') === 'vlan');
print_r(array_values($vlans));
```

## Common Issues

- **`Class 'Detain\CiscoParser\CiscoParser' not found`**: The autoloader isn't loaded or the path is wrong. Verify the Composer autoloader is required as the first statement and that `vendor/` exists. Run `composer install` if missing.
- **`Warning: file_get_contents(): No such file`**: argv check passed but the path is relative. Test with an absolute path to the config file.
- **Infinite loop on header scan**: The `while` loop advancing past `Building configuration...` will loop forever if the header is absent. Guard it with a bounds check: `while ($x < count($lines) && mb_substr(...) !== $start) { $x++; }`.
- **Garbled multibyte output**: You used `strlen`/`substr` instead of `mb_strlen`/`mb_substr`. Replace all bare string functions with `mb_*` equivalents.
