<?php

declare(strict_types=1);

use App\Application\Authorization\Services\AuthorizationServiceInterface;
use App\Domain\Authorization\ValueObjects\PermissionId;
use App\Infrastructure\Persistence\Eloquent\Models\PermissionModel;
use App\Infrastructure\Persistence\Eloquent\Models\RoleModel;
use Illuminate\Database\Migrations\Migration;

/**
 * Registered users (role "member") may cancel their own bookings on the website.
 *
 * Module updates run migrations but not permissions:sync, so the permission row may not
 * exist yet: it is created with the fields permissions:sync writes (which later finds it by
 * key and only refreshes its label). A site without the "member" role is left as it is.
 */
return new class extends Migration
{
    private const string KEY = 'venuebookings:bookings.cancel_own';

    private const string LABEL_KEY = 'venue-bookings::messages.permissions.cancel_own';

    public function up(): void
    {
        $permission = PermissionModel::query()->firstOrCreate(
            ['key' => self::KEY],
            [
                'id' => PermissionId::generate()->value,
                'label' => $this->label(),
                'resource' => 'bookings',
                'action' => 'cancel_own',
                'module' => 'venuebookings',
            ],
        );

        $member = RoleModel::query()->where('name', 'member')->first();

        if ($member !== null) {
            $member->permissions()->syncWithoutDetaching([$permission->id]);
        }

        // Role permissions were written directly: cached permission sets must not survive
        app(AuthorizationServiceInterface::class)->invalidateCache();
    }

    public function down(): void
    {
        // Nothing to undo: roles may have been edited since, and permission data is never deleted
    }

    private function label(): string
    {
        $label = __(self::LABEL_KEY);

        // The module's translations are not registered when it is migrated before it boots.
        // Keep the fallback equal to permissions.cancel_own in lang/es/messages.php.
        return is_string($label) && $label !== self::LABEL_KEY ? $label : 'Cancelar reservas propias';
    }
};
