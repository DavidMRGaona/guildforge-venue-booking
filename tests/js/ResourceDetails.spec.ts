import { describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import ResourceDetails from '../../resources/js/components/ResourceDetails.vue';
import type { BookableResource } from '../../resources/js/types/bookings';

function space(overrides: Partial<BookableResource> = {}): BookableResource {
    return {
        id: 'resource-1',
        name: 'Sala grande',
        slug: 'sala-grande',
        description: null,
        capacity: null,
        status: 'active',
        status_label: 'Activo',
        status_color: 'success',
        is_active: true,
        sort_order: 0,
        scheduling_mode: 'time_slots',
        field_definitions: [],
        min_consecutive_slots: 1,
        max_consecutive_slots: 4,
        ...overrides,
    };
}

describe('ResourceDetails', () => {
    it('shows the description and the capacity of the space', () => {
        const wrapper = mount(ResourceDetails, {
            props: {
                resource: space({ description: 'Mesas modulares.\nLuz natural.', capacity: 20 }),
            },
        });

        expect(wrapper.text()).toContain('Mesas modulares.');
        expect(wrapper.text()).toContain('Capacidad: hasta 20 personas');
    });

    it('says person in the singular for a capacity of one', () => {
        const wrapper = mount(ResourceDetails, { props: { resource: space({ capacity: 1 }) } });

        expect(wrapper.text()).toContain('Capacidad: hasta 1 persona');
        expect(wrapper.text()).not.toContain('personas');
    });

    it('renders nothing for a space without description or capacity', () => {
        const wrapper = mount(ResourceDetails, { props: { resource: space() } });

        expect(wrapper.find('section').exists()).toBe(false);
    });
});
