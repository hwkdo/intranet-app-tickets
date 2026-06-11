<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('intranet_app_ticket_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('ticket_category_id');
            $table->foreign('ticket_category_id', 'ticket_req_cat_fk')
                ->references('id')
                ->on('intranet_app_ticket_categories')
                ->restrictOnDelete();
            $table->unsignedBigInteger('requested_by_user_id');
            $table->unsignedBigInteger('on_behalf_of_user_id')->nullable();
            $table->string('subject');
            $table->longText('body');
            $table->json('form_data')->nullable();
            $table->string('status');
            $table->text('rejection_reason')->nullable();
            $table->text('approval_note')->nullable();
            $table->unsignedBigInteger('approved_by_user_id')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('dispatched_at')->nullable();
            $table->unsignedBigInteger('zammad_ticket_id')->nullable();
            $table->text('dispatch_error')->nullable();
            $table->timestamps();

            $table->index(['requested_by_user_id', 'status']);
            $table->index(['on_behalf_of_user_id', 'status']);
            $table->index(['ticket_category_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('intranet_app_ticket_requests');
    }
};
