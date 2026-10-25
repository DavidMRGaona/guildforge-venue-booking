<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Http\Requests;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Modules\VenueBookings\Application\Services\BookingFieldConfigServiceInterface;
use Modules\VenueBookings\Infrastructure\Services\BookingSettingsReader;

final class StoreBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $fieldConfigService = app(BookingFieldConfigServiceInterface::class);
        $resourceId = $this->input('resource_id', '');
        $fieldRules = $fieldConfigService->getValidationRules($resourceId, $this->user());

        // A past day is refused outright, judged by the venue's day rather than the server's
        $venueToday = CarbonImmutable::now(app(BookingSettingsReader::class)->getDateTimeZone())->toDateString();

        return array_merge([
            'resource_id' => ['required', 'string', 'uuid'],
            // bail: a malformed date stops at date_format and never reaches the comparison
            'date' => ['bail', 'required', 'date_format:Y-m-d', 'after_or_equal:'.$venueToday],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i'],
            // The game table and campaign come inside field_values, checked against the ones
            // offered to the user; an id sent anywhere else is not read
            'field_values' => ['nullable', 'array'],
        ], $fieldRules);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'date.after_or_equal' => (string) __('venue-bookings::messages.frontend.booking_date_in_the_past'),
        ];
    }
}
