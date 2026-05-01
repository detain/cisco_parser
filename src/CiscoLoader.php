<?php
/**
 * Cisco IOS SSH Communications Layer.
 *
 * Wraps the PECL `ssh2` extension to talk to a Cisco IOS device over an
 * interactive shell, with helpers that issue common `show` commands and parse
 * their output into structured arrays. Designed for use against catalyst-style
 * switches and routers.
 *
 * @author    Joe Huss <detain@interserver.net>
 * @copyright 2025
 * @license   LGPL-2.1-only
 * @package   MyAdmin
 * @category  Network
 */

namespace Detain\CiscoParser;

/**
 * SSH-based interface for issuing IOS commands and parsing the responses.
 *
 * Requires the `ssh2` PHP extension. SSH calls are guarded only at the entry
 * points; if the extension is missing, `connect()` will fail.
 */
class CiscoLoader
{
    /**
     * Whether `exec()` should auto-connect when not already connected.
     *
     * @var bool
     */
    public $autoconnect = true;

    /**
     * Minimum default socket timeout (seconds). Pass `0` or `false` to disable.
     *
     * @var int
     */
    public $min_timeout = 300;

    /**
     * Connection state. `true` once `connect()` succeeds.
     *
     * @var bool
     */
    public $connected = false;

    /**
     * Remote SSH hostname or IP address.
     *
     * @var string
     */
    private $_hostname;

    /**
     * SSH username.
     *
     * @var string
     */
    private $_username;

    /**
     * SSH password.
     *
     * @var string
     */
    private $_password;

    /**
     * SSH port. Defaults to 22.
     *
     * @var int
     */
    private $_port;

    /**
     * Captured Message-of-the-Day / login banner.
     *
     * @var string
     */
    private $_motd;

    /**
     * Captured shell prompt (e.g. `switch1>` or `switch1#`).
     *
     * @var string
     */
    private $_prompt;

    /**
     * SSH connection handle returned by `ssh2_connect()`.
     *
     * @var resource|null
     */
    private $_ssh;

    /**
     * SSH shell stream returned by `ssh2_shell()`.
     *
     * @var resource|null
     */
    private $_stream;

    /**
     * Last formatted response, set by parser methods.
     *
     * @var mixed
     */
    private $_data;

    /**
     * Raw response buffer accumulated by `read()`.
     *
     * @var string
     */
    private $_response;

    /**
     * Configure the connection details. Does not actually open the socket;
     * call `connect()` (or rely on `autoconnect`) for that.
     *
     * @param string $hostname Remote IOS device hostname or IP.
     * @param string $username SSH username.
     * @param string $password SSH password.
     * @param int    $port     SSH port. Defaults to 22.
     */
    public function __construct($hostname, $username, $password, $port = 22)
    {
        $this->_hostname = $hostname;
        $this->_username = $username;
        $this->_password = $password;
        $this->_port = $port;
        if ($this->min_timeout && ini_get('default_socket_timeout') < $this->min_timeout) {
            ini_set('default_socket_timeout', $this->min_timeout);
        }
    }

    /**
     * Shift `$index` characters off the front of `$string` (multibyte-safe).
     *
     * Returns the removed prefix and mutates `$string` in place to be the
     * remainder.
     *
     * @param string $string Reference to the string to shift from.
     * @param int    $index  Number of characters to remove from the front.
     *
     * @return string The removed prefix.
     */
    public function _string_shift(&$string, $index = 1)
    {
        $substr = mb_substr($string, 0, $index);
        $string = mb_substr($string, $index);
        return $substr;
    }

