<script setup lang="ts">
import { BookOpen, Box, Building, Calendar, CircleCheck, Clock, User, X } from '@lucide/vue';
import { computed, Teleport, Transition } from 'vue';
import { useI18n } from 'vue-i18n';
import type { BookingDetail } from '../types/bookings';
import BookingStatusBadge from './BookingStatusBadge.vue';
import { VENUE_TIMEZONE } from '@/utils/datetime';

interface Props {
    booking: BookingDetail;
}

const props = defineProps<Props>();

const emit = defineEmits<{
    close: [];
}>();

const { t, locale } = useI18n();

const formattedDate = computed(() => {
    const date = new Date(`${props.booking.date}T12:00:00Z`);
    return date.toLocaleDateString(locale.value, {
        timeZone: VENUE_TIMEZONE,
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
                                <X class="h-5 w-5" aria-hidden="true" />
                                <span class="sr-only">{{ t('booking_detail.close') }}</span>
                            </button>
                        </div>

                        <div class="px-6 py-5">
                            <!-- Booking summary -->
                            <div class="space-y-2.5 rounded-lg bg-muted p-4">
                                <!-- Resource -->
                                <div class="flex items-center gap-2 text-sm text-base-secondary">
                                    <Building
                                        class="h-4 w-4 flex-shrink-0 text-base-muted"
                                        aria-hidden="true"
                                    />
                                    <span class="font-medium">{{ booking.resource_name }}</span>
                                </div>

                                <!-- Date -->
                                <div class="flex items-center gap-2 text-sm text-base-secondary">
                                    <Calendar
                                        class="h-4 w-4 flex-shrink-0 text-base-muted"
                                        aria-hidden="true"
                                    />
                                    <span>{{ formattedDate }}</span>
                                </div>

                                <!-- Time -->
                                <div class="flex items-center gap-2 text-sm text-base-secondary">
                                    <Clock
                                        class="h-4 w-4 flex-shrink-0 text-base-muted"
                                        aria-hidden="true"
                                    />
                                    <span>{{ timeRange }}</span>
                                </div>

                                <!-- Status -->
                                <div class="flex items-center gap-2 text-sm">
                                    <CircleCheck
                                        class="h-4 w-4 flex-shrink-0 text-base-muted"
                                        aria-hidden="true"
                                    />
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
                                    <User
                                        class="h-4 w-4 flex-shrink-0 text-base-muted"
                                        aria-hidden="true"
                                    />
                                    <span>{{ booking.user_name }}</span>
                                </div>

                                <!-- Game table -->
                                <div
                                    v-if="booking.game_table_name"
                                    class="flex items-center gap-2 text-sm text-base-secondary"
                                >
                                    <Box
                                        class="h-4 w-4 flex-shrink-0 text-base-muted"
                                        aria-hidden="true"
                                    />
                                    <span>{{ t('booking_detail.game_table') }}: {{ booking.game_table_name }}</span>
                                </div>

                                <!-- Campaign -->
                                <div
                                    v-if="booking.campaign_name"
                                    class="flex items-center gap-2 text-sm text-base-secondary"
                                >
                                    <BookOpen
                                        class="h-4 w-4 flex-shrink-0 text-base-muted"
                                        aria-hidden="true"
                                    />
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
