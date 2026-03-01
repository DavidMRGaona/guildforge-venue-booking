<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import DefaultLayout from '@/layouts/DefaultLayout.vue';
import { useSeo } from '@/composables/useSeo';
import { useFlashMessages } from '@/composables/useFlashMessages';
import { useAuth } from '@/composables/useAuth';
import type {
    BookableResource,
    BookingDetail,
    BookingSlot,
    BookingEligibility,
} from '../../types/bookings';
import ResourceSelector from '../../components/ResourceSelector.vue';
import BookingCalendar from '../../components/BookingCalendar.vue';
import BookingSlotPicker from '../../components/BookingSlotPicker.vue';
import BookingForm from '../../components/BookingForm.vue';
import BookingDetailModal from '../../components/BookingDetailModal.vue';

interface Props {
    resources: BookableResource[];
    selectedResourceId: string | null;
}

const props = withDefaults(defineProps<Props>(), {
    resources: () => [],
});

const { t } = useI18n();
const { success, error: flashError, hasMessages } = useFlashMessages();
const { isAuthenticated } = useAuth();

useSeo({
    title: t('booking_calendar.title'),
});

// State
const firstResource = props.resources[0];
const currentResourceId = ref<string>(
    props.selectedResourceId ?? (firstResource ? firstResource.id : '')
);
const selectedDate = ref<string | null>(null);
const slots = ref<BookingSlot[]>([]);
const selectedSlots = ref<BookingSlot[]>([]);
const showForm = ref(false);
const slotsLoading = ref(false);
const slotsError = ref<string | null>(null);
const eligibility = ref<BookingEligibility | null>(null);
const selectedBooking = ref<BookingDetail | null>(null);

// Computed
const currentResource = computed(() =>
    props.resources.find((r) => r.id === currentResourceId.value) ?? null
);

const currentSchedulingMode = computed(() =>
    currentResource.value?.scheduling_mode ?? 'time_slots'
);

const currentFieldDefinitions = computed(() =>
    currentResource.value?.field_definitions ?? []
);

const bookingStartTime = computed(() => {
    if (selectedSlots.value.length === 0) return '';
    if (currentSchedulingMode.value === 'time_blocks') {
        const slot = selectedSlots.value[0];
        return slot ? slot.start_time : '';
    }
    const sorted = [...selectedSlots.value].sort((a, b) =>
        getSlotIndex(a) - getSlotIndex(b)
    );
    const first = sorted[0];
    return first ? first.start_time : '';
});

const bookingEndTime = computed(() => {
    if (selectedSlots.value.length === 0) return '';
    if (currentSchedulingMode.value === 'time_blocks') {
        const slot = selectedSlots.value[0];
        return slot ? slot.end_time : '';
    }
    const sorted = [...selectedSlots.value].sort((a, b) =>
        getSlotIndex(a) - getSlotIndex(b)
    );
    const last = sorted[sorted.length - 1];
    return last ? last.end_time : '';
});

function getSlotIndex(slot: BookingSlot): number {
    return slots.value.findIndex(
        (s) => s.start_time === slot.start_time && s.end_time === slot.end_time
    );
}

// Reset slots and eligibility when resource changes
watch(currentResourceId, () => {
    selectedDate.value = null;
    slots.value = [];
    selectedSlots.value = [];
    showForm.value = false;
    eligibility.value = null;
});

// Methods
function onResourceSelect(id: string): void {
    currentResourceId.value = id;
}

async function onDateSelect(date: string): Promise<void> {
    selectedDate.value = date;
    selectedSlots.value = [];
    showForm.value = false;
    slotsLoading.value = true;
    slotsError.value = null;
    eligibility.value = null;

    try {
        const params = new URLSearchParams({
            resource_id: currentResourceId.value,
            date,
        });

        const jsonHeaders = { headers: { Accept: 'application/json' } };

        const [slotsResponse, eligibilityResult] = await Promise.all([
            fetch(`/reservas/api/slots?${params}`, jsonHeaders),
            isAuthenticated.value
                ? fetch(`/reservas/api/eligibility?${params}`, jsonHeaders).then(
                      (r) => (r.ok ? r.json() : null)
                  )
                : Promise.resolve(null),
        ]);

        if (!slotsResponse.ok) {
            throw new Error('Failed to fetch slots');
        }

        slots.value = await slotsResponse.json();
        eligibility.value = eligibilityResult;
    } catch (e) {
        console.error('Error fetching slots:', e);
        slotsError.value = t('booking_calendar.error');
        slots.value = [];
    } finally {
        slotsLoading.value = false;
    }
}