    /**
     * Read from the interactive shell until `$pattern` is seen.
     *
     * If `$regex` is `false`, `$pattern` is matched as a literal substring.
     * If `$regex` is `true`, `$pattern` is treated as a PCRE pattern and the
     * first occurrence of any match is consumed.
     *
     * @param string $pattern Literal substring or PCRE pattern to wait for.
     * @param bool   $regex   `true` to interpret `$pattern` as a regex.
     *
     * @return string The portion of the shell output up to and including the
     *                first match. If the stream reaches EOF without matching,
     *                whatever has been buffered so far is returned.
     */
    public function read($pattern = '', $regex = false)
    {
        $this->_response = '';
        $match = $pattern;
        while (!feof($this->_stream)) {
            if ($regex) {
                preg_match($pattern, $this->_response, $matches);
                $match = $matches[0] ?? '';
            }
            $pos = ($match !== '' && $match !== null) ? mb_strpos($this->_response, $match) : false;
            if ($pos !== false) {
                return $this->_string_shift($this->_response, $pos + mb_strlen($match));
            }
            usleep(1000);
            $response = fgets($this->_stream);
            if ($response === false) {
                break;
            }
            $this->_response .= $response;
        }
        return $this->_response;
    }

    /**
     * Write a string to the interactive shell.
     *
     * @param string $cmd Raw bytes to send. The caller is responsible for any
     *                    trailing newline.
     *
     * @return void
     */
    public function write($cmd)
    {
        fwrite($this->_stream, $cmd);
    }

    /**
     * Open the SSH connection, authenticate, request an interactive shell,
     * and capture the device's banner and prompt.
     *
     * @return bool `true` on success, `false` if the underlying TCP / SSH
     *              handshake failed.
     */
    public function connect()
    {
        if (!extension_loaded('ssh2')) {
            return false;
        }
        $this->_ssh = ssh2_connect($this->_hostname, $this->_port);
        if ($this->_ssh === false) {
            return false;
        }
        if (!ssh2_auth_password($this->_ssh, $this->_username, $this->_password)) {
            return false;
        }
        $this->_stream = ssh2_shell($this->_ssh);
        if ($this->_stream === false) {
            return false;
        }
        $this->connected = true;
        $this->parse_motd_and_prompt();
        return true;
    }

    /**
     * Read the banner / MOTD that was emitted on login, then send a newline
     * and capture the resulting bare prompt for use by `exec()`.
     *
     * Strips the trailing prompt off the banner so the two are cleanly
     * separated.
     *
     * @return bool Always `true`.
     */
    public function parse_motd_and_prompt()
    {
        $this->_motd = trim($this->read('/.*[>|#]/', true));
        $this->write("\n");
        $this->_prompt = trim($this->read('/.*[>|#]/', true));
        $length = mb_strlen($this->_prompt);
        if ($length > 0 && mb_substr($this->_motd, -$length) == $this->_prompt) {
            $this->_motd = mb_substr($this->_motd, 0, -$length);
        }
        return true;
    }

    /**
     * Execute an IOS command and return its output.
     *
     * If `autoconnect` is enabled and the session is not yet open, this will
     * call `connect()` first. The trailing prompt is stripped from the
     * returned data.
     *
     * @param string $cmd The IOS command to execute. A trailing newline is
     *                    appended if not present.
     *
     * @return string Command output with the trailing prompt removed.
     */
    public function exec($cmd)
    {
        if ($this->autoconnect === true && $this->connected === false) {
            $this->connect();
        }
        if (mb_substr($cmd, -1) != "\n") {
            $cmd .= "\n";
        }
        $this->_data = false;
        fwrite($this->_stream, $cmd);
        $this->_response = trim($this->read($this->_prompt));
        $length = mb_strlen($this->_prompt);
        if ($length > 0 && mb_substr($this->_response, -$length) == $this->_prompt) {
            $this->_response = mb_substr($this->_response, 0, -$length);
        }
        $this->_data = $this->_response;
        return $this->_data;
    }

    /**
     * Return the raw response buffer from the most recent `read()`.
     *
     * @return string
     */
    public function get_response()
    {
        return $this->_response;
    }

    /**
     * Close the interactive shell stream and mark the session disconnected.
     *
     * Note: the PECL `ssh2` extension does not expose an explicit disconnect
     * function; closing the stream and clearing the handles is sufficient for
     * the underlying connection to be released.
     *
     * @return void
     */
    public function disconnect()
    {
        if (is_resource($this->_stream)) {
            @fclose($this->_stream);
        }
        $this->_stream = null;
        $this->_ssh = null;
        $this->connected = false;
    }

