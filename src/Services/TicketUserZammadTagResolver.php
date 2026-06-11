<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppTickets\Services;

use Hwkdo\IntranetAppTickets\Models\TicketGvpTag;
use Hwkdo\IntranetAppTickets\Models\TicketStandortTag;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

class TicketUserZammadTagResolver
{
    /**
     * @return list<string>
     */
    public function resolveForUser(Authenticatable $user): array
    {
        $tags = [];

        $standortId = $this->attributeValue($user, 'standort_id');

        if ($standortId !== null) {
            $tag = TicketStandortTag::query()
                ->where('standort_id', $standortId)
                ->value('tag');

            if (is_string($tag) && trim($tag) !== '') {
                $tags[] = trim($tag);
            }
        }

        $gvpId = $this->attributeValue($user, 'gvp_id');

        if ($gvpId !== null) {
            $tag = TicketGvpTag::query()
                ->where('gvp_id', $gvpId)
                ->value('tag');

            if (is_string($tag) && trim($tag) !== '') {
                $tags[] = trim($tag);
            }
        }

        return array_values(array_unique($tags));
    }

    private function attributeValue(Authenticatable $user, string $attribute): ?int
    {
        if ($user instanceof Model) {
            $value = $user->getAttribute($attribute);

            if ($value !== null) {
                return (int) $value;
            }
        }

        if (property_exists($user, $attribute) && $user->{$attribute} !== null) {
            return (int) $user->{$attribute};
        }

        return null;
    }
}
