<?php
/**
 * PHPUnit tests for the CiscoParser class.
 *
 * @author    Joe Huss <detain@interserver.net>
 * @copyright 2025
 * @license   LGPL-2.1-only
 * @package   MyAdmin
 * @category  Network
 */

use PHPUnit\Framework\TestCase;
use Detain\CiscoParser\CiscoParser;

/**
 * @covers \Detain\CiscoParser\CiscoParser
 */
class ciscoTest extends TestCase
{
    /**
     * @var CiscoParser
     */
    protected $object;

    /**
     * Sets up a fresh parser instance before each test.
     *
     * @return void
     */
    protected function setUp(): void
    {
        $this->object = new CiscoParser();
    }

    /**
     * Tears down the fixture, for example, closes a network connection.
     *
     * @return void
     */
    protected function tearDown(): void
    {
    }

    /**
     * @return void
     */
    public function testGetSpaceDepthReturnsZeroForUnindentedLine()
    {
        $lines = ['hostname Switch1'];
        $this->assertSame(0, $this->object->get_space_depth($lines, 0));
    }

    /**
     * @return void
     */
    public function testGetSpaceDepthCountsLeadingSpaces()
    {
        $lines = [
            'interface FastEthernet0/1',
            ' switchport mode access',
            '  description nested two-space line',
        ];
        $this->assertSame(0, $this->object->get_space_depth($lines, 0));
        $this->assertSame(1, $this->object->get_space_depth($lines, 1));
        $this->assertSame(2, $this->object->get_space_depth($lines, 2));
    }

    /**
     * @return void
     */
    public function testGetSpaceDepthHandlesEmptyLine()
    {
        $lines = ['', '   ', "\t"];
        $this->assertSame(0, $this->object->get_space_depth($lines, 0));
        $this->assertSame(0, $this->object->get_space_depth($lines, 1));
        $this->assertSame(0, $this->object->get_space_depth($lines, 2));
    }

    /**
     * @return void
     */
    public function testGetSpaceDepthHandlesMissingIndex()
    {
        $this->assertSame(0, $this->object->get_space_depth([], 5));
    }

    /**
     * @return void
     */
    public function testParseCiscoChildrenFlatLines()
    {
        $lines = [
            'hostname Switch1',
            'no ip domain-lookup',
        ];
        $expected = [
            ['command' => 'hostname', 'arguments' => 'Switch1'],
            ['command' => 'no', 'arguments' => 'ip domain-lookup'],
        ];
        $this->assertSame($expected, $this->object->parse_cisco_children($lines));
    }

    /**
     * @return void
     */
    public function testParseCiscoChildrenWithChildren()
    {
        $lines = [
            'interface FastEthernet0/1',
            ' switchport access vlan 10',
            ' spanning-tree portfast',
            'interface FastEthernet0/2',
            ' description Uplink',
        ];
        $expected = [
            [
                'command' => 'interface',
                'arguments' => 'FastEthernet0/1',
                'children' => [
                    ['command' => 'switchport', 'arguments' => 'access vlan 10'],
                    ['command' => 'spanning-tree', 'arguments' => 'portfast'],
                ],
            ],
            [
                'command' => 'interface',
                'arguments' => 'FastEthernet0/2',
                'children' => [
                    ['command' => 'description', 'arguments' => 'Uplink'],
                ],
            ],
        ];
        $this->assertSame($expected, $this->object->parse_cisco_children($lines));
    }

    /**
     * @return void
     */
    public function testParseCiscoChildrenSkipsBlankLines()
    {
        $lines = [
            'hostname Switch1',
            '',
            'no ip domain-lookup',
        ];
        $expected = [
            ['command' => 'hostname', 'arguments' => 'Switch1'],
            ['command' => 'no', 'arguments' => 'ip domain-lookup'],
        ];
        $this->assertSame($expected, $this->object->parse_cisco_children($lines));
    }

    /**
     * @return void
     */
    public function testParseCiscoChildrenCommandWithoutArguments()
    {
        $lines = [
            '!',
            'end',
        ];
        $expected = [
            ['command' => '!'],
            ['command' => 'end'],
        ];
        $this->assertSame($expected, $this->object->parse_cisco_children($lines));
    }

    /**
     * @return void
     */
    public function testParseCiscoChildrenWithDeepNesting()
    {
        $lines = [
            'router bgp 65000',
            ' address-family ipv4',
            '  neighbor 10.0.0.1 activate',
        ];
        $expected = [
            [
                'command' => 'router',
                'arguments' => 'bgp 65000',
                'children' => [
                    [
                        'command' => 'address-family',
                        'arguments' => 'ipv4',
                        'children' => [
                            ['command' => 'neighbor', 'arguments' => '10.0.0.1 activate'],
                        ],
                    ],
                ],
            ],
        ];
        $this->assertSame($expected, $this->object->parse_cisco_children($lines));
    }

    /**
     * Children at the very end of the input must not run past the array bound.
     *
     * @return void
     */
    public function testParseCiscoChildrenAtEndOfInput()
    {
        $lines = [
            'interface FastEthernet0/1',
            ' description Last line is a child',
        ];
        $expected = [
            [
                'command' => 'interface',
                'arguments' => 'FastEthernet0/1',
                'children' => [
                    ['command' => 'description', 'arguments' => 'Last line is a child'],
                ],
            ],
        ];
        $this->assertSame($expected, $this->object->parse_cisco_children($lines));
    }

    /**
     * @return void
     */
    public function testParseCiscoChildrenEmptyInput()
    {
        $this->assertSame([], $this->object->parse_cisco_children([]));
    }
}
