<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\VenueBookings\Application\Services\BookingFieldConfigServiceInterface;

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

        return array_merge([
            'resource_id' => ['required', 'string', 'uuid'],
            'date' => ['required', 'date_format:Y-m-d'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i'],
            'field_values' => ['nullable', 'array'],
            'event_id' => ['nullable', 'string', 'uuid'],
            'game_table_id' => ['nullable', 'string'],
            'tournament_id' => ['nullable', 'string'],
            'campaign_id' => ['nullable', 'string'],
        ], $fieldRules);
    }
}
