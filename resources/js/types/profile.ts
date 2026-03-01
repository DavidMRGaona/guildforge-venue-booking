import type { BookingListItem } from './bookings';

export interface ProfileBookingsData {
    upcoming: BookingListItem[];
    past: BookingListItem[];
    total: number;
}