    /**
     * Auto-disconnect on object destruction.
     */
    public function __destruct()
    {
        if ($this->connected === true) {
            $this->disconnect();
        }
    }

    /**
     * Run `show run int <int>` and return the body of the running config for
     * the given interface, with the surrounding header / trailer stripped.
     *
     * @param string $int Interface name (e.g. `Gi1/0/1`).
     *
     * @return string Cleaned interface configuration.
     */
    public function show_int_config($int)
    {
        $this->exec('show run int '.$int);
        return $this->show_int_config_parser();
    }

    /**
     * Strip the header (5 lines) and trailer (2 lines) from the buffered
     * `show run int` response and rejoin the rest with `\n`.
     *
     * @return string Cleaned interface configuration body.
     */
    public function show_int_config_parser()
    {
        $this->_data = explode("\r\n", $this->_data);
        for ($i = 0; $i < 5; $i++) {
            array_shift($this->_data);
        }
        for ($i = 0; $i < 2; $i++) {
            array_pop($this->_data);
        }
        $this->_data = implode("\n", $this->_data);
        return $this->_data;
    }

    /**
     * Run `show int status` and return one entry per port.
     *
     * Each entry contains: `interface`, `description`, `status`, `vlan`,
     * `duplex`, `speed`, `type`.
     *
     * @return array<int, array<string, string>>
     */
    public function show_int_status()
    {
        $result = [];
        $this->exec('show int status');
        $this->_data = explode("\r\n", $this->_data);
        for ($i = 0; $i < 2; $i++) {
            array_shift($this->_data);
        }
        array_pop($this->_data);
        $pos = mb_strpos($this->_data[0], 'Status');
        foreach ($this->_data as $entry) {
            $temp = trim($entry);
            if (mb_strlen($temp) > 1 && $temp[2] != 'r' && $temp[0] != '-') {
                $entry = [];
                $entry['interface'] = mb_substr($temp, 0, mb_strpos($temp, ' '));
                $entry['description'] = trim(mb_substr($temp, mb_strpos($temp, ' ') + 1, $pos - mb_strlen($entry['interface']) - 1));
                $temp = mb_substr($temp, $pos);
                /** @noinspection PrintfScanfArgumentsInspection */
                $temp = sscanf($temp, '%s %s %s %s %s %s');
                $entry['status'] = $temp[0];
                $entry['vlan'] = $temp[1];
                $entry['duplex'] = $temp[2];
                $entry['speed'] = $temp[3];
                $entry['type'] = trim($temp[4].' '.$temp[5]);
                $result[] = $entry;
            } // if
        } // foreach
        $this->_data = $result;
        return $this->_data;
    }

    /**
     * Run `sh log | inc %` and return parsed log entries.
     *
     * Each entry contains: `timestamp`, `type`, `message`. Requires the
     * connected user to be in privileged-exec mode (i.e. the prompt ends with
     * `#`); throws `RuntimeException` otherwise.
     *
     * @return array<int, array<string, string>>
     *
     * @throws \RuntimeException If the session is not in enable mode.
     */
    public function show_log()
    {
        if (mb_strpos($this->_prompt, '#') === false) {
            throw new \RuntimeException('User must be enabled to use show_log()');
        }
        $result = [];
        $this->exec('sh log | inc %');
        $this->_data = explode("\r\n", $this->_data);
        array_shift($this->_data);
        array_pop($this->_data);
        foreach ($this->_data as $entry) {
            $temp = trim($entry);
            $entry = [];
            $entry['timestamp'] = mb_substr($temp, 0, mb_strpos($temp, '%') - 2);
            if ($entry['timestamp'][0] == '.' || $entry['timestamp'][0] == '*') {
                $entry['timestamp'] = mb_substr($entry['timestamp'], 1);
            }
            $temp = mb_substr($temp, mb_strpos($temp, '%') + 1);
            $entry['type'] = mb_substr($temp, 0, mb_strpos($temp, ':'));
            $temp = mb_substr($temp, mb_strpos($temp, ':') + 2);
            $entry['message'] = $temp;
            $result[] = $entry;
        } // foreach
        $this->_data = $result;
        return $this->_data;
    }

