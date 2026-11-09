import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import FullCalendar from '@fullcalendar/vue3';
import type { CalendarOptions } from '@fullcalendar/vue3';
import { calendarToolbarOptions } from '@/utils/calendarTheme';
import BookingCalendar from '../../resources/js/components/BookingCalendar.vue';

const VIEWS = 'dayGridMonth,timeGridWeek,timeGridDay';

beforeEach(() => {
    // The calendar asks the server for the month's bookings as soon as it mounts
    vi.stubGlobal(
        'fetch',
        vi.fn(() => Promise.resolve(new Response('[]', { status: 200 })))
    );
});

afterEach(() => {
    vi.unstubAllGlobals();
});

function calendarOptions(): CalendarOptions {
    const wrapper = mount(BookingCalendar, {
        props: { resourceId: 'resource-1', minDate: '2026-11-09' },
    });

    return wrapper.findComponent(FullCalendar).props('options') as CalendarOptions;
}

describe('BookingCalendar toolbar', () => {
    it('uses the toolbar shared by every site calendar, with the three booking views', () => {
        const options = calendarOptions();
        const shared = calendarToolbarOptions(VIEWS);

        expect(options.headerToolbar).toEqual(shared.headerToolbar);
        expect(options.headerToolbarClass).toBe(shared.headerToolbarClass);
    });

    it('lets the calendar width, not the window, decide between one and two rows', () => {
        expect(calendarOptions().headerToolbarClass).toBe('calendar-toolbar--responsive');
    });

    it('keeps the labels of the buttons in Spanish', () => {
        const buttons = calendarOptions().buttons;

        expect(buttons?.today).toMatchObject({ text: 'Hoy' });
        expect(buttons?.dayGridMonth).toMatchObject({ text: 'Mes' });
        expect(buttons?.timeGridWeek).toMatchObject({ text: 'Semana' });
        expect(buttons?.timeGridDay).toMatchObject({ text: 'Día' });
    });
});
