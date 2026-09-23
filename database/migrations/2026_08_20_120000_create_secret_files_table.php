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
        Schema::table('secrets', function (Blueprint $table) {
            $table->text('value')->nullable()->change();
        });

        Schema::create('secret_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('secret_id')->constrained()->cascadeOnDelete();
            $table->string('original_name');
            $table->string('storage_name')->unique();
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('secret_files');

        Schema::table('secrets', function (Blueprint $table) {
            $table->text('value')->nullable(false)->change();
        });
    }
};
