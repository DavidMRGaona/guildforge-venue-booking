<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Modules\VenueBookings\Application\Services\BookingEligibilityServiceInterface;
use Modules\VenueBookings\Application\Services\BookingFieldConfigServiceInterface;
use Modules\VenueBookings\Application\Services\BookingQueryServiceInterface;
use Modules\VenueBookings\Application\Services\ModuleIntegrationServiceInterface;
use Modules\VenueBookings\Domain\Repositories\BookableResourceRepositoryInterface;
use Modules\VenueBookings\Domain\ValueObjects\BookableResourceId;

final class BookingApiController extends Controller
{
    public function __construct(
        private readonly BookingQueryServiceInterface $queryService,
        private readonly BookingEligibilityServiceInterface $eligibilityService,
        private readonly BookingFieldConfigServiceInterface $fieldConfigService,
        private readonly BookableResourceRepositoryInterface $resourceRepository,
        private readonly ModuleIntegrationServiceInterface $moduleIntegration,
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

        $events = $this->queryService->getCalendarEvents(
            $request->query('resource_id'),
            $request->query('start'),
            $request->query('end'),
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

        if ($dto === null) {
            return response()->json(['message' => 'Not found'], 404);
        }

        /** @var UserModel|null $user */
        $user = $request->user();
        $isAdmin = $user !== null && $user->isAdmin();

        $resource = $this->resourceRepository->find(
            BookableResourceId::fromString($dto->resourceId),
        );
        $resourceName = $resource !== null ? $resource->name : '';

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
            'cancellation_reason' => $dto->cancellationReason,
        ];

        if ($dto->gameTableId !== null) {
            $data['game_table_name'] = $this->moduleIntegration->getGameTableName($dto->gameTableId);
        }

        if ($dto->campaignId !== null) {
            $data['campaign_name'] = $this->moduleIntegration->getCampaignName($dto->campaignId);
        }

        if ($isAdmin) {
            $bookingUser = UserModel::find($dto->userId);
            $data['user_name'] = $bookingUser !== null ? $bookingUser->name : '';
            $data['admin_notes'] = $dto->adminNotes;
        }

        return response()->json($data);
    }
}
