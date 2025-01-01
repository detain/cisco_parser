<?php
/**
 * CISCO Switch Interface Class
 * Basically this is a wrapper to do and parse things from IOS.
 * Links I might find helpful in improving this class:
 *    http://www.networker.gr/index.php/2011/03/parsing-the-cisco-ios-configuration/
 *    http://technologyordie.com/parsing-cisco-show-command-output
 *    http://packetpushers.net/rocking-your-show-commands-with-regex/
 * Based on code from http://www.soucy.org/project/cisco/
 *
 * @author Joe Huss <detain@interserver.net>
 * @version $Revision: 58 $
 * @copyright 2025
 * @package MyAdmin
 * @category Network
 */

namespace Detain\CiscoParser;

/**
 * Class cisco_parser
 */
class CiscoParser
{
    /**
     * @param $lines
     * @param integer $x
     * @return int
     */
    public function get_space_depth($lines, $x)
    {
        if (preg_match('/^(?P<spaces>\s+)*(?P<rest>\S.*)$/', $lines[$x], $matches)) {
            $cdepth = mb_strlen($matches['spaces']);
        } else {
            $cdepth = 0;
        }
        return $cdepth;
    }

    /**
     * @param     $lines
     * @param int $x
     * @param int $depth
     * @return array
     */
    public function parse_cisco_children($lines, $x = 0, $depth = 0)
    {
        //global $x;
        $data = [];
        $last_command = false;
        for ($xMax = count($lines); $x < $xMax; $x++) {
            $cdepth = $this->get_space_depth($lines, $x);
            $command = ltrim($lines[$x]);
            $arguments = '';
            $spacepos = mb_strpos($command, ' ');
            if ($spacepos !== false) {
                $arguments = mb_substr($command, $spacepos + 1);
                $command = mb_substr($command, 0, $spacepos);
                //echo "Got C|$command|A|$arguments|<br>";
            }
            if ($cdepth == $depth) {
                $new_data = ['command' => $command];
                if ($arguments != '') {
                    $new_data['arguments'] = trim($arguments);
                }
                if ($x + 1 < count($lines)) {
                    $next_depth = $this->get_space_depth($lines, $x + 1);
                    if ($next_depth > $depth) {
                        $new_data['children'] = $this->parse_cisco_children($lines, $x + 1, $next_depth);
                        while ($this->get_space_depth($lines, $x + 1) > $depth) {
                            ++$x;
                        }
                    }
                }
                $data[] = $new_data;
            } elseif ($cdepth < $depth) {
                return $data;
            } else {
                echo "SHOULD NEVER GET HERE\n";
            }
        }
        return $data;
    }
}
