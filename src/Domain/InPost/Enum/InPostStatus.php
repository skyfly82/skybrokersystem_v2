<?php

declare(strict_types=1);

namespace App\Domain\InPost\Enum;

enum InPostStatus: string
{
    case CREATED = 'created';
    case OFFERS_PREPARED = 'offers_prepared';
    case OFFER_SELECTED = 'offer_selected';
    case CONFIRMED = 'confirmed';
    case DISPATCHED_BY_SENDER = 'dispatched_by_sender';
    case COLLECTED_FROM_SENDER = 'collected_from_sender';
    case TAKEN_BY_COURIER = 'taken_by_courier';
    case ADOPTED_AT_SOURCE_BRANCH = 'adopted_at_source_branch';
    case SENT_FROM_SOURCE_BRANCH = 'sent_from_source_branch';
    case ADOPTED_AT_SORTING_CENTER = 'adopted_at_sorting_center';
    case SENT_FROM_sorting_center = 'sent_from_sorting_center';
    case ADOPTED_AT_TARGET_BRANCH = 'adopted_at_target_branch';
    case SENT_FROM_TARGET_BRANCH = 'sent_from_target_branch';
    case OUT_FOR_DELIVERY = 'out_for_delivery';
    case READY_TO_PICKUP = 'ready_to_pickup';
    case PICKUP_REMINDER_SENT = 'pickup_reminder_sent';
    case DELIVERED = 'delivered';
    case RETURNED_TO_SENDER = 'returned_to_sender';
    case AVIZO = 'avizo';
    case CANCELED = 'canceled';
    case ERROR = 'error';

    public function getDisplayName(): string
    {
        return match ($this) {
            self::CREATED => 'Utworzona',
            self::OFFERS_PREPARED => 'Przygotowane oferty',
            self::OFFER_SELECTED => 'Wybrana oferta',
            self::CONFIRMED => 'Potwierdzona',
            self::DISPATCHED_BY_SENDER => 'Wysłana przez nadawcę',
            self::COLLECTED_FROM_SENDER => 'Odebrana od nadawcy',
            self::TAKEN_BY_COURIER => 'Odebrana przez kuriera',
            self::ADOPTED_AT_SOURCE_BRANCH => 'Przyjęta w oddziale nadawczym',
            self::SENT_FROM_SOURCE_BRANCH => 'Wysłana z oddziału nadawczego',
            self::ADOPTED_AT_SORTING_CENTER => 'Przyjęta w centrum sortowania',
            self::SENT_FROM_sorting_center => 'Wysłana z centrum sortowania',
            self::ADOPTED_AT_TARGET_BRANCH => 'Przyjęta w oddziale docelowym',
            self::SENT_FROM_TARGET_BRANCH => 'Wysłana z oddziału docelowego',
            self::OUT_FOR_DELIVERY => 'W dostawie',
            self::READY_TO_PICKUP => 'Gotowa do odbioru',
            self::PICKUP_REMINDER_SENT => 'Wysłane przypomnienie o odbiorze',
            self::DELIVERED => 'Dostarczona',
            self::RETURNED_TO_SENDER => 'Zwrócona do nadawcy',
            self::AVIZO => 'Awizo',
            self::CANCELED => 'Anulowana',
            self::ERROR => 'Błąd',
        };
    }

    public function isDelivered(): bool
    {
        return $this === self::DELIVERED;
    }

    public function isInTransit(): bool
    {
        return match ($this) {
            self::DISPATCHED_BY_SENDER,
            self::COLLECTED_FROM_SENDER,
            self::TAKEN_BY_COURIER,
            self::ADOPTED_AT_SOURCE_BRANCH,
            self::SENT_FROM_SOURCE_BRANCH,
            self::ADOPTED_AT_SORTING_CENTER,
            self::SENT_FROM_sorting_center,
            self::ADOPTED_AT_TARGET_BRANCH,
            self::SENT_FROM_TARGET_BRANCH,
            self::OUT_FOR_DELIVERY => true,
            default => false,
        };
    }

    public function isAwaitingPickup(): bool
    {
        return match ($this) {
            self::READY_TO_PICKUP,
            self::PICKUP_REMINDER_SENT => true,
            default => false,
        };
    }

    public function isFinalStatus(): bool
    {
        return match ($this) {
            self::DELIVERED,
            self::RETURNED_TO_SENDER,
            self::CANCELED,
            self::ERROR => true,
            default => false,
        };
    }

    public function isError(): bool
    {
        return $this === self::ERROR;
    }

    public function isCanceled(): bool
    {
        return $this === self::CANCELED;
    }
}