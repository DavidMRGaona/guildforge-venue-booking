<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Tests\Unit\Policies;

use App\Application\Authorization\Services\AuthorizationServiceInterface;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Modules\VenueBookings\Infrastructure\Persistence\Eloquent\Models\BookingModel;
use Modules\VenueBookings\Policies\BookingPolicy;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The panel actions call Gate::authorize() with these abilities when they run,
 * so each one must ask for exactly its permission and nothing broader.
 */
final class BookingPolicyTest extends TestCase
{
    /**
     * @return array<string, array{string, string}>
     */
    public static function actionAbilities(): array
    {
        return [
            'confirm' => ['confirm', 'venuebookings:bookings.manage'],
            'reject' => ['reject', 'venuebookings:bookings.manage'],
            'no show' => ['markNoShow', 'venuebookings:bookings.manage'],
            'cancel' => ['cancel', 'venuebookings:bookings.cancel_any'],
        ];
    }

    #[DataProvider('actionAbilities')]
    public function test_each_action_ability_needs_its_permission(string $ability, string $permission): void
    {
        $user = new UserModel;
        $booking = new BookingModel;
        /** @var list<string> $granted */
        $granted = [];
        $authorization = $this->createStub(AuthorizationServiceInterface::class);
        $authorization->method('can')->willReturnCallback(
            function (object $who, string $key) use (&$granted, $user): bool {
                return $who === $user && in_array($key, $granted, true);
            },
        );
        $this->app->instance(AuthorizationServiceInterface::class, $authorization);
        $policy = new BookingPolicy;

        $this->assertFalse($policy->{$ability}($user, $booking), 'No permission must deny');

        $granted = array_values(array_diff([
            'venuebookings:bookings.view_any',
            'venuebookings:bookings.create',
            'venuebookings:bookings.cancel_own',
            'venuebookings:bookings.cancel_any',
            'venuebookings:bookings.manage',
            'venuebookings:bookings.settings',
        ], [$permission]));
        $this->assertFalse($policy->{$ability}($user, $booking), 'Every other permission must still deny');

        $granted = [$permission];
        $this->assertTrue($policy->{$ability}($user, $booking));
    }

    /**
     * The panel has no page of its own for one booking: whoever may see the list may see each
     * booking in it, and everyone may see their own.
     */
    public function test_viewing_one_booking_needs_the_list_permission_or_owning_it(): void
    {
        $user = new UserModel;
        $user->id = 'user-1';
        $booking = new BookingModel;
        $booking->setAttribute('user_id', 'someone-else');
        $policy = new BookingPolicy;

        $this->grant($user, ['venuebookings:bookings.view_any']);
        $this->assertTrue($policy->view($user, $booking));

        $this->grant($user, ['venuebookings:bookings.view']);
        $this->assertFalse($policy->view($user, $booking), 'The retired view permission alone must not grant it');

        $this->grant($user, []);
        $booking->setAttribute('user_id', 'user-1');
        $this->assertTrue($policy->view($user, $booking));
    }

    /**
     * @param  list<string>  $granted
     */
    private function grant(UserModel $user, array $granted): void
    {
        $authorization = $this->createStub(AuthorizationServiceInterface::class);
        $authorization->method('can')->willReturnCallback(
            static fn (object $who, string $key): bool => $who === $user && in_array($key, $granted, true),
        );
        $this->app->instance(AuthorizationServiceInterface::class, $authorization);
    }
}
