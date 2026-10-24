<script setup lang="ts">
import { Calendar, ChevronDown, Plus } from '@lucide/vue';
import { ref, computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { router, Link } from '@inertiajs/vue3';
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
const canCancel = computed(() => props.profileBookings?.canCancel ?? false);
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
    <div class="space-y-10">
        <!-- Links to the full list and to the calendar: shown with no bookings too -->
        <div class="flex flex-wrap justify-end gap-3">
            <Link
                href="/reservas/mis-reservas"
                class="inline-flex min-h-[44px] items-center gap-2 rounded-md bg-primary px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-primary-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary"
            >
                {{ t('venueBookings.profile.viewAll') }}
            </Link>
            <Link
                href="/reservas"
                class="inline-flex min-h-[44px] items-center gap-2 rounded-md border border-default px-4 py-2 text-sm font-semibold text-base-primary hover:bg-base-secondary/10 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary"
            >
                {{ t('booking_calendar.new_booking') }}
            </Link>
        </div>

        <template v-if="profileBookings && profileBookings.total > 0">
            <!-- Upcoming section -->
            <section>
                <h2 class="mb-6 flex items-center gap-2 text-lg font-semibold text-base-primary">
                    <span class="flex h-6 w-6 items-center justify-center rounded-full bg-primary-light text-xs font-bold text-primary">
                        {{ upcomingBookings.length }}
                    </span>
                    {{ t('venueBookings.profile.upcoming') }}
                </h2>

                <div v-if="upcomingBookings.length > 0" class="space-y-3">
                    <BookingCard
                        v-for="booking in upcomingBookings"
                        :key="booking.id"
                        :booking="booking"
                        :can-cancel="canCancel"
                        @cancel="handleCancel"
                    />
                </div>

                <p
                    v-else
                    class="rounded-lg border border-dashed border-default bg-muted p-6 text-center text-sm text-base-muted"
                >
                    {{ t('venueBookings.profile.noUpcoming') }}
                </p>
            </section>

            <!-- Past section (collapsible) -->
            <section v-if="pastBookings.length > 0" class="border-t border-default pt-8">
                <button
                    type="button"
                    class="mb-4 flex w-full items-center justify-between rounded-lg bg-base-secondary/10 px-4 py-3 text-left transition-colors hover:bg-base-secondary/20"
                    :aria-expanded="showPastSection"
                    @click="togglePastSection"
                >
                    <span class="flex items-center gap-2 text-lg font-semibold text-base-primary">
                        <span class="flex h-6 w-6 items-center justify-center rounded-full bg-base-secondary/20 text-xs font-bold text-base-secondary">
                            {{ pastBookings.length }}
                        </span>
                        {{ t('venueBookings.profile.past') }}
                    </span>
                    <ChevronDown
                        :class="['h-5 w-5 text-base-muted transition-transform', showPastSection ? 'rotate-180' : '']"
                        aria-hidden="true"
                    />
                </button>

                <div v-if="showPastSection" class="space-y-3">
                    <BookingCard
                        v-for="booking in visiblePastBookings"
                        :key="booking.id"
                        :booking="booking"
                        :can-cancel="canCancel"
                        @cancel="handleCancel"
                    />

                    <!-- Load more button -->
                    <button
                        v-if="hasMorePast"
                        type="button"
                        class="flex w-full items-center justify-center gap-2 rounded-lg border border-default bg-transparent py-3 text-sm font-medium text-base-secondary transition-colors hover:bg-base-secondary/10"
                        @click="loadMore"
                    >
                        <Plus class="h-4 w-4" aria-hidden="true" />
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
        </template>

        <!-- Empty state when no bookings at all -->
        <div
            v-else
            class="rounded-lg border border-dashed border-default bg-muted p-12 text-center"
        >
            <Calendar class="mx-auto h-12 w-12 text-base-muted" aria-hidden="true" />
            <h3 class="mt-4 text-lg font-medium text-base-primary">
                {{ t('venueBookings.profile.noBookings') }}
            </h3>
            <p class="mt-1 text-sm text-base-muted">
                {{ t('venueBookings.profile.noBookingsDescription') }}
            </p>
        </div>
    </div>
</template>
