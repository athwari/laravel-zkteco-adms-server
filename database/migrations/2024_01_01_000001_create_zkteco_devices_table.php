<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $prefix = config('zkteco-adms.table_prefix', 'zkteco_');

        Schema::create($prefix . 'devices', function (Blueprint $table) {
            $table->id();
            $table->string('serial_number', 64)->unique();
            $table->timestamp('last_activity')->nullable();
            $table->json('options')->nullable();
            $table->string('timezone', 64)->nullable();
            $table->timestamps();

            $table->index('last_activity');
        });
    }

    public function down(): void
    {
        $prefix = config('zkteco-adms.table_prefix', 'zkteco_');
        Schema::dropIfExists($prefix . 'devices');
    }
};
