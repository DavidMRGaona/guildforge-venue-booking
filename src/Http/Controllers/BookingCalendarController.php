<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\VenueBookings\Application\DTOs\Response\BookableResourceResponseDTO;
use Modules\VenueBookings\Application\DTOs\Response\FieldDefinitionResponseDTO;
use Modules\VenueBookings\Application\Services\BookingFieldConfigServiceInterface;
use Modules\VenueBookings\Domain\Repositories\BookableResourceRepositoryInterface;
use Modules\VenueBookings\Domain\Repositories\OperatingScheduleRepositoryInterface;

final class BookingCalendarController extends Controller
{
    public function __construct(
        private readonly BookableResourceRepositoryInterface $resourceRepository,
        private readonly BookingFieldConfigServiceInterface $fieldConfigService,
        private readonly OperatingScheduleRepositoryInterface $scheduleRepository,
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $resources = $this->resourceRepository->getActive();

        $resourceDtos = array_map(
            function ($r) use ($user) {
                $schedule = $this->scheduleRepository->findByResource($r->id);
                $schedulingMode = $schedule !== null ? $schedule->schedulingMode->value : 'time_slots';

                $visibleFields = $this->fieldConfigService->getVisibleFields($r->id->value, $user);
                $fieldDefs = array_map(
                    fn ($f) => FieldDefinitionResponseDTO::fromConfig($f)->toArray(),
                    $visibleFields,
                );

                return BookableResourceResponseDTO::fromEntity($r, $schedulingMode, $fieldDefs)->toArray();
            },
            $resources,
        );

        $selectedResourceId = $request->query('resource_id');
        $selectedResourceId = is_string($selectedResourceId) && $selectedResourceId !== ''
            ? $selectedResourceId
            : ($resources[0]->id->value ?? null);

        return Inertia::render('VenueBookings/Index', [
            'resources' => $resourceDtos,
            'selectedResourceId' => $selectedResourceId,
        ]);
    }
}
