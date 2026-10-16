<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Http\Controllers;

use App\Application\Seo\DTOs\SeoPageDTO;
use App\Application\Seo\Services\SeoMetadataFactoryInterface;
use App\Http\Controllers\Controller;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\VenueBookings\Application\DTOs\Response\BookableResourceResponseDTO;
use Modules\VenueBookings\Application\DTOs\Response\FieldDefinitionResponseDTO;
use Modules\VenueBookings\Application\Services\BookingFieldConfigServiceInterface;
use Modules\VenueBookings\Domain\Repositories\BookableResourceRepositoryInterface;
use Modules\VenueBookings\Domain\Repositories\OperatingScheduleRepositoryInterface;
use Modules\VenueBookings\Infrastructure\Services\BookingSettingsReader;

final class BookingCalendarController extends Controller
{
    public function __construct(
        private readonly BookableResourceRepositoryInterface $resourceRepository,
        private readonly BookingFieldConfigServiceInterface $fieldConfigService,
        private readonly OperatingScheduleRepositoryInterface $scheduleRepository,
        private readonly SeoMetadataFactoryInterface $seo,
        private readonly BookingSettingsReader $settingsReader,
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $resources = $this->resourceRepository->getActive();

        $resourceDtos = array_map(
            function ($r) use ($user) {
                $schedule = $this->scheduleRepository->findByResource($r->id);
                $schedulingMode = $schedule !== null ? $schedule->schedulingMode->value : 'time_slots';
                $minConsecutiveSlots = $schedule !== null ? $schedule->minConsecutiveSlots : 1;
                $maxConsecutiveSlots = $schedule !== null ? $schedule->maxConsecutiveSlots : 4;

                $visibleFields = $this->fieldConfigService->getVisibleFields($r->id->value, $user);
                $fieldDefs = array_map(
                    fn ($f) => FieldDefinitionResponseDTO::fromConfig($f)->toArray(),
                    $visibleFields,
                );

                return BookableResourceResponseDTO::fromEntity($r, $schedulingMode, $fieldDefs, $minConsecutiveSlots, $maxConsecutiveSlots)->toArray();
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
            // First day the calendar offers: the venue's today, not the visitor's
            'today' => CarbonImmutable::now($this->settingsReader->getDateTimeZone())->toDateString(),
            'seo' => $this->seo->forPage(new SeoPageDTO(
                title: (string) __('venue-bookings::messages.seo.calendar_title'),
            ))->toArray(),
        ]);
    }
}
