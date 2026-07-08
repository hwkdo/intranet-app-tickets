<?php

declare(strict_types=1);

use Hwkdo\IntranetAppTickets\Services\TeamsTicketQuotedAttachmentResolver;
use Hwkdo\MsGraphLaravel\Services\TeamsChatMessageMediaService;
use Illuminate\Http\UploadedFile;

it('returns uploaded files from quoted message hosted contents', function (): void {
    $pngBytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');

    $mediaService = Mockery::mock(TeamsChatMessageMediaService::class);
    $mediaService->shouldReceive('fetchHostedContents')
        ->once()
        ->with('19:chat@thread.v2', '1783060714709')
        ->andReturn([
            [
                'filename' => 'teams-bild-1.png',
                'mimeType' => 'image/png',
                'contents' => $pngBytes,
            ],
        ]);

    $files = (new TeamsTicketQuotedAttachmentResolver($mediaService))->resolve(
        '19:chat@thread.v2',
        '1783060714709',
    );

    expect($files)->toHaveCount(1)
        ->and($files[0])->toBeInstanceOf(UploadedFile::class)
        ->and($files[0]->getClientOriginalName())->toBe('teams-bild-1.png')
        ->and($files[0]->getMimeType())->toBe('image/png');
});

it('returns empty list when conversation or message id is missing', function (): void {
    $mediaService = Mockery::mock(TeamsChatMessageMediaService::class);
    $mediaService->shouldNotReceive('fetchHostedContents');

    $resolver = new TeamsTicketQuotedAttachmentResolver($mediaService);

    expect($resolver->resolve(null, '1783060714709'))->toBe([])
        ->and($resolver->resolve('19:chat@thread.v2', null))->toBe([]);
});

it('returns empty list when media download fails', function (): void {
    $mediaService = Mockery::mock(TeamsChatMessageMediaService::class);
    $mediaService->shouldReceive('fetchHostedContents')
        ->once()
        ->andThrow(new RuntimeException('Graph error'));

    $files = (new TeamsTicketQuotedAttachmentResolver($mediaService))->resolve(
        '19:chat@thread.v2',
        '1783060714709',
    );

    expect($files)->toBe([]);
});
