/**
 * Time of day ('HH:MM') of a venue wall-clock timestamp sent without offset,
 * such as '2026-11-10T18:00' or '2026-11-10T18:00:00'.
 *
 * Booking times are stored as the venue's local time, so the hour is read
 * straight from the text: `new Date()` would take it as the browser's local
 * time and shift it for visitors in another timezone.
 */
export function wallClockTime(naiveIso: string): string {
    const match = /T(\d{2}):(\d{2})/.exec(naiveIso);

    if (!match) {
        return '';
    }

    return `${match[1] ?? ''}:${match[2] ?? ''}`;
}