    /**
     * Run `show int <int>` and parse the multi-line interface detail output
     * into a single associative array of keys.
     *
     * Returned keys (a subset, depending on the interface) include:
     * `interface`, `status`, `description`, `mtu`, `bandwidth`, `dly`,
     * `duplex`, `speed`, `type`, `in_rate`, `in_packet_rate`, `out_rate`,
     * `out_packet_rate`, `in_packet`, `in`, `no_buffer`, `broadcast`, `runt`,
     * `giant`, `throttle`, `in_error`, `crc`, `frame`, `overrun`, `ignored`,
     * `watchdog`, `multicast`, `pause_in`, `in_dribble`, `out_packet`, `out`,
     * `underrun`, `out_error`, `collision`, `reset`, `babble`,
     * `late_collision`, `deferred`, `lost_carrier`, `no_carrier`, `pause_out`,
     * `out_buffer_fail`, `out_buffer_swap`.
     *
     * @param string $int Interface name (e.g. `Gi1/0/1`).
     *
     * @return array<string, mixed>
     */
    public function show_int($int)
    {
        $result = [];
        $this->exec('show int '.$int);
        $this->_data = explode("\r\n", $this->_data);
        foreach ($this->_data as $entry) {
            $entry = trim($entry);
            if (mb_strpos($entry, 'line protocol') !== false) {
                $result['interface'] = mb_substr($entry, 0, mb_strpos($entry, ' '));
                if (mb_strpos($entry, 'administratively') !== false) {
                    $result['status'] = 'disabled';
                } elseif (mb_substr($entry, mb_strpos($entry, 'line protocol') + 17, 2) == 'up') {
                    $result['status'] = 'connected';
                } else {
                    $result['status'] = 'notconnect';
                } // if .. else
            } elseif (mb_strpos($entry, 'Description: ') !== false) {
                $entry = explode(':', $entry);
                $result['description'] = trim($entry[1]);
            } elseif (mb_strpos($entry, 'MTU') !== false) {
                $entry = explode(',', $entry);
                $entry[0] = trim($entry[0]);
                $entry[0] = explode(' ', $entry[0]);
                $result['mtu'] = $entry[0][1];
                $entry[1] = trim($entry[1]);
                $entry[1] = explode(' ', $entry[1]);
                $result['bandwidth'] = $entry[1][1];
                $entry[2] = trim($entry[2]);
                $entry[2] = explode(' ', $entry[2]);
                $result['dly'] = $entry[2][1];
            } elseif (mb_strpos($entry, 'duplex') !== false) {
                $entry = explode(',', $entry);
                $entry[0] = trim($entry[0]);
                $entry[0] = explode(' ', $entry[0]);
                $entry[0][0] = explode('-', $entry[0][0]);
                $result['duplex'] = strtolower($entry[0][0][0]);
                $entry[1] = trim($entry[1]);
                if (mb_strpos($entry[1], 'Auto') !== false) {
                    $result['speed'] = 'auto';
                } else {
                    $result['speed'] = (int) $entry[1];
                } // if .. else
                $entry[2] = rtrim($entry[2]);
                $result['type'] = mb_substr($entry[2], mb_strrpos($entry[2], ' ') + 1);
            } elseif (mb_strpos($entry, 'input rate') !== false) {
                $entry = explode(',', $entry);
                $result['in_rate'] = mb_substr($entry[0], mb_strpos($entry[0], 'rate') + 5, mb_strrpos($entry[0], ' ') - (mb_strpos($entry[0], 'rate') + 5));
                $entry = trim($entry[1]);
                $entry = explode(' ', $entry);
                $result['in_packet_rate'] = $entry[0];
            } elseif (mb_strpos($entry, 'output rate') !== false) {
                $entry = explode(',', $entry);
                $result['out_rate'] = mb_substr($entry[0], mb_strpos($entry[0], 'rate') + 5, mb_strrpos($entry[0], ' ') - (mb_strpos($entry[0], 'rate') + 5));
                $entry = trim($entry[1]);
                $entry = explode(' ', $entry);
                $result['out_packet_rate'] = $entry[0];
            } elseif (mb_strpos($entry, 'packets input') !== false) {
                $entry = explode(',', $entry);
                $entry[0] = trim($entry[0]);
                $entry[0] = explode(' ', $entry[0]);
                $result['in_packet'] = $entry[0][0];
                $entry[1] = trim($entry[1]);
                $entry[1] = explode(' ', $entry[1]);
                $result['in'] = $entry[1][0];
                if (count($entry) > 2) {
                    $entry[2] = trim($entry[2]);
                    $entry[2] = explode(' ', $entry[2]);
                    $result['no_buffer'] = $entry[2][0];
                } // if
            } elseif (mb_strpos($entry, 'Received') !== false) {
                $entry = explode(',', $entry);
                $entry[0] = trim($entry[0]);
                $entry[0] = explode(' ', $entry[0]);
                $result['broadcast'] = $entry[0][1];
                if (count($entry) > 1) {
                    $entry[1] = trim($entry[1]);
                    $entry[1] = explode(' ', $entry[1]);
                    $result['runt'] = $entry[1][0];
                    $entry[2] = trim($entry[2]);
                    $entry[2] = explode(' ', $entry[2]);
                    $result['giant'] = $entry[2][0];
                    $entry[3] = trim($entry[3]);
                    $entry[3] = explode(' ', $entry[3]);
                    $result['throttle'] = $entry[3][0];
                } // if
            } elseif (mb_strpos($entry, 'CRC') !== false) {
                $entry = explode(',', $entry);
                $entry[0] = trim($entry[0]);
                $entry[0] = explode(' ', $entry[0]);
                $result['in_error'] = $entry[0][0];
                $entry[1] = trim($entry[1]);
                $entry[1] = explode(' ', $entry[1]);
                $result['crc'] = $entry[1][0];
                $entry[2] = trim($entry[2]);
                $entry[2] = explode(' ', $entry[2]);
                $result['frame'] = $entry[2][0];
                $entry[3] = trim($entry[3]);
                $entry[3] = explode(' ', $entry[3]);
                $result['overrun'] = $entry[3][0];
                $entry[4] = trim($entry[4]);
                $entry[4] = explode(' ', $entry[4]);
                $result['ignored'] = $entry[4][0];
            } elseif (mb_strpos($entry, 'watchdog') !== false) {
                $entry = explode(',', $entry);
                $entry[0] = trim($entry[0]);
                $entry[0] = explode(' ', $entry[0]);
                $result['watchdog'] = $entry[0][0];
                $entry[1] = trim($entry[1]);
                $entry[1] = explode(' ', $entry[1]);
                $result['multicast'] = $entry[1][0];
                if (count($entry) > 2) {
                    $entry[2] = trim($entry[2]);
                    $entry[2] = explode(' ', $entry[2]);
                    $result['pause_in'] = $entry[2][0];
                } // if
            } elseif (mb_strpos($entry, 'dribble') !== false) {
                $entry = trim($entry);
                $entry = explode(' ', $entry);
                $result['in_dribble'] = $entry[0];
            } elseif (mb_strpos($entry, 'packets output') !== false) {
                $entry = explode(',', $entry);
                $entry[0] = trim($entry[0]);
                $entry[0] = explode(' ', $entry[0]);
                $result['out_packet'] = $entry[0][0];
                $entry[1] = trim($entry[1]);
                $entry[1] = explode(' ', $entry[1]);
                $result['out'] = $entry[1][0];
                $entry[2] = trim($entry[2]);
                $entry[2] = explode(' ', $entry[2]);
                $result['underrun'] = $entry[2][0];
            } elseif (mb_strpos($entry, 'output errors') !== false) {
                $entry = explode(',', $entry);
                $entry[0] = trim($entry[0]);
                $entry[0] = explode(' ', $entry[0]);
                $result['out_error'] = $entry[0][0];
                if (count($entry) > 2) {
                    $entry[1] = trim($entry[1]);
                    $entry[1] = explode(' ', $entry[1]);
                    $result['collision'] = $entry[1][0];
                    $entry[2] = trim($entry[2]);
                    $entry[2] = explode(' ', $entry[2]);
                    $result['reset'] = $entry[2][0];
                } else {
                    $entry[1] = trim($entry[1]);
                    $entry[1] = explode(' ', $entry[1]);
                    $result['reset'] = $entry[1][0];
                } // if .. else
            } elseif (mb_strpos($entry, 'babbles') !== false) {
                $entry = explode(',', $entry);
                $entry[0] = trim($entry[0]);
                $entry[0] = explode(' ', $entry[0]);
                $result['babble'] = $entry[0][0];
                $entry[1] = trim($entry[1]);
                $entry[1] = explode(' ', $entry[1]);
                $result['late_collision'] = $entry[1][0];
                $entry[2] = trim($entry[2]);
                $entry[2] = explode(' ', $entry[2]);
                $result['deferred'] = $entry[2][0];
            } elseif (mb_strpos($entry, 'lost carrier') !== false) {
                $entry = explode(',', $entry);
                $entry[0] = trim($entry[0]);
                $entry[0] = explode(' ', $entry[0]);
                $result['lost_carrier'] = $entry[0][0];
                $entry[1] = trim($entry[1]);
                $entry[1] = explode(' ', $entry[1]);
                $result['no_carrier'] = $entry[1][0];
                if (count($entry) > 2) {
                    $entry[2] = trim($entry[2]);
                    $entry[2] = explode(' ', $entry[2]);
                    $result['pause_out'] = $entry[2][0];
                } // if
            } elseif (mb_strpos($entry, 'output buffer failures') !== false) {
                $entry = explode(',', $entry);
                $entry[0] = trim($entry[0]);
                $entry[0] = explode(' ', $entry[0]);
                $result['out_buffer_fail'] = $entry[0][0];
                $entry[1] = trim($entry[1]);
                $entry[1] = explode(' ', $entry[1]);
                $result['out_buffer_swap'] = $entry[1][0];
            } // if .. elseif
        } // foreach
        $this->_data = $result;
        return $this->_data;
    }

