<?php
/* This was probably written before i wrote the class, so need to update a few links to use the class instead */
include 'class.cisco.php';
if (!isset($_SERVER['argv'][1]) || !file_exists($_SERVER['argv'][1])) {
	die('Specify a (valid) file as the first argument to get it parsed');
}
$file = str_replace("\r", '', file_get_contents($_SERVER['argv'][1]));
$lines = explode("\n", $file);
$start_str = 'Building configuration...';
$x = 0;
while (mb_substr($lines[$x], 0, mb_strlen($start_str)) != $start_str)
	$x++;
$info = [];
if (preg_match('/^Current configuration\s*:\s*(?P<config_bytes>$|\d+)( bytes)$/', $lines[$x + 2], $matches)) {
	$info['config_bytes'] = $matches['config_bytes'];
}
$x += 3;
$cisco = new cisco();
$info['data'] = $cisco->parse_cisco_children($lines, $x + 1);
print_r($info);
