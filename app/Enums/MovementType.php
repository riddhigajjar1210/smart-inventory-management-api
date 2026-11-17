<?php

namespace App\Enums;

/**
 * The four movement types accepted by POST /api/stock/adjust.
 *
 * Keeping these as a backed enum removes every magic string from the
 * service, validation, reporting and seeding code.
 */
enum MovementType: string
{
    case StockIn = 'stock_in';
    case StockOut = 'stock_out';
    case Reservation = 'reservation';
    case ReservationRelease = 'reservation_release';

    public function label(): string
    {
        return match ($this) {
            self::StockIn => 'Stock in',
            self::StockOut => 'Stock out',
            self::Reservation => 'Reservation',
            self::ReservationRelease => 'Reservation release',
        };
    }

    /**
     * Types that consume availability and must be rejected when stock is short.
     */
    public function reducesAvailability(): bool
    {
        return match ($this) {
            self::StockOut, self::Reservation => true,
            self::StockIn, self::ReservationRelease => false,
        };
    }

    public function isInbound(): bool
    {
        return match ($this) {
            self::StockIn, self::ReservationRelease => true,
            self::StockOut, self::Reservation => false,
        };
    }

    public function isOutbound(): bool
    {
        return ! $this->isInbound();
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * @return array<int, string>
     */
    public static function inboundValues(): array
    {
        return array_values(array_map(
            static fn (self $type): string => $type->value,
            array_filter(self::cases(), static fn (self $type): bool => $type->isInbound()),
        ));
    }

    /**
     * @return array<int, string>
     */
    public static function outboundValues(): array
    {
        return array_values(array_map(
            static fn (self $type): string => $type->value,
            array_filter(self::cases(), static fn (self $type): bool => $type->isOutbound()),
        ));
    }
}
