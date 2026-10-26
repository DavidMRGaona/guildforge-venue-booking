<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Tests\Integration\Database;

use App\Application\Authorization\Services\AuthorizationServiceInterface;
use App\Infrastructure\Persistence\Eloquent\Models\RoleModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\Modules\ModuleTestCase;

/**
 * Registered users ("member") may cancel their own bookings: the module grants them
 * cancel_own on update, creating the permission row if permissions:sync has not run yet.
 */
final class GrantCancelOwnToMemberRoleTest extends ModuleTestCase
{
    private const string KEY = 'venuebookings:bookings.cancel_own';

    protected ?string $moduleName = 'venue-bookings';

    protected bool $autoEnableModule = true;

    /** The migration is an anonymous class: there is no type to name its up() and down() with. */
    private mixed $migration;

    protected function setUp(): void
    {
        parent::setUp();

        $this->migration = require dirname(__DIR__, 3).'/database/migrations/2026_10_14_200001_grant_cancel_own_to_member_role.php';

        // Enabling the module already ran the migration; start from a site that never had it
        DB::table('permissions')->where('key', self::KEY)->delete();
    }

    public function test_it_creates_the_permission_like_permissions_sync_does(): void
    {
        $this->migration->up();

        $row = DB::table('permissions')->where('key', self::KEY)->first();
        $this->assertNotNull($row);
        $this->assertTrue(Str::isUuid($row->id));
        $this->assertSame('Cancelar reservas propias', $row->label);
        $this->assertSame('bookings', $row->resource);
        $this->assertSame('cancel_own', $row->action);
        $this->assertSame('venuebookings', $row->module);
        $this->assertNotNull($row->created_at);
    }

    public function test_permissions_sync_keeps_the_row_the_migration_created(): void
    {
        $this->migration->up();
        $created = (array) DB::table('permissions')->where('key', self::KEY)->first();

        $this->assertSame(0, Artisan::call('permissions:sync'));

        $rows = DB::table('permissions')->where('key', self::KEY)->get();
        $this->assertCount(1, $rows);
        $synced = (array) $rows->first();
        unset($created['updated_at'], $synced['updated_at']);
        $this->assertSame($created, $synced);
    }

    public function test_it_grants_the_permission_to_the_member_role(): void
    {
        $member = $this->memberRole();

        $this->migration->up();

        $this->assertSame(1, $this->grants($member));
    }

    public function test_it_reuses_an_existing_permission_row(): void
    {
        $member = $this->memberRole();
        $this->assertSame(0, Artisan::call('permissions:sync'));
        $id = DB::table('permissions')->where('key', self::KEY)->value('id');
        $this->assertNotNull($id, 'permissions:sync must know the module permission');

        $this->migration->up();

        $this->assertSame(1, DB::table('permissions')->where('key', self::KEY)->count());
        $this->assertSame($id, DB::table('permissions')->where('key', self::KEY)->value('id'));
        $this->assertSame(1, $this->grants($member));
    }

    public function test_running_it_twice_leaves_one_row_in_each_table(): void
    {
        $member = $this->memberRole();

        $this->migration->up();
        $this->migration->up();

        $this->assertSame(1, DB::table('permissions')->where('key', self::KEY)->count());
        $this->assertSame(1, $this->grants($member));
    }

    public function test_a_site_without_the_member_role_still_migrates(): void
    {
        RoleModel::query()->where('name', 'member')->delete();

        $this->migration->up();

        $this->assertSame(1, DB::table('permissions')->where('key', self::KEY)->count());
        $this->assertSame(0, DB::table('role_permission')
            ->join('permissions', 'permissions.id', '=', 'role_permission.permission_id')
            ->where('permissions.key', self::KEY)
            ->count());
    }

    public function test_members_can_cancel_their_bookings_right_after_it_runs(): void
    {
        $member = $this->memberRole();
        $user = UserModel::factory()->create();
        $user->roles()->attach($member->id);
        $authorization = $this->app->make(AuthorizationServiceInterface::class);
        $this->assertFalse($authorization->can($user, self::KEY));

        $this->migration->up();

        $this->assertTrue($authorization->can($user, self::KEY));
    }

    public function test_down_changes_nothing(): void
    {
        $member = $this->memberRole();
        $this->migration->up();

        $this->migration->down();

        $this->assertSame(1, DB::table('permissions')->where('key', self::KEY)->count());
        $this->assertSame(1, $this->grants($member));
    }

    private function memberRole(): RoleModel
    {
        return RoleModel::query()->firstOrCreate(['name' => 'member'], ['display_name' => 'Member']);
    }

    private function grants(RoleModel $role): int
    {
        return DB::table('role_permission')
            ->join('permissions', 'permissions.id', '=', 'role_permission.permission_id')
            ->where('role_permission.role_id', $role->id)
            ->where('permissions.key', self::KEY)
            ->count();
    }
}
