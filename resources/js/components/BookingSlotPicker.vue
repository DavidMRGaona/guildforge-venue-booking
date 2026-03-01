<script setup lang="ts">
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import type { BookingSlot } from '../types/bookings';

interface Props {
    slots: BookingSlot[];
    selectedSlots: BookingSlot[];
    schedulingMode?: 'time_slots' | 'time_blocks';
    minConsecutiveSlots?: number;
    maxConsecutiveSlots?: number;
}

const props = withDefaults(defineProps<Props>(), {
    schedulingMode: 'time_slots',
    minConsecutiveSlots: 1,
    maxConsecutiveSlots: 4,
});

const emit = defineEmits<{
    'update:selectedSlots': [slots: BookingSlot[]];
}>();

const { t } = useI18n();

const consecutiveError = ref(false);

const isBlockMode = computed(() => props.schedulingMode === 'time_blocks');

const selectedTimeRange = computed(() => {
    if (props.selectedSlots.length === 0) return null;

    if (isBlockMode.value) {
        const slot = props.selectedSlots[0];
        if (!slot) return null;
        return {
            start: slot.start_time.slice(0, 5),
            end: slot.end_time.slice(0, 5),
            label: slot.label ?? null,
        };
    }

    const sorted = [...props.selectedSlots].sort((a, b) =>
        getSlotIndex(a) - getSlotIndex(b)
    );

    const first = sorted[0];
    const last = sorted[sorted.length - 1];

    if (!first || !last) return null;

    return {
        start: first.start_time.slice(0, 5),
        end: last.end_time.slice(0, 5),
        label: null,
    };
});

function isSelected(slot: BookingSlot): boolean {
    return props.selectedSlots.some(
        (s) => s.start_time === slot.start_time && s.end_time === slot.end_time
    );
}

function getSlotIndex(slot: BookingSlot): number {
    return props.slots.findIndex(
        (s) => s.start_time === slot.start_time && s.end_time === slot.end_time
    );
}

function areConsecutive(slots: BookingSlot[]): boolean {
    if (slots.length <= 1) return true;

    const indices = slots
        .map((slot) => getSlotIndex(slot))
        .sort((a, b) => a - b);

    for (let i = 1; i < indices.length; i++) {
        const current = indices[i];
        const previous = indices[i - 1];
        if (current === undefined || previous === undefined) return false;
        if (current !== previous + 1) return false;
    }

    return true;
}

function isDisabledByConsecutive(slot: BookingSlot): boolean {
    if (isBlockMode.value || props.selectedSlots.length === 0) return false;
    if (isSelected(slot)) return false;

    // Max consecutive reached — disable all unselected slots
    if (props.selectedSlots.length >= props.maxConsecutiveSlots) return true;

    // Only allow slots adjacent to the current contiguous block
    const indices = props.selectedSlots
        .map((s) => getSlotIndex(s))
        .sort((a, b) => a - b);

    const minIndex = indices[0];
    const maxIndex = indices[indices.length - 1];
    if (minIndex === undefined || maxIndex === undefined) return false;

    const slotIndex = getSlotIndex(slot);
    return slotIndex !== minIndex - 1 && slotIndex !== maxIndex + 1;
}

function isDisabled(slot: BookingSlot): boolean {
    return !slot.is_available || isDisabledByConsecutive(slot);
}

function toggleSlot(slot: BookingSlot): void {
    if (isDisabled(slot)) return;

    consecutiveError.value = false;

    if (isBlockMode.value) {
        // Block mode: single selection — clicking replaces, clicking same deselects
        if (isSelected(slot)) {
            emit('update:selectedSlots', []);
        } else {
            emit('update:selectedSlots', [slot]);
        }
        return;
    }

    // Time slots mode: consecutive multi-selection
    const alreadySelected = isSelected(slot);

    if (alreadySelected) {
        const newSelection = props.selectedSlots.filter(
            (s) => s.start_time !== slot.start_time || s.end_time !== slot.end_time
        );

        if (areConsecutive(newSelection)) {
            emit('update:selectedSlots', newSelection);
        } else {
            // Removing this slot would break consecutiveness; clear selection
            emit('update:selectedSlots', []);
        }
    } else {
        const newSelection = [...props.selectedSlots, slot];

        if (areConsecutive(newSelection)) {
            emit('update:selectedSlots', newSelection);
        } else {
            consecutiveError.value = true;
        }
    }
}

function formatBlockTime(slot: BookingSlot): string {
    return `${slot.start_time.slice(0, 5)} - ${slot.end_time.slice(0, 5)}`;
}
</script>

