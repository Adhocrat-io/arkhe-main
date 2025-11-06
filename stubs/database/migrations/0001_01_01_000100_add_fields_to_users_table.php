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
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('name');
            $table->longText('first_name')->after('id');
            $table->longText('last_name')->after('first_name');
            $table->date('date_of_birth')->after('last_name')->nullable();
            $table->longText('civility')->nullable()->after('date_of_birth');
            $table->longText('profession')->nullable()->after('civility');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('first_name');
            $table->dropColumn('last_name');
            $table->dropColumn('date_of_birth');
            $table->dropColumn('civility');
            $table->dropColumn('profession');
        });
    }
};
