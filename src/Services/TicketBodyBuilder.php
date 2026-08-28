<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppTickets\Services;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

class TicketBodyBuilder
{
    /**
     * @var array<string, string>
     */
    private const FIELD_LABELS = [
        'pruefungstermin_id' => 'PrüfungsterminID',
        'pruefung_id' => 'PrüfungID',
        'datum' => 'Datum',
        'gewerk' => 'Gewerk (Ordnung)',
        'raeume' => 'Räume',
        'anzahl_teilnehmer' => 'Anzahl Teilnehmer',
        'ansprechpartner' => 'Ansprechpartner',
        'verwendete_anwendungen' => 'Verwendete Anwendungen',
        'weitere_wichtige_informationen' => 'Weitere Wichtige Informationen',
        'sperre_pruefungsbenutzer_ab' => 'Sperre Prüfungsbenutzer ab',
        'loeschung_pruefungsbenutzer_ab' => 'Löschung Prüfungsbenutzer ab',
    ];

    /**
     * @param  array<string, mixed>  $formData
     * @param  list<string>  $excludeKeys
     */
    public function build(
        string $baseContent,
        array $formData,
        array $excludeKeys = [],
        ?Authenticatable $requester = null,
        ?Authenticatable $onBehalfOf = null,
        ?Authenticatable $supervisor = null,
        ?string $standortName = null,
        ?string $ansprechpartnerName = null,
    ): string {
        $body = trim($baseContent);

        $defaultExclude = [
            'betreff',
            'betreff2',
            'inhalt',
            'ticket_fuer',
            'on_behalf_of_user_id',
            'abgestimmt_mit',
            'geschaeftsbereich',
            'standort',
            'attachments',
            '_token',
            '_method',
            'pruefungstermine',
        ];

        if ($ansprechpartnerName !== null) {
            $defaultExclude[] = 'ansprechpartner';
        }

        $exclude = array_merge($defaultExclude, $excludeKeys);

        if ($this->isMultiPruefung($formData)) {
            /** @var list<array{pruefungstermin_id: mixed, raeume: mixed, anzahl_teilnehmer: mixed}> $termine */
            $termine = array_values($formData['pruefungstermine']);
            $count = count($termine);

            $body = $this->appendLine($body, 'Die Prüfung findet in '.$count.' Räumen statt.');

            if (isset($formData['pruefung_id']) && $formData['pruefung_id'] !== null && $formData['pruefung_id'] !== '') {
                $body = $this->appendLine($body, 'PrüfungID: '.(string) $formData['pruefung_id']);
                $exclude[] = 'pruefung_id';
            }

            foreach ($termine as $termin) {
                $body = $this->appendLine($body, 'PrüfungsterminID: '.(string) $termin['pruefungstermin_id']);
                $body = $this->appendLine($body, 'Raum: '.(string) $termin['raeume']);
                $body = $this->appendLine($body, 'Anzahl Teilnehmer: '.(string) $termin['anzahl_teilnehmer']);
            }
        }

        foreach ($formData as $key => $value) {
            if (in_array($key, $exclude, true) || $value === null || $value === '') {
                continue;
            }

            if (is_bool($value)) {
                $value = $value ? 'Ja' : 'Nein';
            }

            if (is_array($value)) {
                continue;
            }

            $label = self::FIELD_LABELS[$key] ?? ucfirst(str_replace('_', ' ', (string) $key));
            $body = $this->appendLine($body, $label.': '.(string) $value);
        }

        if ($supervisor instanceof Authenticatable) {
            $body = $this->appendLine($body, 'Abgestimmt mit: '.$this->userDisplayName($supervisor));
        }

        if ($onBehalfOf instanceof Authenticatable && $requester instanceof Authenticatable) {
            $body = $this->appendLine($body, 'Erstellt von: '.$this->userDisplayName($requester));
            $body = $this->appendLine($body, 'Erstellt für: '.$this->userDisplayName($onBehalfOf));
        }

        if ($ansprechpartnerName !== null) {
            $body = $this->appendLine($body, 'Ansprechpartner für Auktionsgut: '.$ansprechpartnerName);
        }

        if ($standortName !== null) {
            $body = $this->appendLine($body, 'Standort der Auktionsware: '.$standortName);
        }

        return $body;
    }

    public function buildSubject(string $betreff, ?string $betreff2 = null, ?int $requestId = null): string
    {
        $subject = $betreff2 !== null && $betreff2 !== ''
            ? $betreff.' ('.$betreff2.')'
            : $betreff;

        if ($requestId !== null) {
            $subject .= ' (#'.$requestId.')';
        }

        return $subject;
    }

    /**
     * @param  array<string, mixed>  $formData
     */
    private function isMultiPruefung(array $formData): bool
    {
        return isset($formData['pruefungstermine'])
            && is_array($formData['pruefungstermine'])
            && count($formData['pruefungstermine']) > 1;
    }

    private function appendLine(string $body, string $line): string
    {
        $line = trim($line);

        if ($line === '') {
            return $body;
        }

        if ($body === '') {
            return $line;
        }

        return $body."\n\n".$line;
    }

    private function userDisplayName(Authenticatable $user): string
    {
        if ($user instanceof Model && isset($user->name)) {
            return (string) $user->name;
        }

        return (string) ($user->email ?? 'Unbekannt');
    }
}
