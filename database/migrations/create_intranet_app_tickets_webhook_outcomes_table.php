<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('intranet_app_tickets_webhook_outcomes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('webhook_call_id')->unique();
            $table->string('status');
            $table->text('message')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('processed_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('intranet_app_tickets_webhook_outcomes');
    }
};
