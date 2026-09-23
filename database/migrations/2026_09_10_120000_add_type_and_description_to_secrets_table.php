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
        Schema::table('secrets', function (Blueprint $table) {
            $table->string('type')->default('password')->after('key');
            $table->text('description')->nullable()->after('value');
        });

        $secretIdsWithFiles = DB::table('secret_files')->distinct()->pluck('secret_id');

        if ($secretIdsWithFiles->isNotEmpty()) {
            DB::table('secrets')
                ->whereIn('id', $secretIdsWithFiles)
                ->update(['type' => 'file']);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('secrets', function (Blueprint $table) {
            $table->dropColumn(['type', 'description']);
        });
    }
};
