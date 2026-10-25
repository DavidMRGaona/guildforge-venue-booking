import { reactive } from 'vue';

/**
 * The Inertia page the mocked `usePage()` returns. Tests set the shared props they need
 * (`auth.user`, `flash`, …) and `setup.ts` empties them before each test.
 */
export const page = reactive<{ props: Record<string, unknown>; url: string; component: string }>({
    props: {},
    url: '/',
    component: 'Test',
});
