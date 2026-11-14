import { afterEach, describe, expect, it, vi } from 'vitest';
import { mount, type VueWrapper } from '@vue/test-utils';
import BookingDetailModal from '../../resources/js/components/BookingDetailModal.vue';
import { bookingDetail, focusedOpener } from './support/fixtures';

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

function mountModal(): VueWrapper {
    return mount(BookingDetailModal, {
        props: { booking: bookingDetail() },
        attachTo: document.body,
    });
}

function dialog(): HTMLDialogElement {
    const element = document.body.querySelector('dialog');
    if (element === null) {
        throw new Error('The detail is not a <dialog>.');
    }

    return element;
}

afterEach(() => {
    document.body.innerHTML = '';
});

describe('BookingDetailModal', () => {
    it('opens as a modal dialog named by its title', () => {
        mountModal();

        expect(dialog().open).toBe(true);
        expect(dialog().getAttribute('aria-modal')).toBe('true');
        const titleId = dialog().getAttribute('aria-labelledby');
        expect(titleId).toBeTruthy();
        expect(document.getElementById(titleId ?? '')?.textContent?.trim()).toBe(
            'Detalle de reserva'
        );
    });

    it('moves the focus inside the dialog', () => {
        focusedOpener();
        mountModal();

        expect(dialog().contains(document.activeElement)).toBe(true);
    });

    it('asks to close on Escape instead of closing by itself', () => {
        const wrapper = mountModal();

        const cancel = new Event('cancel', { cancelable: true });
        dialog().dispatchEvent(cancel);

        expect(cancel.defaultPrevented).toBe(true);
        expect(wrapper.emitted('close')).toHaveLength(1);
    });

    it('asks to close on a click on the backdrop, not on its content', () => {
        const wrapper = mountModal();

        const title = document.getElementById(dialog().getAttribute('aria-labelledby') ?? '');
        title?.dispatchEvent(new MouseEvent('click', { bubbles: true }));
        expect(wrapper.emitted('close')).toBeUndefined();

        dialog().dispatchEvent(new MouseEvent('click', { bubbles: true }));
        expect(wrapper.emitted('close')).toHaveLength(1);
    });

    it('gives the focus back to the element that opened it', () => {
        const opener = focusedOpener();
        const wrapper = mountModal();

        wrapper.unmount();

        expect(document.activeElement).toBe(opener);
    });
});

describe('BookingDetailModal date', () => {
    it.each(ZONES)('shows the booking day, not the next one, in %s', (zone) => {
        venueZone.current = zone;

        mountModal();

        const text = dialog().textContent ?? '';
        expect(text).toContain('sábado');
        expect(text).toMatch(/\b7 de noviembre de 2026/);
    });
});
