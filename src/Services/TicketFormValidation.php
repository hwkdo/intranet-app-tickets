<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppTickets\Services;

use Carbon\Carbon;
use Hwkdo\IntranetAppTickets\Enums\TicketFormType;
use Hwkdo\IntranetAppTickets\Rules\ValidTicketAttachment;
use Illuminate\Validation\Rule;

class TicketFormValidation
{
    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function filterFormData(TicketFormType $form, array $data): array
    {
        if ($form === TicketFormType::ItGestuetztePruefung) {
            $data = $this->combineOptionalDateTimes($data);
            $data = $this->normalizePruefungstermine($data);
        }

        return collect($data)
            ->only($this->persistedFieldKeys($form, $data))
            ->reject(fn (mixed $value): bool => $value === null || $value === '')
            ->all();
    }

    /**
     * @return list<string>
     */
    public function persistedFieldKeys(TicketFormType $form, array $data = []): array
    {
        return match ($form) {
            TicketFormType::ItSupport,
            TicketFormType::Hausmeisterservice,
            TicketFormType::Moodle => [
                'betreff',
                'inhalt',
                'on_behalf_of_user_id',
            ],
            TicketFormType::Webchange => [
                'betreff',
                'auswahl',
                'url',
                'inhalt',
                'abgestimmt_mit',
            ],
            TicketFormType::Marketing => [
                'betreff',
                'art',
                'geschaeftsbereich',
                'fachbereich',
                'auswahl',
                'inhalt',
                'datum',
                'bemerkung',
                'abgestimmt_mit',
                'jahresplanung',
            ],
            TicketFormType::Druckauftrag => [
                'meisterbrief',
                'inhalt',
                'datum',
                'Druckanzahl',
                'druckseitig',
                'farbe',
                'binden',
                'sortieren',
            ],
            TicketFormType::Vertragsmanagement => [
                'betreff',
                'vertragstyp',
                'betreff2',
                'inhalt',
            ],
            TicketFormType::Zollauktion => [
                'betreff',
                'anzahl',
                'inhalt',
                'etikettennummer',
                'foerderung',
                'mingebot',
                'kostenstellen',
                'Hersteller',
                'ArtTypBez',
                'Inventurnummer',
                'Baujahr',
                'Gewicht',
                'Masse',
                'Techdata',
                'cekenn',
                'wartung',
                'wartung_date',
                'standort',
                'ansprechpartner',
            ],
            TicketFormType::ItGestuetztePruefung => $this->isMultiPruefung($data)
                ? [
                    'betreff',
                    'pruefung_id',
                    'pruefungstermine',
                    'datum',
                    'gewerk',
                    'ansprechpartner',
                    'verwendete_anwendungen',
                    'weitere_wichtige_informationen',
                    'sperre_pruefungsbenutzer_ab',
                    'loeschung_pruefungsbenutzer_ab',
                ]
                : [
                    'betreff',
                    'pruefungstermin_id',
                    'pruefung_id',
                    'datum',
                    'gewerk',
                    'raeume',
                    'anzahl_teilnehmer',
                    'ansprechpartner',
                    'verwendete_anwendungen',
                    'weitere_wichtige_informationen',
                    'sperre_pruefungsbenutzer_ab',
                    'loeschung_pruefungsbenutzer_ab',
                ],
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function rulesFor(TicketFormType $form, bool $meisterbrief = false, bool $multiPruefung = false): array
    {
        return match ($form) {
            TicketFormType::ItSupport,
            TicketFormType::Hausmeisterservice,
            TicketFormType::Moodle => array_merge([
                'betreff' => ['required', 'string', 'max:150'],
                'inhalt' => ['required', 'string', 'max:3000'],
                'on_behalf_of_user_id' => ['required', 'integer', 'exists:users,id'],
            ], $this->attachmentRules()),
            TicketFormType::Webchange => array_merge([
                'betreff' => ['required', 'string', 'max:150'],
                'auswahl' => ['required', 'string', 'max:100'],
                'url' => ['required', 'url', 'max:150'],
                'inhalt' => ['required', 'string', 'max:3000'],
                'abgestimmt_mit' => ['required', 'integer', 'exists:users,id'],
            ], $this->attachmentRules()),
            TicketFormType::Marketing => array_merge([
                'betreff' => ['required', 'string', 'max:100'],
                'art' => ['required', 'string', 'max:100'],
                'geschaeftsbereich' => ['required', 'integer', 'exists:gvps,id'],
                'fachbereich' => ['required', 'string', 'max:255'],
                'auswahl' => ['required', 'string', 'max:100'],
                'inhalt' => ['required', 'string', 'max:3000'],
                'datum' => ['required', 'date', 'after_or_equal:'.now()->addWeeks(12)->toDateString()],
                'bemerkung' => ['nullable', 'string', 'max:200'],
                'abgestimmt_mit' => ['required', 'integer', 'exists:users,id'],
                'jahresplanung' => ['required', Rule::in(['Ja', 'Nein'])],
            ], $this->attachmentRules()),
            TicketFormType::Druckauftrag => $this->druckRules($meisterbrief),
            TicketFormType::Vertragsmanagement => array_merge([
                'betreff' => ['required', 'string', 'max:100'],
                'vertragstyp' => ['nullable', 'string', 'max:100'],
                'betreff2' => ['required', 'string', 'max:150'],
                'inhalt' => ['required', 'string', 'max:3000'],
            ], $this->attachmentRules(required: true)),
            TicketFormType::Zollauktion => array_merge([
                'betreff' => ['required', 'string', 'max:150'],
                'anzahl' => ['required', 'integer', 'min:1'],
                'inhalt' => ['required', 'string', 'max:3000'],
                'etikettennummer' => ['required', 'string', 'max:150'],
                'foerderung' => ['required', 'string', 'max:100'],
                'mingebot' => ['required', 'numeric', 'min:1'],
                'kostenstellen' => ['required', 'string', 'max:255'],
                'Hersteller' => ['required', 'string', 'max:255'],
                'ArtTypBez' => ['required', 'string', 'max:255'],
                'Inventurnummer' => ['required', 'string', 'max:255'],
                'Baujahr' => ['required', 'string', 'max:255'],
                'Gewicht' => ['required', 'string', 'max:255'],
                'Masse' => ['required', 'string', 'max:255'],
                'Techdata' => ['required', 'string', 'max:255'],
                'cekenn' => ['nullable', 'boolean'],
                'wartung' => ['nullable', 'boolean'],
                'wartung_date' => ['nullable', 'date'],
                'standort' => ['required', 'integer', 'exists:standorts,id'],
                'ansprechpartner' => ['required', 'integer', 'exists:users,id'],
            ], $this->attachmentRules()),
            TicketFormType::ItGestuetztePruefung => $multiPruefung
                ? [
                    'betreff' => ['required', 'string', 'max:200'],
                    'pruefung_id' => ['required', 'integer'],
                    'pruefungstermine' => ['required', 'array', 'min:2'],
                    'pruefungstermine.*.pruefungstermin_id' => ['required', 'integer'],
                    'pruefungstermine.*.raeume' => ['required', 'string', 'max:500'],
                    'pruefungstermine.*.anzahl_teilnehmer' => ['required', 'integer', 'min:0'],
                    'datum' => ['required', 'date'],
                    'gewerk' => ['required', 'string', 'max:500'],
                    'ansprechpartner' => ['required', 'string', 'max:500'],
                    'verwendete_anwendungen' => ['required', 'string', 'max:3000'],
                    'weitere_wichtige_informationen' => ['nullable', 'string', 'max:3000'],
                    'sperre_pruefungsbenutzer_ab_datum' => ['nullable', 'date', 'required_with:sperre_pruefungsbenutzer_ab_uhrzeit'],
                    'sperre_pruefungsbenutzer_ab_uhrzeit' => ['nullable', 'date_format:H:i', 'required_with:sperre_pruefungsbenutzer_ab_datum'],
                    'loeschung_pruefungsbenutzer_ab_datum' => ['nullable', 'date', 'required_with:loeschung_pruefungsbenutzer_ab_uhrzeit'],
                    'loeschung_pruefungsbenutzer_ab_uhrzeit' => ['nullable', 'date_format:H:i', 'required_with:loeschung_pruefungsbenutzer_ab_datum'],
                ]
                : [
                    'betreff' => ['required', 'string', 'max:200'],
                    'pruefungstermin_id' => ['required', 'integer'],
                    'pruefung_id' => ['required', 'integer'],
                    'datum' => ['required', 'date'],
                    'gewerk' => ['required', 'string', 'max:500'],
                    'raeume' => ['required', 'string', 'max:500'],
                    'anzahl_teilnehmer' => ['required', 'integer', 'min:0'],
                    'ansprechpartner' => ['required', 'string', 'max:500'],
                    'verwendete_anwendungen' => ['required', 'string', 'max:3000'],
                    'weitere_wichtige_informationen' => ['nullable', 'string', 'max:3000'],
                    'sperre_pruefungsbenutzer_ab_datum' => ['nullable', 'date', 'required_with:sperre_pruefungsbenutzer_ab_uhrzeit'],
                    'sperre_pruefungsbenutzer_ab_uhrzeit' => ['nullable', 'date_format:H:i', 'required_with:sperre_pruefungsbenutzer_ab_datum'],
                    'loeschung_pruefungsbenutzer_ab_datum' => ['nullable', 'date', 'required_with:loeschung_pruefungsbenutzer_ab_uhrzeit'],
                    'loeschung_pruefungsbenutzer_ab_uhrzeit' => ['nullable', 'date_format:H:i', 'required_with:loeschung_pruefungsbenutzer_ab_datum'],
                ],
        };
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalizePruefungstermine(array $data): array
    {
        if (! isset($data['pruefungstermine']) || ! is_array($data['pruefungstermine'])) {
            return $data;
        }

        $termine = collect($data['pruefungstermine'])
            ->filter(fn (mixed $termin): bool => is_array($termin))
            ->map(fn (array $termin): array => [
                'pruefungstermin_id' => isset($termin['pruefungstermin_id']) ? (int) $termin['pruefungstermin_id'] : null,
                'raeume' => isset($termin['raeume']) ? (string) $termin['raeume'] : '',
                'anzahl_teilnehmer' => isset($termin['anzahl_teilnehmer']) ? (int) $termin['anzahl_teilnehmer'] : null,
            ])
            ->values()
            ->all();

        if (count($termine) > 1) {
            $data['pruefungstermine'] = $termine;
            unset($data['pruefungstermin_id'], $data['raeume'], $data['anzahl_teilnehmer']);
        } else {
            unset($data['pruefungstermine']);
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function isMultiPruefung(array $data): bool
    {
        return isset($data['pruefungstermine'])
            && is_array($data['pruefungstermine'])
            && count($data['pruefungstermine']) > 1;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function combineOptionalDateTimes(array $data): array
    {
        foreach (['sperre_pruefungsbenutzer_ab', 'loeschung_pruefungsbenutzer_ab'] as $key) {
            $date = $data[$key.'_datum'] ?? null;
            $time = $data[$key.'_uhrzeit'] ?? null;

            if (is_string($date) && $date !== '' && is_string($time) && $time !== '') {
                $data[$key] = Carbon::parse($date.' '.$time)->format('Y-m-d H:i');
            }

            unset($data[$key.'_datum'], $data[$key.'_uhrzeit']);
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    private function attachmentRules(bool $required = false): array
    {
        return [
            'attachments' => array_filter([
                $required ? 'required' : 'nullable',
                'array',
                $required ? 'min:1' : null,
            ]),
            'attachments.*' => ['nullable', new ValidTicketAttachment],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function druckRules(bool $meisterbrief): array
    {
        $rules = array_merge([
            'meisterbrief' => ['nullable', 'boolean'],
            'inhalt' => ['required', 'string', 'max:3000'],
            'datum' => ['required', 'date', 'after_or_equal:'.now()->toDateString()],
        ], $this->attachmentRules());

        if (! $meisterbrief) {
            $rules['Druckanzahl'] = ['required', 'integer', 'min:1'];
            $rules['druckseitig'] = ['required', 'string', 'max:50'];
            $rules['farbe'] = ['required', 'string', 'max:50'];
        }

        return $rules;
    }
}
