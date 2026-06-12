<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppTickets\Support;

use Illuminate\Database\Eloquent\Builder;

class IntranetUserSearchFilter
{
    public function apply(Builder $query, string $search): Builder
    {
        $search = trim($search);

        if ($search === '') {
            return $query;
        }

        $terms = collect(preg_split('/\s+/', $search) ?: [])
            ->map(fn (string $term): string => trim($term))
            ->filter()
            ->values();

        if ($terms->isEmpty()) {
            return $query;
        }

        $emailSuffix = (string) config('ms-graph-laravel.default_suffix', '');

        foreach ($terms as $term) {
            $like = '%'.$term.'%';

            $query->where(function (Builder $inner) use ($like, $emailSuffix): void {
                $inner->where('vorname', 'like', $like)
                    ->orWhere('nachname', 'like', $like)
                    ->orWhere('username', 'like', $like)
                    ->orWhere('email', 'like', $like);

                $driver = $inner->getConnection()->getDriverName();

                if ($driver === 'sqlite') {
                    $inner->orWhereRaw("(vorname || ' ' || nachname) like ?", [$like])
                        ->orWhereRaw("(nachname || ' ' || vorname) like ?", [$like]);
                } else {
                    $inner->orWhereRaw("CONCAT(vorname, ' ', nachname) like ?", [$like])
                        ->orWhereRaw("CONCAT(nachname, ' ', vorname) like ?", [$like]);
                }

                if ($emailSuffix !== '') {
                    if ($driver === 'sqlite') {
                        $inner->orWhereRaw("(username || '@' || ?) like ?", [$emailSuffix, $like]);
                    } else {
                        $inner->orWhereRaw("CONCAT(username, '@', ?) like ?", [$emailSuffix, $like]);
                    }
                }
            });
        }

        return $query;
    }
}