    /**
     * Return a list of trunk port names.
     *
     * Internally calls `show interface status | include trunk` and pulls the
     * first whitespace-delimited token off each result row.
     *
     * @return array<int, string>
     */
    public function trunk_ports()
    {
        $result = [];
        $this->exec('show interface status | include trunk');
        $this->_data = explode("\r\n", $this->_data);
        array_shift($this->_data);
        array_pop($this->_data);
        if (count($this->_data) > 0) {
            foreach ($this->_data as $interface) {
                $interface = explode(' ', $interface);
                $result[] = $interface[0];
            } // foreach
        } // if
        $this->_data = $result;
        return $this->_data;
    }

    /**
     * Return the list of VLAN ids known to the spanning-tree summary.
     *
     * Internally calls `show spanning-tree summary | include ^VLAN` and parses
     * out the trailing numeric VLAN id.
     *
     * @return array<int, int>
     */
    public function vlans()
    {
        $result = [];
        $this->exec('show spanning-tree summary | include ^VLAN');
        $this->_data = explode("\r\n", $this->_data);
        array_shift($this->_data);
        array_pop($this->_data);
        if (count($this->_data) > 0) {
            foreach ($this->_data as $vlan) {
                $vlan = explode(' ', $vlan);
                $vlan = mb_substr($vlan[0], 4);
                $result[] = (int) $vlan;
            } // foreach
        } // if
        $this->_data = $result;
        return $this->_data;
    }

