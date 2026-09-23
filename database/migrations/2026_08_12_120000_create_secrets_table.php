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
        if (! Schema::hasTable('secrets')) {
            Schema::create('secrets', function (Blueprint $table) {
                $table->id();
                $table->string('key')->unique();
                $table->text('value');
                $table->timestamps();
            });
        }

        if (Schema::hasColumn('secrets', 'environment')) {
            return;
        }

        // MySQL rejects a literal default on JSON (error 1101). An expression default works on 8.0.13+.
        // PostgreSQL wants jsonb. SQLite accepts a literal default.
        match (DB::getDriverName()) {
            'pgsql' => DB::statement("ALTER TABLE secrets ADD COLUMN environment jsonb NOT NULL DEFAULT '[]'::jsonb"),
            'mysql' => DB::statement('ALTER TABLE secrets ADD COLUMN environment JSON NOT NULL DEFAULT (JSON_ARRAY())'),
            default => Schema::table('secrets', function (Blueprint $table) {
                $table->json('environment')->default('[]');
            }),
        };
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('secrets');
    }
};
