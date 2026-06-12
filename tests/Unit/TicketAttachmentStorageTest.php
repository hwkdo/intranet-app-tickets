<?php

declare(strict_types=1);

use App\Models\User;
use Hwkdo\IntranetAppTickets\Database\Seeders\TicketCategorySeeder;
use Hwkdo\IntranetAppTickets\Enums\TicketRequestStatus;
use Hwkdo\IntranetAppTickets\Models\TicketCategory;
use Hwkdo\IntranetAppTickets\Models\TicketRequest;
use Hwkdo\IntranetAppTickets\Services\TicketAttachmentStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

uses(RefreshDatabase::class);

function attachmentStorageTicketRequest(): TicketRequest
{
    $user = User::factory()->create(['active' => true]);

    $category = TicketCategory::query()->where('slug', 'it-support')->firstOrFail();

    return TicketRequest::query()->create([
        'ticket_category_id' => $category->id,
        'requested_by_user_id' => $user->id,
        'subject' => 'Test',
        'body' => '<p>Test</p>',
        'form_data' => [],
        'status' => TicketRequestStatus::Approved,
    ]);
}

beforeEach(function (): void {
    $this->seed(TicketCategorySeeder::class);
});

test('missing livewire temp file throws readable error', function (): void {
    Storage::fake('local');

    $ticketRequest = attachmentStorageTicketRequest();

    $file = TemporaryUploadedFile::createFromLivewire('missing-upload.xlsx');

    expect(fn () => app(TicketAttachmentStorage::class)->storeForRequest($ticketRequest, [$file]))
        ->toThrow(RuntimeException::class, 'ist nicht verfügbar');
});

test('stored attachment is persisted on disk', function (): void {
    Storage::fake('local');

    $ticketRequest = attachmentStorageTicketRequest();
    $upload = UploadedFile::fake()->create('anhang.pdf', 100, 'application/pdf');

    app(TicketAttachmentStorage::class)->storeForRequest($ticketRequest, [$upload]);

    $attachment = $ticketRequest->attachments()->first();

    expect($attachment)->not->toBeNull()
        ->and(Storage::disk('local')->exists($attachment->path))->toBeTrue();
});
