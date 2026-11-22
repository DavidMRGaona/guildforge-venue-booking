<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Http\Controllers;

use App\Application\Authorization\Services\AuthorizationServiceInterface;
use App\Http\Controllers\Controller;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Modules\VenueBookings\Application\DTOs\Response\BookingResponseDTO;
use Modules\VenueBookings\Application\Services\BookingEligibilityServiceInterface;
use Modules\VenueBookings\Application\Services\BookingFieldConfigServiceInterface;
use Modules\VenueBookings\Application\Services\BookingQueryServiceInterface;
use Modules\VenueBookings\Application\Services\ModuleIntegrationServiceInterface;
use Modules\VenueBookings\Domain\Repositories\BookableResourceRepositoryInterface;
use Modules\VenueBookings\Domain\ValueObjects\BookableResourceId;
use Modules\VenueBookings\Domain\ValueObjects\BookingFieldConfig;

final class BookingApiController extends Controller
{
    private const string MANAGE_PERMISSION = 'venuebookings:bookings.manage';

    public function __construct(
        private readonly BookingQueryServiceInterface $queryService,
        private readonly BookingEligibilityServiceInterface $eligibilityService,
        private readonly BookingFieldConfigServiceInterface $fieldConfigService,
        private readonly BookableResourceRepositoryInterface $resourceRepository,
        private readonly ModuleIntegrationServiceInterface $moduleIntegration,
        private readonly AuthorizationServiceInterface $authorization,
    ) {}

    public function slots(Request $request): JsonResponse
    {
        $request->validate([
            'resource_id' => ['required', 'string', 'uuid'],
            'date' => ['required', 'date_format:Y-m-d'],
        ]);

        $slots = $this->queryService->getAvailableSlots(
            $request->query('resource_id'),
            $request->query('date'),
        );

        return response()->json(
            array_map(fn ($s) => $s->toArray(), $slots),
        );
    }

    public function eligibility(Request $request): JsonResponse
    {
        $request->validate([
            'resource_id' => ['required', 'string', 'uuid'],
            'date' => ['required', 'date_format:Y-m-d'],
        ]);

        // The route is behind the auth middleware
        /** @var UserModel $user */
        $user = $request->user();

        $result = $this->eligibilityService->canUserBook(
            (string) $user->id,
            $request->query('resource_id'),
            $request->query('date'),
        );

        return response()->json($result->toArray());
    }

    public function calendarEvents(Request $request): JsonResponse
    {
        $request->validate([
            'resource_id' => ['required', 'string', 'uuid'],
            'start' => ['required', 'date'],
            'end' => ['required', 'date'],
        ]);

        /** @var UserModel|null $user */
        $user = $request->user();

        $events = $this->queryService->getCalendarEvents(
            $request->query('resource_id'),
            $request->query('start'),
            $request->query('end'),
            $user !== null ? (string) $user->id : null,
        );

        return response()->json(
            array_map(fn ($e) => $e->toArray(), $events),
        );
    }

    public function show(Request $request, string $booking): JsonResponse
    {
        if (! Str::isUuid($booking)) {
            return response()->json(['message' => 'Not found'], 404);
        }

        $dto = $this->queryService->getBookingById($booking);

        if (! $dto instanceof BookingResponseDTO) {
            return response()->json(['message' => 'Not found'], 404);
        }

        /** @var UserModel|null $user */
        $user = $request->user();
        // Who manages bookings sees who booked and the internal notes, whatever role grants it
        $canManage = $user !== null && $this->authorization->can($user, self::MANAGE_PERMISSION);
        $isBooker = $user !== null && (string) $user->id === $dto->userId;

        $resource = $this->resourceRepository->find(
            BookableResourceId::fromString($dto->resourceId),
        );
        $resourceName = $resource->name ?? '';

        $visibleFieldKeys = array_column(
            $this->fieldConfigService->getVisibleFields($dto->resourceId, $user),
            'key',
        );

        $associationKeys = ['game_table_id', 'campaign_id', 'tournament_id'];

        $filteredFieldValues = array_values(array_filter(
            $dto->fieldValues,
            fn (array $fv): bool => in_array($fv['field_key'], $visibleFieldKeys, true)
                && ! in_array($fv['field_key'], $associationKeys, true),
        ));

        $data = [
            'id' => $dto->id,
            'resource_name' => $resourceName,
            'date' => $dto->date,
            'start_time' => $dto->startTime,
            'end_time' => $dto->endTime,
            'status' => $dto->status->value,
            'status_label' => $dto->status->label(),
            'status_color' => $dto->status->color(),
            'field_values' => $filteredFieldValues,
        ];

        // Free text written by whoever booked or by the team: only those two read it back
        if ($canManage || $isBooker) {
            $data['cancellation_reason'] = $dto->cancellationReason;
        }

        // The association is named only when its setting shows it to this viewer (the field is
        // among the visible ones) and the module that owns it shows the record to this viewer
        $gameTableName = $dto->gameTableId !== null && in_array(BookingFieldConfig::GAME_TABLE_KEY, $visibleFieldKeys, true)
            ? $this->moduleIntegration->getGameTableNameVisibleTo($dto->gameTableId, $user)
            : null;

        if ($gameTableName !== null) {
            $data['game_table_name'] = $gameTableName;
        }

        $campaignName = $dto->campaignId !== null && in_array(BookingFieldConfig::CAMPAIGN_KEY, $visibleFieldKeys, true)
            ? $this->moduleIntegration->getCampaignNameVisibleTo($dto->campaignId, $user)
            : null;

        if ($campaignName !== null) {
            $data['campaign_name'] = $campaignName;
        }

        if ($canManage) {
            $bookingUser = UserModel::find($dto->userId);
            $data['user_name'] = $bookingUser->name ?? '';
            $data['admin_notes'] = $dto->adminNotes;
        }

        return response()->json($data);
    }
}
