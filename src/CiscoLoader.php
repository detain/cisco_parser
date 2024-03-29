<?php

namespace Detain\CiscoParser;

/**
 * cisco
 *
 * @access public
 */
class CiscoLoader
{
    /**
     * @var bool
     */
    public $autoconnect = true; // Sets whether or not exec() will automatically connect() if needed
    /**
     * @var int
     */
    public $min_timeout = 300; // sets a minimum timeout, 0 or false to disable
    /**
     * @var bool
     */
    public $connected = false; // True/False Whether or not you are currently connected
    /**
     * @var
     */
    private $_hostname; // SSH Connection Hostname
    /**
     * @var
     */
    private $_username; // SSH Connection Username
    /**
     * @var
     */
    private $_password; // SSH Connection Password
    /**
     * @var int
     */
    private $_port; // SSH Connection Port
    /**
     * @var
     */
    private $_motd; // MOTD / Message of the day / Banner
    /**
     * @var
     */
    private $_prompt; // Prompt
    /**
     * @var
     */
    private $_ssh; // SSH Connection Resource
    /**
     * @var
     */
    private $_stream; // Data Stream
    /**
     * @var
     */
    private $_data; // Formatted Response
    /**
     * @var
     */
    private $_response; // Raw Response

    /**
     * @param     $hostname
     * @param     $username
     * @param     $password
     * @param int $port
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
     * @param     string $string
     * @param int $index
     * @return string
     */
    public function _string_shift(&$string, $index = 1)
    {
        $substr = mb_substr($string, 0, $index);
        $string = mb_substr($string, $index);
        return $substr;
    }

    /**
     * Returns the output of an interactive shell
     * Gathers output from a shell until $pattern is met, Pattern is a regular string
     * unless $regex = true, then it matches it with preg_match as a regular expression.
     *
     * @param $pattern string the string or the pattern to match
     * @param $regex bool Whether or not we are trying to match a regex pattern or just a simple string
     * @return String
     * @access public
     */
    public function read($pattern = '', $regex = false)
    {
        //usleep(1000);
        $this->_response = '';
        $match = $pattern;
        $i = 0;
        while (!feof($this->_stream)) {
            if ($regex) {
                preg_match($pattern, $this->_response, $matches);
                //echo 'M:'.print_r($matches, TRUE).'<br>';
                $match = $matches[0] ?? [];
            }
            $pos = !empty($match) ? mb_strpos($this->_response, $match) : false;
            //echo ++$i . "POS:".var_export($pos, TRUE).'<br>';
            if ($pos !== false) {
                //echo "$match Matching $pattern @ $pos <br>";
                return $this->_string_shift($this->_response, $pos + mb_strlen($match));
            }
            usleep(1000);
            $response = fgets($this->_stream);
            //echo "R$i:$response<br>";
            if (is_bool($response)) {
                //echo "Return B $response::".$this->_response."<br>";
                //					return $response ? $this->_string_shift($this->_response, mb_strlen($this->_response)) : false;
            }
            $this->_response .= $response;
        }
        echo 'FEOF !!!!<br>';
        return $this->_response;
    }

    /**
     * @param string $cmd
     */
    public function write($cmd)
    {
        fwrite($this->_stream, $cmd);
    }

    /**
     * @return bool
     */
    public function connect()
    {
        //echo "Connecting to " . $this->_hostname . "<br>";
        $this->_ssh = ssh2_connect($this->_hostname, $this->_port);
        if ($this->_ssh === false) {
            return false;
        }
        ssh2_auth_password($this->_ssh, $this->_username, $this->_password);
        $this->_stream = ssh2_shell($this->_ssh);
        $this->connected = true;
        $this->parse_motd_and_prompt();
        return true;
    }

