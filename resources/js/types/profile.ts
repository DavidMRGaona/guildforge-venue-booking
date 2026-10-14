import type { BookingListItem } from './bookings';

export interface ProfileBookingsData {
    upcoming: BookingListItem[];
    past: BookingListItem[];
    total: number;
    /** Whether the server lets the user cancel their own bookings. */
    canCancel: boolean;
}
