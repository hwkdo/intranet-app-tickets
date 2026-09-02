<?php

declare(strict_types=1);

use App\Models\Gvp;
use App\Models\Standort;
use App\Models\User;
use Carbon\Carbon;
use Flux\Flux;
use Hwkdo\BueLaravel\Facades\BueLaravel;
use Hwkdo\IntranetAppTickets\Enums\TicketFormType;
use Hwkdo\IntranetAppTickets\Enums\TransmissionChannel;
use Hwkdo\IntranetAppTickets\Models\TicketCategory;
use Hwkdo\IntranetAppTickets\Services\TicketFormValidation;
use Hwkdo\IntranetAppTickets\Services\TicketSubmissionService;
use Hwkdo\IntranetAppTickets\Support\PruefungTourDemo;
use Illuminate\Support\Facades\Auth;
use Livewire\WithFileUploads;

use function Livewire\Volt\computed;
use function Livewire\Volt\mount;
use function Livewire\Volt\state;
use function Livewire\Volt\title;
use function Livewire\Volt\uses;

uses([WithFileUploads::class]);

state([
    'categoryId' => null,
    'betreff' => '',
    'betreff2' => '',
    'inhalt' => '',
    'on_behalf_of_user_id' => null,
    'auswahl' => '',
    'art' => '',
    'url' => '',
    'abgestimmt_mit' => null,
    'geschaeftsbereich' => null,
    'fachbereich' => '',
    'datum' => '',
    'bemerkung' => '',
    'jahresplanung' => '',
    'meisterbrief' => false,
    'Druckanzahl' => null,
    'druckseitig' => '',
    'farbe' => '',
    'binden' => '',
    'sortieren' => '',
    'vertragstyp' => '',
    'anzahl' => null,
    'etikettennummer' => '',
    'foerderung' => 'Ja',
    'mingebot' => null,
    'kostenstellen' => '',
    'Hersteller' => '',
    'ArtTypBez' => '',
    'Inventurnummer' => '',
    'Baujahr' => '',
    'Gewicht' => '',
    'Masse' => '',
    'Techdata' => '',
    'cekenn' => false,
    'wartung' => false,
    'wartung_date' => '',
    'standort' => null,
    'ansprechpartner' => null,
    'attachments' => [null],
    'pruefung_step' => 1,
    'pruefung_datum' => '',
    'pruefung_termine' => [],
    'pruefung_selected_ids' => [],
    'pruefung_selected_pruefung_id' => null,
    'pruefungstermin_id' => null,
    'pruefung_id' => null,
    'pruefungstermine' => [],
    'gewerk' => '',
    'raeume' => '',
    'anzahl_teilnehmer' => null,
    'verwendete_anwendungen' => '',
    'weitere_wichtige_informationen' => '',
    'personalisierte_pruefungsbenutzer' => 'Nein',
    'sperre_pruefungsbenutzer_ab_datum' => '',
    'sperre_pruefungsbenutzer_ab_uhrzeit' => '',
    'loeschung_pruefungsbenutzer_ab_datum' => '',
    'loeschung_pruefungsbenutzer_ab_uhrzeit' => '',
]);

$category = computed(fn () => TicketCategory::query()->findOrFail($this->categoryId));

mount(function (TicketCategory $category) {
    if (! $category->active) {
        abort(404);
    }

    $this->categoryId = $category->id;
    $this->on_behalf_of_user_id = Auth::id();
    $this->abgestimmt_mit = Auth::user()->getVorgesetzte()->first()?->id;
    $this->standort = Auth::user()->standort_id ?? null;
    $this->ansprechpartner = Auth::id();

    if ($category->form === TicketFormType::Druckauftrag) {
        $this->betreff = 'Druckauftrag';
    }

    if ($category->form === TicketFormType::ItGestuetztePruefung) {
        $this->ansprechpartner = '';
        $this->pruefung_step = 1;
    }
});

$employees = computed(fn () => User::query()->aktiv()->orderBy('vorname')->orderBy('nachname')->get(['id', 'vorname', 'nachname']));
$supervisors = computed(fn () => Auth::user()->getVorgesetzte());
$geschaeftsbereiche = computed(fn () => Gvp::query()->where('kuerzel', 'HGF')->first()?->childGvps()->orderBy('name')->get() ?? collect());
$fachbereiche = computed(function () {
    if (! $this->geschaeftsbereich) {
        return collect();
    }

    return Gvp::query()->find($this->geschaeftsbereich)?->childGvps()->orderBy('name')->get() ?? collect();
});
$standorte = computed(fn () => Standort::query()->orderBy('name')->get(['id', 'name']));

