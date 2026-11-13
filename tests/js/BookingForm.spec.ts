import { afterEach, describe, expect, it } from 'vitest';
import { nextTick } from 'vue';
import { mount, type VueWrapper } from '@vue/test-utils';
import BookingForm from '../../resources/js/components/BookingForm.vue';
import type { FieldDefinition } from '../../resources/js/types/bookings';
import { focusedOpener } from './support/fixtures';

const notesField = {
    key: 'notes',
    label: 'Notas',
    type: 'text',
    required: false,
} as FieldDefinition;

function mountForm(fieldDefinitions: FieldDefinition[] = [notesField]): VueWrapper {
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

/** The values the form will send, as the user left them. */
function fieldValues(wrapper: VueWrapper): Record<string, unknown> {
    return (wrapper.vm as unknown as { form: { field_values: Record<string, unknown> } }).form
        .field_values;
}

function element<T extends HTMLElement>(selector: string): T {
    const found = document.body.querySelector<T>(selector);
    if (found === null) {
        throw new Error(`Nothing matches ${selector}.`);
    }

    return found;
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

    it('offers the options of a select field in the site dropdown and keeps the chosen value', async () => {
        const wrapper = mountForm([
            {
                key: 'game_table_id',
                label: 'Partida',
                type: 'select',
                required: false,
                options: [{ value: 'table-1', label: 'La tumba de los horrores' }],
            } as FieldDefinition,
        ]);

        expect(dialog().querySelector('select')).toBeNull();
        const button = element<HTMLButtonElement>('#field-game_table_id');
        expect(button.tagName).toBe('BUTTON');
        expect(element('label[for="field-game_table_id"]').textContent?.trim()).toBe('Partida');

        button.click();
        await nextTick();
        const option = Array.from(dialog().querySelectorAll<HTMLElement>('[role="option"]')).find(
            (candidate) => candidate.textContent?.trim() === 'La tumba de los horrores'
        );
        expect(option).toBeDefined();
        option?.click();
        await nextTick();

        expect(fieldValues(wrapper).game_table_id).toBe('table-1');
    });

    it('takes a number field with the site stepper and stores a number', async () => {
        const wrapper = mountForm([
            {
                key: 'num_participants',
                label: 'Número de participantes',
                type: 'number',
                required: false,
                options: null,
                max: 8,
            },
        ]);

        const input = element<HTMLInputElement>('#field-num_participants');
        const buttons = input.parentElement?.querySelectorAll<HTMLButtonElement>('button') ?? [];
        expect(buttons).toHaveLength(2);

        buttons[1]?.click();
        await nextTick();
        expect(fieldValues(wrapper).num_participants).toBe(1);

        input.value = '5';
        input.dispatchEvent(new Event('input'));
        await nextTick();
        expect(fieldValues(wrapper).num_participants).toBe(5);
    });

    it('shows a yes/no field as the site checkbox, named by its label', async () => {
        const wrapper = mountForm([
            {
                key: 'shared_table',
                label: 'Mesa compartida',
                type: 'toggle',
                required: false,
                options: null,
                max: null,
            },
        ]);

        const input = element<HTMLInputElement>('#field-shared_table');
        expect(input.type).toBe('checkbox');
        expect(input.classList.contains('sr-only')).toBe(true);
        const label = input.closest('label');
        expect(label?.textContent?.trim()).toBe('Mesa compartida');
        expect(dialog().querySelectorAll('label')).toHaveLength(1);

        label?.click();
        await nextTick();

        expect(fieldValues(wrapper).shared_table).toBe(true);
    });

    it('caps a number field at its maximum, such as the capacity of the space', () => {
        mountForm([
            {
                key: 'num_participants',
                label: 'Número de participantes',
                type: 'number',
                required: false,
                options: null,
                max: 8,
            },
            {
                key: 'tables',
                label: 'Mesas',
                type: 'number',
                required: false,
                options: null,
                max: null,
            },
        ]);

        expect(document.body.querySelector('#field-num_participants')?.getAttribute('max')).toBe(
            '8'
        );
        expect(document.body.querySelector('#field-tables')?.hasAttribute('max')).toBe(false);
    });

    it('gives the focus back to the element that opened it', () => {
        const opener = focusedOpener();
        const wrapper = mountForm();

        wrapper.unmount();

        expect(document.activeElement).toBe(opener);
    });
});