    /**
     *
     */
    public function parse_motd_and_prompt()
    {
        $this->_motd = trim($this->read('/.*[>|#]/', true));
        $this->write("\n");
        $this->_prompt = trim($this->read('/.*[>|#]/', true));
        $length = mb_strlen($this->_prompt);
        if (mb_substr($this->_motd, -$length) == $this->_prompt) {
            $this->_motd = mb_substr($this->_motd, 0, -$length);
        }
        //echo "MOTD:".$this->_motd."<br>";
        //echo "Prompt:".$this->_prompt.'<br>';
        return true;
        sleep(1);
        $this->_motd = '';
        while ($this->_response = fgets($this->_stream)) {
            $this->_motd .= $this->_response;
        }
        $this->_motd = trim($this->_motd);
        fwrite($this->_stream, "\n");
        $this->_response = stream_get_contents($this->_stream);
        //stream_set_blocking($this->_stream, FALSE);
        $this->_prompt = trim($this->_response);
        /*			sleep (1);
        while ($this->_response = fgets($this->_stream))
            $this->_prompt .= $this->_response;
        $this->_prompt = trim($this->_prompt);*/
        echo 'MOTD:'.$this->_motd.'<br>';
        echo 'Prompt:'.$this->_prompt.'<br>';
        $length = mb_strlen($this->_prompt);
        if (mb_substr($this->_motd, -$length) == $this->_prompt) {
            //echo "Found Prompt<br>";
            $this->_motd = mb_substr($this->_motd, 0, -$length);
        }
        //echo "MOTD:".$this->_motd . "<br>";
        echo 'Prompt:'.$this->_prompt.'<br>';
        /*			$this->_stream = ssh2_exec($this->_ssh, "#");
        stream_set_blocking($this->_stream, TRUE);
        $this->_response = stream_get_contents($this->_stream);
        $this->_data = $this->_response;
        stream_set_blocking($this->_stream, FALSE);
        var_dump($this->_response);
        */
    }

    /**
     * @param string $cmd
     * @return string
     */
    public function exec($cmd)
    {
        if ($this->autoconnect === true && $this->connected === false) {
            $this->connect();
        }
        if (mb_substr($cmd, -1) != "\n") {
            //error_log("Adding NEWLINE Character To SSH2 Command $cmd", __LINE__, __FILE__);
            $cmd .= "\n";
        }
        $this->_data = false;
        fwrite($this->_stream, $cmd);
        $this->_response = trim($this->read($this->_prompt));
        $length = mb_strlen($this->_prompt);
        if (mb_substr($this->_response, -$length) == $this->_prompt) {
            //echo "Found Prompt<br>";
            $this->_response = mb_substr($this->_response, 0, -$length);
        }
        $this->_data = $this->_response;
        //stream_set_blocking($this->_stream, FALSE);
        //if (mb_strpos($this->_data, '% Invalid input detected') !== FALSE) $this->_data = FALSE;
        return $this->_data;
    }

    /**
     * @return string
     */
    public function get_response()
    {
        return $this->_response;
    }

    /**
     *
     */
    public function disconnect()
    {
        //ssh2_exec($this->_ssh, 'quit');
        $this->connected = false;
    }

    /**
     *
     */
    public function __destruct()
    {
        if ($this->connected === true) {
            $this->disconnect();
        }
    }

    /**
     * @param $int
     * @return string
     */
    public function show_int_config($int)
    {
        // Enabled Only
        //if (mb_strpos($this->_prompt, '#') === FALSE)
        //	die('Error: User must be enabled to use show_int_config()'.PHP_EOL);
        $this->exec('show run int '.$int);
        return $this->show_int_config_parser();
    }

    /**
     * @return string
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
     * @return array
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
     * @return array
     */
    public function show_log()
    {
        // Enabled Only
        if (mb_strpos($this->_prompt, '#') === false) {
            die('Error: User must be enabled to use show_log()'.PHP_EOL);
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
     * @param $int
     * @return array
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
     * @return array
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
     * @return array
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
     * @return array
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
     * @return array
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
     * @return array
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
     * @return array
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
     * @return array
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
     * @return array
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
     * @param $config
     * @return null|boolean
     */
    public function configure($config)
    {
        // USE AT OWN RISK: This function will apply configuration statements to a device.
        // Enabled Only
        if (mb_strpos($this->_prompt, '#') === false) {
            die('Error: User must be enabled to use configure()'.PHP_EOL);
        }
        $this->_data = explode("\n", $config);
        $this->_ssh->write("config t\n");
        $config_prompt = $this->_ssh->read('/.*[>|#]/', NET_SSH2_READ_REGEX);
        $config_prompt = str_replace("\r\n", '', trim($config_prompt));
        if (mb_strpos($config_prompt, 'config)#') !== false) {
            foreach ($this->_data as $c) {
                $this->_ssh->write($c."\n");
            }
            $this->_ssh->write("end\n");
        }
        $result = $this->_ssh->read($this->_prompt);
        $result = explode("\r\n", $result);
        if (count($this->_data) == (count($result) - 2)) {
            return true;
        } else {
            die('Error: Switch rejected configuration: '.PHP_EOL.$config."\n");
        }
    }

    /**
     * @return bool
     */
    public function write_config()
    {
        $this->exec('write');
        if (mb_strpos($this->_data, '[OK]') !== false) {
            return true;
        } else {
            return false;
        }
    }
}
