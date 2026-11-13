<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Tests\Support\Proposals;

use App\Application\Proposals\Contracts\EventProposalRequestInterface;
use App\Domain\ValueObjects\EventId;
use App\Domain\Proposals\Exceptions\ProposalRequestRefusedException;

/**
 * A request of another module that always refuses and runs after the booking request, so a test
 * can make an approval fail once the booking has been made inside its transaction.
 */
final readonly class FailingProposalRequest implements EventProposalRequestInterface
{
    public const string MODULE = 'failing';

    public const string KEY = 'failing.step';

    public function key(): string
    {
        return self::KEY;
    }

    public function label(): string
    {
        return 'Paso que falla';
    }

    public function question(): string
    {
        return '¿Fallar?';
    }

    public function order(): int
    {
        return PHP_INT_MAX;
    }

    public function fields(): array
    {
        return [];
    }

    public function isAvailable(): bool
    {
        return true;
    }

    public function apply(EventId $eventId, array $values): void
    {
        throw ProposalRequestRefusedException::because('Otro paso falla.');
    }

    public function describe(array $values): string
    {
        return 'Paso que falla';
    }
}
