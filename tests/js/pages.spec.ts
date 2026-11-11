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

    // Same width and title band as the core's lists, so the title lines up with the logo
    it('the calendar page uses the site width and the core title band', () => {
        const wrapper = mount(Index, {
            props: { resources: [], selectedResourceId: null, today: '2026-11-07' },
            global: { stubs: { BookingCalendar: true, ResourceSelector: true } },
        });
        const band = wrapper.get('[data-testid="page-band"]');

        expect(band.classes()).toEqual(expect.arrayContaining(['bg-surface', 'shadow']));
        expect(band.text()).toContain('Elige un espacio y un día para reservar');
        expect(wrapper.find('.max-w-5xl').exists()).toBe(false);
        expect(wrapper.findAll('.max-w-7xl')).toHaveLength(2);
    });

    it('my bookings page uses the site width and the core title band', () => {
        const wrapper = mount(MyBookings, { props: { bookings: [] } });
        const band = wrapper.get('[data-testid="page-band"]');

        expect(band.classes()).toEqual(expect.arrayContaining(['bg-surface', 'shadow']));
        expect(band.text()).toContain('Tus reservas próximas y las que ya pasaron');
        expect(wrapper.find('.max-w-5xl').exists()).toBe(false);
        expect(wrapper.findAll('.max-w-7xl')).toHaveLength(2);
    });
});
