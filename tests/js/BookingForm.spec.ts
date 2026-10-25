import { afterEach, describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import BookingForm from '../../resources/js/components/BookingForm.vue';
import type { FieldDefinition } from '../../resources/js/types/bookings';
import { focusedOpener } from './support/fixtures';

const notesField = {
    key: 'notes',
    label: 'Notas',
    type: 'text',
    required: false,
} as FieldDefinition;

function mountForm(fieldDefinitions: FieldDefinition[] = [notesField]) {
    return mount(BookingForm, {
        props: {
            resourceId: 'resource-1',
            date: '2026-11-07',
            startTime: '17:00',
            endTime: '21:00',
            fieldDefinitions,
            eligibility: { can_book: true, reasons: [] },
        },
        attachTo: document.body,
    });
}

function dialog(): HTMLDialogElement {
    const element = document.body.querySelector('dialog');
    if (element === null) {
        throw new Error('The booking form is not in a <dialog>.');
    }

    return element;
}

afterEach(() => {
    document.body.innerHTML = '';
});

describe('BookingForm', () => {
    it('opens as a modal dialog named by its title', () => {
        mountForm();

        expect(dialog().open).toBe(true);
        expect(dialog().getAttribute('aria-modal')).toBe('true');
        const titleId = dialog().getAttribute('aria-labelledby');
        expect(document.getElementById(titleId ?? '')?.textContent?.trim()).toBe('Reservar');
    });

    it('moves the focus to the first field', () => {
        focusedOpener();
        mountForm();

        expect(document.activeElement).toBe(document.getElementById('field-notes'));
    });

    it('asks to close on Escape', () => {
        const wrapper = mountForm();

        const cancel = new Event('cancel', { cancelable: true });
        dialog().dispatchEvent(cancel);

        expect(cancel.defaultPrevented).toBe(true);
        expect(wrapper.emitted('close')).toHaveLength(1);
    });

    it('asks to close on a click on the backdrop', () => {
        const wrapper = mountForm();

        dialog().dispatchEvent(new MouseEvent('click', { bubbles: true }));

        expect(wrapper.emitted('close')).toHaveLength(1);
    });

    it('gives the focus back to the element that opened it', () => {
        const opener = focusedOpener();
        const wrapper = mountForm();

        wrapper.unmount();

        expect(document.activeElement).toBe(opener);
    });
});