$addAttachment = fn () => $this->attachments[] = null;

$removeAttachment = function (int $index): void {
    unset($this->attachments[$index]);
    $this->attachments = array_values($this->attachments);

    if ($this->attachments === []) {
        $this->attachments = [null];
    }
};

$buildPruefungTerminSelectionKey = function (array $data): string {
    $terminId = (int) ($data['termin_id'] ?? 0);
    $lfdnr = $data['termin_lfdnr'] ?? null;

    if ($lfdnr !== null && $lfdnr !== '' && (int) $lfdnr > 0) {
        return $terminId.'-'.(int) $lfdnr;
    }

    $parts = array_filter([
        (string) $terminId,
        isset($data['pruefungsort_id']) && $data['pruefungsort_id'] !== '' ? (string) $data['pruefungsort_id'] : null,
        ($data['pruefungsort_name'] ?? '') !== '' ? (string) $data['pruefungsort_name'] : null,
        ($data['gebaeudenummer'] ?? '') !== '' ? (string) $data['gebaeudenummer'] : null,
        ($data['raumnummer'] ?? '') !== '' ? (string) $data['raumnummer'] : null,
        ($data['termin_bezeichnung'] ?? '') !== '' ? (string) $data['termin_bezeichnung'] : null,
        ($data['uhrzeit_von'] ?? '') !== '' ? (string) $data['uhrzeit_von'] : null,
        ($data['uhrzeit_bis'] ?? '') !== '' ? (string) $data['uhrzeit_bis'] : null,
    ], fn (?string $part): bool => $part !== null && $part !== '');

    return implode('|', $parts);
};

$ensureUniquePruefungTerminSelectionKeys = function (array $termine) use ($buildPruefungTerminSelectionKey): array {
    $keyCounts = [];

    foreach ($termine as $index => $termin) {
        $key = $buildPruefungTerminSelectionKey($termin);

        if (array_key_exists($key, $keyCounts)) {
            $keyCounts[$key]++;
            $key = $key.'#'.$keyCounts[$key];
        } else {
            $keyCounts[$key] = 0;
        }

        $termine[$index]['selection_key'] = $key;
    }

    return $termine;
};

$mapPruefungTerminRow = function (object|array $row) use ($buildPruefungTerminSelectionKey): array {
    $data = array_change_key_case(is_array($row) ? $row : (array) $row, CASE_LOWER);

    $mapped = [
        'termin_id' => (int) ($data['termin_id'] ?? 0),
        'termin_lfdnr' => isset($data['termin_lfdnr']) ? (int) $data['termin_lfdnr'] : null,
        'pruefung_id' => isset($data['pruefung_id']) ? (int) $data['pruefung_id'] : null,
        'pruefung_bezeichnung' => (string) ($data['pruefung_bezeichnung'] ?? ''),
        'termin_bezeichnung' => (string) ($data['termin_bezeichnung'] ?? ''),
        'ordnung' => (string) ($data['ordnung'] ?? ''),
        'uhrzeit_von' => (string) ($data['uhrzeit_von'] ?? ''),
        'uhrzeit_bis' => (string) ($data['uhrzeit_bis'] ?? ''),
        'pruefungsort_id' => isset($data['pruefungsort_id']) ? (int) $data['pruefungsort_id'] : null,
        'pruefungsort_name' => (string) ($data['pruefungsort_name'] ?? ''),
        'gebaeudenummer' => (string) ($data['gebaeudenummer'] ?? ''),
        'raumnummer' => (string) ($data['raumnummer'] ?? ''),
        'anzahl_prueflinge' => $data['anzahl_prueflinge'] ?? null,
        'bearbeiter_vorname' => (string) ($data['bearbeiter_vorname'] ?? ''),
        'bearbeiter_nachname' => (string) ($data['bearbeiter_nachname'] ?? ''),
        'bearbeiter_telefon' => (string) ($data['bearbeiter_telefon'] ?? ''),
        'bearbeiter_email' => (string) ($data['bearbeiter_email'] ?? ''),
        'datum' => (string) ($data['datum'] ?? ''),
    ];

    $mapped['selection_key'] = $buildPruefungTerminSelectionKey($mapped);

    return $mapped;
};

