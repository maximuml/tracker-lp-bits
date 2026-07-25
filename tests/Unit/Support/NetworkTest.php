<?php

namespace Tests\Unit\Support;

use App\Support\Network;
use PHPUnit\Framework\TestCase;

class NetworkTest extends TestCase
{
    // ---------- ipInRange(): single-IP form ($ipTwo === false) ----------

    public function test_single_ip_check_dotted_quad_match(): void
    {
        // $long = false → both sides are dotted-quad strings.
        $this->assertTrue(Network::ipInRange(false, '1.2.3.4', '1.2.3.4'));
        $this->assertFalse(Network::ipInRange(false, '1.2.3.4', '1.2.3.5'));
    }

    public function test_single_ip_check_long_form(): void
    {
        // $long = true → $ipOne is an integer from ip2long().
        $this->assertTrue(Network::ipInRange(true, '1.2.3.4', ip2long('1.2.3.4')));
        $this->assertFalse(Network::ipInRange(true, '1.2.3.4', ip2long('5.6.7.8')));
    }

    // ---------- ipInRange(): two-IP range ----------

    public function test_two_ip_range_dotted_quad_inclusive(): void
    {
        $this->assertTrue(Network::ipInRange(false, '1.2.3.5', '1.2.3.1', '1.2.3.10'));
        // Lower bound is inclusive.
        $this->assertTrue(Network::ipInRange(false, '1.2.3.1', '1.2.3.1', '1.2.3.10'));
        // Upper bound is inclusive.
        $this->assertTrue(Network::ipInRange(false, '1.2.3.10', '1.2.3.1', '1.2.3.10'));
    }

    public function test_two_ip_range_dotted_quad_outside(): void
    {
        $this->assertFalse(Network::ipInRange(false, '1.2.3.0', '1.2.3.1', '1.2.3.10'));
        $this->assertFalse(Network::ipInRange(false, '1.2.3.11', '1.2.3.1', '1.2.3.10'));
    }

    public function test_two_ip_range_long_form(): void
    {
        $low = ip2long('1.2.3.1');
        $high = ip2long('1.2.3.10');
        $this->assertTrue(Network::ipInRange(true, '1.2.3.5', $low, $high));
        $this->assertTrue(Network::ipInRange(true, '1.2.3.1', $low, $high));
        $this->assertTrue(Network::ipInRange(true, '1.2.3.10', $low, $high));
        $this->assertFalse(Network::ipInRange(true, '1.2.3.0', $low, $high));
        $this->assertFalse(Network::ipInRange(true, '1.2.3.11', $low, $high));
    }

    public function test_two_ip_range_handles_cross_octet_boundaries(): void
    {
        // Pinned: the comparison uses ip2long() arithmetic, so a range
        // that spans an octet boundary like 1.2.3.250–1.2.4.10 still
        // works correctly (a naive lexicographic compare would not).
        $this->assertTrue(Network::ipInRange(false, '1.2.4.5', '1.2.3.250', '1.2.4.10'));
        $this->assertTrue(Network::ipInRange(false, '1.2.3.255', '1.2.3.250', '1.2.4.10'));
        $this->assertFalse(Network::ipInRange(false, '1.2.4.11', '1.2.3.250', '1.2.4.10'));
    }

    // ---------- isValid() / isIpv4() / isIpv6() ----------

    public function test_isValid_allows_public_ipv4_and_ipv6(): void
    {
        $this->assertTrue(Network::isValid('1.2.3.4'));
        $this->assertTrue(Network::isValid('2001:4860:4860::8888'));
    }

    public function test_isValid_rejects_reserved_ipv4_ranges(): void
    {
        $this->assertFalse(Network::isValid('192.0.2.1'));
        $this->assertFalse(Network::isValid('192.168.1.1'));
        $this->assertFalse(Network::isValid('255.255.255.255'));
    }

    public function test_isIpv4_and_isIpv6_match_filter_var(): void
    {
        $this->assertTrue(Network::isIpv4('1.2.3.4'));
        $this->assertFalse(Network::isIpv4('2001:4860:4860::8888'));

        $this->assertTrue(Network::isIpv6('2001:4860:4860::8888'));
        $this->assertFalse(Network::isIpv6('1.2.3.4'));
    }

    // ---------- clientIp() ----------

    public function test_clientIp_prefers_x_forwarded_for(): void
    {
        $backup = $_SERVER;
        $_SERVER = [
            'HTTP_X_FORWARDED_FOR' => '10.0.0.1, 10.0.0.2',
            'HTTP_CLIENT_IP' => '10.0.0.3',
            'REMOTE_ADDR' => '10.0.0.4',
        ];

        $this->assertSame('10.0.0.1', Network::clientIp());
        $this->assertSame('10.0.0.1, 10.0.0.2', Network::clientIp(false));

        $_SERVER = $backup;
    }

    public function test_clientIp_falls_back_to_client_ip_and_remote_addr(): void
    {
        $backup = $_SERVER;

        $_SERVER = ['HTTP_CLIENT_IP' => '10.0.0.3', 'REMOTE_ADDR' => '10.0.0.4'];
        $this->assertSame('10.0.0.3', Network::clientIp());

        $_SERVER = ['REMOTE_ADDR' => '10.0.0.4'];
        $this->assertSame('10.0.0.4', Network::clientIp());

        $_SERVER = $backup;
    }
}
