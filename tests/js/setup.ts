import { afterEach, beforeEach, vi, type Mock } from 'vitest';
import type { Reactive, VNode } from 'vue';
import { config, enableAutoUnmount } from '@vue/test-utils';
import { createI18n } from 'vue-i18n';
import coreEs from '@/locales/es';
import moduleLocales from '../../resources/js/locales';
import { page } from './support/page';

// Inertia is the core's: its router, form and links are replaced by spies and plain anchors
vi.mock('@inertiajs/vue3', async () => {
    const { defineComponent, h, reactive } = await import('vue');
    const { page: currentPage } = await import('./support/page');

    return {
        usePage: (): typeof currentPage => currentPage,
        router: { visit: vi.fn(), reload: vi.fn(), delete: vi.fn(), post: vi.fn(), get: vi.fn() },
        useForm: <T extends Record<string, unknown>>(
            data: T
        ): Reactive<
            T & {
                errors: Record<string, string>;
                hasErrors: boolean;
                processing: boolean;
                post: Mock;
            }
        > =>
            reactive({
                ...data,
                errors: {},
                hasErrors: false,
                processing: false,
                post: vi.fn(),
            }),
        Link: defineComponent({
            // Inertia's own name, which the stand-in keeps
            // eslint-disable-next-line vue/multi-word-component-names, vue/no-reserved-component-names
            name: 'Link',
            props: { href: { type: String, required: true } },
            setup(props, { slots }) {
                return (): VNode => h('a', { href: props.href }, slots.default?.());
            },
        }),
    };
});

// The core's texts with the module's on top, in Spanish, as the site loads them
config.global.plugins = [
    createI18n({
        legacy: false,
        locale: 'es',
        fallbackLocale: 'es',
        messages: { es: { ...coreEs, ...moduleLocales.es } },
    }),
];

// Every mounted component is unmounted after its test, attached ones included
enableAutoUnmount(afterEach);

beforeEach(() => {
    page.props = {};
});
