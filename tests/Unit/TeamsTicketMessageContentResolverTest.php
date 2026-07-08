<?php

declare(strict_types=1);

use Hwkdo\IntranetAppTickets\Services\TeamsTicketMessageContentResolver;

beforeEach(function (): void {
    $this->resolver = new TeamsTicketMessageContentResolver;
});

it('uses quoted text when the command only contains a placeholder', function (): void {
    $resolved = $this->resolver->resolve(
        parsedBody: 'dafür',
        quotedText: 'Der Drucker im 2. OG druckt nur leere Seiten.',
        quotedSenderName: 'Anna Beispiel',
        isDirectMessage: false,
    );

    expect($resolved->contentFromQuote)->toBeTrue()
        ->and($resolved->content)->toBe("Zitierte Nachricht von Anna Beispiel:\nDer Drucker im 2. OG druckt nur leere Seiten.");
});

it('uses forwarded text in direct messages and assigns the ticket to the original sender', function (): void {
    $resolved = $this->resolver->resolve(
        parsedBody: 'dafür',
        quotedText: 'Der Drucker im 2. OG druckt nur leere Seiten.',
        quotedSenderName: 'Anna Beispiel',
        isDirectMessage: true,
    );

    expect($resolved->contentFromQuote)->toBeTrue()
        ->and($resolved->content)->toBe("Weitergeleitete Nachricht von Anna Beispiel:\nDer Drucker im 2. OG druckt nur leere Seiten.");
});

it('uses quoted text when the parsed body is empty', function (): void {
    $resolved = $this->resolver->resolve(
        parsedBody: '',
        quotedText: 'WLAN im Besprechungsraum fällt ständig aus',
        isDirectMessage: false,
    );

    expect($resolved->contentFromQuote)->toBeTrue()
        ->and($resolved->content)->toBe('WLAN im Besprechungsraum fällt ständig aus');
});

it('uses forwarded text in direct messages when the parsed body is empty', function (): void {
    $resolved = $this->resolver->resolve(
        parsedBody: '',
        quotedText: 'WLAN im Besprechungsraum fällt ständig aus',
        quotedSenderName: 'Anna Beispiel',
        isDirectMessage: true,
    );

    expect($resolved->contentFromQuote)->toBeTrue()
        ->and($resolved->content)->toBe("Weitergeleitete Nachricht von Anna Beispiel:\nWLAN im Besprechungsraum fällt ständig aus");
});

it('keeps explicit ticket content when a real description was provided', function (): void {
    $resolved = $this->resolver->resolve(
        parsedBody: 'Monitor bleibt schwarz',
        quotedText: 'Tastatur reagiert nicht mehr',
    );

    expect($resolved->contentFromQuote)->toBeFalse()
        ->and($resolved->content)->toBe('Monitor bleibt schwarz');
});
