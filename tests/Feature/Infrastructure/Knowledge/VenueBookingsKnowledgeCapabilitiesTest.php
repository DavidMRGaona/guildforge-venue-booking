<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Tests\Feature\Infrastructure\Knowledge;

use App\Application\Authorization\Services\AuthorizationServiceInterface;
use App\Application\Knowledge\Services\KnowledgeCapabilityRegistryInterface;
use App\Infrastructure\Persistence\Eloquent\Models\PermissionModel;
use App\Infrastructure\Persistence\Eloquent\Models\RoleModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use stdClass;
use Tests\Support\Modules\ModuleTestCase;

/**
 * The capability the cancel guide uses answers as the "my bookings" page does: the person may
 * cancel their own bookings with either cancel permission.
 */
final class VenueBookingsKnowledgeCapabilitiesTest extends ModuleTestCase
{
    private const string KEY = 'venue-bookings.can_cancel_own_bookings';

    protected ?string $moduleName = 'venue-bookings';

    protected bool $autoEnableModule = true;

    private KnowledgeCapabilityRegistryInterface $capabilities;

    private UserModel $jane;

    protected function setUp(): void
    {
        parent::setUp();

        $this->capabilities = $this->app->make(KnowledgeCapabilityRegistryInterface::class);
        $this->jane = UserModel::factory()->create(['name' => 'Jane Doe']);
    }

    public function test_the_module_registers_its_capability(): void
    {
        $this->assertContains(self::KEY, $this->capabilities->keys());
    }

    public function test_without_a_cancel_permission_the_capability_does_not_pass(): void
    {
        $this->assertFalse($this->capabilities->evaluate(self::KEY, $this->jane));
    }

    public function test_cancelling_own_bookings_passes(): void
    {
        $this->grant($this->jane, 'venuebookings:bookings.cancel_own');

        $this->assertTrue($this->capabilities->evaluate(self::KEY, $this->jane));
    }

    public function test_cancelling_any_booking_passes_too(): void
    {
        $this->grant($this->jane, 'venuebookings:bookings.cancel_any');

        $this->assertTrue($this->capabilities->evaluate(self::KEY, $this->jane));
    }

    public function test_something_that_is_not_an_account_does_not_pass(): void
    {
        $this->assertFalse($this->capabilities->evaluate(self::KEY, new stdClass));
    }

    private function grant(UserModel $user, string $key): void
    {
        $role = RoleModel::create(['name' => 'grant-'.uniqid(), 'display_name' => 'Grant']);
        [$resource, $action] = explode('.', $key, 2);
        $permission = PermissionModel::firstOrCreate(
            ['key' => $key],
            ['label' => $key, 'resource' => $resource, 'action' => $action],
        );
        $role->permissions()->attach($permission->id);
        $user->roles()->attach($role->id);

        $this->app->make(AuthorizationServiceInterface::class)->invalidateCache();
    }
}
