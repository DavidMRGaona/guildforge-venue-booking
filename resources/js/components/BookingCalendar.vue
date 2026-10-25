<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import FullCalendar from '@fullcalendar/vue3';
import dayGridPlugin from '@fullcalendar/vue3/daygrid';
import timeGridPlugin from '@fullcalendar/vue3/timegrid';
import interactionPlugin from '@fullcalendar/vue3/interaction';
import { useCalendarLocale } from '@/composables/useCalendarLocale';
import { calendarClassOptions, calendarThemePlugin } from '@/utils/calendarTheme';
import type {
    CalendarOptions,
    DateClickInfo,
    EventClickInfo,
    EventHoveringInfo,
    EventInput,
    EventSourceFuncInfo,
} from '@fullcalendar/vue3';
import type { BookingCalendarEvent, BookingTooltipData } from '../types/bookings';
import BookingTooltip from './BookingTooltip.vue';
import { VENUE_TIMEZONE } from '@/utils/datetime';

interface Props {
    resourceId: string;
    /** First selectable day (YYYY-MM-DD, venue day); the server refuses earlier ones too. */
    minDate: string;
}

const props = defineProps<Props>();

const emit = defineEmits<{
    dateSelect: [date: string];
    eventSelect: [bookingId: string];
}>();

const { t } = useI18n();
const calendarLocale = useCalendarLocale();

const isLoading = ref(false);
const error = ref<string | null>(null);
const eventsCache = ref<Map<string, BookingTooltipData>>(new Map());

// Tooltip state
const tooltipBooking = ref<BookingTooltipData | null>(null);
const tooltipX = ref(0);
const tooltipY = ref(0);
const tooltipVisible = ref(false);

const fetchEvents = async (
    info: EventSourceFuncInfo,
    successCallback: (events: EventInput[]) => void,
    failureCallback: (error: Error) => void
): Promise<void> => {
    isLoading.value = true;
    error.value = null;

    try {
        const params = new URLSearchParams({
            resource_id: props.resourceId,
            start: info.startStr,
            end: info.endStr,
        });

        const response = await fetch(`/reservas/api/events?${params}`, {
            headers: { Accept: 'application/json' },
        });
        if (!response.ok) {
            throw new Error('Failed to fetch booking events');
        }

        const data: BookingCalendarEvent[] = await response.json();

        // Cache events for tooltip access
        eventsCache.value.clear();
        data.forEach((event) => {
            eventsCache.value.set(event.id, {
                id: event.id,
                title: event.title,
                start: event.start,
                end: event.end,
                status: event.status,
                statusLabel: event.status_label,
            });
        });

        const mappedEvents: EventInput[] = data.map((event) => ({
            id: event.id,
            title: event.title,
            start: event.start,
            end: event.end,
            color: event.color,
            // The event colour is a pale status background in both themes
            contrastColor: 'var(--color-text-primary)',
        }));

        successCallback(mappedEvents);
    } catch (e) {
        error.value = t('booking_calendar.error');
        console.error('Error fetching booking events:', e);
        failureCallback(e instanceof Error ? e : new Error('Unknown error'));
    } finally {
        isLoading.value = false;
    }
};

const handleDateClick = (info: DateClickInfo): void => {
    emit('dateSelect', info.dateStr.slice(0, 10));
};

const handleEventClick = (info: EventClickInfo): void => {
    info.jsEvent.preventDefault();
    emit('eventSelect', info.event.id);
};

const handleMouseEnter = (info: EventHoveringInfo): void => {
    const cached = eventsCache.value.get(info.event.id);
    if (cached) {
        tooltipBooking.value = cached;
        tooltipX.value = info.jsEvent.clientX;
        tooltipY.value = info.jsEvent.clientY;
        tooltipVisible.value = true;
    }
};

const handleMouseLeave = (): void => {
    tooltipVisible.value = false;
};

// Track cursor position while tooltip is visible
const handleGlobalMouseMove = (event: MouseEvent): void => {
    if (tooltipVisible.value) {
        tooltipX.value = event.clientX;
        tooltipY.value = event.clientY;
    }
};

watch(tooltipVisible, (visible) => {
    if (visible) {
        document.addEventListener('mousemove', handleGlobalMouseMove);
    } else {
        document.removeEventListener('mousemove', handleGlobalMouseMove);
    }
});

const calendarOptions = computed<CalendarOptions>(() => ({
    ...calendarClassOptions,
    plugins: [calendarThemePlugin, dayGridPlugin, timeGridPlugin, interactionPlugin],
    initialView: 'dayGridMonth',
    // The week/day time grids position real hours, so the calendar must resolve
    // them in the venue timezone rather than the visitor's.
    timeZone: VENUE_TIMEZONE,
    // Past days cannot be booked: grey them out and stop navigating before today
    validRange: { start: props.minDate },
    locale: calendarLocale.value,
    events: fetchEvents,
    dateClick: handleDateClick,
    eventClick: handleEventClick,
    eventMouseEnter: handleMouseEnter,
    eventMouseLeave: handleMouseLeave,
    displayEventEnd: true,
    eventTimeFormat: {
        hour: '2-digit',
        minute: '2-digit',
        hour12: false,
    },
    slotHeaderFormat: {
        hour: '2-digit',
        minute: '2-digit',
        hour12: false,
    },
    headerToolbar: {
        left: 'prev,next today',
        center: 'title',
        right: 'dayGridMonth,timeGridWeek,timeGridDay',
    },
    buttons: {
        today: { text: t('calendar.today') },
        dayGridMonth: { text: t('booking_calendar.view_month') },
        timeGridWeek: { text: t('booking_calendar.view_week') },
        timeGridDay: { text: t('booking_calendar.view_day') },
    },
    allDayText: t('booking_calendar.all_day'),
    height: 'auto',
    fixedWeekCount: false,
    eventDisplay: 'block',
    loading: (isLoadingArg: boolean) => {
        isLoading.value = isLoadingArg;
    },
}));
</script>

<template>
    <div class="event-calendar booking-calendar">
        <div v-if="error" role="alert" class="mb-4 rounded-lg bg-error-light p-4 text-center">
            <p class="text-sm font-medium text-error">
                {{ error }}
            </p>
        </div>

        <div class="relative">
            <div
                v-if="isLoading"
                class="absolute inset-0 z-10 flex items-center justify-center bg-surface/70"
            >
                <div class="text-center">
                    <div
                        class="inline-block h-8 w-8 animate-spin rounded-full border-4 border-primary-500 border-t-transparent"
                    ></div>
                    <p class="mt-2 text-sm text-base-secondary">
                        {{ t('booking_calendar.loading') }}
                    </p>
                </div>
            </div>

            <FullCalendar :key="resourceId" :options="calendarOptions" />
        </div>

        <!-- Tooltip -->
        <BookingTooltip
            :booking="tooltipBooking"
            :x="tooltipX"
            :y="tooltipY"
            :visible="tooltipVisible"
        />
    </div>
</template>
