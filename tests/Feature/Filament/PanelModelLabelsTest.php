<?php

declare(strict_types=1);

namespace Modules\VenueBookings\Tests\Feature\Filament;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\Page;
use Filament\Resources\Resource;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Modules\VenueBookings\Filament\Resources\BookableResourceResource;
use Modules\VenueBookings\Filament\Resources\BookableResourceResource\Pages\EditBookableResource;
use Modules\VenueBookings\Filament\Resources\BookableResourceResource\Pages\ListBookableResources;
use Modules\VenueBookings\Filament\Resources\BookingResource;
use Modules\VenueBookings\Filament\Resources\BookingResource\Pages\EditBooking;
use Modules\VenueBookings\Filament\Resources\BookingResource\Pages\ListBookings;
use Modules\VenueBookings\Infrastructure\Persistence\Eloquent\Models\BookableResourceModel;
use Modules\VenueBookings\Infrastructure\Persistence\Eloquent\Models\BookingModel;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\Modules\InteractsWithModulePanel;
use Tests\Support\Modules\ModuleTestCase;

/**
 * Filament builds the list title and the breadcrumb from the plural model label and the delete
 * modal («Borrar reserva») from the singular one, as they are written. The plural starts with a
 * capital, as in the core («Eventos»); the singular stays in lowercase because it sits inside
 * a sentence.
 */
final class PanelModelLabelsTest extends ModuleTestCase
{
    use InteractsWithModulePanel;

    protected ?string $moduleName = 'venue-bookings';

    protected bool $autoEnableModule = true;

    protected function setUp(): void
    {
        parent::setUp();

        $this->registerOnAdminPanel([BookingResource::class, BookableResourceResource::class]);
        $this->actingAsPanelAdmin();
    }

    /**
     * @return array<string, array{string, class-string<resource>, string, string}>
     */
    public static function labels(): array
    {
        return [
            'es: bookings' => ['es', BookingResource::class, 'reserva', 'Reservas'],
            'es: spaces' => ['es', BookableResourceResource::class, 'espacio reservable', 'Espacios reservables'],
            'en: bookings' => ['en', BookingResource::class, 'booking', 'Bookings'],
            'en: spaces' => ['en', BookableResourceResource::class, 'bookable space', 'Bookable spaces'],
        ];
    }

    /**
     * @param  class-string<resource>  $resource
     */
    #[DataProvider('labels')]
    public function test_the_singular_label_is_in_lowercase_and_the_plural_starts_with_a_capital(string $locale, string $resource, string $singular, string $plural): void
    {
        $this->app->setLocale($locale);

        $this->assertSame($singular, $resource::getModelLabel());
        $this->assertSame($plural, $resource::getPluralModelLabel());
    }

    /**
     * @param  class-string<resource>  $resource
     */
    #[DataProvider('labels')]
    public function test_the_breadcrumb_of_the_list_is_the_plural_with_a_capital(string $locale, string $resource, string $singular, string $plural): void
    {
        $this->app->setLocale($locale);

        $this->assertSame($plural, $resource::getBreadcrumb());
    }

    /**
     * @return array<string, array{string, class-string, string}>
     */
    public static function listTitles(): array
    {
        return [
            'es: bookings' => ['es', ListBookings::class, 'Reservas'],
            'es: spaces' => ['es', ListBookableResources::class, 'Espacios reservables'],
            'en: bookings' => ['en', ListBookings::class, 'Bookings'],
            'en: spaces' => ['en', ListBookableResources::class, 'Bookable spaces'],
        ];
    }

    #[DataProvider('listTitles')]
    public function test_the_list_title_starts_with_a_capital(string $locale, string $page, string $title): void
    {
        $this->app->setLocale($locale);

        $instance = Livewire::test($page)->instance();
        $this->assertInstanceOf(Page::class, $instance);

        $this->assertSame($title, $this->plain($instance->getTitle()));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function deleteHeadings(): array
    {
        return [
            'es' => ['es', 'Borrar'],
            'en' => ['en', 'Delete'],
        ];
    }

    #[DataProvider('deleteHeadings')]
    public function test_deleting_a_booking_keeps_the_singular_in_lowercase(string $locale, string $verb): void
    {
        $this->app->setLocale($locale);
        $space = $this->space();
        $booking = BookingModel::create([
            'id' => (string) Str::uuid(),
            'resource_id' => $space->id,
            'user_id' => UserModel::factory()->create()->id,
            'date' => now()->addDay()->toDateString(),
            'start_time' => '10:00',
            'end_time' => '12:00',
            'status' => 'confirmed',
        ]);

        $this->assertSame(
            $verb.' '.($locale === 'es' ? 'reserva' : 'booking'),
            $this->deleteHeading(EditBooking::class, $booking->getRouteKey()),
        );
    }

    #[DataProvider('deleteHeadings')]
    public function test_deleting_a_space_keeps_the_singular_in_lowercase(string $locale, string $verb): void
    {
        $this->app->setLocale($locale);
        $space = $this->space();

        $this->assertSame(
            $verb.' '.($locale === 'es' ? 'espacio reservable' : 'bookable space'),
            $this->deleteHeading(EditBookableResource::class, $space->getRouteKey()),
        );
    }

    public function test_the_keys_not_yet_translated_to_english_fall_back_to_spanish(): void
    {
        $this->app->setLocale('en');
        $this->app->make('translator')->setFallback('es');

        $this->assertSame('Pendiente', __('venue-bookings::messages.enums.booking_status.pending'));
    }

    /**
     * @param  class-string<EditBooking|EditBookableResource>  $page
     */
    private function deleteHeading(string $page, mixed $record): string
    {
        $component = Livewire::test($page, ['record' => $record])->mountAction(DeleteAction::class);

        $instance = $component->instance();
        $this->assertTrue($instance instanceof EditBooking || $instance instanceof EditBookableResource);

        return $this->plain($instance->getMountedAction()?->getModalHeading());
    }

    private function plain(Htmlable|string|null $text): string
    {
        return $text instanceof Htmlable ? $text->toHtml() : (string) $text;
    }

    private function space(): BookableResourceModel
    {
        return BookableResourceModel::create([
            'id' => (string) Str::uuid(),
            'name' => 'Sala grande',
            'slug' => 'sala-grande',
            'status' => 'active',
            'sort_order' => 0,
        ]);
    }
}
