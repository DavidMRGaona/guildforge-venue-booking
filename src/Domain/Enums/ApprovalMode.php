<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Domain\Enums;

enum ApprovalMode: string
{
    case AutoConfirm = 'auto_confirm';
    case RequireApproval = 'require_approval';

    public function label(): string
    {
        return match ($this) {
            self::AutoConfirm => __('venue-bookings::messages.enums.approval_mode.auto_confirm'),
            self::RequireApproval => __('venue-bookings::messages.enums.approval_mode.require_approval'),
        };
    }

    public function requiresApproval(): bool
    {
        return $this === self::RequireApproval;
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return array_combine(
            array_column(self::cases(), 'value'),
            array_map(fn (self $case): string => $case->label(), self::cases()),
        );
    }
}
