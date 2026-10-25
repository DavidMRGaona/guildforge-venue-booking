<script setup lang="ts">
import { Calendar, Clock } from '@lucide/vue';
import { ref, computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { router, Link } from '@inertiajs/vue3';
import { useFlashMessages } from '@/composables/useFlashMessages';
import ConfirmDialog from '@/components/ui/ConfirmDialog.vue';
import type { BookingListItem } from '../../types/bookings';
import BookingCard from '../../components/BookingCard.vue';
import { venueDayKey } from '@/utils/datetime';

interface Props {
    bookings: BookingListItem[];
    canCancel?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    bookings: () => [],
    canCancel: false,
});

const { t } = useI18n();
const { success, error: flashError, hasMessages } = useFlashMessages();

// State
const showCancelDialog = ref(false);
const pendingCancelId = ref<string | null>(null);

// Split bookings into upcoming and past. `b.date` is a venue calendar day, so
// "today" has to be the venue's day too -- toISOString() would give the UTC day
// and misclassify bookings during the small hours.
const today = venueDayKey(new Date());

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
    <div class="bg-surface shadow">
        <div class="mx-auto flex max-w-5xl flex-wrap items-center justify-between gap-4 px-4 py-8 sm:px-6 lg:px-8">
            <h1 class="text-3xl font-bold tracking-tight text-base-primary">
                {{ t('booking_calendar.my_bookings') }}
            </h1>
            <Link
                href="/reservas"
                class="inline-flex min-h-[44px] items-center gap-2 rounded-md bg-primary px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-primary-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary"
            >
                {{ t('booking_calendar.new_booking') }}
            </Link>
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
                    :can-cancel="props.canCancel"
                    @cancel="handleCancel"
                />
            </div>

            <div
                v-else
                class="rounded-lg border border-dashed border-default p-8 text-center"
            >
                <Calendar class="mx-auto h-12 w-12 text-base-muted" aria-hidden="true" />
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
                    :can-cancel="props.canCancel"
                    @cancel="handleCancel"
                />
            </div>

            <div
                v-else
                class="rounded-lg border border-dashed border-default p-8 text-center"
            >
                <Clock class="mx-auto h-12 w-12 text-base-muted" aria-hidden="true" />
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
</template>
