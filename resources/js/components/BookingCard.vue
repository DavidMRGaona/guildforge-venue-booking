<script setup lang="ts">
import { computed, useId } from 'vue';
import { useI18n } from 'vue-i18n';
import { Link } from '@inertiajs/vue3';
import type { BookingListItem } from '../types/bookings';
import BookingStatusBadge from './BookingStatusBadge.vue';
import { VENUE_TIMEZONE } from '@/utils/datetime';
import { useVerificationGate } from '@/composables/useVerificationGate';

interface Props {
    booking: BookingListItem;
    /** Whether the server lets the current user cancel this booking. */
    canCancel?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    canCancel: false,
});

const emit = defineEmits<{
    cancel: [id: string];
}>();

const { t, locale } = useI18n();
// DELETE /reservas/{id} is closed to accounts that must verify their email (the profile page is not)
const gate = useVerificationGate();
const cancelBlockedId = useId();

const showCancel = computed(() => props.canCancel && props.booking.is_cancellable);

const visibleFields = computed(() =>
    props.booking.field_values.filter(
        (f) => f.value !== null && f.value !== '' && f.value !== false,
    ),
);

const formattedDate = computed(() => {
    const date = new Date(`${props.booking.date}T12:00:00Z`);
    return date.toLocaleDateString(locale.value, {
        timeZone: VENUE_TIMEZONE,
        weekday: 'short',
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    });
});

const timeRange = computed(() =>
    t('booking_calendar.time_range', {
        start: props.booking.start_time.slice(0, 5),
        end: props.booking.end_time.slice(0, 5),
    })
);

function handleCancel(): void {
    if (gate.blocked.value) return;
    emit('cancel', props.booking.id);
}
</script>

<template>
    <div class="overflow-hidden rounded-lg border border-default bg-surface shadow-sm transition-shadow hover:shadow-md">
        <div class="p-4">
            <div class="flex items-start justify-between gap-4">
                <div class="min-w-0 flex-1">
                    <div class="mb-1 flex items-center gap-2">
                        <h3 class="truncate text-base font-semibold text-base-primary">
                            {{ booking.resource_name }}
                        </h3>
                        <BookingStatusBadge
                            :status="booking.status"
                            :label="booking.status_label"
                            :color="booking.status_color"
                        />
                    </div>

                    <div class="flex flex-wrap items-center gap-4 text-sm text-base-secondary">
                        <!-- Date -->
                        <div class="flex items-center gap-1.5">
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
                        <div class="flex items-center gap-1.5">
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
                    </div>

                    <!-- Field values -->
                    <div v-if="visibleFields.length > 0" class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-sm text-base-secondary">
                        <span v-for="field in visibleFields" :key="field.field_key">
                            <span class="font-medium text-base-primary">{{ field.field_label }}:</span>
                            {{ field.value }}
                        </span>
                    </div>
                </div>

                <div v-if="showCancel" class="flex-shrink-0">
                    <button
                        type="button"
                        :disabled="gate.blocked.value"
                        :aria-describedby="gate.blocked.value ? cancelBlockedId : undefined"
                        class="rounded-lg border border-error enabled:hover:bg-error/10 px-3 py-1.5 text-sm font-medium text-error transition-colors focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2 focus:ring-offset-surface disabled:cursor-not-allowed disabled:opacity-50"
                        @click="handleCancel"
                    >
                        {{ t('booking_calendar.cancel_booking') }}
                    </button>
                </div>
            </div>
        </div>

        <!-- Why cancelling is disabled for an account that must verify its email -->
        <p
            v-if="showCancel && gate.blocked.value"
            :id="cancelBlockedId"
            class="border-t border-default bg-warning-light px-4 py-2 text-sm text-base-primary"
        >
            {{ gate.reason('cancelBooking') }}
            <Link
                :href="gate.noticeUrl"
                class="font-semibold underline underline-offset-2 hover:no-underline"
            >
                {{ gate.linkLabel.value }}
                <span aria-hidden="true">→</span>
            </Link>
        </p>

        <!-- Cancellation reason -->
        <div
            v-if="booking.cancellation_reason"
            class="border-t border-default px-4 py-2 text-sm text-error"
        >
            <span class="font-medium">{{ t('booking_calendar.cancellation_reason_label') }}:</span>
            {{ booking.cancellation_reason }}
        </div>
    </div>
</template>
