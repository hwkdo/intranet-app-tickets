<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppTickets\Services;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class TeamsTicketUserResolver
{
    public function resolve(?string $upn, ?string $azureUserId = null): ?Authenticatable
    {
        $modelClass = config('intranet-app-tickets.user_model');

        if (! is_string($modelClass) || ! class_exists($modelClass)) {
            return null;
        }

        /** @var class-string<Model> $modelClass */
        $user = $this->resolveByAzureObjectId($modelClass, $azureUserId)
            ?? $this->resolveByUpn($modelClass, $upn);

        return $user instanceof Authenticatable ? $user : null;
    }

    public function resolveQuotedSender(?string $azureUserId, ?string $displayName): ?Authenticatable
    {
        $user = $this->resolve(null, $azureUserId);

        if ($user instanceof Authenticatable) {
            return $user;
        }

        $modelClass = config('intranet-app-tickets.user_model');

        if (! is_string($modelClass) || ! class_exists($modelClass)) {
            return null;
        }

        /** @var class-string<Model> $modelClass */
        return $this->resolveByDisplayName($modelClass, $displayName);
    }

    /**
     * Azure-AD-Object-ID wird beim Microsoft-Socialite-Login in `socialite_id` gespeichert und ist
     * die zuverlässigste Verknüpfung, da Teams im Webhook häufig keinen UPN liefert.
     *
     * @param  class-string<Model>  $modelClass
     */
    private function resolveByAzureObjectId(string $modelClass, ?string $azureUserId): ?Authenticatable
    {
        $azureUserId = is_string($azureUserId) ? trim($azureUserId) : '';

        if ($azureUserId === '') {
            return null;
        }

        $model = new $modelClass;

        if (! Schema::connection($model->getConnectionName())->hasColumn($model->getTable(), 'socialite_id')) {
            return null;
        }

        $user = $modelClass::query()
            ->whereRaw('LOWER(socialite_id) = ?', [mb_strtolower($azureUserId)])
            ->first();

        return $user instanceof Authenticatable ? $user : null;
    }

    /**
     * @param  class-string<Model>  $modelClass
     */
    private function resolveByUpn(string $modelClass, ?string $upn): ?Authenticatable
    {
        $upn = is_string($upn) ? trim($upn) : '';

        if ($upn === '') {
            return null;
        }

        $username = Str::of($upn)->before('@')->trim()->value();

        if ($username !== '') {
            $user = $modelClass::query()->where('username', $username)->first();

            if ($user instanceof Authenticatable) {
                return $user;
            }
        }

        $user = $modelClass::query()->where('email', $upn)->first();

        return $user instanceof Authenticatable ? $user : null;
    }

    /**
     * Teams liefert bei Zitaten häufig nur den Anzeigenamen (z. B. „Nachname, Vorname“ aus AD/Teams).
     *
     * @param  class-string<Model>  $modelClass
     */
    private function resolveByDisplayName(string $modelClass, ?string $displayName): ?Authenticatable
    {
        $displayName = trim((string) $displayName);

        if ($displayName === '') {
            return null;
        }

        if (preg_match('/^([^,]+),\s*(.+)$/u', $displayName, $matches) === 1) {
            $user = $this->firstUniqueUserMatch(
                $modelClass,
                trim($matches[2]),
                trim($matches[1]),
            );

            if ($user instanceof Authenticatable) {
                return $user;
            }
        }

        $parts = preg_split('/\s+/u', $displayName) ?: [];

        if (count($parts) >= 2) {
            $vorname = array_shift($parts);
            $nachname = trim(implode(' ', $parts));

            if (is_string($vorname) && $vorname !== '' && $nachname !== '') {
                $user = $this->firstUniqueUserMatch($modelClass, $vorname, $nachname);

                if ($user instanceof Authenticatable) {
                    return $user;
                }
            }
        }

        return null;
    }

    /**
     * @param  class-string<Model>  $modelClass
     */
    private function firstUniqueUserMatch(string $modelClass, string $vorname, string $nachname): ?Authenticatable
    {
        $users = $this->activeUsersQuery($modelClass)
            ->whereRaw('LOWER(vorname) = ?', [mb_strtolower($vorname)])
            ->whereRaw('LOWER(nachname) = ?', [mb_strtolower($nachname)])
            ->limit(2)
            ->get();

        return $this->soleMatch($users);
    }

    /**
     * @param  class-string<Model>  $modelClass
     */
    private function activeUsersQuery(string $modelClass): Builder
    {
        $query = $modelClass::query();
        $model = new $modelClass;

        if (Schema::connection($model->getConnectionName())->hasColumn($model->getTable(), 'active')) {
            $query->where('active', true);
        }

        return $query;
    }

    /**
     * @param  Collection<int, Model>  $users
     */
    private function soleMatch(Collection $users): ?Authenticatable
    {
        if ($users->count() !== 1) {
            return null;
        }

        $user = $users->first();

        return $user instanceof Authenticatable ? $user : null;
    }
}
