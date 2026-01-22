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
     * Handles three cases:
     * - first_name + last_name → name
     * - username → name
     * - Both present (first_name/last_name takes priority)
     */
    public function up(): void
    {
        $hasFirstName = Schema::hasColumn('users', 'first_name');
        $hasLastName = Schema::hasColumn('users', 'last_name');
        $hasUsername = Schema::hasColumn('users', 'username');
        $hasName = Schema::hasColumn('users', 'name');

        // Nothing to migrate if none of the source columns exist
        if (! $hasFirstName && ! $hasLastName && ! $hasUsername) {
            // If name column doesn't exist either, create it
            if (! $hasName) {
                Schema::table('users', function (Blueprint $table) {
                    $table->string('name')->after('id');
                });
            }

            return;
        }

        // Create the name column if it doesn't exist
        if (! $hasName) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('name')->after('id');
            });
        }

        // Migrate data: priority to first_name/last_name, fallback to username
        if ($hasFirstName && $hasLastName) {
            DB::table('users')
                ->whereNotNull('first_name')
                ->orWhereNotNull('last_name')
                ->orderBy('id')
                ->each(function ($user) {
                    $firstName = trim($user->first_name ?? '');
                    $lastName = trim($user->last_name ?? '');
                    $newName = trim("$firstName $lastName");

                    if (! empty($newName)) {
                        DB::table('users')
                            ->where('id', $user->id)
                            ->update(['name' => $newName]);
                    }
                });
        } elseif ($hasFirstName) {
            DB::table('users')
                ->whereNotNull('first_name')
                ->where('first_name', '!=', '')
                ->update(['name' => DB::raw('first_name')]);
        } elseif ($hasLastName) {
            DB::table('users')
                ->whereNotNull('last_name')
                ->where('last_name', '!=', '')
                ->update(['name' => DB::raw('last_name')]);
        } elseif ($hasUsername) {
            DB::table('users')
                ->whereNotNull('username')
                ->where('username', '!=', '')
                ->update(['name' => DB::raw('username')]);
        }

        // Drop the old columns
        Schema::table('users', function (Blueprint $table) use ($hasFirstName, $hasLastName, $hasUsername) {
            if ($hasFirstName) {
                $table->dropColumn('first_name');
            }
            if ($hasLastName) {
                $table->dropColumn('last_name');
            }
            if ($hasUsername) {
                $table->dropColumn('username');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $hasName = Schema::hasColumn('users', 'name');

        // We can only restore to first_name/last_name since we don't know
        // the original structure. Username users will get first_name/last_name.
        if (! Schema::hasColumn('users', 'first_name')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('first_name')->nullable()->after('id');
            });
        }

        if (! Schema::hasColumn('users', 'last_name')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('last_name')->nullable()->after('first_name');
            });
        }

        // Drop name column if it exists
        if ($hasName) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('name');
            });
        }
    }
};
