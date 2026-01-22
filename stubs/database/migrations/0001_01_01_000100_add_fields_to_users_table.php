<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'date_of_birth')) {
            Schema::table('users', function (Blueprint $table) {
                $table->date('date_of_birth')->nullable();
            });
        }

        if (! Schema::hasColumn('users', 'civility')) {
            Schema::table('users', function (Blueprint $table) {
                $table->longText('civility')->nullable();
            });
        }

        if (! Schema::hasColumn('users', 'profession')) {
            Schema::table('users', function (Blueprint $table) {
                $table->longText('profession')->nullable();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('date_of_birth');
            $table->dropColumn('civility');
            $table->dropColumn('profession');
        });
    }
};