    /**
     * Return a list of err-disabled interfaces.
     *
     * Each entry contains: `interface`, `description`, `status`, `reason`.
     *
     * @return array<int, array<string, string>>
     */
    public function errdisabled()
    {
        $result = [];
        $this->exec('show int status err');
        $this->_data = explode("\r\n", $this->_data);
        for ($i = 0; $i < 2; $i++) {
            array_shift($this->_data);
        }
        array_pop($this->_data);
        $pos = mb_strpos($this->_data[0], 'Status');
        foreach ($this->_data as $entry) {
            $temp = trim($entry);
            if (mb_strlen($temp) > 1 && $temp[2] != 'r') {
                $entry = [];
                $entry['interface'] = mb_substr($temp, 0, mb_strpos($temp, ' '));
                $entry['description'] = trim(mb_substr($temp, mb_strpos($temp, ' ') + 1, $pos - mb_strlen($entry['interface']) - 1));
                $temp = mb_substr($temp, $pos);
                /** @noinspection PrintfScanfArgumentsInspection */
                $temp = sscanf($temp, '%s %s');
                $entry['status'] = $temp[0];
                $entry['reason'] = $temp[1];
                $result[] = $entry;
            } // if
        } // foreach
        $this->_data = $result;
        return $this->_data;
    }

