<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Application\Services;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Modules\VenueBookings\Domain\ValueObjects\BookingFieldConfig;
use Modules\VenueBookings\Domain\ValueObjects\ResourceFieldConfig;

interface BookingFieldConfigServiceInterface
{
    /**
     * @return array<BookingFieldConfig>
     */
    public function getEnabledFields(string $resourceId, ?UserModel $user = null): array;

    /**
     * @return array<BookingFieldConfig>
     */
    public function getVisibleFields(string $resourceId, ?UserModel $user): array;

    /**
     * Keys of the fields getVisibleFields() would return, decided from the settings alone:
     * the game tables, campaigns and tournaments a form would offer are never listed.
     *
     * @return list<string>
     */
    public function getVisibleFieldKeys(string $resourceId, ?UserModel $user): array;

    /**
     * @return array<string, mixed>
     */
    public function getValidationRules(string $resourceId, ?UserModel $user): array;

    /**
     * @return array<BookingFieldConfig>
     */
    public function getAssociationFields(?UserModel $user = null): array;

    public function getDefaultFieldConfig(): ResourceFieldConfig;
}
