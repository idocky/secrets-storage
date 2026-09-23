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
        Schema::create('secrets', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value');
            $table->timestamps();
        });

        // jsonb on PostgreSQL; json elsewhere (e.g. sqlite tests)
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE secrets ADD COLUMN environment jsonb NOT NULL DEFAULT \'[]\'::jsonb');
        } else {
            Schema::table('secrets', function (Blueprint $table) {
                $table->json('environment')->default('[]');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('secrets');
    }
};