    /**
     * Return DHCP snooping bindings (only those marked `dhcp-snooping`).
     *
     * Each entry contains: `mac_address` (lowercase, no colons), `ip_address`,
     * `lease`, `vlan`, `interface`.
     *
     * @return array<int, array<string, string>>
     */
    public function dhcpsnoop_bindings()
    {
        $result = [];
        $this->exec('sh ip dhcp snoop binding | inc dhcp-snooping');
        $this->_data = explode("\r\n", $this->_data);
        array_shift($this->_data);
        array_pop($this->_data);
        foreach ($this->_data as $entry) {
            /** @noinspection PrintfScanfArgumentsInspection */
            $temp = sscanf($entry, '%s %s %s %s %s %s');
            $entry = [];
            $entry['mac_address'] = $temp[0];
            $entry['mac_address'] = strtolower(str_replace(':', '', $entry['mac_address']));
            $entry['ip_address'] = $temp[1];
            $entry['lease'] = $temp[2];
            $entry['vlan'] = $temp[4];
            $entry['interface'] = $temp[5];
            if ($temp[3] == 'dhcp-snooping') {
                $result[] = $entry;
            }
        }
        $this->_data = $result;
        return $this->_data;
    }

    /**
     * Return the MAC address table, excluding entries on trunk ports and the
     * CPU.
     *
     * Each entry contains: `mac_address`, `interface`.
     *
     * @return array<int, array<string, string>>
     */
    public function mac_address_table()
    {
        $result = [];
        $omit = $this->trunk_ports();
        $this->exec('show mac address-table | exclude CPU');
        $this->_data = str_replace('          ', '', $this->_data);
        $this->_data = explode("\r\n", $this->_data);
        for ($i = 0; $i < 6; $i++) {
            array_shift($this->_data);
        }
        for ($i = 0; $i < 2; $i++) {
            array_pop($this->_data);
        }
        foreach ($this->_data as $entry) {
            /** @noinspection PrintfScanfArgumentsInspection */
            $temp = sscanf($entry, '%s %s %s %s');
            $entry = [];
            $entry['mac_address'] = $temp[1];
            $entry['interface'] = $temp[3];
            if (in_array($entry['interface'], $omit) == false) {
                $result[] = $entry;
            } // if
        } // foreach
        $this->_data = $result;
        return $this->_data;
    }

    /**
     * Return the IPv4 ARP table, excluding `Incomplete` entries.
     *
     * Each entry contains: `ip`, `mac_address`, `age` (`'0'` if `-`),
     * `interface`.
     *
     * @return array<int, array<string, string>>
     */
    public function arp_table()
    {
        $result = [];
        $this->exec('show arp | exc Incomplete');
        $this->_data = explode("\r\n", $this->_data);
        for ($i = 0; $i < 2; $i++) {
            array_shift($this->_data);
        }
        array_pop($this->_data);
        foreach ($this->_data as $entry) {
            /** @noinspection PrintfScanfArgumentsInspection */
            $temp = sscanf($entry, '%s %s %s %s %s %s');
            $entry = [];
            $entry['ip'] = $temp[1];
            $entry['mac_address'] = $temp[3];
            if ($temp[2] == '-') {
                $temp[2] = '0';
            }
            $entry['age'] = $temp[2];
            $entry['interface'] = $temp[5];
            if ($entry['ip'] != 'Address' && $entry['mac_address'] != 'Incomplete') {
                $result[] = $entry;
            } // if
        } // foreach
        $this->_data = $result;
        return $this->_data;
    }

