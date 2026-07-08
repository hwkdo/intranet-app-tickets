<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppTickets;

use Hwkdo\IntranetAppTickets\Commands\SeedTicketCategoriesCommand;
use Hwkdo\IntranetAppTickets\Listeners\HandleTeamsBotTicketCommand;
use Hwkdo\IntranetAppTickets\Livewire\Admin\ZammadUserRoles;
use Hwkdo\IntranetAppTickets\Models\TicketRequest;
use Hwkdo\IntranetAppTickets\Models\ZammadWebhookOutcome;
use Hwkdo\IntranetAppTickets\Policies\TicketRequestPolicy;
use Hwkdo\IntranetAppTickets\Services\Dispatchers\EmailTicketDispatcher;
use Hwkdo\IntranetAppTickets\Services\Dispatchers\ZammadTicketDispatcher;
use Hwkdo\IntranetAppTickets\Services\TeamsTicketContentGenerator;
use Hwkdo\IntranetAppTickets\Services\TeamsTicketQuotedSenderResolver;
use Hwkdo\IntranetAppTickets\Services\TicketApprovalService;
use Hwkdo\IntranetAppTickets\Services\TicketAttachmentStorage;
use Hwkdo\IntranetAppTickets\Services\TicketBodyBuilder;
use Hwkdo\IntranetAppTickets\Services\TicketDispatchService;
use Hwkdo\IntranetAppTickets\Services\TicketListService;
use Hwkdo\IntranetAppTickets\Services\TicketReadStateService;
use Hwkdo\IntranetAppTickets\Services\TicketsAppSettingsStore;
use Hwkdo\IntranetAppTickets\Services\TicketSubmissionService;
use Hwkdo\IntranetAppTickets\Services\TicketUserZammadTagResolver;
use Hwkdo\IntranetAppTickets\Services\ZammadClientFactory;
use Hwkdo\IntranetAppTickets\Services\ZammadGroupService;
use Hwkdo\IntranetAppTickets\Services\ZammadRoleService;
use Hwkdo\IntranetAppTickets\Services\ZammadTicketService;
use Hwkdo\IntranetAppTickets\Services\ZammadUserBulkService;
use Hwkdo\IntranetAppTickets\Services\ZammadUserResolver;
use Hwkdo\IntranetAppTickets\Services\ZammadUserRoleService;
use Hwkdo\IntranetAppTickets\Services\ZammadWebhookOutcomeRecorder;
use Hwkdo\IntranetAppTickets\Webhooks\Jobs\ZammadWebhookJob;
use Hwkdo\IntranetAppTickets\Webhooks\SignatureValidators\ZammadSignatureValidator;
use Hwkdo\MsGraphLaravel\Events\TeamsBotMessageReceived;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Livewire\Volt\Volt;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Spatie\WebhookClient\Models\WebhookCall;
use Spatie\WebhookClient\WebhookProfile\ProcessEverythingWebhookProfile;
use Spatie\WebhookClient\WebhookResponse\DefaultRespondsTo;

class IntranetAppTicketsServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('intranet-app-tickets')
            ->hasConfigFile()
            ->hasViews()
            ->hasCommand(SeedTicketCategoriesCommand::class)
            ->discoversMigrations();
    }

    public function register(): void
    {
        parent::register();

        $this->app->singleton(ZammadClientFactory::class);
        $this->app->singleton(ZammadUserResolver::class);
        $this->app->singleton(ZammadTicketService::class);
        $this->app->singleton(TicketUserZammadTagResolver::class);
        $this->app->singleton(ZammadGroupService::class);
        $this->app->singleton(ZammadRoleService::class);
        $this->app->singleton(ZammadUserRoleService::class);
        $this->app->singleton(ZammadUserBulkService::class);
        $this->app->singleton(TicketsAppSettingsStore::class);
        $this->app->singleton(TicketBodyBuilder::class);
        $this->app->singleton(TicketAttachmentStorage::class);
        $this->app->singleton(ZammadTicketDispatcher::class);
        $this->app->singleton(EmailTicketDispatcher::class);
        $this->app->singleton(TicketDispatchService::class);
        $this->app->singleton(TicketApprovalService::class);
        $this->app->singleton(TicketSubmissionService::class);
        $this->app->singleton(TicketListService::class);
        $this->app->singleton(TicketReadStateService::class);
        $this->app->singleton(TeamsTicketQuotedSenderResolver::class);
        $this->app->singleton(ZammadWebhookOutcomeRecorder::class);
        $this->app->singleton(TeamsTicketContentGenerator::class);

        $this->registerWebhookConfig();
    }

    public function boot(): void
    {
        parent::boot();

        $this->app->booted(function () {
            Volt::mount(__DIR__.'/../resources/views/livewire');

            Livewire::component('intranet-app-tickets.admin.zammad-user-roles', ZammadUserRoles::class);
        });

        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        $this->loadRoutesFrom(__DIR__.'/../routes/channels.php');

        Gate::policy(TicketRequest::class, TicketRequestPolicy::class);

        WebhookCall::resolveRelationUsing('zammadOutcome', function (WebhookCall $webhookCall): HasOne {
            return $webhookCall->hasOne(ZammadWebhookOutcome::class, 'webhook_call_id');
        });

        WebhookCall::created(function (WebhookCall $webhookCall): void {
            if ($webhookCall->name !== 'tickets-zammad') {
                return;
            }

            app(ZammadWebhookOutcomeRecorder::class)->recordReceived((int) $webhookCall->id);
        });

        $this->registerTeamsBotTicketListener();
    }

    /**
     * Registriert den Teams-Bot-Listener nur, wenn das ms-graph-laravel Bridge-Event verfügbar ist.
     * So bleibt die Kopplung optional (kein harter Composer-Zwang zwischen den Packages).
     */
    private function registerTeamsBotTicketListener(): void
    {
        if (! class_exists(TeamsBotMessageReceived::class)) {
            return;
        }

        Event::listen(TeamsBotMessageReceived::class, [HandleTeamsBotTicketCommand::class, 'handle']);
    }

    private function registerWebhookConfig(): void
    {
        $this->app->booting(function () {
            config([
                'webhook-client.configs' => array_merge(
                    config('webhook-client.configs', []),
                    [
                        [
                            'name' => 'tickets-zammad',
                            'signing_secret' => config('intranet-app-tickets.webhook.secret'),
                            'signature_header_name' => 'X-Hub-Signature',
                            'signature_validator' => ZammadSignatureValidator::class,
                            'webhook_model' => WebhookCall::class,
                            'webhook_profile' => ProcessEverythingWebhookProfile::class,
                            'webhook_response' => DefaultRespondsTo::class,
                            'process_webhook_job' => ZammadWebhookJob::class,
                            'store_headers' => [
                                'X-Hub-Signature',
                                'X-Zammad-Trigger',
                                'X-Zammad-Delivery',
                            ],
                        ],
                    ],
                ),
            ]);
        });
    }
}
