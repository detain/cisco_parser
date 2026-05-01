<?php
/**
 * Cisco IOS Configuration Parser.
 *
 * Parses Cisco IOS-style running/startup configuration text into a nested
 * associative-array tree based on indentation depth. Each node has:
 *
 *   - `command`   string  the first whitespace-delimited token of the line
 *   - `arguments` string  (optional) anything after the first token
 *   - `children`  array   (optional) the sub-block of more deeply indented lines
 *
 * Useful references:
 *  - http://www.networker.gr/index.php/2011/03/parsing-the-cisco-ios-configuration/
 *  - http://technologyordie.com/parsing-cisco-show-command-output
 *  - http://packetpushers.net/rocking-your-show-commands-with-regex/
 *
 * Originally based on code from http://www.soucy.org/project/cisco/
 *
 * @author    Joe Huss <detain@interserver.net>
 * @copyright 2025
 * @license   LGPL-2.1-only
 * @package   MyAdmin
 * @category  Network
 */

namespace Detain\CiscoParser;

/**
 * Recursive Cisco IOS configuration parser.
 */
class CiscoParser
{
    /**
     * Returns the indentation depth (in characters) of a line.
     *
     * Counts leading whitespace on `$lines[$x]` and returns the count. Lines
     * that are empty or consist only of whitespace are reported as depth `0`.
     *
     * @param array $lines Array of configuration lines (zero-indexed).
     * @param int   $x     Index of the line to inspect.
     *
     * @return int Number of leading whitespace characters, or 0 if the line
     *             contains no non-whitespace content.
     */
    public function get_space_depth($lines, $x)
    {
        if (!isset($lines[$x])) {
            return 0;
        }
        if (preg_match('/^(?P<spaces>\s*)(?P<rest>\S.*)$/', $lines[$x], $matches)) {
            return mb_strlen($matches['spaces']);
        }
        return 0;
    }

    /**
     * Recursively parse Cisco configuration lines into a nested tree.
     *
     * Walks `$lines` starting at offset `$x`, treating any line whose leading-
     * whitespace count exceeds `$depth` as a child of the previous sibling.
     * Returns when the next line's depth drops below `$depth` or when `$lines`
     * is exhausted.
     *
     * @param array $lines Configuration lines (already split on newlines, with
     *                     `\r` stripped).
     * @param int   $x     Line index at which to begin parsing. Defaults to 0.
     * @param int   $depth Current expected indentation depth. Defaults to 0
     *                     (the top level of a configuration).
     *
     * @return array Nested list of associative arrays, one per command at this
     *               depth. Each entry contains a `command` key and may contain
     *               `arguments` and/or `children` keys.
     */
    public function parse_cisco_children($lines, $x = 0, $depth = 0)
    {
        $data = [];
        for ($xMax = count($lines); $x < $xMax; $x++) {
            $cdepth = $this->get_space_depth($lines, $x);
            $command = ltrim($lines[$x]);
            if ($command === '') {
                continue;
            }
            $arguments = '';
            $spacepos = mb_strpos($command, ' ');
            if ($spacepos !== false) {
                $arguments = mb_substr($command, $spacepos + 1);
                $command = mb_substr($command, 0, $spacepos);
            }
            if ($cdepth == $depth) {
                $new_data = ['command' => $command];
                if ($arguments != '') {
                    $new_data['arguments'] = trim($arguments);
                }
                if ($x + 1 < $xMax) {
                    $next_depth = $this->get_space_depth($lines, $x + 1);
                    if ($next_depth > $depth) {
                        $new_data['children'] = $this->parse_cisco_children($lines, $x + 1, $next_depth);
                        while ($x + 1 < $xMax && $this->get_space_depth($lines, $x + 1) > $depth) {
                            ++$x;
                        }
                    }
                }
                $data[] = $new_data;
            } elseif ($cdepth < $depth) {
                return $data;
            } else {
                throw new \RuntimeException(sprintf(
                    'Unexpected indentation jump at line %d (depth %d, expected <= %d).',
                    $x,
                    $cdepth,
                    $depth
                ));
            }
        }
        return $data;
    }
}