$formatPruefungRaum = function (array $termin): string {
    return implode(', ', array_filter([
        ($termin['pruefungsort_name'] ?? '') !== '' ? $termin['pruefungsort_name'] : null,
        ($termin['gebaeudenummer'] ?? '') !== '' ? $termin['gebaeudenummer'] : null,
        ($termin['raumnummer'] ?? '') !== '' ? $termin['raumnummer'] : null,
    ]));
};

$formatPruefungAnsprechpartner = function (array $termin): string {
    return implode(', ', array_filter([
        trim(($termin['bearbeiter_vorname'] ?? '').' '.($termin['bearbeiter_nachname'] ?? '')) ?: null,
        ($termin['bearbeiter_telefon'] ?? '') !== '' ? $termin['bearbeiter_telefon'] : null,
        ($termin['bearbeiter_email'] ?? '') !== '' ? $termin['bearbeiter_email'] : null,
    ]));
};

$loadPruefungen = function (): void {
    $this->validate([
        'pruefung_datum' => ['required', 'date'],
    ], [], [
        'pruefung_datum' => 'Datum',
    ]);

    $rows = PruefungTourDemo::isActive()
        ? PruefungTourDemo::termineForDatum($this->pruefung_datum)
        : BueLaravel::getTicketPruefungenByDatum($this->pruefung_datum);

    $termine = $rows
        ->map(fn (object $row): array => $this->mapPruefungTerminRow($row))
        ->values()
        ->all();

    $this->pruefung_termine = $this->ensureUniquePruefungTerminSelectionKeys($termine);
    $this->pruefung_selected_ids = [];
    $this->pruefung_selected_pruefung_id = null;
    $this->pruefung_step = 2;
};

$togglePruefungTermin = function (string $selectionKey): void {
    $termin = collect($this->pruefung_termine)
        ->first(fn (array $row): bool => $row['selection_key'] === $selectionKey);

    if ($termin === null) {
        Flux::toast(heading: 'Fehler', text: 'Der gewählte Prüfungstermin wurde nicht gefunden.', variant: 'danger');

        return;
    }

    $selectedKeys = collect($this->pruefung_selected_ids)->map(fn ($key): string => (string) $key)->all();
    $isSelected = in_array($selectionKey, $selectedKeys, true);

    if ($isSelected) {
        $selectedKeys = array_values(array_filter($selectedKeys, fn (string $key): bool => $key !== $selectionKey));
        $this->pruefung_selected_ids = $selectedKeys;
        $this->pruefung_selected_pruefung_id = $selectedKeys === []
            ? null
            : (int) $this->pruefung_selected_pruefung_id;

        return;
    }

    $pruefungId = isset($termin['pruefung_id']) ? (int) $termin['pruefung_id'] : null;

    if ($this->pruefung_selected_pruefung_id !== null && $pruefungId !== (int) $this->pruefung_selected_pruefung_id) {
        Flux::toast(
            heading: 'Hinweis',
            text: 'Es können nur Termine derselben Prüfung gemeinsam ausgewählt werden.',
            variant: 'warning',
        );

        return;
    }

    $selectedKeys[] = $selectionKey;
    $this->pruefung_selected_ids = $selectedKeys;
    $this->pruefung_selected_pruefung_id = $pruefungId;
};

