<?php

namespace Tests\Feature;

use App\Jobs\CalculateUserSeedBonus;
use App\Models\Torrent;
use App\Models\User;
use App\Support\Settings;
use Nexus\Database\NexusDB;
use Rhilip\Bencode\Bencode;
use Tests\TestCase;

/**
 * End-to-end feature test for the legacy public/*.php critical path.
 *
 * The test exercises the real web server inside the Docker Compose stack
 * (openresty container) because the legacy pages are not routed through
 * Laravel. It must therefore run inside the php container with network
 * access to the openresty container.
 */
class CriticalPathTest extends TestCase
{
    private string $baseUrl;
    private string $cookieFile;
    private string $torrentFile;

    protected function setUp(): void
    {
        parent::setUp();

        $base = $_SERVER['CRITICAL_PATH_BASE_URL'] ?? getenv('CRITICAL_PATH_BASE_URL') ?? null;
        if (! is_string($base) || $base === '') {
            $this->markTestSkipped('CRITICAL_PATH_BASE_URL is not configured');
        }

        $this->baseUrl = rtrim($base, '/');
        $this->cookieFile = sys_get_temp_dir() . '/critical_path_' . uniqid() . '.txt';
        touch($this->cookieFile);

        // Disable CAPTCHA and challenge/response so forms can be submitted
        // without browser interaction. Allow the test user to upload.
        Settings::saveBatch('security', ['iv' => '', 'maxip' => '100', 'use_challenge_response_authentication' => 'no']);
        Settings::saveBatch('basic', ['BASEURL' => 'openresty']);
        Settings::saveBatch('authority', ['upload' => '1']);
        Settings::saveBatch('main', ['maxusers' => '100000', 'smtptype' => 'none']);

        // The upload handler saves .torrent files to the configured directory.
        // Make sure it is writable for the FPM worker (www-data).
        $torrentDir = base_path('torrents');
        if (! is_dir($torrentDir) && ! @mkdir($torrentDir, 0777, true) && ! is_dir($torrentDir)) {
            $this->fail("Cannot create torrent save directory: {$torrentDir}");
        }
        @chmod($torrentDir, 0777);

        // The FPM workers cache settings in Redis. Clear those keys so the
        // updated values are loaded on the next legacy request.
        try {
            NexusDB::redis()->del('nexus_settings_in_nexus', 'nexus_settings_in_laravel', 'setting_protected_forum');
        } catch (\Throwable $e) {
            // Redis may not be reachable in this environment; the request may
            // still work if the cache already holds compatible values.
        }
    }

    protected function tearDown(): void
    {
        if (! empty($this->cookieFile) && file_exists($this->cookieFile)) {
            @unlink($this->cookieFile);
        }
        if (! empty($this->torrentFile) && file_exists($this->torrentFile)) {
            @unlink($this->torrentFile);
        }
        parent::tearDown();
    }

    /**
     * Fetch the Laravel CSRF token from the legacy login page.
     */
    private function fetchLoginToken(): string
    {
        $response = $this->request('GET', '/login.php', [], true);
        $this->assertSame(200, $response['status'], 'Could not fetch login page');

        if (preg_match('/name="_token"[^>]*value="([^"]+)"/', $response['body'], $matches)) {
            return $matches[1];
        }

        $this->fail('CSRF token not found on login page');
    }

    /**
     * Fetch the Laravel CSRF token from the legacy signup page.
     */
    private function fetchSignupToken(): string
    {
        $response = $this->request('GET', '/signup.php', [], true);
        $this->assertSame(200, $response['status'], 'Could not fetch signup page');

        if (preg_match('/name="_token"[^>]*value="([^"]+)"/', $response['body'], $matches)) {
            return $matches[1];
        }

        $this->fail('CSRF token not found on signup page');
    }

    /**
     * Execute a cURL request against the legacy web server.
     *
     * @param  array<int|string|CURLFile, mixed>  $data
     * @return array<string, mixed>
     */
    private function request(string $method, string $path, array $data = [], bool $follow = true): array
    {
        $url = $this->baseUrl . '/' . ltrim($path, '/');
        $headers = [];

        $ch = curl_init($url);
        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => $follow,
            CURLOPT_COOKIEJAR => $this->cookieFile,
            CURLOPT_COOKIEFILE => $this->cookieFile,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HEADER => false,
            CURLOPT_USERAGENT => 'qBittorrent/4.5.2',
            CURLOPT_HTTPHEADER => $headers,
        ];

