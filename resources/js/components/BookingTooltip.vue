<script setup lang="ts">
import { Clock } from '@lucide/vue';
import { computed } from 'vue';
import type { BookingTooltipData } from '../types/bookings';
import { wallClockTime } from '../utils/wallClock';

interface Props {
    booking: BookingTooltipData | null;
    x: number;
    y: number;
    visible: boolean;
}

const props = defineProps<Props>();

const statusDotColor = computed(() => {
    if (!props.booking) return '';

    const colorMap: Record<string, string> = {
        pending: 'var(--color-warning)',
        confirmed: 'var(--color-success)',
        completed: 'var(--color-text-muted)',
        cancelled: 'var(--color-error)',
        no_show: 'var(--color-error)',
        rejected: 'var(--color-error)',
    };

    return colorMap[props.booking.status] ?? 'var(--color-text-muted)';
});

// start/end are venue wall-clock timestamps without offset: show their time as written
const formattedTime = computed(() => {
    if (!props.booking) return '';

    return `${wallClockTime(props.booking.start)} - ${wallClockTime(props.booking.end)}`;
});

const tooltipStyle = computed(() => ({
    left: `${props.x + 10}px`,
    top: `${props.y + 10}px`,
}));
</script>

<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transition-opacity duration-150"
            leave-active-class="transition-opacity duration-150"
            enter-from-class="opacity-0"
            leave-to-class="opacity-0"
        >
            <div
                v-if="visible && booking"
                class="pointer-events-none fixed z-50 max-w-xs rounded-lg bg-tooltip px-3.5 py-2.5 text-sm text-tooltip shadow-xl ring-1 ring-tooltip"
                :style="tooltipStyle"
            >
                <!-- Title -->
                <p class="font-semibold leading-snug">{{ booking.title }}</p>

                <!-- Time -->
                <p class="mt-1 text-tooltip-secondary">
                    <Clock class="mr-1 inline-block h-3 w-3" aria-hidden="true" />
                    {{ formattedTime }}
                </p>

                <!-- Status -->
                <p class="mt-1 flex items-center gap-1.5 text-tooltip-secondary">
                    <span
                        class="inline-block h-2 w-2 rounded-full"
                        :style="{ backgroundColor: statusDotColor }"
                    ></span>
                    {{ booking.statusLabel }}
                </p>

                <!-- Arrow -->
                <div class="absolute -left-1 top-3 h-2 w-2 rotate-45 bg-tooltip ring-1 ring-tooltip"></div>
            </div>
        </Transition>
    </Teleport>
</template>
