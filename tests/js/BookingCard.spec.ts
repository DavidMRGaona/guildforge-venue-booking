import { describe, expect, it, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import BookingCard from '../../resources/js/components/BookingCard.vue';
import type { BookingListItem } from '../../resources/js/types/bookings';

// The display zone is read once at import in the core: the specs set it per test
const venueZone = vi.hoisted(() => ({ current: 'Europe/Madrid' }));
vi.mock('@/utils/datetime', async (importOriginal) => ({
    ...(await importOriginal<typeof import('@/utils/datetime')>()),
    get VENUE_TIMEZONE(): string {
        return venueZone.current;
    },
}));

// A booking date is a calendar date: it reads the same in every zone, UTC+13 and UTC+14 included
const ZONES = ['Europe/Madrid', 'America/Los_Angeles', 'Pacific/Auckland', 'Pacific/Kiritimati'];

function booking(): BookingListItem {
    return {
        id: 'booking-1',
        resource_id: 'resource-1',
        resource_name: 'Mesa grande',
        date: '2026-11-07',
        start_time: '17:00:00',
        end_time: '21:00:00',
        status: 'confirmed',
        status_label: 'Confirmada',
        status_color: 'success',
        is_cancellable: false,
        field_values: [],
        cancellation_reason: null,
    };
}

describe('BookingCard date', () => {
    it.each(ZONES)('shows the booking day, not the next one, in %s', (zone) => {
        venueZone.current = zone;

        const wrapper = mount(BookingCard, { props: { booking: booking() } });

        const text = wrapper.text();
        expect(text).toMatch(/sáb/);
        expect(text).toMatch(/\b7 nov/);
    });
});
