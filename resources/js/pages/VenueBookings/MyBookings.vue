<script setup lang="ts">
import { ref, computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { router } from '@inertiajs/vue3';
import DefaultLayout from '@/layouts/DefaultLayout.vue';
import { useSeo } from '@/composables/useSeo';
import { useFlashMessages } from '@/composables/useFlashMessages';
import ConfirmDialog from '@/components/ui/ConfirmDialog.vue';
import type { BookingListItem } from '../../types/bookings';
import BookingCard from '../../components/BookingCard.vue';

interface Props {
    bookings: BookingListItem[];
}

const props = withDefaults(defineProps<Props>(), {
    bookings: () => [],
});

const { t } = useI18n();
const { success, error: flashError, hasMessages } = useFlashMessages();

useSeo({
    title: t('booking_calendar.my_bookings'),
});

// State
const showCancelDialog = ref(false);
const pendingCancelId = ref<string | null>(null);

// Split bookings into upcoming and past
const today = new Date().toISOString().substring(0, 10);

const upcomingBookings = computed(() =>
    props.bookings.filter((b) => b.date >= today)
);

const pastBookings = computed(() =>
    props.bookings.filter((b) => b.date < today)
);

// Methods
function handleCancel(id: string): void {
    pendingCancelId.value = id;
    showCancelDialog.value = true;
}

function confirmCancel(): void {
    if (pendingCancelId.value) {
        router.delete(`/reservas/${pendingCancelId.value}`, {
            preserveScroll: true,
            onFinish: () => {
                pendingCancelId.value = null;
            },
        });
    }
}
</script>

<template>
    <DefaultLayout>
        <div class="bg-surface shadow dark:shadow-neutral-900/50">
            <div class="mx-auto max-w-5xl px-4 py-8 sm:px-6 lg:px-8">
                <h1 class="text-3xl font-bold tracking-tight text-base-primary">
                    {{ t('booking_calendar.my_bookings') }}
                </h1>
            </div>
        </div>

        <main class="mx-auto max-w-5xl px-4 py-8 sm:px-6 lg:px-8">
            <!-- Flash messages -->
            <div v-if="hasMessages" class="mb-6">
                <div
                    v-if="success"
                    class="rounded-lg bg-success-light p-4 text-sm font-medium text-success"
                    role="alert"
                >
                    {{ success }}
                </div>
                <div
                    v-if="flashError"
                    class="rounded-lg bg-error-light p-4 text-sm font-medium text-error"
                    role="alert"
                >
                    {{ flashError }}
                </div>
            </div>

            <!-- Upcoming bookings -->
            <section class="mb-8">
                <h2 class="mb-4 text-xl font-semibold text-base-primary">
                    {{ t('booking_calendar.upcoming') }}
                </h2>

                <div
                    v-if="upcomingBookings.length > 0"
                    class="space-y-3"
                >
                    <BookingCard
                        v-for="booking in upcomingBookings"
                        :key="booking.id"
                        :booking="booking"
                        @cancel="handleCancel"
                    />
                </div>

                <div
                    v-else
                    class="rounded-lg border border-dashed border-default p-8 text-center"
                >
                    <svg
                        class="mx-auto h-12 w-12 text-base-muted"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                        aria-hidden="true"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"
                        />
                    </svg>
                    <p class="mt-3 text-sm text-base-secondary">
                        {{ t('booking_calendar.no_upcoming') }}
                    </p>
                </div>
            </section>

            <!-- Past bookings -->
            <section>
                <h2 class="mb-4 text-xl font-semibold text-base-primary">
                    {{ t('booking_calendar.past') }}
                </h2>

                <div
                    v-if="pastBookings.length > 0"
                    class="space-y-3"
                >
                    <BookingCard
                        v-for="booking in pastBookings"
                        :key="booking.id"
                        :booking="booking"
                        @cancel="handleCancel"
                    />
                </div>

                <div
                    v-else
                    class="rounded-lg border border-dashed border-default p-8 text-center"
                >
                    <svg
                        class="mx-auto h-12 w-12 text-base-muted"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                        aria-hidden="true"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"
                        />
                    </svg>
                    <p class="mt-3 text-sm text-base-secondary">
                        {{ t('booking_calendar.no_past') }}
                    </p>
                </div>
            </section>
        </main>

        <!-- Cancel confirmation dialog -->
        <ConfirmDialog
            v-model="showCancelDialog"
            :title="t('booking_calendar.cancel_booking')"
            :message="t('booking_calendar.cancel_confirm')"
            :confirm-label="t('booking_calendar.cancel_booking')"
            :cancel-label="t('booking_calendar.cancel')"
            confirm-variant="danger"
            @confirm="confirmCancel"
        />
    </DefaultLayout>
</template>