$confirmPruefungTermine = function (): void {
    $selectedKeys = collect($this->pruefung_selected_ids)->map(fn ($key): string => (string) $key)->unique()->values();

    if ($selectedKeys->isEmpty()) {
        Flux::toast(heading: 'Hinweis', text: 'Bitte mindestens einen Prüfungstermin auswählen.', variant: 'warning');

        return;
    }

    $selectedTermine = collect($this->pruefung_termine)
        ->filter(fn (array $termin): bool => $selectedKeys->contains($termin['selection_key']))
        ->sortBy(fn (array $termin): int => (int) $selectedKeys->search($termin['selection_key']))
        ->values();

    if ($selectedTermine->count() !== $selectedKeys->count()) {
        Flux::toast(heading: 'Fehler', text: 'Mindestens ein gewählter Prüfungstermin ist ungültig.', variant: 'danger');

        return;
    }

    $pruefungIds = $selectedTermine->pluck('pruefung_id')->unique()->filter()->values();

    if ($pruefungIds->count() > 1) {
        Flux::toast(
            heading: 'Fehler',
            text: 'Es können nur Termine derselben Prüfung gemeinsam ausgewählt werden.',
            variant: 'danger',
        );

        return;
    }

    $first = $selectedTermine->first();
    $bezeichnung = ($first['pruefung_bezeichnung'] ?? '') !== ''
        ? $first['pruefung_bezeichnung']
        : ($first['termin_bezeichnung'] ?? '');

    $betreff = 'IT-gestützte Prüfung: '.($bezeichnung !== '' ? $bezeichnung : 'Termin '.$first['termin_id']);

    if ($selectedTermine->count() > 1) {
        $betreff .= ' ('.$selectedTermine->count().' Räume)';
    }

    $this->datum = Carbon::parse(($first['datum'] ?? '') !== '' ? $first['datum'] : $this->pruefung_datum)->toDateString();
    $this->gewerk = (string) ($first['ordnung'] ?? '');
    $this->ansprechpartner = $this->formatPruefungAnsprechpartner($first);
    $this->betreff = $betreff;

    if ($selectedTermine->count() === 1) {
        $this->pruefungstermin_id = (int) $first['termin_id'];
        $this->pruefung_id = isset($first['pruefung_id']) ? (int) $first['pruefung_id'] : null;
        $this->raeume = $this->formatPruefungRaum($first);
        $this->anzahl_teilnehmer = (int) ($first['anzahl_prueflinge'] ?? 0);
        $this->pruefungstermine = [];
    } else {
        $this->pruefungstermin_id = null;
        $this->pruefung_id = isset($first['pruefung_id']) ? (int) $first['pruefung_id'] : null;
        $this->raeume = '';
        $this->anzahl_teilnehmer = null;
        $this->pruefungstermine = $selectedTermine
            ->map(fn (array $termin): array => [
                'pruefungstermin_id' => (int) $termin['termin_id'],
                'raeume' => $this->formatPruefungRaum($termin),
                'anzahl_teilnehmer' => (int) ($termin['anzahl_prueflinge'] ?? 0),
            ])
            ->all();
    }

    $this->pruefung_step = 3;
};

$pruefungZurueck = function (): void {
    if ($this->pruefung_step <= 1) {
        return;
    }

    $this->pruefung_step--;

    if ($this->pruefung_step === 1) {
        $this->pruefung_termine = [];
        $this->pruefung_selected_ids = [];
        $this->pruefung_selected_pruefung_id = null;
        $this->pruefungstermin_id = null;
        $this->pruefung_id = null;
        $this->pruefungstermine = [];
    }
};

$resetPruefungForTour = function (): void {
    if ($this->category->form !== TicketFormType::ItGestuetztePruefung) {
        return;
    }

    $this->pruefung_step = 1;
    $this->pruefung_datum = '';
    $this->pruefung_termine = [];
    $this->pruefung_selected_ids = [];
    $this->pruefung_selected_pruefung_id = null;
    $this->pruefungstermin_id = null;
    $this->pruefung_id = null;
    $this->pruefungstermine = [];
    $this->betreff = '';
    $this->gewerk = '';
    $this->raeume = '';
    $this->anzahl_teilnehmer = null;
    $this->verwendete_anwendungen = '';
    $this->weitere_wichtige_informationen = '';
    $this->personalisierte_pruefungsbenutzer = 'Nein';
    $this->sperre_pruefungsbenutzer_ab_datum = '';
    $this->sperre_pruefungsbenutzer_ab_uhrzeit = '';
    $this->loeschung_pruefungsbenutzer_ab_datum = '';
    $this->loeschung_pruefungsbenutzer_ab_uhrzeit = '';
};

