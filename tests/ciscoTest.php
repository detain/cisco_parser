<?php

use PHPUnit\Framework\TestCase;

/**
 * Generated by PHPUnit_SkeletonGenerator on 2017-08-18 at 02:20:18.
 */
class ciscoTest extends TestCase
{
	protected $hostname;
	protected $username;
	protected $password;
    /**
     * @var cisco
     */
    protected $object;

    /**
     * Sets up the fixture, for example, opens a network connection.
     * This method is called before a test is executed.
     */
    protected function setUp()
    {
	require_once __DIR__.'/../class.cisco.php';
        $this->object = new cisco($this->hostname, $this->username, $this->password);
    }

    /**
     * Tears down the fixture, for example, closes a network connection.
     * This method is called after a test is executed.
     */
    protected function tearDown()
    {
    }

    /**
     * @covers cisco::_string_shift
     * @todo   Implement test_string_shift().
     */
    public function test_string_shift()
    {
        // Remove the following lines when you implement this test.
        $this->markTestIncomplete(
            'This test has not been implemented yet.'
        );
    }

    /**
     * @covers cisco::read
     * @todo   Implement testRead().
     */
    public function testRead()
    {
        // Remove the following lines when you implement this test.
        $this->markTestIncomplete(
            'This test has not been implemented yet.'
        );
    }

    /**
     * @covers cisco::write
     * @todo   Implement testWrite().
     */
    public function testWrite()
    {
        // Remove the following lines when you implement this test.
        $this->markTestIncomplete(
            'This test has not been implemented yet.'
        );
    }

    /**
     * @covers cisco::connect
     * @todo   Implement testConnect().
     */
    public function testConnect()
    {
        // Remove the following lines when you implement this test.
        $this->markTestIncomplete(
            'This test has not been implemented yet.'
        );
    }

    /**
     * @covers cisco::parse_motd_and_prompt
     * @todo   Implement testParse_motd_and_prompt().
     */
    public function testParse_motd_and_prompt()
    {
        // Remove the following lines when you implement this test.
        $this->markTestIncomplete(
            'This test has not been implemented yet.'
        );
    }

    /**
     * @covers cisco::exec
     * @todo   Implement testExec().
     */
    public function testExec()
    {
        // Remove the following lines when you implement this test.
        $this->markTestIncomplete(
            'This test has not been implemented yet.'
        );
    }

    /**
     * @covers cisco::get_response
     * @todo   Implement testGet_response().
     */
    public function testGet_response()
    {
        // Remove the following lines when you implement this test.
        $this->markTestIncomplete(
            'This test has not been implemented yet.'
        );
    }

    /**
     * @covers cisco::disconnect
     * @todo   Implement testDisconnect().
     */
    public function testDisconnect()
    {
        // Remove the following lines when you implement this test.
        $this->markTestIncomplete(
            'This test has not been implemented yet.'
        );
    }

    /**
     * @covers cisco::__destruct
     * @todo   Implement test__destruct().
     */
    public function test__destruct()
    {
        // Remove the following lines when you implement this test.
        $this->markTestIncomplete(
            'This test has not been implemented yet.'
        );
    }

    /**
     * @covers cisco::show_int_config
     * @todo   Implement testShow_int_config().
     */
    public function testShow_int_config()
    {
        // Remove the following lines when you implement this test.
        $this->markTestIncomplete(
            'This test has not been implemented yet.'
        );
    }

    /**
     * @covers cisco::show_int_config_parser
     * @todo   Implement testShow_int_config_parser().
     */
    public function testShow_int_config_parser()
    {
        // Remove the following lines when you implement this test.
        $this->markTestIncomplete(
            'This test has not been implemented yet.'
        );
    }

    /**
     * @covers cisco::show_int_status
     * @todo   Implement testShow_int_status().
     */
    public function testShow_int_status()
    {
        // Remove the following lines when you implement this test.
        $this->markTestIncomplete(
            'This test has not been implemented yet.'
        );
    }

    /**
     * @covers cisco::show_log
     * @todo   Implement testShow_log().
     */
    public function testShow_log()
    {
        // Remove the following lines when you implement this test.
        $this->markTestIncomplete(
            'This test has not been implemented yet.'
        );
    }

    /**
     * @covers cisco::show_int
     * @todo   Implement testShow_int().
     */
    public function testShow_int()
    {
        // Remove the following lines when you implement this test.
        $this->markTestIncomplete(
            'This test has not been implemented yet.'
        );
    }

    /**
     * @covers cisco::trunk_ports
     * @todo   Implement testTrunk_ports().
     */
    public function testTrunk_ports()
    {
        // Remove the following lines when you implement this test.
        $this->markTestIncomplete(
            'This test has not been implemented yet.'
        );
    }

    /**
     * @covers cisco::vlans
     * @todo   Implement testVlans().
     */
    public function testVlans()
    {
        // Remove the following lines when you implement this test.
        $this->markTestIncomplete(
            'This test has not been implemented yet.'
        );
    }

    /**
     * @covers cisco::errdisabled
     * @todo   Implement testErrdisabled().
     */
    public function testErrdisabled()
    {
        // Remove the following lines when you implement this test.
        $this->markTestIncomplete(
            'This test has not been implemented yet.'
        );
    }

    /**
     * @covers cisco::dhcpsnoop_bindings
     * @todo   Implement testDhcpsnoop_bindings().
     */
    public function testDhcpsnoop_bindings()
    {
        // Remove the following lines when you implement this test.
        $this->markTestIncomplete(
            'This test has not been implemented yet.'
        );
    }

    /**
     * @covers cisco::mac_address_table
     * @todo   Implement testMac_address_table().
     */
    public function testMac_address_table()
    {
        // Remove the following lines when you implement this test.
        $this->markTestIncomplete(
            'This test has not been implemented yet.'
        );
    }

    /**
     * @covers cisco::arp_table
     * @todo   Implement testArp_table().
     */
    public function testArp_table()
    {
        // Remove the following lines when you implement this test.
        $this->markTestIncomplete(
            'This test has not been implemented yet.'
        );
    }

    /**
     * @covers cisco::ipv6_neighbor_table
     * @todo   Implement testIpv6_neighbor_table().
     */
    public function testIpv6_neighbor_table()
    {
        // Remove the following lines when you implement this test.
        $this->markTestIncomplete(
            'This test has not been implemented yet.'
        );
    }

    /**
     * @covers cisco::ipv6_routers
     * @todo   Implement testIpv6_routers().
     */
    public function testIpv6_routers()
    {
        // Remove the following lines when you implement this test.
        $this->markTestIncomplete(
            'This test has not been implemented yet.'
        );
    }

    /**
     * @covers cisco::configure
     * @todo   Implement testConfigure().
     */
    public function testConfigure()
    {
        // Remove the following lines when you implement this test.
        $this->markTestIncomplete(
            'This test has not been implemented yet.'
        );
    }

    /**
     * @covers cisco::write_config
     * @todo   Implement testWrite_config().
     */
    public function testWrite_config()
    {
        // Remove the following lines when you implement this test.
        $this->markTestIncomplete(
            'This test has not been implemented yet.'
        );
    }
}