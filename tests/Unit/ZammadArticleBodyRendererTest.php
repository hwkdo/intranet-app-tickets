<?php

declare(strict_types=1);

use Hwkdo\IntranetAppTickets\Services\ZammadArticleBodyRenderer;

test('rewrites zammad ticket attachment urls to intranet download route', function (): void {
    $renderer = new ZammadArticleBodyRenderer;

    $body = '<img src="/api/v1/ticket_attachment/38922/147410/260174?view=inline">';

    $rendered = $renderer->render($body, 38922, 147410);

    expect($rendered)
        ->toContain(route('apps.tickets.attachments.download', [
            'ticketId' => 38922,
            'articleId' => 147410,
            'attachmentId' => 260174,
        ]))
        ->not->toContain('/api/v1/ticket_attachment/');
});

test('rewrites absolute zammad ticket attachment urls', function (): void {
    $renderer = new ZammadArticleBodyRenderer;

    $body = '<img src="https://ticket.example.com/api/v1/ticket_attachment/10/20/30?view=inline">';

    $rendered = $renderer->render($body, 10, 20);

    expect($rendered)->toContain(route('apps.tickets.attachments.download', [
        'ticketId' => 10,
        'articleId' => 20,
        'attachmentId' => 30,
    ]));
});

test('does not rewrite ticket attachment urls for a different ticket or article', function (): void {
    $renderer = new ZammadArticleBodyRenderer;

    $body = '<img src="/api/v1/ticket_attachment/99/147410/260174?view=inline">';

    $rendered = $renderer->render($body, 38922, 147410);

    expect($rendered)->toContain('/api/v1/ticket_attachment/99/147410/260174');
});

test('rewrites cid inline image references using attachment content id', function (): void {
    $renderer = new ZammadArticleBodyRenderer;

    $body = '<img style="width:100px" src="cid:image001@example.com" alt="screenshot">';

    $rendered = $renderer->render($body, 5, 37, [
        [
            'id' => 19,
            'filename' => 'image1.png',
            'preferences' => [
                'Content-ID' => 'image001@example.com',
                'Content-Type' => 'image/png',
            ],
        ],
    ]);

    expect($rendered)
        ->toContain(route('apps.tickets.attachments.download', [
            'ticketId' => 5,
            'articleId' => 37,
            'attachmentId' => 19,
        ]))
        ->not->toContain('cid:');
});

test('returns empty body unchanged', function (): void {
    $renderer = new ZammadArticleBodyRenderer;

    expect($renderer->render('', 1, 1))->toBe('');
});