function onSlotsUpdate(newSlots: BookingSlot[]): void {
    selectedSlots.value = newSlots;
}

function openBookingForm(): void {
    if (selectedSlots.value.length > 0) {
        showForm.value = true;
    }
}

function onBookingSubmitted(): void {
    showForm.value = false;
    selectedDate.value = null;
    slots.value = [];
    selectedSlots.value = [];
}

async function onEventSelect(bookingId: string): Promise<void> {
    selectedBooking.value = null;

    try {
        const response = await fetch(`/reservas/api/bookings/${bookingId}`, {
            headers: { Accept: 'application/json' },
        });
        if (!response.ok) {
            throw new Error('Failed to fetch booking detail');
        }
        selectedBooking.value = await response.json();
    } catch (e) {
        console.error('Error fetching booking detail:', e);
    }
}
</script>

<template>
    <DefaultLayout>
        <div class="bg-surface shadow dark:shadow-neutral-900/50">
            <div class="mx-auto max-w-5xl px-4 py-8 sm:px-6 lg:px-8">
                <h1 class="text-3xl font-bold tracking-tight text-base-primary">
                    {{ t('booking_calendar.title') }}
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

            <!-- Empty state: no resources -->
            <div
                v-if="resources.length === 0"
                class="flex flex-col items-center justify-center py-16 text-center"
            >
                <svg
                    xmlns="http://www.w3.org/2000/svg"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke-width="1.5"
                    stroke="currentColor"
                    class="mb-4 h-12 w-12 text-base-tertiary"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5"
                    />
                </svg>
                <p class="text-base-secondary">
                    {{ t('booking_calendar.no_resources') }}
                </p>
            </div>

            <!-- Content when resources exist -->
            <template v-else>
                <!-- Resource selector -->
                <ResourceSelector
                    v-if="resources.length > 1"
                    :resources="resources"
                    :selected-id="currentResourceId"
                    @select="onResourceSelect"
                />

                <!-- Calendar -->
                <BookingCalendar
                    v-if="currentResourceId"
                    :resource-id="currentResourceId"
                    @date-select="onDateSelect"
                    @event-select="onEventSelect"
                />

                <!-- Date selection prompt -->
                <p
                    v-if="!selectedDate && currentResourceId"
                    class="mt-6 text-center text-sm text-base-secondary"
                >
                    {{ t('booking_calendar.select_date') }}
                </p>

                <!-- Slots loading -->
                <div
                    v-if="slotsLoading"
                    class="mt-6 flex items-center justify-center"
                >
                    <div
                        class="inline-block h-6 w-6 animate-spin rounded-full border-4 border-primary-500 border-t-transparent"
                    ></div>
                    <span class="ml-2 text-sm text-base-secondary">
                        {{ t('booking_calendar.loading') }}
                    </span>
                </div>

                <!-- Slots error -->
                <div
                    v-if="slotsError"
                    class="mt-6 rounded-lg bg-error-light p-4 text-center text-sm font-medium text-error"
                    role="alert"
                >
                    {{ slotsError }}
                </div>

                <!-- Slot picker -->
                <BookingSlotPicker
                    v-if="selectedDate && !slotsLoading && !slotsError"
                    :slots="slots"
                    :selected-slots="selectedSlots"
                    :scheduling-mode="currentSchedulingMode"
                    @update:selected-slots="onSlotsUpdate"
                />

                <!-- Book button -->
                <div
                    v-if="selectedSlots.length > 0"
                    class="mt-6 flex justify-center"
                >
                    <button
                        type="button"
                        class="rounded-lg bg-primary-600 px-8 py-3 text-sm font-semibold text-white transition-colors hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 dark:focus:ring-offset-page"
                        @click="openBookingForm"
                    >
                        {{ t('booking_calendar.book') }}
                    </button>
                </div>

                <!-- Booking form modal -->
                <BookingForm
                    v-if="showForm && selectedDate"
                    :resource-id="currentResourceId"
                    :date="selectedDate"
                    :start-time="bookingStartTime"
                    :end-time="bookingEndTime"
                    :field-definitions="currentFieldDefinitions"
                    :eligibility="eligibility"
                    @close="showForm = false"
                    @submitted="onBookingSubmitted"
                />

                <!-- Booking detail modal -->
                <BookingDetailModal
                    v-if="selectedBooking"
                    :booking="selectedBooking"
                    @close="selectedBooking = null"
                />
            </template>
        </main>
    </DefaultLayout>
</template>
