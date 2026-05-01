# Cisco Parser

Cisco IOS communications and configuration-parsing library for PHP. Provides:

- `Detain\CiscoParser\CiscoParser` &mdash; converts a Cisco IOS running/startup
  configuration text dump into a nested associative-array tree based on
  indentation depth.
- `Detain\CiscoParser\CiscoLoader` &mdash; SSH transport (via PECL `ssh2`) that
  drives an interactive shell against a real device and parses the output of
  common `show` commands (`show int status`, `show int <int>`, `show log`,
  `show arp`, `show mac address-table`, `show ipv6 neighbors`, `show ipv6
  routers`, DHCP snooping bindings, trunk ports, VLANs, err-disabled, &hellip;).

## Build Status and Code Analysis

| Site                                                          | Status                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                              |
| ------------------------------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| ![Travis-CI](http://i.is.cc/storage/GYd75qN.png "Travis-CI")  | [![Build Status](https://travis-ci.org/detain/cisco_parser.svg?branch=master)](https://travis-ci.org/detain/cisco_parser)                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                          |
| ![CodeClimate](http://i.is.cc/storage/GYlageh.png "CodeClimate") | [![Code Climate](https://codeclimate.com/github/detain/cisco_parser/badges/gpa.svg)](https://codeclimate.com/github/detain/cisco_parser) [![Test Coverage](https://codeclimate.com/github/detain/cisco_parser/badges/coverage.svg)](https://codeclimate.com/github/detain/cisco_parser/coverage) [![Issue Count](https://codeclimate.com/github/detain/cisco_parser/badges/issue_count.svg)](https://codeclimate.com/github/detain/cisco_parser)                                                                                                                                                                                                                                                                                       |
| ![Scrutinizer](http://i.is.cc/storage/GYeUnux.png "Scrutinizer") | [![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/detain/cisco_parser/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/detain/cisco_parser/?branch=master) [![Code Coverage](https://scrutinizer-ci.com/g/detain/cisco_parser/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/detain/cisco_parser/?branch=master) [![Build Status](https://scrutinizer-ci.com/g/detain/cisco_parser/badges/build.png?b=master)](https://scrutinizer-ci.com/g/detain/cisco_parser/build-status/master)                                                                                                                                                                                                            |
| ![Codacy](http://i.is.cc/storage/GYi66Cx.png "Codacy")        | [![Codacy Badge](https://api.codacy.com/project/badge/Grade/226251fc068f4fd5b4b4ef9a40011d06)](https://www.codacy.com/app/detain/cisco_parser) [![Codacy Badge](https://api.codacy.com/project/badge/Coverage/25fa74eb74c947bf969602fcfe87e349)](https://www.codacy.com/app/detain/cisco_parser?utm_source=github.com&utm_medium=referral&utm_content=detain/cisco_parser&utm_campaign=Badge_Coverage)                                                                                                                                                                                                                                                                                                                              |
| ![Coveralls](http://i.is.cc/storage/GYjNSim.png "Coveralls")  | [![Coverage Status](https://coveralls.io/repos/github/detain/db_abstraction/badge.svg?branch=master)](https://coveralls.io/github/detain/cisco_parser?branch=master)                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                               |
| ![Packagist](http://i.is.cc/storage/GYacBEX.png "Packagist")  | [![Latest Stable Version](https://poser.pugx.org/detain/cisco_parser/version)](https://packagist.org/packages/detain/cisco_parser) [![Total Downloads](https://poser.pugx.org/detain/cisco_parser/downloads)](https://packagist.org/packages/detain/cisco_parser) [![Latest Unstable Version](https://poser.pugx.org/detain/cisco_parser/v/unstable)](//packagist.org/packages/detain/cisco_parser) [![Monthly Downloads](https://poser.pugx.org/detain/cisco_parser/d/monthly)](https://packagist.org/packages/detain/cisco_parser) [![Daily Downloads](https://poser.pugx.org/detain/cisco_parser/d/daily)](https://packagist.org/packages/detain/cisco_parser) [![License](https://poser.pugx.org/detain/cisco_parser/license)](https://packagist.org/packages/detain/cisco_parser) |

## Requirements

- PHP **7.4** or newer
- `ext-mbstring`
- `ext-ssh2` &mdash; **only** required if you use `CiscoLoader` to talk to a real
  device. The pure-text `CiscoParser` works without it.

## Installation

Install with Composer:

```sh
composer require detain/cisco_parser
```

## Usage

### Parsing a configuration

`CiscoParser::parse_cisco_children()` takes an array of lines (typically from
`explode("\n", file_get_contents(...))` after stripping `\r`) and produces a
nested tree where each node has:

- `command` &mdash; the first whitespace-delimited token on the line
- `arguments` &mdash; (optional) anything after the first token
- `children` &mdash; (optional) the sub-block of more deeply-indented lines

```php
use Detain\CiscoParser\CiscoParser;

$config = <<<CFG
hostname Switch1
interface FastEthernet0/1
 switchport access vlan 10
 spanning-tree portfast
interface FastEthernet0/2
 description Uplink
CFG;

$lines  = explode("\n", str_replace("\r", '', $config));
$parser = new CiscoParser();
$tree   = $parser->parse_cisco_children($lines);

print_r($tree);
```

Output:

```
Array
(
    [0] => Array
        (
            [command] => hostname
            [arguments] => Switch1
        )
    [1] => Array
        (
            [command] => interface
            [arguments] => FastEthernet0/1
            [children] => Array
                (
                    [0] => Array
                        (
                            [command] => switchport
                            [arguments] => access vlan 10
                        )
                    [1] => Array
                        (
                            [command] => spanning-tree
                            [arguments] => portfast
                        )
                )
        )
    [2] => Array
        (
            [command] => interface
            [arguments] => FastEthernet0/2
            [children] => Array
                (
                    [0] => Array
                        (
                            [command] => description
                            [arguments] => Uplink
                        )
                )
        )
)
```

### Parsing from the command line

A small wrapper script is shipped at `bin/cisco_parser.php`:

```sh
php bin/cisco_parser.php /path/to/show-running-config.txt
```

If installed via Composer, the same script is exposed at
`vendor/bin/cisco_parser.php`.

The wrapper recognises an optional `Building configuration...` /
`Current configuration : N bytes` header (as emitted by `show running-config`)
and pulls the byte count out into the result. Files without that header are
parsed in full.

### Talking to a real device

`CiscoLoader` opens an SSH session and exposes typed helpers around the most
common `show` commands. It requires the `ssh2` PECL extension.

```php
use Detain\CiscoParser\CiscoLoader;

$device = new CiscoLoader('switch1.example.net', 'admin', 's3cret');

// Implicit connect on first exec() because $autoconnect is true by default.
$status = $device->show_int_status();
foreach ($status as $port) {
    echo "{$port['interface']}\t{$port['status']}\t{$port['vlan']}\n";
}

// Other helpers
$device->show_log();              // requires enable mode (#)
$device->show_int('Gi1/0/1');
$device->trunk_ports();
$device->vlans();
$device->errdisabled();
$device->dhcpsnoop_bindings();
$device->mac_address_table();
$device->arp_table();
$device->ipv6_neighbor_table();
$device->ipv6_routers();

// Push configuration. Requires enable mode (#).
$device->configure("interface Gi1/0/2\n description new label\n");
$device->write_config();          // saves running-config to startup-config

$device->disconnect();
```

`CiscoLoader::exec($cmd)` is available for any other command not covered by a
helper; it returns the device's raw response with the trailing prompt stripped.

## Testing

```sh
composer install
composer test
```

For coverage output:

```sh
composer test-coverage
# → tests/ + coverage.xml (clover format)
```

## License

The Cisco Parser library is licensed under the
[LGPL-2.1-only](https://opensource.org/licenses/LGPL-2.1) license.