$submit = function (): void {
    if ($this->category->form === TicketFormType::ItGestuetztePruefung && $this->pruefung_step !== 3) {
        return;
    }

    $isMultiPruefung = $this->category->form === TicketFormType::ItGestuetztePruefung
        && is_array($this->pruefungstermine)
        && count($this->pruefungstermine) > 1;

    $needsPersonalisierteBenutzer = $this->category->form === TicketFormType::ItGestuetztePruefung
        && $this->personalisierte_pruefungsbenutzer === 'Ja';

    $rules = app(TicketFormValidation::class)->rulesFor(
        $this->category->form,
        (bool) $this->meisterbrief,
        $isMultiPruefung,
        $needsPersonalisierteBenutzer,
    );
    $validated = $this->validate($rules);

    if ($this->category->form === TicketFormType::ItGestuetztePruefung && ! PruefungTourDemo::isActive()) {
        $terminIds = $isMultiPruefung
            ? collect($validated['pruefungstermine'])->pluck('pruefungstermin_id')->map(fn ($id): int => (int) $id)
            : collect([(int) $validated['pruefungstermin_id']]);

        $resolved = $terminIds
            ->map(fn (int $terminId) => BueLaravel::getTicketPruefungByTerminId($terminId))
            ->filter();

        if ($resolved->count() !== $terminIds->count()) {
            Flux::toast(heading: 'Fehler', text: 'Mindestens ein gewählter Prüfungstermin ist ungültig.', variant: 'danger');

            return;
        }

        $pruefungIds = $resolved
            ->map(fn (object $row): int => (int) $row->pruefung_id)
            ->unique()
            ->values();

        if ($pruefungIds->count() > 1) {
            Flux::toast(
                heading: 'Fehler',
                text: 'Es können nur Termine derselben Prüfung gemeinsam ausgewählt werden.',
                variant: 'danger',
            );

            return;
        }
    }

    $files = collect($this->attachments)->filter()->values()->all();

    if ($this->category->form === TicketFormType::Vertragsmanagement) {
        $validated['attachments'] = $files;
    }

    $onBehalfOf = isset($validated['on_behalf_of_user_id'])
        ? User::query()->find($validated['on_behalf_of_user_id'])
        : null;
    $supervisor = isset($validated['abgestimmt_mit'])
        ? User::query()->find($validated['abgestimmt_mit'])
        : null;
    $ansprechpartner = isset($validated['ansprechpartner']) && is_numeric($validated['ansprechpartner'])
        ? User::query()->find($validated['ansprechpartner'])
        : null;
    $standortName = isset($validated['standort'])
        ? Standort::query()->find($validated['standort'])?->name
        : null;

    $formData = app(TicketFormValidation::class)->filterFormData($this->category->form, $validated);

    if ($this->category->form === TicketFormType::Marketing && isset($validated['geschaeftsbereich'])) {
        $formData['geschaeftsbereich'] = Gvp::query()->find($validated['geschaeftsbereich'])?->bezeichnung;
    }

    try {
        $request = app(TicketSubmissionService::class)->submit(
            category: $this->category,
            formData: $formData,
            files: $files,
            requester: Auth::user(),
            onBehalfOf: $onBehalfOf,
            supervisor: $supervisor,
            standortName: $standortName,
            ansprechpartnerName: $ansprechpartner?->name,
        );
    } catch (Throwable $e) {
        Flux::toast(heading: 'Fehler', text: $e->getMessage(), variant: 'danger');

        return;
    }

    Flux::toast(
        heading: 'Erfolg',
        text: $this->category->requires_approval
            ? 'Ihre Anfrage wurde zur Genehmigung eingereicht.'
            : 'Ihr Ticket wurde übermittelt.',
        variant: 'success',
    );

    $this->redirect(route('apps.tickets.requests.show', $request), navigate: true);
};

title(fn () => 'Neues Ticket: '.($this->category->label ?? ''));

?>

<div>
    <x-intranet-app-tickets::tickets-layout
        :heading="'Neues Ticket: '.$this->category->label"
        subheading="Formular ausfüllen und absenden"
    >
        <form wire:submit="submit" class="mx-auto max-w-4xl space-y-6" data-tour="tickets-create-form">
            @if ($this->category->transmission === TransmissionChannel::Email)
                <flux:callout variant="info" icon="envelope">
                    Dieses Ticket wird per E-Mail übermittelt.
                </flux:callout>
            @endif

            @if ($this->category->requires_approval)
                <flux:callout variant="warning" icon="clock">
                    Dieses Ticket muss vor der Übermittlung genehmigt werden.
                </flux:callout>
            @endif

            @include('intranet-app-tickets::livewire.apps.tickets.create.forms.'.$this->category->form->value)

            <div class="flex gap-3">
                @if ($this->category->form !== TicketFormType::ItGestuetztePruefung || $pruefung_step === 3)
                    <flux:button
                        type="submit"
                        variant="primary"
                        wire:loading.attr="disabled"
                        wire:target="submit, attachments"
                    >
                        {{ $this->category->requires_approval ? 'Anfrage senden' : 'Ticket senden' }}
                    </flux:button>
                @endif
                <flux:button href="{{ route('apps.tickets.create.index') }}" variant="ghost" wire:navigate>
                    Abbrechen
                </flux:button>
            </div>
        </form>
    </x-intranet-app-tickets::tickets-layout>
</div>
