<?php

declare(strict_types=1);

namespace Tests\Integration\Services;

use App\Exceptions\AuthenticationException;
use App\Models\User;
use App\Services\PasswordRecoveryService;
use App\Services\SecureTokenService;
use App\Support\PasswordHasher;
use App\Support\Token;
use Illuminate\Support\Facades\DB;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

#[TestCategory(TestCategory::SERVICE_INTEGRATION, TestCategory::CONCURRENCY)]
final class PasswordRecoveryConcurrencyTest extends TestCase
{
    public function test_concurrent_password_resets_allow_only_one_success(): void
    {
        $userId = $this->createUser();
        $tokenService = app(SecureTokenService::class);
        $token = $tokenService->generate();
        $tokenService->store('password_recovery_tokens', $token, [
            'user_id' => $userId,
            'ip' => '127.0.0.1',
        ]);

        $scriptPath = $this->writeChildProcessScript();
        $childPassword = 'child-'.bin2hex(random_bytes(6));
        $pipes = [];
        $process = proc_open(
            [PHP_BINARY, $scriptPath, (string) $userId, $token, $childPassword],
            [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']],
            $pipes,
            base_path(),
        );
        $this->assertIsResource($process, 'Failed to start concurrent reset process');

        $parentSucceeded = false;
        $parentFailedWithExpectedError = false;

        try {
            usleep(50000);

            try {
                app(PasswordRecoveryService::class)->resetPassword(
                    $userId,
                    $token,
                    'ParentReset123',
                    'ParentReset123',
                );
                $parentSucceeded = true;
            } catch (AuthenticationException) {
                $parentFailedWithExpectedError = true;
            }

            $childOutput = stream_get_contents($pipes[1]);
            $childError = stream_get_contents($pipes[2]);
            fclose($pipes[0]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            $childCode = proc_close($process);

            $this->assertNotSame(3, $childCode, 'Child process failed: '.$childError.$childOutput);
            $childSucceeded = $childCode === 0;
            $this->assertTrue(
                $parentSucceeded !== $childSucceeded,
                'Exactly one concurrent password reset must succeed',
            );
            $this->assertTrue(
                $parentSucceeded || $parentFailedWithExpectedError,
                'Parent reset should either succeed or fail as a consumed token',
            );

            $freshUser = User::query()->find($userId);
            $this->assertNotNull($freshUser);
            $winningPassword = $parentSucceeded ? 'ParentReset123' : $childPassword;
            $losingPassword = $parentSucceeded ? $childPassword : 'ParentReset123';
            $this->assertTrue(PasswordHasher::verify(
                $winningPassword,
                (string) $freshUser->passhash,
                (string) $freshUser->secret,
                (string) $freshUser->passhash_algo,
            ));
            $this->assertFalse(PasswordHasher::verify(
                $losingPassword,
                (string) $freshUser->passhash,
                (string) $freshUser->secret,
                (string) $freshUser->passhash_algo,
            ));

            $tokenRow = DB::table('password_recovery_tokens')
                ->where('token_digest', $tokenService->digest($token))
                ->first();
            $this->assertNotNull($tokenRow);
            $this->assertNotNull($tokenRow->consumed_at);
        } finally {
            if (is_resource($process)) {
                proc_close($process);
            }
            foreach ($pipes as $pipe) {
                if (is_resource($pipe)) {
                    fclose($pipe);
                }
            }
            if (is_file($scriptPath)) {
                unlink($scriptPath);
            }
            DB::table('password_recovery_tokens')->where('user_id', $userId)->delete();
            DB::table('outbox_events')
                ->where('aggregate_type', 'user')
                ->where('aggregate_id', $userId)
                ->delete();
            DB::table('users')->where('id', $userId)->delete();
        }
    }

    private function writeChildProcessScript(): string
    {
        $scriptPath = (string) tempnam(sys_get_temp_dir(), 'password-reset-race-');
        $basePath = var_export(base_path(), true);
        $script = <<<PHP
<?php

declare(strict_types=1);

require {$basePath}.'/vendor/autoload.php';

\$app = require {$basePath}.'/bootstrap/app.php';
\$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

try {
    app(App\Services\PasswordRecoveryService::class)->resetPassword(
        (int) \$argv[1],
        (string) \$argv[2],
        (string) \$argv[3],
        (string) \$argv[3],
    );
    exit(0);
} catch (App\Exceptions\AuthenticationException) {
    exit(2);
} catch (Throwable \$exception) {
    fwrite(STDERR, \$exception::class.': '.\$exception->getMessage());
    exit(3);
}
PHP;

        file_put_contents($scriptPath, $script);

        return $scriptPath;
    }

    private function createUser(): int
    {
        return (int) DB::table('users')->insertGetId([
            'username' => 'race_'.uniqid(),
            'email' => 'race_'.uniqid().'@test.com',
            'passhash' => hash('sha256', 'secret'.uniqid()),
            'passhash_algo' => 'sha256',
            'secret' => Token::randomHex(),
            'passkey' => str_pad((string) mt_rand(1, 999999), 32, '0'),
            'class' => 1,
            'added' => now()->toDateTimeString(),
            'last_access' => now()->toDateTimeString(),
            'status' => 1,
            'enabled' => 1,
            'parked' => 0,
            'downloadpos' => 1,
            'seedbonus' => 100.0,
        ]);
    }
}
