<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppTickets\Support;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

class ZammadUserProfileMapper
{
    /**
     * @return array<string, mixed>
     */
    public function toCreatePayload(Authenticatable $user, ?int $roleId): array
    {
        $email = $this->resolveEmail($user);

        if ($email === null) {
            throw new \InvalidArgumentException('Intranet-Benutzer hat keine E-Mail-Adresse für Zammad.');
        }

        $firstname = trim((string) ($this->attribute($user, 'vorname') ?? ''));
        $lastname = trim((string) ($this->attribute($user, 'nachname') ?? ''));

        if ($firstname === '' && $lastname === '') {
            $name = trim((string) ($this->attribute($user, 'name') ?? ''));
            $firstname = $name !== '' ? $name : $email;
        }

        $payload = [
            'firstname' => $firstname,
            'lastname' => $lastname,
            'email' => $email,
            'login' => $email,
            'phone' => $this->nullableString($this->attribute($user, 'telefon')),
            'active' => true,
        ];

        $department = $this->resolveDepartment($user);
        if ($department !== null) {
            $payload['department'] = $department;
        }

        $note = $this->buildNote($user);
        if ($note !== null) {
            $payload['note'] = $note;
        }

        if ($roleId !== null) {
            $payload['role_ids'] = [$roleId];
        }

        return $payload;
    }

    public function resolveEmail(Authenticatable $user): ?string
    {
        $email = trim((string) ($this->attribute($user, 'email') ?? ''));

        if ($email !== '') {
            return $email;
        }

        $upn = trim((string) ($this->attribute($user, 'upn') ?? ''));

        return $upn !== '' ? $upn : null;
    }

    private function resolveDepartment(Authenticatable $user): ?string
    {
        if ($user instanceof Model) {
            $user->loadMissing('standort');

            $standortName = trim((string) ($user->getRelationValue('standort')?->name ?? ''));

            if ($standortName !== '') {
                return $standortName;
            }
        }

        return $this->nullableString($this->attribute($user, 'standort'));
    }

    private function buildNote(Authenticatable $user): ?string
    {
        $parts = [];

        $raum = $this->nullableString($this->attribute($user, 'raum'));
        if ($raum !== null) {
            $parts[] = 'Raum: '.$raum;
        }

        $username = $this->nullableString($this->attribute($user, 'username'));
        if ($username !== null) {
            $parts[] = 'Intranet-Benutzer: '.$username;
        }

        if ($parts === []) {
            return null;
        }

        return implode("\n", $parts);
    }

    private function attribute(Authenticatable $user, string $key): mixed
    {
        if ($user instanceof Model) {
            return $user->getAttribute($key);
        }

        return $user->{$key} ?? null;
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $string = trim((string) $value);

        return $string !== '' ? $string : null;
    }
}
