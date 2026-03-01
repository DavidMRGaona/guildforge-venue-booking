<script setup lang="ts">
import { ref, computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { router } from '@inertiajs/vue3';
import ConfirmDialog from '@/components/ui/ConfirmDialog.vue';
import BookingCard from '../BookingCard.vue';
import type { ProfileBookingsData } from '../../types/profile';

interface Props {
    profileBookings: ProfileBookingsData | null;
}

const props = defineProps<Props>();
const { t } = useI18n();

const INITIAL_PAST_COUNT = 5;
const LOAD_MORE_COUNT = 5;

const showPastSection = ref(false);
const visiblePastCount = ref(INITIAL_PAST_COUNT);
const showCancelDialog = ref(false);
const pendingCancelId = ref<string | null>(null);

const upcomingBookings = computed(() => props.profileBookings?.upcoming ?? []);
const pastBookings = computed(() => props.profileBookings?.past ?? []);
const visiblePastBookings = computed(() => pastBookings.value.slice(0, visiblePastCount.value));
const hasMorePast = computed(() => visiblePastCount.value < pastBookings.value.length);
const remainingPastCount = computed(() => pastBookings.value.length - visiblePastCount.value);

function togglePastSection(): void {
    showPastSection.value = !showPastSection.value;
}

function loadMore(): void {
    visiblePastCount.value = Math.min(
        visiblePastCount.value + LOAD_MORE_COUNT,
        pastBookings.value.length,
    );
}

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
    <div v-if="profileBookings && profileBookings.total > 0" class="space-y-10">
        <!-- Upcoming section -->
        <section>
            <h2 class="mb-6 flex items-center gap-2 text-lg font-semibold text-base-primary">
                <span class="flex h-6 w-6 items-center justify-center rounded-full bg-primary-light text-xs font-bold text-primary-700 dark:text-primary-400">
                    {{ upcomingBookings.length }}
                </span>
                {{ t('venueBookings.profile.upcoming') }}
            </h2>

            <div v-if="upcomingBookings.length > 0" class="space-y-3">
                <BookingCard
                    v-for="booking in upcomingBookings"
                    :key="booking.id"
                    :booking="booking"
                    @cancel="handleCancel"
                />
            </div>

            <p
                v-else
                class="rounded-lg border border-dashed border-stone-300 bg-muted p-6 text-center text-sm text-base-muted dark:border-stone-600"
            >
                {{ t('venueBookings.profile.noUpcoming') }}
            </p>
        </section>

        <!-- Past section (collapsible) -->
        <section v-if="pastBookings.length > 0" class="border-t border-default pt-8">
            <button
                type="button"
                class="mb-4 flex w-full items-center justify-between rounded-lg bg-muted px-4 py-3 text-left transition-colors hover:bg-stone-200 dark:hover:bg-stone-700"
                :aria-expanded="showPastSection"
                @click="togglePastSection"
            >
                <span class="flex items-center gap-2 text-lg font-semibold text-base-primary">
                    <span class="flex h-6 w-6 items-center justify-center rounded-full bg-stone-200 text-xs font-bold text-stone-600 dark:bg-stone-700 dark:text-stone-400">
                        {{ pastBookings.length }}
                    </span>
                    {{ t('venueBookings.profile.past') }}
                </span>
                <svg
                    :class="['h-5 w-5 text-base-muted transition-transform', showPastSection ? 'rotate-180' : '']"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                    aria-hidden="true"
                >
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                </svg>
            </button>

            <div v-if="showPastSection" class="space-y-3">
                <BookingCard
                    v-for="booking in visiblePastBookings"
                    :key="booking.id"
                    :booking="booking"
                    @cancel="handleCancel"
                />

                <!-- Load more button -->
                <button
                    v-if="hasMorePast"
                    type="button"
                    class="flex w-full items-center justify-center gap-2 rounded-lg border border-stone-300 bg-surface py-3 text-sm font-medium text-base-secondary transition-colors hover:bg-stone-50 dark:border-stone-600 dark:hover:bg-stone-700"
                    @click="loadMore"
                >
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                    </svg>
                    {{ t('venueBookings.profile.loadMore', { count: remainingPastCount }) }}
                </button>
            </div>
        </section>

        <!-- Cancel confirmation dialog -->
        <ConfirmDialog
            v-model="showCancelDialog"
            :title="t('venueBookings.profile.cancelConfirmTitle')"
            :message="t('venueBookings.profile.cancelConfirmMessage')"
            :confirm-label="t('venueBookings.profile.cancelConfirmButton')"
            confirm-variant="danger"
            @confirm="confirmCancel"
        />
    </div>

    <!-- Empty state when no bookings at all -->
    <div
        v-else
        class="rounded-lg border border-dashed border-stone-300 bg-muted p-12 text-center dark:border-stone-600"
    >
        <svg
            class="mx-auto h-12 w-12 text-stone-400"
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
        <h3 class="mt-4 text-lg font-medium text-base-primary">
            {{ t('venueBookings.profile.noBookings') }}
        </h3>
        <p class="mt-1 text-sm text-base-muted">
            {{ t('venueBookings.profile.noBookingsDescription') }}
        </p>
    </div>
</template>
