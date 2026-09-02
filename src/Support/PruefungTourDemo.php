<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppTickets\Support;

use Illuminate\Support\Collection;

class PruefungTourDemo
{
    public const SESSION_KEY = 'intranet_tickets_pruefung_tour_demo';

    public const DEMO_DATUM = '2026-09-04';

    public static function isActive(): bool
    {
        return (bool) session(self::SESSION_KEY, false);
    }

    public static function enable(): void
    {
        session([self::SESSION_KEY => true]);
    }

    public static function disable(): void
    {
        session()->forget(self::SESSION_KEY);
    }

    /**
     * @return Collection<int, object>
     */
    public static function termineForDatum(string $datum): Collection
    {
        if (! self::isActive() || ! self::matchesDatum($datum)) {
            return collect();
        }

        return collect([
            (object) [
                'termin_id' => 990001,
                'termin_lfdnr' => 1,
                'pruefung_id' => 99001,
                'pruefung_bezeichnung' => 'Orthopädietechniker WH Sommer 2026 (Demo)',
                'termin_bezeichnung' => 'Abnahme und Bewertung der Meisterprüfungsarbeit',
                'ordnung' => 'Orthopädietechnikerhandwerk',
                'uhrzeit_von' => '09:00',
                'uhrzeit_bis' => '13:00',
                'pruefungsort_name' => '2309',
                'gebaeudenummer' => 'Bildungszentrum HWK Haus II',
                'raumnummer' => null,
                'anzahl_prueflinge' => 4,
                'bearbeiter_vorname' => 'Max',
                'bearbeiter_nachname' => 'Mustermann',
                'bearbeiter_telefon' => '0231 5493-100',
                'bearbeiter_email' => 'max.mustermann@example.com',
                'datum' => self::DEMO_DATUM.' 00:00:00',
            ],
            (object) [
                'termin_id' => 990001,
                'termin_lfdnr' => 2,
                'pruefung_id' => 99001,
                'pruefung_bezeichnung' => 'Orthopädietechniker WH Sommer 2026 (Demo)',
                'termin_bezeichnung' => 'Abnahme und Bewertung der Meisterprüfungsarbeit',
                'ordnung' => 'Orthopädietechnikerhandwerk',
                'uhrzeit_von' => '09:00',
                'uhrzeit_bis' => '13:00',
                'pruefungsort_name' => '2305',
                'gebaeudenummer' => 'Bildungszentrum HWK Haus II',
                'raumnummer' => null,
                'anzahl_prueflinge' => 4,
                'bearbeiter_vorname' => 'Max',
                'bearbeiter_nachname' => 'Mustermann',
                'bearbeiter_telefon' => '0231 5493-100',
                'bearbeiter_email' => 'max.mustermann@example.com',
                'datum' => self::DEMO_DATUM.' 00:00:00',
            ],
        ]);
    }

    public static function firstSelectionKey(): string
    {
        return '990001-1';
    }

    private static function matchesDatum(string $datum): bool
    {
        return str_starts_with($datum, self::DEMO_DATUM);
    }
}
