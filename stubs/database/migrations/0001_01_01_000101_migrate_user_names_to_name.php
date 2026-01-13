<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $hasFirstName = Schema::hasColumn('users', 'first_name');
        $hasLastName = Schema::hasColumn('users', 'last_name');

        if (! $hasFirstName && ! $hasLastName) {
            return;
        }

        // Migrate data: concatenate first_name and last_name into name
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
        } else {
            DB::table('users')
                ->whereNotNull('last_name')
                ->where('last_name', '!=', '')
                ->update(['name' => DB::raw('last_name')]);
        }

        // Drop the old columns
        Schema::table('users', function (Blueprint $table) use ($hasFirstName, $hasLastName) {
            if ($hasFirstName) {
                $table->dropColumn('first_name');
            }
            if ($hasLastName) {
                $table->dropColumn('last_name');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $hasFirstName = Schema::hasColumn('users', 'first_name');
        $hasLastName = Schema::hasColumn('users', 'last_name');

        if (! $hasFirstName || ! $hasLastName) {
            Schema::table('users', function (Blueprint $table) use ($hasFirstName, $hasLastName) {
                if (! $hasFirstName) {
                    $table->string('first_name')->nullable()->after('id');
                }
                if (! $hasLastName) {
                    $table->string('last_name')->nullable()->after('first_name');
                }
            });
        }
    }
};
