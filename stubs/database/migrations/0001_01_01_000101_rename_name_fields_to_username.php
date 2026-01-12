<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Handles two scenarios:
     * 1. Standard Laravel users table with 'name' column -> rename to 'username'
     * 2. Existing Arkhe projects with 'first_name'/'last_name' -> combine into 'username'
     */
    public function up(): void
    {
        // Scenario 1: Standard Laravel with 'name' column
        if (Schema::hasColumn('users', 'name') && ! Schema::hasColumn('users', 'username')) {
            Schema::table('users', function (Blueprint $table) {
                $table->renameColumn('name', 'username');
            });

            return;
        }

        // Scenario 2: Existing Arkhe with first_name/last_name
        if (Schema::hasColumn('users', 'first_name') && Schema::hasColumn('users', 'last_name')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('username')->after('id')->nullable();
            });

            // Combine first_name and last_name into username
            DB::table('users')->update([
                'username' => DB::raw("TRIM(CONCAT(COALESCE(first_name, ''), ' ', COALESCE(last_name, '')))"),
            ]);

            Schema::table('users', function (Blueprint $table) {
                $table->string('username')->nullable(false)->change();
                $table->dropColumn(['first_name', 'last_name']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasColumn('users', 'username')) {
            return;
        }

        // Revert to 'name' column (standard Laravel)
        Schema::table('users', function (Blueprint $table) {
            $table->renameColumn('username', 'name');
        });
    }
};
