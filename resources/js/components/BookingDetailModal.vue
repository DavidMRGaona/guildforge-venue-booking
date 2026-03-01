<script setup lang="ts">
import { computed, Teleport, Transition } from 'vue';
import { useI18n } from 'vue-i18n';
import type { BookingDetail } from '../types/bookings';
import BookingStatusBadge from './BookingStatusBadge.vue';

interface Props {
    booking: BookingDetail;
}

const props = defineProps<Props>();

const emit = defineEmits<{
    close: [];
}>();

const { t, locale } = useI18n();

const formattedDate = computed(() => {
    const date = new Date(props.booking.date + 'T12:00:00');
    return date.toLocaleDateString(locale.value, {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    });
});

const timeRange = computed(() =>
    t('booking_calendar.time_range', {
        start: props.booking.start_time.slice(0, 5),
        end: props.booking.end_time.slice(0, 5),
    })
);
</script>

<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transition-opacity duration-200"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition-opacity duration-150"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
                @click.self="emit('close')"
            >
                <Transition
                    enter-active-class="transition-all duration-200"
                    enter-from-class="scale-95 opacity-0"
                    enter-to-class="scale-100 opacity-100"
                    leave-active-class="transition-all duration-150"
                    leave-from-class="scale-100 opacity-100"
                    leave-to-class="scale-95 opacity-0"
                >
                    <div class="w-full max-w-lg rounded-xl bg-surface shadow-2xl">
                        <!-- Header -->
                        <div class="flex items-center justify-between border-b border-default px-6 py-4">
                            <h3 class="text-lg font-semibold text-base-primary">
                                {{ t('booking_detail.title') }}
                            </h3>
                            <button
                                type="button"
                                class="rounded-lg p-1.5 text-base-muted transition-colors hover:bg-muted hover:text-base-secondary"
                                @click="emit('close')"
                            >
                                <svg
                                    class="h-5 w-5"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                    aria-hidden="true"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M6 18L18 6M6 6l12 12"
                                    />
                                </svg>
                                <span class="sr-only">{{ t('booking_detail.close') }}</span>
                            </button>
                        </div>

                        <div class="px-6 py-5">
                            <!-- Booking summary -->
                            <div class="space-y-2.5 rounded-lg bg-muted p-4">
                                <!-- Resource -->
                                <div class="flex items-center gap-2 text-sm text-base-secondary">
                                    <svg
                                        class="h-4 w-4 flex-shrink-0 text-base-muted"
                                        fill="none"
                                        stroke="currentColor"
                                        viewBox="0 0 24 24"
                                        aria-hidden="true"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="2"
                                            d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"
                                        />
                                    </svg>
                                    <span class="font-medium">{{ booking.resource_name }}</span>
                                </div>

                                <!-- Date -->
                                <div class="flex items-center gap-2 text-sm text-base-secondary">
                                    <svg
                                        class="h-4 w-4 flex-shrink-0 text-base-muted"
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
                                    <span>{{ formattedDate }}</span>
                                </div>

                                <!-- Time -->
                                <div class="flex items-center gap-2 text-sm text-base-secondary">
                                    <svg
                                        class="h-4 w-4 flex-shrink-0 text-base-muted"
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
                                    <span>{{ timeRange }}</span>
                                </div>

                                <!-- Status -->
                                <div class="flex items-center gap-2 text-sm">
                                    <svg
                                        class="h-4 w-4 flex-shrink-0 text-base-muted"
                                        fill="none"
                                        stroke="currentColor"
                                        viewBox="0 0 24 24"
                                        aria-hidden="true"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="2"
                                            d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"
                                        />
                                    </svg>
                                    <BookingStatusBadge
                                        :status="booking.status"
                                        :label="booking.status_label"
                                        :color="booking.status_color"
                                    />
                                </div>

                                <!-- User (admin only) -->
                                <div
                                    v-if="booking.user_name"
                                    class="flex items-center gap-2 text-sm text-base-secondary"
                                >
                                    <svg
                                        class="h-4 w-4 flex-shrink-0 text-base-muted"
                                        fill="none"
                                        stroke="currentColor"
                                        viewBox="0 0 24 24"
                                        aria-hidden="true"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="2"
                                            d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"
                                        />
                                    </svg>
                                    <span>{{ booking.user_name }}</span>
                                </div>

                                <!-- Game table -->
                                <div
                                    v-if="booking.game_table_name"
                                    class="flex items-center gap-2 text-sm text-base-secondary"
                                >
                                    <svg
                                        class="h-4 w-4 flex-shrink-0 text-base-muted"
                                        fill="none"
                                        stroke="currentColor"
                                        viewBox="0 0 24 24"
                                        aria-hidden="true"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="2"
                                            d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"
                                        />
                                    </svg>
                                    <span>{{ t('booking_detail.game_table') }}: {{ booking.game_table_name }}</span>
                                </div>

                                <!-- Campaign -->
                                <div
                                    v-if="booking.campaign_name"
                                    class="flex items-center gap-2 text-sm text-base-secondary"
                                >
                                    <svg
                                        class="h-4 w-4 flex-shrink-0 text-base-muted"
                                        fill="none"
                                        stroke="currentColor"
                                        viewBox="0 0 24 24"
                                        aria-hidden="true"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="2"
                                            d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"
                                        />
                                    </svg>
                                    <span>{{ t('booking_detail.campaign') }}: {{ booking.campaign_name }}</span>
                                </div>
                            </div>

                            <!-- Dynamic field values -->
                            <div v-if="booking.field_values.length > 0" class="mt-5">
                                <dl class="grid grid-cols-2 gap-x-6 gap-y-4">
                                    <div
                                        v-for="field in booking.field_values"
                                        :key="field.field_key"
                                    >
                                        <dt class="text-xs font-medium uppercase tracking-wide text-base-muted">
                                            {{ field.field_label }}
                                        </dt>
                                        <dd class="mt-1 text-sm text-base-primary">
                                            {{ field.value ?? '—' }}
                                        </dd>
                                    </div>
                                </dl>
                            </div>

                            <!-- Cancellation reason -->
                            <div
                                v-if="booking.cancellation_reason"
                                class="mt-4 rounded-lg bg-error-light p-3"
                            >
                                <p class="text-xs font-medium text-error">
                                    {{ t('booking_detail.cancellation_reason') }}
                                </p>
                                <p class="mt-0.5 text-sm text-error">
                                    {{ booking.cancellation_reason }}
                                </p>
                            </div>

                            <!-- Admin notes -->
                            <div
                                v-if="booking.admin_notes"
                                class="mt-4 rounded-lg bg-warning-light p-3"
                            >
                                <p class="text-xs font-medium text-warning">
                                    {{ t('booking_detail.admin_notes') }}
                                </p>
                                <p class="mt-0.5 text-sm text-warning">
                                    {{ booking.admin_notes }}
                                </p>
                            </div>
                        </div>

                        <!-- Footer -->
                        <div class="border-t border-default px-6 py-4">
                            <button
                                type="button"
                                class="w-full rounded-lg bg-muted px-4 py-2.5 text-sm font-medium text-base-primary transition-colors hover:bg-muted/80"
                                @click="emit('close')"
                            >
                                {{ t('booking_detail.close') }}
                            </button>
                        </div>
                    </div>
                </Transition>
            </div>
        </Transition>
    </Teleport>
</template>
