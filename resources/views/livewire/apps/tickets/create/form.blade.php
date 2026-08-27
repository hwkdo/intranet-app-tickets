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
use Illuminate\Support\Facades\Auth;
use Livewire\WithFileUploads;
use function Livewire\Volt\{computed, mount, state, title, uses};

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
    'pruefungstermin_id' => null,
    'gewerk' => '',
    'raeume' => '',
    'anzahl_teilnehmer' => null,
    'verwendete_anwendungen' => '',
    'weitere_wichtige_informationen' => '',
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

$loadPruefungen = function (): void {
    $this->validate([
        'pruefung_datum' => ['required', 'date'],
    ], [], [
        'pruefung_datum' => 'Datum',
    ]);

    $termine = BueLaravel::getTicketPruefungenByDatum($this->pruefung_datum)
        ->map(fn (object $row): array => [
            'termin_id' => (int) $row->termin_id,
            'pruefung_bezeichnung' => (string) ($row->pruefung_bezeichnung ?? ''),
            'termin_bezeichnung' => (string) ($row->termin_bezeichnung ?? ''),
            'ordnung' => (string) ($row->ordnung ?? ''),
            'uhrzeit_von' => (string) ($row->uhrzeit_von ?? ''),
            'uhrzeit_bis' => (string) ($row->uhrzeit_bis ?? ''),
            'pruefungsort_name' => (string) ($row->pruefungsort_name ?? ''),
            'gebaeudenummer' => (string) ($row->gebaeudenummer ?? ''),
            'raumnummer' => (string) ($row->raumnummer ?? ''),
            'anzahl_prueflinge' => $row->anzahl_prueflinge,
            'bearbeiter_vorname' => (string) ($row->bearbeiter_vorname ?? ''),
            'bearbeiter_nachname' => (string) ($row->bearbeiter_nachname ?? ''),
            'bearbeiter_telefon' => (string) ($row->bearbeiter_telefon ?? ''),
            'bearbeiter_email' => (string) ($row->bearbeiter_email ?? ''),
            'datum' => (string) ($row->datum ?? ''),
        ])
        ->values()
        ->all();

    $this->pruefung_termine = $termine;
    $this->pruefung_step = 2;
};

$selectPruefungTermin = function (int $terminId): void {
    $termin = collect($this->pruefung_termine)->firstWhere('termin_id', $terminId);

    if ($termin === null) {
        $row = BueLaravel::getTicketPruefungByTerminId($terminId);

        if ($row === null) {
            Flux::toast(heading: 'Fehler', text: 'Der gewählte Prüfungstermin wurde nicht gefunden.', variant: 'danger');

            return;
        }

        $termin = [
            'termin_id' => (int) $row->termin_id,
            'pruefung_bezeichnung' => (string) ($row->pruefung_bezeichnung ?? ''),
            'termin_bezeichnung' => (string) ($row->termin_bezeichnung ?? ''),
            'ordnung' => (string) ($row->ordnung ?? ''),
            'uhrzeit_von' => (string) ($row->uhrzeit_von ?? ''),
            'uhrzeit_bis' => (string) ($row->uhrzeit_bis ?? ''),
            'pruefungsort_name' => (string) ($row->pruefungsort_name ?? ''),
            'gebaeudenummer' => (string) ($row->gebaeudenummer ?? ''),
            'raumnummer' => (string) ($row->raumnummer ?? ''),
            'anzahl_prueflinge' => $row->anzahl_prueflinge,
            'bearbeiter_vorname' => (string) ($row->bearbeiter_vorname ?? ''),
            'bearbeiter_nachname' => (string) ($row->bearbeiter_nachname ?? ''),
            'bearbeiter_telefon' => (string) ($row->bearbeiter_telefon ?? ''),
            'bearbeiter_email' => (string) ($row->bearbeiter_email ?? ''),
            'datum' => (string) ($row->datum ?? ''),
        ];
    }

    $raumParts = array_filter([
        $termin['pruefungsort_name'] ?: null,
        $termin['gebaeudenummer'] ?: null,
        $termin['raumnummer'] ?: null,
    ]);

    $ansprechpartnerParts = array_filter([
        trim(($termin['bearbeiter_vorname'] ?? '').' '.($termin['bearbeiter_nachname'] ?? '')) ?: null,
        $termin['bearbeiter_telefon'] ?: null,
        $termin['bearbeiter_email'] ?: null,
    ]);

    $bezeichnung = $termin['pruefung_bezeichnung'] !== ''
        ? $termin['pruefung_bezeichnung']
        : $termin['termin_bezeichnung'];

    $this->pruefungstermin_id = $termin['termin_id'];
    $this->datum = Carbon::parse($termin['datum'] !== '' ? $termin['datum'] : $this->pruefung_datum)->toDateString();
    $this->gewerk = $termin['ordnung'];
    $this->raeume = implode(', ', $raumParts);
    $this->anzahl_teilnehmer = (int) ($termin['anzahl_prueflinge'] ?? 0);
    $this->ansprechpartner = implode(', ', $ansprechpartnerParts);
    $this->betreff = 'IT-gestützte Prüfung: '.($bezeichnung !== '' ? $bezeichnung : 'Termin '.$termin['termin_id']);
    $this->pruefung_step = 3;
};

$pruefungZurueck = function (): void {
    if ($this->pruefung_step <= 1) {
        return;
    }

    $this->pruefung_step--;

    if ($this->pruefung_step === 1) {
        $this->pruefung_termine = [];
        $this->pruefungstermin_id = null;
    }
};

$submit = function (): void {
    if ($this->category->form === TicketFormType::ItGestuetztePruefung && $this->pruefung_step !== 3) {
        return;
    }

    $rules = app(TicketFormValidation::class)->rulesFor($this->category->form, (bool) $this->meisterbrief);
    $validated = $this->validate($rules);

    if ($this->category->form === TicketFormType::ItGestuetztePruefung) {
        $termin = BueLaravel::getTicketPruefungByTerminId((int) $validated['pruefungstermin_id']);

        if ($termin === null) {
            Flux::toast(heading: 'Fehler', text: 'Der gewählte Prüfungstermin ist ungültig.', variant: 'danger');

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
