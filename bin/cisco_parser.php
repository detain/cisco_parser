<?php
/**
 * Cisco running-config CLI parser.
 *
 * Reads a Cisco IOS `show running-config` text dump from a file passed as the
 * first argument, parses it into a nested tree, and prints the result.
 *
 * Usage:
 *   php bin/cisco_parser.php <path-to-config-file>
 *
 * @author    Joe Huss <detain@interserver.net>
 * @copyright 2025
 * @license   LGPL-2.1-only
 * @package   MyAdmin
 * @category  Network
 */

$autoloadCandidates = [
    __DIR__.'/../vendor/autoload.php',  // installed standalone
    __DIR__.'/../../../autoload.php',   // installed as a Composer dependency
];
$autoloaderFound = false;
foreach ($autoloadCandidates as $autoload) {
    if (file_exists($autoload)) {
        require $autoload;
        $autoloaderFound = true;
        break;
    }
}
if (!$autoloaderFound) {
    fwrite(STDERR, "Composer autoloader not found. Run `composer install` first.\n");
    exit(1);
}

if (!isset($_SERVER['argv'][1]) || !file_exists($_SERVER['argv'][1])) {
    fwrite(STDERR, "Specify a (valid) file as the first argument to get it parsed\n");
    exit(1);
}

$file = str_replace("\r", '', file_get_contents($_SERVER['argv'][1]));
$lines = explode("\n", $file);

$start_str = 'Building configuration...';
$startCount = count($lines);
$x = 0;
while ($x < $startCount && mb_substr($lines[$x], 0, mb_strlen($start_str)) != $start_str) {
    $x++;
}
$info = [];
if ($x >= $startCount) {
    // No "Building configuration..." header — treat the whole file as config.
    $startIndex = 0;
} else {
    if (isset($lines[$x + 2]) && preg_match('/^Current configuration\s*:\s*(?P<config_bytes>\d+)\s*bytes$/', $lines[$x + 2], $matches)) {
        $info['config_bytes'] = $matches['config_bytes'];
    }
    $startIndex = $x + 4;
}

$parser = new \Detain\CiscoParser\CiscoParser();
$info['data'] = $parser->parse_cisco_children($lines, $startIndex);
print_r($info);
