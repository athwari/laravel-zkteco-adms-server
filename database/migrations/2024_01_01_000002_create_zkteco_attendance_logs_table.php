<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        $prefix = config('zkteco-adms.table_prefix', 'zkteco_');

        Schema::create($prefix.'attendance_logs', function (Blueprint $table) use ($prefix) {
            $table->id();
            $table->string('device_serial_number', 64);
            $table->string('user_id', 64);
            $table->timestamp('recorded_at');
            $table->tinyInteger('status')->default(0);
            $table->tinyInteger('verify_mode')->default(0);
            $table->string('work_code', 32)->default('');
            $table->timestamps();

            $table->index('device_serial_number');
            $table->index('user_id');
            $table->index('recorded_at');
            $table->index(['device_serial_number', 'recorded_at']);

            $table->foreign('device_serial_number')
                ->references('serial_number')
                ->on($prefix.'devices')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        $prefix = config('zkteco-adms.table_prefix', 'zkteco_');
        Schema::dropIfExists($prefix.'attendance_logs');
    }
};
