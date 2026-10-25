import type { BookingDetail } from '../../../resources/js/types/bookings';

/** A confirmed booking as the detail endpoint returns it; override what the test is about. */
export function bookingDetail(overrides: Partial<BookingDetail> = {}): BookingDetail {
    return {
        id: 'booking-1',
        resource_name: 'Mesa grande',
        date: '2026-11-07',
        start_time: '17:00:00',
        end_time: '21:00:00',
        status: 'confirmed',
        status_label: 'Confirmada',
        status_color: 'success',
        field_values: [],
        cancellation_reason: null,
        ...overrides,
    };
}

/** A button outside the dialog, focused, standing for the element that opened it. */
export function focusedOpener(): HTMLButtonElement {
    const opener = document.createElement('button');
    opener.textContent = 'Abrir';
    document.body.appendChild(opener);
    opener.focus();

    return opener;
}
