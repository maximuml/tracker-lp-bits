<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The v2.0.1 release created activity_log via three migrations (create,
     * +event, +batch_uuid) matching spatie/laravel-activitylog v4. After the
     * tag the package was upgraded to v5 (batch system removed,
     * attribute_changes added) and the create migration was edited in place
     * while the two follow-ups were deleted. Databases migrated at v2.0.1
     * therefore lack attribute_changes (read by ActivityLogResource) and
     * still carry the vestigial batch_uuid. This migration converges both
     * upgrade paths to the same schema.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('activity_log', 'attribute_changes')) {
            Schema::table('activity_log', function ($table) {
                $table->json('attribute_changes')->nullable()->after('event');
            });
        }

        if (Schema::hasColumn('activity_log', 'batch_uuid')) {
            Schema::table('activity_log', function ($table) {
                $table->dropColumn('batch_uuid');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('activity_log', 'batch_uuid')) {
            Schema::table('activity_log', function ($table) {
                $table->uuid('batch_uuid')->nullable()->after('properties');
            });
        }
    }
};
