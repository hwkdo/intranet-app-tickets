<?php

declare(strict_types=1);

use Hwkdo\IntranetAppTickets\Services\TeamsTicketCommandParser;

beforeEach(function (): void {
    $this->parser = new TeamsTicketCommandParser;
});

it('parses a natural language create ticket command', function (): void {
    $parsed = $this->parser->parse('erstelle mir ein Ticket, dass der Drucker im 2. OG defekt ist');

    expect($parsed)->not->toBeNull()
        ->and($parsed->body)->toBe('der Drucker im 2. OG defekt ist')
        ->and($parsed->subject)->toBe('der Drucker im 2. OG defekt ist');
});

it('parses a colon based command', function (): void {
    $parsed = $this->parser->parse('Ticket: Monitor bleibt schwarz');

    expect($parsed)->not->toBeNull()
        ->and($parsed->body)->toBe('Monitor bleibt schwarz');
});

it('parses ticket erstellen with trailing verb', function (): void {
    $parsed = $this->parser->parse('kannst du ein Ticket erstellen dass das WLAN nicht funktioniert');

    expect($parsed)->not->toBeNull()
        ->and($parsed->body)->toBe('das WLAN nicht funktioniert');
});

it('strips filler words like über', function (): void {
    $parsed = $this->parser->parse('erstell ein ticket über die kaputte Maus');

    expect($parsed)->not->toBeNull()
        ->and($parsed->body)->toBe('die kaputte Maus');
});

it('returns a command without content when no description is given', function (): void {
    $parsed = $this->parser->parse('erstelle ein Ticket');

    expect($parsed)->not->toBeNull()
        ->and($parsed->hasContent())->toBeFalse();
});

it('limits the subject to 120 characters', function (): void {
    $long = str_repeat('a', 200);
    $parsed = $this->parser->parse('Ticket: '.$long);

    expect($parsed)->not->toBeNull()
        ->and(mb_strlen($parsed->subject))->toBe(120)
        ->and($parsed->body)->toBe($long);
});

it('does not match unrelated messages', function (): void {
    expect($this->parser->parse('Hallo, wie geht es dir?'))->toBeNull()
        ->and($this->parser->parse('Wann ist das nächste Meeting?'))->toBeNull()
        ->and($this->parser->parse(''))->toBeNull();
});
