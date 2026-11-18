<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * After the middle class tiers were retired (Crazy=4, Insane=5, Extreme=7,
 * Nexus Master=9), settings that store a minimum-class threshold can still
 * hold a retired value. Remap them to the same surviving tier the users on
 * that tier landed on, so effective permissions are unchanged:
 *   4, 5 -> 3 (Elite User)   7 -> 6 (Veteran User)   9 -> 8 (Ultimate User)
 */
return new class extends Migration
{
    /** @var array<int, int> */
    private const REMAP = [4 => 3, 5 => 3, 7 => 6, 9 => 8];

    /**
     * Settings keys whose value is a minimum user-class threshold.
     *
     * @var list<string>
     */
    private const KEYS = [
        'authority.log',
        'authority.updateextinfo',
        'authority.beanonymous',
        'authority.buyinvite',
        'attachment.classthree',
    ];

    public function up(): void
    {
        foreach (self::REMAP as $old => $new) {
            DB::table('settings')
                ->whereIn('name', self::KEYS)
                ->where('value', (string) $old)
                ->update(['value' => (string) $new]);
        }
    }

    public function down(): void
    {
        foreach (self::REMAP as $old => $new) {
            DB::table('settings')
                ->whereIn('name', self::KEYS)
                ->where('value', (string) $new)
                ->update(['value' => (string) $old]);
        }
    }
};