        if ($method === 'POST') {
            $options[CURLOPT_POST] = true;
            $options[CURLOPT_POSTFIELDS] = $data;
        }

        curl_setopt_array($ch, $options);
        $body = curl_exec($ch);
        $info = curl_getinfo($ch);
        $error = curl_error($ch);
        curl_close($ch);

        $this->assertSame('', $error, "cURL error for {$url}: {$error}");

        return [
            'status' => (int) ($info['http_code'] ?? 0),
            'body' => (string) $body,
            'url' => (string) ($info['url'] ?? $url),
            'redirect_url' => (string) ($info['redirect_url'] ?? ''),
        ];
    }

    /**
     * Build a minimal single-file .torrent and return its filesystem path.
     */
    private function buildTorrentFile(): string
    {
        $path = sys_get_temp_dir() . '/critical_path_' . uniqid() . '.torrent';
        $info = [
            'length' => 1234,
            'name' => 'CriticalPathTest',
            'piece length' => 1048576,
            'pieces' => str_repeat('A', 20),
        ];
        $data = [
            'announce' => $this->baseUrl . '/announce.php',
            'comment' => 'Critical path feature test torrent',
            'created by' => 'PHPUnit',
            'creation date' => time(),
            'encoding' => 'UTF-8',
            'info' => $info,
        ];
        file_put_contents($path, Bencode::encode($data));

        return $path;
    }

    public function testCriticalPath(): void
    {
        $username = 'crit' . substr(str_replace(['.', '-'], '', uniqid('', true)), -8);
        $password = 'Password123!';
        $email = $username . '@example.com';

        // 1. Signup
        $signupToken = $this->fetchSignupToken();
        $signup = $this->request('POST', '/takesignup.php', [
            '_token' => $signupToken,
            'wantusername' => $username,
            'wantpassword' => $password,
            'passagain' => $password,
            'email' => $email,
            'country' => '1',
            'gender' => 'Male',
            'rulesverify' => 'yes',
            'faqverify' => 'yes',
            'ageverify' => 'yes',
        ], false);

        $this->assertSame(302, $signup['status'], "Signup returned unexpected status: {$signup['status']}\n{$signup['body']}");
        $this->assertNotEmpty($signup['redirect_url'], 'Signup did not redirect to confirmation URL');
        $this->assertStringContainsString('confirm.php', $signup['redirect_url'], 'Signup redirect target is not confirm.php');

        // 2. Confirm account (and login)
        $confirm = $this->request('GET', '/' . ltrim((string) parse_url($signup['redirect_url'], PHP_URL_PATH), '/') . '?' . (string) parse_url($signup['redirect_url'], PHP_URL_QUERY), [], true);
        $this->assertSame(200, $confirm['status'], "Account confirmation failed: {$confirm['status']}\n{$confirm['body']}");

        // 3. Logout and log back in to verify the standalone login flow
        $this->request('GET', '/logout.php', [], true);

        $loginToken = $this->fetchLoginToken();
        $login = $this->request('POST', '/takelogin.php', [
            '_token' => $loginToken,
            'username' => $username,
            'password' => $password,
        ], true);
        $this->assertSame(200, $login['status'], "Login failed: {$login['status']}\n{$login['body']}");

        // 4. Upload a torrent
        $this->torrentFile = $this->buildTorrentFile();
        $catid = '401';
        $catmod = '4';
        $upload = $this->request('POST', '/takeupload.php', [
            'name' => 'CriticalPathTest',
            'descr' => 'Critical path feature test upload.',
            'type' => $catid,
            "source_sel[{$catmod}]" => '1',
            "medium_sel[{$catmod}]" => '4',
            "codec_sel[{$catmod}]" => '1',
            "standard_sel[{$catmod}]" => '1',
            "processing_sel[{$catmod}]" => '1',
            "audiocodec_sel[{$catmod}]" => '1',
            'file' => new \CURLFile($this->torrentFile, 'application/x-bittorrent', 'critical-path.torrent'),
        ], false);

        $this->assertSame(302, $upload['status'], "Upload failed: {$upload['status']}\n{$upload['body']}");
        $this->assertNotEmpty($upload['redirect_url'], 'Upload did not redirect to the details page');
        $this->assertMatchesRegularExpression('/details\.php\?.*\bid=\d+/', $upload['redirect_url'], 'Upload redirect URL missing torrent id');

        // Extract the newly created torrent id and its binary info_hash
        preg_match('/[?&]id=(\d+)/', $upload['redirect_url'], $matches);
        $this->assertNotEmpty($matches[1], 'Could not parse torrent id from upload redirect');
        $torrentId = (int) $matches[1];

        $torrent = Torrent::query()->find($torrentId);
        $this->assertNotNull($torrent, 'Torrent row was not created');
        $infoHash = $torrent->info_hash;
        $this->assertSame(20, strlen($infoHash), 'Torrent info_hash is not 20 bytes');

        // Force normal promotion so the ratio calculation is predictable.
        $torrent->update(['sp_state' => Torrent::PROMOTION_NORMAL]);

        $user = User::query()->where('username', $username)->first();
        $this->assertNotNull($user, 'Newly registered user not found');
        $passkey = $user->passkey;
        $this->assertSame(32, strlen($passkey), 'Passkey is not 32 characters');

        // 5. Start announce
        // Use a peer id that the allowed-client check will accept. The
        // qBittorrent 4.x family expects a 20-byte id beginning with
        // "-qB4" followed by two version digits.
        $peerId = '-qB4' . sprintf('%02d', random_int(0, 99)) . random_bytes(14);
        $port = 51413;
        $startParams = [
            'passkey' => $passkey,
            'info_hash' => $infoHash,
            'peer_id' => $peerId,
            'port' => (string) $port,
            'uploaded' => '0',
            'downloaded' => '0',
            'left' => '0',
            'compact' => '1',
            'numwant' => '50',
        ];
        $startUrl = '/announce.php?' . http_build_query($startParams, '', '&', PHP_QUERY_RFC3986);
        $start = $this->request('GET', $startUrl, [], true);
        $this->assertSame(200, $start['status'], "Start announce failed: {$start['status']}\n{$start['body']}");

        $startResponse = Bencode::decode($start['body']);
        $this->assertIsArray($startResponse, 'Start announce response is not valid bencode');
        $this->assertArrayHasKey('interval', $startResponse, 'Start announce response missing interval');

        // 6. Calculate seed bonus for the user
        dispatch(new CalculateUserSeedBonus($user->id, $user->id, (string) $user->id, ''));
        $user->refresh();
        $this->assertGreaterThan(0, (float) $user->seed_points_per_hour, 'Seed points per hour were not updated');

        // 7. Complete announce and update traffic (20 GB up / 10 GB down)
        // The announce handler uses a short re-announce lock (5s). Wait for it
        // to expire so the completed request is treated as a new announce.
        sleep(6);
        $completedParams = [
            'passkey' => $passkey,
            'info_hash' => $infoHash,
            'peer_id' => $peerId,
            'port' => (string) $port,
            'uploaded' => '21474836480',
            'downloaded' => '10737418240',
            'left' => '0',
            'event' => 'completed',
            'compact' => '1',
            'numwant' => '50',
        ];
        $completedUrl = '/announce.php?' . http_build_query($completedParams, '', '&', PHP_QUERY_RFC3986);
        $completed = $this->request('GET', $completedUrl, [], true);
        $this->assertSame(200, $completed['status'], "Completed announce failed: {$completed['status']}\n{$completed['body']}");

        $completedResponse = Bencode::decode($completed['body']);
        $this->assertIsArray($completedResponse, 'Completed announce response is not valid bencode');
        $this->assertArrayHasKey('interval', $completedResponse, 'Completed announce response missing interval');

        $user->refresh();
        $this->assertSame(21474836480, (int) $user->uploaded, 'Uploaded bytes not updated after completed announce');
        $this->assertSame(10737418240, (int) $user->downloaded, 'Downloaded bytes not updated after completed announce');

        // 8. Verify ratio display on userdetails.php
        $details = $this->request('GET', "/userdetails.php?id={$user->id}", [], true);
        $this->assertSame(200, $details['status'], "userdetails.php failed: {$details['status']}\n{$details['body']}");
        $this->assertStringContainsString('Share Ratio', $details['body'], 'userdetails.php is missing share ratio label');
        $this->assertStringContainsString('2.000', $details['body'], 'userdetails.php does not show expected ratio 2.000');
    }
}
