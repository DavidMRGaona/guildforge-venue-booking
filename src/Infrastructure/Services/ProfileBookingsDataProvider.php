<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Infrastructure\Services;

use Modules\VenueBookings\Application\DTOs\Response\BookingListDTO;
use Modules\VenueBookings\Application\Services\BookingQueryServiceInterface;
use Modules\VenueBookings\Domain\Repositories\BookableResourceRepositoryInterface;
use Modules\VenueBookings\Domain\ValueObjects\BookableResourceId;

final readonly class ProfileBookingsDataProvider
{
    public function __construct(
        private BookingQueryServiceInterface $queryService,
        private BookableResourceRepositoryInterface $resourceRepository,
    ) {}

    /**
     * Get bookings data for a user's profile page.
     *
     * @return array{upcoming: array<array<string, mixed>>, past: array<array<string, mixed>>, total: int}|null
     */
    public function getDataForUser(?string $userId): ?array
    {
        if ($userId === null) {
            return null;
        }

        $bookings = $this->queryService->getUserBookings($userId);

        if (count($bookings) === 0) {
            return null;
        }

        $resourceNames = $this->loadResourceNames($bookings);

        $upcoming = [];
        $past = [];
        $today = date('Y-m-d');

        foreach ($bookings as $booking) {
            $enriched = $this->enrichWithResourceName($booking, $resourceNames);
            $isUpcoming = $booking->date >= $today && $booking->status->isActive();

            if ($isUpcoming) {
                $upcoming[] = $enriched;
            } else {
                $past[] = $enriched;
            }
        }

        // Upcoming: ascending by date+time
        usort($upcoming, fn (array $a, array $b): int => ($a['date'].$a['start_time']) <=> ($b['date'].$b['start_time']));

        // Past: descending by date+time
        usort($past, fn (array $a, array $b): int => ($b['date'].$b['start_time']) <=> ($a['date'].$a['start_time']));

        return [
            'upcoming' => $upcoming,
            'past' => $past,
            'total' => count($bookings),
        ];
    }

    /**
     * @param  array<BookingListDTO>  $bookings
     * @return array<string, string>
     */
    private function loadResourceNames(array $bookings): array
    {
        $resourceIds = array_unique(array_map(
            fn (BookingListDTO $b): string => $b->resourceId,
            $bookings,
        ));

        $names = [];
        foreach ($resourceIds as $resourceId) {
            $resource = $this->resourceRepository->find(BookableResourceId::fromString($resourceId));
            if ($resource !== null) {
                $names[$resourceId] = $resource->name;
            }
        }

        return $names;
    }

    /**
     * @param  array<string, string>  $resourceNames
     * @return array<string, mixed>
     */
    private function enrichWithResourceName(BookingListDTO $booking, array $resourceNames): array
    {
        $data = $booking->toArray();
        $data['resource_name'] = $resourceNames[$booking->resourceId] ?? '';

        return $data;
    }
}