    /**
     * Return the IPv6 neighbor table, excluding INCMP entries.
     *
     * Each entry contains: `ipv6`, `mac_address`, `age`, `interface`.
     *
     * @return array<int, array<string, string>>
     */
    public function ipv6_neighbor_table()
    {
        $result = [];
        $this->exec('show ipv6 neighbors | exc INCMP');
        $this->_data = explode("\r\n", $this->_data);
        for ($i = 0; $i < 2; $i++) {
            array_shift($this->_data);
        }
        for ($i = 0; $i < 2; $i++) {
            array_pop($this->_data);
        }
        foreach ($this->_data as $entry) {
            /** @noinspection PrintfScanfArgumentsInspection */
            $temp = sscanf($entry, '%s %s %s %s %s');
            $entry = [];
            $entry['ipv6'] = $temp[0];
            $entry['mac_address'] = $temp[2];
            $entry['age'] = $temp[1];
            $entry['interface'] = $temp[4];
            $result[] = $entry;
        } // foreach
        $this->_data = $result;
        return $this->_data;
    }

    /**
     * Return the list of detected IPv6 routers from `show ipv6 routers`.
     *
     * Each entry contains: `router`, `interface`, `prefix`.
     *
     * @return array<int, array<string, string>>
     */
    public function ipv6_routers()
    {
        $result = [];
        $this->exec('show ipv6 routers');
        $this->_data = explode("\r\n", $this->_data);
        array_shift($this->_data);
        array_pop($this->_data);
        for ($i = 0, $iMax = count($this->_data); $i < $iMax; $i++) {
            $entry = trim($this->_data[$i]);
            if (mb_substr($entry, 0, 7) == 'Router ') {
                /** @noinspection PrintfScanfArgumentsInspection */
                $temp = sscanf($entry, '%s %s %s %s');
                $entry = [];
                $entry['router'] = $temp[1];
                $entry['interface'] = str_replace(',', '', $temp[3]);
                /** @noinspection PrintfScanfArgumentsInspection */
                $temp = sscanf(trim($this->_data[$i + 4]), '%s %s %s');
                $entry['prefix'] = $temp[1];
                $i += 5;
                $result[] = $entry;
            } // if
        } // for
        $this->_data = $result;
        return $this->_data;
    }

    /**
     * Apply a multi-line configuration block to the device.
     *
     * Sends `configure terminal`, then each line of `$config`, then `end`.
     * Requires the connected user to be in privileged-exec mode (i.e. the
     * prompt ends with `#`).
     *
     * USE AT OWN RISK: this issues live `config t` commands against the
     * device.
     *
     * @param string $config Newline-separated configuration commands.
     *
     * @return bool `true` if the line count of the response matches the number
     *              of commands sent, `false` otherwise.
     *
     * @throws \RuntimeException If the session is not in enable mode.
     */
    public function configure($config)
    {
        if (mb_strpos($this->_prompt, '#') === false) {
            throw new \RuntimeException('User must be enabled to use configure()');
        }
        $lines = explode("\n", $config);
        $this->write("config t\n");
        $config_prompt = $this->read('/.*[>|#]/', true);
        $config_prompt = str_replace("\r\n", '', trim($config_prompt));
        if (mb_strpos($config_prompt, 'config)#') !== false) {
            foreach ($lines as $c) {
                $this->write($c."\n");
            }
            $this->write("end\n");
        }
        $result = $this->read($this->_prompt);
        $result = explode("\r\n", $result);
        $this->_data = $result;
        return count($lines) == (count($result) - 2);
    }

    /**
     * Save the running config to startup config (`write memory`).
     *
     * @return bool `true` if the device responded with `[OK]`.
     */
    public function write_config()
    {
        $this->exec('write');
        return mb_strpos($this->_data, '[OK]') !== false;
    }
}
