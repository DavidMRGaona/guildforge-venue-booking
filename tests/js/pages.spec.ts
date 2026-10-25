import { describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import Index from '../../resources/js/pages/VenueBookings/Index.vue';
import MyBookings from '../../resources/js/pages/VenueBookings/MyBookings.vue';

// The core layout already wraps every page in its <main id="main-content">
describe('booking pages', () => {
    it('the calendar page does not open a second <main>', () => {
        const wrapper = mount(Index, {
            props: { resources: [], selectedResourceId: null, today: '2026-11-07' },
            global: { stubs: { BookingCalendar: true, ResourceSelector: true } },
        });

        expect(wrapper.find('main').exists()).toBe(false);
    });

    it('my bookings page does not open a second <main>', () => {
        const wrapper = mount(MyBookings, { props: { bookings: [] } });

        expect(wrapper.find('main').exists()).toBe(false);
    });
});
