<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The Cloudflare Turnstile and Google reCAPTCHA v2 captcha drivers are
 * removed — only the built-in image captcha ships now. Drop their
 * settings rows (both the current `captcha.drivers.*` layout and the
 * legacy `captcha.turnstile`/`captcha.recaptcha` keys) and pin
 * `captcha.default` back to `image` when it points at a removed driver.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('settings')
            ->where('name', 'like', 'captcha.drivers.cloudflare\_turnstile%')
            ->orWhere('name', 'like', 'captcha.drivers.google\_recaptcha\_v2%')
            ->orWhere('name', 'like', 'captcha.turnstile%')
            ->orWhere('name', 'like', 'captcha.recaptcha%')
            ->delete();

        DB::table('settings')
            ->where('name', 'captcha.default')
            ->whereNotIn('value', ['image'])
            ->update(['value' => 'image']);
    }

    public function down(): void
    {
        // Removed settings values cannot be restored.
    }
};
