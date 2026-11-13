import { describe, expect, it } from 'vitest';
import { mount, type VueWrapper } from '@vue/test-utils';
import ResourceSelector from '../../resources/js/components/ResourceSelector.vue';
import type { BookableResource } from '../../resources/js/types/bookings';

function resources(count: number): BookableResource[] {
    return Array.from(
        { length: count },
        (_, index) =>
            ({
                id: `resource-${index + 1}`,
                name: `Mesa ${index + 1}`,
            }) as BookableResource
    );
}

function mountSelector(count: number, selectedId: string | null = null): VueWrapper {
    return mount(ResourceSelector, {
        props: { resources: resources(count), selectedId },
        attachTo: document.body,
    });
}

describe('ResourceSelector', () => {
    it('offers five or more spaces in the site dropdown, named by its label', () => {
        const wrapper = mountSelector(5);

        expect(wrapper.find('select').exists()).toBe(false);
        const button = wrapper.get('#resource-selector');
        expect(button.element.tagName).toBe('BUTTON');
        expect(wrapper.get('label[for="resource-selector"]').text()).toBe('Espacio');
        expect(button.text()).toBe('Selecciona un espacio');
    });

    it('shows the chosen space and tells when another one is picked', async () => {
        const wrapper = mountSelector(5, 'resource-2');

        const button = wrapper.get('#resource-selector');
        expect(button.text()).toBe('Mesa 2');

        await button.trigger('click');
        const option = wrapper.findAll('[role="option"]').find((candidate) => {
            return candidate.text() === 'Mesa 4';
        });
        expect(option).toBeDefined();
        await option?.trigger('click');

        expect(wrapper.emitted('select')).toEqual([['resource-4']]);
    });

    it('ignores the placeholder, so no space is ever chosen as empty', async () => {
        const wrapper = mountSelector(5, 'resource-2');

        await wrapper.get('#resource-selector').trigger('click');
        const placeholder = wrapper.findAll('[role="option"]').find((candidate) => {
            return candidate.text() === 'Selecciona un espacio';
        });
        await placeholder?.trigger('click');

        expect(wrapper.emitted('select')).toBeUndefined();
    });

    it('keeps the tabs for fewer than five spaces', () => {
        const wrapper = mountSelector(3);

        expect(wrapper.find('#resource-selector').exists()).toBe(false);
        expect(wrapper.findAll('[role="tab"]')).toHaveLength(3);
    });
});