<template>
    <div class="mt-6">
        <h3 class="mb-3 text-lg font-semibold text-base-primary">
            {{ isBlockMode ? t('booking_calendar.select_block') : t('booking_calendar.select_slots') }}
        </h3>

        <!-- Min consecutive hint (only in time_slots mode when min > 1) -->
        <p
            v-if="!isBlockMode && props.minConsecutiveSlots > 1"
            class="mb-3 text-sm text-base-secondary"
        >
            {{ t('booking_calendar.min_consecutive_required', { min: props.minConsecutiveSlots }) }}
        </p>

        <!-- Consecutive error (only in time_slots mode) -->
        <div
            v-if="consecutiveError && !isBlockMode"
            class="mb-3 rounded-lg bg-warning-light p-3 text-sm text-warning"
            role="alert"
        >
            {{ t('booking_calendar.consecutive_only') }}
        </div>

        <!-- Time blocks mode -->
        <template v-if="isBlockMode">
            <div
                v-if="slots.length > 0"
                class="flex flex-col gap-3"
            >
                <button
                    v-for="slot in slots"
                    :key="slot.start_time + '-' + slot.end_time"
                    type="button"
                    :disabled="isDisabled(slot)"
                    :aria-pressed="isSelected(slot)"
                    :class="[
                        'flex items-center justify-between rounded-lg border px-4 py-3 text-left transition-colors focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-1 dark:focus:ring-offset-page',
                        isSelected(slot)
                            ? 'border-primary-600 bg-primary-600 text-white'
                            : isDisabled(slot)
                                ? 'cursor-not-allowed border-default bg-muted text-base-muted'
                                : 'border-default bg-surface text-base-primary hover:border-primary-400 hover:bg-primary-light',
                    ]"
                    @click="toggleSlot(slot)"
                >
                    <div>
                        <span
                            v-if="slot.label"
                            class="text-sm font-semibold"
                        >
                            {{ slot.label }}
                        </span>
                        <span class="text-sm" :class="slot.label ? 'ml-2 opacity-75' : 'font-medium'">
                            {{ formatBlockTime(slot) }}
                        </span>
                    </div>
                    <span
                        v-if="!slot.is_available"
                        class="text-xs"
                    >
                        {{ t('booking_calendar.slot_unavailable') }}
                    </span>
                </button>
            </div>

            <!-- No blocks -->
            <p
                v-else
                class="text-sm text-base-secondary"
            >
                {{ t('booking_calendar.no_blocks') }}
            </p>
        </template>

        <!-- Time slots mode (original grid) -->
        <template v-else>
            <div
                v-if="slots.length > 0"
                class="grid grid-cols-3 gap-2 sm:grid-cols-4 md:grid-cols-6"
            >
                <button
                    v-for="slot in slots"
                    :key="slot.start_time"
                    type="button"
                    :disabled="isDisabled(slot)"
                    :aria-pressed="isSelected(slot)"
                    :class="[
                        'rounded-lg border px-3 py-2 text-center text-sm font-medium transition-colors focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-1 dark:focus:ring-offset-page',
                        isSelected(slot)
                            ? 'border-primary-600 bg-primary-600 text-white'
                            : !slot.is_available
                                ? 'cursor-not-allowed border-default bg-muted text-base-muted line-through'
                                : isDisabledByConsecutive(slot)
                                    ? 'cursor-not-allowed border-default bg-muted text-base-muted opacity-50'
                                    : 'border-default bg-surface text-base-primary hover:border-primary-400 hover:bg-primary-light',
                    ]"
                    :title="isDisabledByConsecutive(slot) ? t('booking_calendar.max_consecutive_reached') : undefined"
                    @click="toggleSlot(slot)"
                >
                    {{ slot.start_time.slice(0, 5) }}
                </button>
            </div>

            <!-- No slots -->
            <p
                v-else
                class="text-sm text-base-secondary"
            >
                {{ t('booking_calendar.no_slots') }}
            </p>
        </template>

        <!-- Selected time range summary -->
        <div
            v-if="selectedTimeRange"
            class="mt-4 rounded-lg bg-primary-light p-3"
        >
            <p class="text-sm font-medium text-primary">
                <template v-if="selectedTimeRange.label">
                    {{ t('booking_calendar.selected_block') }}: {{ selectedTimeRange.label }}
                    ({{ t('booking_calendar.time_range', { start: selectedTimeRange.start, end: selectedTimeRange.end }) }})
                </template>
                <template v-else>
                    {{ t('booking_calendar.selected_time') }}:
                    {{ t('booking_calendar.time_range', { start: selectedTimeRange.start, end: selectedTimeRange.end }) }}
                </template>
            </p>
        </div>
    </div>
</template>
