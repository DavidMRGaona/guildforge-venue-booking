import { afterEach, describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import BookingDetailModal from '../../resources/js/components/BookingDetailModal.vue';
import { bookingDetail, focusedOpener } from './support/fixtures';

function mountModal() {
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
