<?php
	/**
	 * CISCO Switch Interface Class
	 *
	 * Basically this is a wrapper to do and parse things from IOS.
	 * 
	 * Links I might find helpful in improving this class:
	 * 	http://www.networker.gr/index.php/2011/03/parsing-the-cisco-ios-configuration/
	 * 	http://technologyordie.com/parsing-cisco-show-command-output
	 * 	http://packetpushers.net/rocking-your-show-commands-with-regex/  
	 * 
	 * Based on code from http://www.soucy.org/project/cisco/ 
	 * 
	 * Last Changed: $LastChangedDate$
	 * @author $Author$
	 * @version $Revision$
	 * @copyright 2014
	 * @package MyAdmin
	 * @category Network
	 */

	/**
	 * cisco
	 * 
	 * @access public
	 */

	class cisco
	{
		public $autoconnect = true;		// Sets whether or not exec() will automatically connect() if needed
		public $min_timeout = 300;		// sets a minimum timeout, 0 or false to disable
		public $connected = false;		// True/False Wether or not you are currently connected
		
		private $_hostname;				// SSH Connection Hostname
		private $_username;				// SSH Connection Username
		private $_password;				// SSH Connection Password
		private $_port;					// SSH Connection Port 
		private $_motd;					// MOTD / Message of the day / Banner
		private $_prompt;				// Prompt
		private $_ssh;					// SSH Connection Resource
		private $_stream;				// Data Stream
		private $_data;					// Formatted Response
		private $_response;				// Raw Response
		
		public function __construct($hostname, $username, $password, $port = 22)
		{
			$this->_hostname = $hostname;
			$this->_username = $username;
			$this->_password = $password;
			$this->_port = $port;
			if ($this->min_timeout && ini_get('default_socket_timeout') < $this->min_timeout)
				ini_set('default_socket_timeout', $this->min_timeout);
		}
		
		public function _string_shift(&$string, $index = 1)
		{
			$substr = substr($string, 0, $index);
			$string = substr($string, $index);
			return $substr;
		}

		/**
		 * Returns the output of an interactive shell
		 *
		 * Gathers output from a shell until $pattern is met, Pattern is a regular string 
		 * unless $regex = true, then it matchs it with preg_match as a regular expression.
		 *
		 * @param $pattern string the string or the pattern to match
		 * @param $regex bool Wether or not we are tryign to match a regex pattern or just a simple string
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
					//echo 'M:'.print_r($matches, true) . '<br>';
					$match = isset($matches[0]) ? $matches[0] : array();
				}
				$pos = !empty($match) ? strpos($this->_response, $match) : false;
				//echo ++$i . "POS:".var_export($pos, true).'<br>';
				if ($pos !== false)
				{
					//echo "$match Matching $pattern @ $pos <br>";
					return $this->_string_shift($this->_response, $pos + strlen($match));
				}
				usleep(1000);
				$response = fgets($this->_stream);
				//echo "R$i:$response<br>";
				if (is_bool($response))
				{
					//echo "Return B $response::".$this->_response."<br>";
//					return $response ? $this->_string_shift($this->_response, strlen($this->_response)) : false;
				}
				$this->_response .= $response;
			}
			
			echo "FEOF !!!!<br>";
			return $this->_response;
		}
		
		public function write($cmd)
		{
			fwrite($this->_stream, $cmd);
		}
		
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
		
		public function parse_motd_and_prompt()
		{
			$this->_motd = trim($this->read('/.*[>|#]/', true));
			$this->write("\n");
			$this->_prompt = trim($this->read('/.*[>|#]/', true));
			$length = strlen($this->_prompt);
			if (substr($this->_motd, -$length) == $this->_prompt)
				$this->_motd = substr($this->_motd, 0, -$length);
			//echo "MOTD:".$this->_motd."<br>";
			//echo "Prompt:".$this->_prompt.'<br>';
			return true;
			
			sleep(1);
			$this->_motd = '';
			while ($this->_response = fgets($this->_stream))
			{
				$this->_motd .= $this->_response;
			}
			$this->_motd = trim($this->_motd);
			fwrite($this->_stream, "\n");
			$this->_response = stream_get_contents($this->_stream);
			//stream_set_blocking($this->_stream, false);
			$this->_prompt = trim($this->_response);
/*			sleep (1);
			while ($this->_response = fgets($this->_stream))
			{
				$this->_prompt .= $this->_response;
			}
			$this->_prompt = trim($this->_prompt);*/
			echo "MOTD:".$this->_motd . "<br>";;
			echo "Prompt:".$this->_prompt . "<br>";
			$length = strlen($this->_prompt);
			if (substr($this->_motd, -$length) == $this->_prompt)
			{
				//echo "Found Prompt<br>";
				$this->_motd = substr($this->_motd, 0, -$length);
			}
			//echo "MOTD:".$this->_motd . "<br>";
			echo "Prompt:".$this->_prompt . "<br>";
/*			$this->_stream = ssh2_exec($this->_ssh, "#");
			stream_set_blocking($this->_stream, true);
			$this->_response = stream_get_contents($this->_stream);
			$this->_data = $this->_response;
			stream_set_blocking($this->_stream, false);
			var_dump($this->_response);
*/
		}
		
		public function exec($cmd)
		{
			if ($this->autoconnect === true && $this->connected === false)
				$this->connect();
			if (substr($cmd, -1) != "\n")
			{
				//error_log("Adding NEWLINE Character To SSH2 Command $cmd", __LINE__, __FILE__);
				$cmd .= "\n";				
			}
			$this->_data   = false;
			fwrite($this->_stream, $cmd);
			$this->_response = trim($this->read($this->_prompt));
			$length = strlen($this->_prompt);
			if (substr($this->_response, -$length) == $this->_prompt)
			{
				//echo "Found Prompt<br>";
				$this->_response = substr($this->_response, 0, -$length);
			}
			$this->_data = $this->_response;
			//stream_set_blocking($this->_stream, false);			
			//if (strpos($this->_data, '% Invalid input detected') !== false) $this->_data = false;			
			return $this->_data;
		}
		
		public function get_response()
		{
			return $this->_response;
		}
		
		public function disconnect()
		{
			//ssh2_exec($this->_ssh, 'quit');
			$this->connected = false;
		}
		
		public function __destruct()
		{
			if ($this->connected === true)
				$this->disconnect();
		}

		
		/**
		* parse_running_config_regex
		* 
* This is my first attempt at writing a cisco configuration parser.
* 
* These regular expressions took forever to come up with , and they're pretty bad-ass.
* Each regex stripts out 1 set of objects from the running cnfig, such as the interfaces
* and their options.
* 
* $file string Contents of a running config output that you want pasrsed
* $return array an array of parsed information from teh config.
		**/
		function parse_runnnig_config_regex($file)
		{
			$data = array();
			$debug = false;
			//$debug = true;   // uncomment to enable excessive debuggnig messages
			var_dump(preg_match_all('/^Building configuration...(?P<config_bytes>(\d)+)\s+bytes\s*$/', $file, $matches));
			$data['config_bytes'] = $matches['config_bytse'];
			//print_r($matches);

			preg_match('/^Current configuration\s*:\s*($|(?P<config_bytes>\d+) bytes)\n(!\n)*!(.*Last configuration change at (?P<config_changed_date>\d{2}:\d{2}:\d{2} \w{3} \w+ \w+ \d+ \d+)\s+by\s+(?P<config_changed_user>\w+)\W*\n!)*(\s+NVRAM config last updated at (?P<nvram_changed_date>\d{2}:\d{2}:\d{2} \w{3} \w+ \w+ \d+ \d+)\s+by\s+(?P<nvram_changed_user>\w+)\W*\n)*$/m', $file, $matches);
			$data['config_bytes'] = $matches['config_bytse'];
			$data['config_changed_date'] = $matches['config_changed_date'];
			$data['config_changed_user'] = $matches['config_changed_user'];
			$data['nvram_changed_date'] = $matches['nvram_changed_date'];
			$data['nvram_changed_user'] = $matches['nvram_changed_user'];

			preg_match('/^hostname (?P<hostname>[^\n]+)$/', $file, $matches);
			$data['hostname'] = $matches['hostname'];
			if ($debug === true) print_r($matches);
			
			preg_match('/^version (?P<option>[^\n]+)$/', $file, $matches);
			$data['version'] = $matches['option'];
			if ($debug === true) print_r($matches);
			
			preg_match('/^banner motd \^CC(?P<motd_data>(.*\n)+)\^C/m', $file, $matches);
			$data['motd'] = $matches['motd_data'];
			if ($debug === true) print_r($matches);
			
			preg_match_all('/^vlan (?P<option_tag>.*)\n(?P<option_data>( [^\n]+\n)+)\S/m', $file, $matches);
			$data['vlans'] = array();
			foreach ($matches['option_tag'] as $idx => $tag)
				$data['vlans'][$tag] = $matches['option_data'][$idx];
			if ($debug === true) print_r($matches);
			
			preg_match_all('/^interface (?P<option_tag>.*)\n(?P<option_data>( [^\n]+\n)+)\S/m', $file, $matches);
			$data['interfaces'] = array();
			foreach ($matches['option_tag'] as $idx => $tag)
				$data['interfaces'][$tag] = $matches['option_data'][$idx];
			if ($debug === true) print_r($matches);

			preg_match_all('/^class-map (?P<option_tag>.*)\n(?P<option_data>( [^\n]+\n)+)\S/m', $file, $matches);
			$data['class-maps'] = array();
			foreach ($matches['option_tag'] as $idx => $tag)
				$data['class-maps'][$tag] = $matches['option_data'][$idx];
			if ($debug === true) print_r($matches);

			preg_match_all('/^policy-map (?P<option_tag>.*)\n(?P<option_data>( [^\n]+\n)+)\S/m', $file, $matches);
			$data['policy-maps'] = array();
			foreach ($matches['option_tag'] as $idx => $tag)
				$data['policy-maps'][$tag] = $matches['option_data'][$idx];
			if ($debug === true) print_r($matches);

			preg_match_all('/^route-map (?P<option_tag>.*)\n(?P<option_data>( [^\n]+\n)+)\S/m', $file, $matches);
			$data['route-maps'] = array();
			foreach ($matches['option_tag'] as $idx => $tag)
				$data['route-map'][$tag] = $matches['option_data'][$idx];
			if ($debug === true) print_r($matches);

			preg_match_all('/^router (?P<option_tag>.*)\n(?P<option_data>( [^\n]+\n)+)\S/m', $file, $matches);
			$data['routers'] = array();
			foreach ($matches['option_tag'] as $idx => $tag)
				$data['router'][$tag] = $matches['option_data'][$idx];
			if ($debug === true) print_r($matches);

			preg_match_all('/^line(?P<line_data>(\s[^\n]+\n)+)/m', $file, $matches);
			$data['lines'] = array();
			foreach ($matches['line_data'] as $idx => $tag)
				$data['lines'][] = $tag;
			if ($debug === true) print_r($matches);

			preg_match_all('/^redundancy(?P<ip_data>(\s[^\n]+\n)+)/m', $file, $matches);
			$data['redundancies'] = array();
			foreach ($matches['ip_data'] as $idx => $tag)
				$data['redundancies'][] = $tag;
			if ($debug === true) print_r($matches);

			preg_match_all('/^ip(?P<ip_data>(\s[^\n]+\n)+)/m', $file, $matches);
			$data['ips'] = array();
			foreach ($matches['ip_data'] as $idx => $tag)
				$data['ips'][] = $tag;
			if ($debug === true) print_r($matches);

			preg_match_all('/^ipv6(?P<ip_data>(\s[^\n]+\n)+)/m', $file, $matches);
			$data['ipsv6'] = array();
			foreach ($matches['ip_data'] as $idx => $tag)
				$data['ipsv6'][] = $tag;
			if ($debug === true) print_r($matches);

			
			return $data;
			print_r($data);
		}
		
		public function show_int_config($int)
		{
			// Enabled Only
			//if (strpos($this->_prompt, '#') === false)
			//	die('Error: User must be enabled to use show_int_config()' . "\n");
			$this->exec('show run int ' . $int);
			return $this->show_int_config_parser();
		}
		
		public function show_int_config_parser()
		{
			$this->_data = explode("\r\n", $this->_data);
			for ($i = 0; $i < 5; $i++)
				array_shift($this->_data);
			for ($i = 0; $i < 2; $i++)
				array_pop($this->_data);
			$this->_data = implode("\n", $this->_data);
			return $this->_data;
		}

		public function show_int_status()
		{
			$result = array();
			$this->exec('show int status');
			$this->_data = explode("\r\n", $this->_data);
			for ($i = 0; $i < 2; $i++)
				array_shift($this->_data);
			array_pop($this->_data);
			$pos = strpos($this->_data[0], "Status");
			foreach ($this->_data as $entry) {
				$temp = trim($entry);
				if (strlen($temp) > 1 && $temp[2] != 'r' && $temp[0] != '-') {
					$entry                = array();
					$entry['interface']   = substr($temp, 0, strpos($temp, ' '));
					$entry['description'] = trim(substr($temp, strpos($temp, ' ') + 1, $pos - strlen($entry['interface']) - 1));
					$temp                 = substr($temp, $pos);
					$temp                 = sscanf($temp, "%s %s %s %s %s %s");
					$entry['status']      = $temp[0];
					$entry['vlan']        = $temp[1];
					$entry['duplex']      = $temp[2];
					$entry['speed']       = $temp[3];
					$entry['type']        = trim($temp[4] . ' ' . $temp[5]);
					array_push($result, $entry);
				} // if
			} // foreach
			$this->_data = $result;
			return $this->_data;
		}
		
		public function show_log()
		{
			// Enabled Only
			if (strpos($this->_prompt, '#') === false)
				die('Error: User must be enabled to use show_log()' . "\n");
			$result = array();
			$this->exec('sh log | inc %');
			$this->_data = explode("\r\n", $this->_data);
			array_shift($this->_data);
			array_pop($this->_data);
			foreach ($this->_data as $entry) {
				$temp               = trim($entry);
				$entry              = array();
				$entry['timestamp'] = substr($temp, 0, strpos($temp, '%') - 2);
				if ($entry['timestamp'][0] == '.' || $entry['timestamp'][0] == '*')
					$entry['timestamp'] = substr($entry['timestamp'], 1);
				$temp             = substr($temp, strpos($temp, '%') + 1);
				$entry['type']    = substr($temp, 0, strpos($temp, ':'));
				$temp             = substr($temp, strpos($temp, ':') + 2);
				$entry['message'] = $temp;
				array_push($result, $entry);
			} // foreach
			$this->_data = $result;
			return $this->_data;
		}
		
		public function show_int($int)
		{
			$result = array();
			$this->exec('show int ' . $int);
			$this->_data = explode("\r\n", $this->_data);
			foreach ($this->_data as $entry) {
				$entry = trim($entry);
				if (strpos($entry, 'line protocol') !== false) {
					$result['interface'] = substr($entry, 0, strpos($entry, ' '));
					if (strpos($entry, 'administratively') !== false) {
						$result['status'] = 'disabled';
					} elseif (substr($entry, strpos($entry, 'line protocol') + 17, 2) == 'up') {
						$result['status'] = 'connected';
					} else {
						$result['status'] = 'notconnect';
					} // if .. else
				} elseif (strpos($entry, 'Description: ') !== false) {
					$entry                 = explode(':', $entry);
					$result['description'] = trim($entry[1]);
				} elseif (strpos($entry, 'MTU') !== false) {
					$entry               = explode(',', $entry);
					$entry[0]            = trim($entry[0]);
					$entry[0]            = explode(' ', $entry[0]);
					$result['mtu']       = $entry[0][1];
					$entry[1]            = trim($entry[1]);
					$entry[1]            = explode(' ', $entry[1]);
					$result['bandwidth'] = $entry[1][1];
					$entry[2]            = trim($entry[2]);
					$entry[2]            = explode(' ', $entry[2]);
					$result['dly']       = $entry[2][1];
				} elseif (strpos($entry, 'duplex') !== false) {
					$entry            = explode(',', $entry);
					$entry[0]         = trim($entry[0]);
					$entry[0]         = explode(' ', $entry[0]);
					$entry[0][0]      = explode('-', $entry[0][0]);
					$result['duplex'] = strtolower($entry[0][0][0]);
					$entry[1]         = trim($entry[1]);
					if (strpos($entry[1], 'Auto') !== false) {
						$result['speed'] = 'auto';
					} else {
						$result['speed'] = intval($entry[1]);
					} // if .. else
					$entry[2]       = rtrim($entry[2]);
					$result['type'] = substr($entry[2], strrpos($entry[2], ' ') + 1);
				} elseif (strpos($entry, 'input rate') !== false) {
					$entry                    = explode(',', $entry);
					$result['in_rate']        = substr($entry[0], strpos($entry[0], 'rate') + 5, strrpos($entry[0], ' ') - (strpos($entry[0], 'rate') + 5));
					$entry                    = trim($entry[1]);
					$entry                    = explode(' ', $entry);
					$result['in_packet_rate'] = $entry[0];
				} elseif (strpos($entry, 'output rate') !== false) {
					$entry                     = explode(',', $entry);
					$result['out_rate']        = substr($entry[0], strpos($entry[0], 'rate') + 5, strrpos($entry[0], ' ') - (strpos($entry[0], 'rate') + 5));
					$entry                     = trim($entry[1]);
					$entry                     = explode(' ', $entry);
					$result['out_packet_rate'] = $entry[0];
				} elseif (strpos($entry, 'packets input') !== false) {
					$entry               = explode(',', $entry);
					$entry[0]            = trim($entry[0]);
					$entry[0]            = explode(' ', $entry[0]);
					$result['in_packet'] = $entry[0][0];
					$entry[1]            = trim($entry[1]);
					$entry[1]            = explode(' ', $entry[1]);
					$result['in']        = $entry[1][0];
					if (count($entry) > 2) {
						$entry[2]            = trim($entry[2]);
						$entry[2]            = explode(' ', $entry[2]);
						$result['no_buffer'] = $entry[2][0];
					} // if
				} elseif (strpos($entry, 'Received') !== false) {
					$entry               = explode(',', $entry);
					$entry[0]            = trim($entry[0]);
					$entry[0]            = explode(' ', $entry[0]);
					$result['broadcast'] = $entry[0][1];
					if (count($entry) > 1) {
						$entry[1]           = trim($entry[1]);
						$entry[1]           = explode(' ', $entry[1]);
						$result['runt']     = $entry[1][0];
						$entry[2]           = trim($entry[2]);
						$entry[2]           = explode(' ', $entry[2]);
						$result['giant']    = $entry[2][0];
						$entry[3]           = trim($entry[3]);
						$entry[3]           = explode(' ', $entry[3]);
						$result['throttle'] = $entry[3][0];
					} // if
				} elseif (strpos($entry, 'CRC') !== false) {
					$entry              = explode(',', $entry);
					$entry[0]           = trim($entry[0]);
					$entry[0]           = explode(' ', $entry[0]);
					$result['in_error'] = $entry[0][0];
					$entry[1]           = trim($entry[1]);
					$entry[1]           = explode(' ', $entry[1]);
					$result['crc']      = $entry[1][0];
					$entry[2]           = trim($entry[2]);
					$entry[2]           = explode(' ', $entry[2]);
					$result['frame']    = $entry[2][0];
					$entry[3]           = trim($entry[3]);
					$entry[3]           = explode(' ', $entry[3]);
					$result['overrun']  = $entry[3][0];
					$entry[4]           = trim($entry[4]);
					$entry[4]           = explode(' ', $entry[4]);
					$result['ignored']  = $entry[4][0];
				} elseif (strpos($entry, 'watchdog') !== false) {
					$entry               = explode(',', $entry);
					$entry[0]            = trim($entry[0]);
					$entry[0]            = explode(' ', $entry[0]);
					$result['watchdog']  = $entry[0][0];
					$entry[1]            = trim($entry[1]);
					$entry[1]            = explode(' ', $entry[1]);
					$result['multicast'] = $entry[1][0];
					if (count($entry) > 2) {
						$entry[2]           = trim($entry[2]);
						$entry[2]           = explode(' ', $entry[2]);
						$result['pause_in'] = $entry[2][0];
					} // if
				} elseif (strpos($entry, 'dribble') !== false) {
					$entry                = trim($entry);
					$entry                = explode(' ', $entry);
					$result['in_dribble'] = $entry[0];
				} elseif (strpos($entry, 'packets output') !== false) {
					$entry                = explode(',', $entry);
					$entry[0]             = trim($entry[0]);
					$entry[0]             = explode(' ', $entry[0]);
					$result['out_packet'] = $entry[0][0];
					$entry[1]             = trim($entry[1]);
					$entry[1]             = explode(' ', $entry[1]);
					$result['out']        = $entry[1][0];
					$entry[2]             = trim($entry[2]);
					$entry[2]             = explode(' ', $entry[2]);
					$result['underrun']   = $entry[2][0];
				} elseif (strpos($entry, 'output errors') !== false) {
					$entry               = explode(',', $entry);
					$entry[0]            = trim($entry[0]);
					$entry[0]            = explode(' ', $entry[0]);
					$result['out_error'] = $entry[0][0];
					if (count($entry) > 2) {
						$entry[1]            = trim($entry[1]);
						$entry[1]            = explode(' ', $entry[1]);
						$result['collision'] = $entry[1][0];
						$entry[2]            = trim($entry[2]);
						$entry[2]            = explode(' ', $entry[2]);
						$result['reset']     = $entry[2][0];
					} else {
						$entry[1]        = trim($entry[1]);
						$entry[1]        = explode(' ', $entry[1]);
						$result['reset'] = $entry[1][0];
					} // if .. else
				} elseif (strpos($entry, 'babbles') !== false) {
					$entry                    = explode(',', $entry);
					$entry[0]                 = trim($entry[0]);
					$entry[0]                 = explode(' ', $entry[0]);
					$result['babble']         = $entry[0][0];
					$entry[1]                 = trim($entry[1]);
					$entry[1]                 = explode(' ', $entry[1]);
					$result['late_collision'] = $entry[1][0];
					$entry[2]                 = trim($entry[2]);
					$entry[2]                 = explode(' ', $entry[2]);
					$result['deferred']       = $entry[2][0];
				} elseif (strpos($entry, 'lost carrier') !== false) {
					$entry                  = explode(',', $entry);
					$entry[0]               = trim($entry[0]);
					$entry[0]               = explode(' ', $entry[0]);
					$result['lost_carrier'] = $entry[0][0];
					$entry[1]               = trim($entry[1]);
					$entry[1]               = explode(' ', $entry[1]);
					$result['no_carrier']   = $entry[1][0];
					if (count($entry) > 2) {
						$entry[2]            = trim($entry[2]);
						$entry[2]            = explode(' ', $entry[2]);
						$result['pause_out'] = $entry[2][0];
					} // if
				} elseif (strpos($entry, 'output buffer failures') !== false) {
					$entry                     = explode(',', $entry);
					$entry[0]                  = trim($entry[0]);
					$entry[0]                  = explode(' ', $entry[0]);
					$result['out_buffer_fail'] = $entry[0][0];
					$entry[1]                  = trim($entry[1]);
					$entry[1]                  = explode(' ', $entry[1]);
					$result['out_buffer_swap'] = $entry[1][0];
				} // if .. elseif
			} // foreach
			$this->_data = $result;
			return $this->_data;
		}
		
		public function trunk_ports()
		{
			$result = array();
			$this->exec('show interface status | include trunk');
			$this->_data = explode("\r\n", $this->_data);
			array_shift($this->_data);
			array_pop($this->_data);
			if (count($this->_data) > 0) {
				foreach ($this->_data as $interface) {
					$interface = explode(' ', $interface);
					array_push($result, $interface[0]);
				} // foreach
			} // if
			$this->_data = $result;
			return $this->_data;
		}
		
		public function vlans()
		{
			$result = array();
			$this->exec('show spanning-tree summary | include ^VLAN');
			$this->_data = explode("\r\n", $this->_data);
			array_shift($this->_data);
			array_pop($this->_data);
			if (count($this->_data) > 0) {
				foreach ($this->_data as $vlan) {
					$vlan = explode(" ", $vlan);
					$vlan = substr($vlan[0], 4);
					array_push($result, intval($vlan));
				} // foreach
			} // if
			$this->_data = $result;
			return $this->_data;
		}
		
		public function errdisabled()
		{
			$result = array();
			$this->exec('show int status err');
			$this->_data = explode("\r\n", $this->_data);
			for ($i = 0; $i < 2; $i++)
				array_shift($this->_data);
			array_pop($this->_data);
			$pos = strpos($this->_data[0], "Status");
			foreach ($this->_data as $entry) {
				$temp = trim($entry);
				if (strlen($temp) > 1 && $temp[2] != 'r') {
					$entry                = array();
					$entry['interface']   = substr($temp, 0, strpos($temp, ' '));
					$entry['description'] = trim(substr($temp, strpos($temp, ' ') + 1, $pos - strlen($entry['interface']) - 1));
					$temp                 = substr($temp, $pos);
					$temp                 = sscanf($temp, "%s %s");
					$entry['status']      = $temp[0];
					$entry['reason']      = $temp[1];
					array_push($result, $entry);
				} // if
			} // foreach
			$this->_data = $result;
			return $this->_data;
		}
		
		public function dhcpsnoop_bindings()
		{
			$result = array();
			$this->exec('sh ip dhcp snoop binding | inc dhcp-snooping');
			$this->_data = explode("\r\n", $this->_data);
			array_shift($this->_data);
			array_pop($this->_data);
			foreach ($this->_data as $entry) {
				$temp                 = sscanf($entry, "%s %s %s %s %s %s");
				$entry                = array();
				$entry['mac_address'] = $temp[0];
				$entry['mac_address'] = strtolower(str_replace(':', '', $entry['mac_address']));
				$entry['ip_address']  = $temp[1];
				$entry['lease']       = $temp[2];
				$entry['vlan']        = $temp[4];
				$entry['interface']   = $temp[5];
				if ($temp[3] == 'dhcp-snooping')
					array_push($result, $entry);
			}
			$this->_data = $result;
			return $this->_data;
		}
		
		public function mac_address_table()
		{
			$result = array();
			$omit   = $this->trunk_ports();
			$this->exec('show mac address-table | exclude CPU');
			$this->_data = str_replace("          ", "", $this->_data);
			$this->_data = explode("\r\n", $this->_data);
			for ($i = 0; $i < 6; $i++)
				array_shift($this->_data);
			for ($i = 0; $i < 2; $i++)
				array_pop($this->_data);
			foreach ($this->_data as $entry) {
				$temp                 = sscanf($entry, "%s %s %s %s");
				$entry                = array();
				$entry['mac_address'] = $temp[1];
				$entry['interface']   = $temp[3];
				if (in_array($entry['interface'], $omit) == false) {
					array_push($result, $entry);
				} // if
			} // foreach
			$this->_data = $result;
			return $this->_data;
		}
		
		public function arp_table()
		{
			$result = array();
			$this->exec('show arp | exc Incomplete');
			$this->_data = explode("\r\n", $this->_data);
			for ($i = 0; $i < 2; $i++)
				array_shift($this->_data);
			array_pop($this->_data);
			foreach ($this->_data as $entry) {
				$temp                 = sscanf($entry, "%s %s %s %s %s %s");
				$entry                = array();
				$entry['ip']          = $temp[1];
				$entry['mac_address'] = $temp[3];
				if ($temp[2] == '-')
					$temp[2] = '0';
				$entry['age']       = $temp[2];
				$entry['interface'] = $temp[5];
				if ($entry['ip'] != 'Address' && $entry['mac_address'] != 'Incomplete') {
					array_push($result, $entry);
				} // if
			} // foreach
			$this->_data = $result;
			return $this->_data;
		}
		
		public function ipv6_neighbor_table()
		{
			$result = array();
			$this->exec('show ipv6 neighbors | exc INCMP');
			$this->_data = explode("\r\n", $this->_data);
			for ($i = 0; $i < 2; $i++)
				array_shift($this->_data);
			for ($i = 0; $i < 2; $i++)
				array_pop($this->_data);
			foreach ($this->_data as $entry) {
				$temp                 = sscanf($entry, "%s %s %s %s %s");
				$entry                = array();
				$entry['ipv6']        = $temp[0];
				$entry['mac_address'] = $temp[2];
				$entry['age']         = $temp[1];
				$entry['interface']   = $temp[4];
				array_push($result, $entry);
			} // foreach
			$this->_data = $result;
			return $this->_data;
		}
		
		public function ipv6_routers()
		{
			$result = array();
			$this->exec('show ipv6 routers');
			$this->_data = explode("\r\n", $this->_data);
			array_shift($this->_data);
			array_pop($this->_data);
			for ($i = 0; $i < count($this->_data); $i++) {
				$entry = trim($this->_data[$i]);
				if (substr($entry, 0, 7) == 'Router ') {
					$temp               = sscanf($entry, "%s %s %s %s");
					$entry              = array();
					$entry['router']    = $temp[1];
					$entry['interface'] = str_replace(',', '', $temp[3]);
					$temp               = sscanf(trim($this->_data[$i + 4]), "%s %s %s");
					$entry['prefix']    = $temp[1];
					$i                  = $i + 5;
					array_push($result, $entry);
				} // if
			} // for
			$this->_data = $result;
			return $this->_data;
		}
		
		public function configure($config)		
		{
			// USE AT OWN RISK: This function will apply configuration statements to a device.
			// Enabled Only
			if (strpos($this->_prompt, '#') === false)
				die('Error: User must be enabled to use configure()' . "\n");
			$this->_data = explode("\n", $config);
			$this->_ssh->write("config t\n");
			$config_prompt = $this->_ssh->read('/.*[>|#]/', NET_SSH2_READ_REGEX);
			$config_prompt = str_replace("\r\n", '', trim($config_prompt));
			if (strpos($config_prompt, 'config)#') !== false) {
				foreach ($this->_data as $c)
					$this->_ssh->write($c . "\n");
				$this->_ssh->write("end\n");
			}
			$result = $this->_ssh->read($this->_prompt);
			$result = explode("\r\n", $result);
			if (count($this->_data) == (count($result) - 2))
				return true;
			else
				die('Error: Switch rejected configuration: ' . "\n" . $config . "\n");
		}
		
		public function write_config()
		{
			$this->exec('write');
			if (strpos($this->_data, '[OK]') !== false)
				return true;
			else
				return false;
		}
	}
?>