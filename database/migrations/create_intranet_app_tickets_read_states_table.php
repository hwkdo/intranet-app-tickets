<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('intranet_app_tickets_read_states', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('zammad_ticket_id');
            $table->string('ticket_number')->nullable();
            $table->string('ticket_title')->nullable();
            $table->unsignedBigInteger('last_read_article_id')->nullable();
            $table->unsignedBigInteger('latest_article_id')->nullable();
            $table->boolean('has_unread')->default(false);
            $table->timestamp('last_notified_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'zammad_ticket_id']);
            $table->index(['user_id', 'has_unread']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('intranet_app_tickets_read_states');
    }
};
