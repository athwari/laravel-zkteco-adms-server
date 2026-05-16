<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        $prefix = config('zkteco-adms.table_prefix', 'zkteco_');

        Schema::create($prefix.'command_logs', function (Blueprint $table) use ($prefix) {
            $table->id();
            $table->string('device_serial_number', 64);
            $table->bigInteger('command_id')->unsigned();
            $table->text('command');
            $table->string('status', 20)->default('pending'); // pending, sent, confirmed, failed
            $table->integer('return_code')->nullable();
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();

            $table->index('device_serial_number');
            $table->index('command_id');
            $table->index('status');

            $table->foreign('device_serial_number')
                ->references('serial_number')
                ->on($prefix.'devices')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        $prefix = config('zkteco-adms.table_prefix', 'zkteco_');
        Schema::dropIfExists($prefix.'command_logs');
    }
};
