<script setup lang="ts">
import { Calendar, Clock } from '@lucide/vue';
import LoadingSpinner from '@/components/ui/LoadingSpinner.vue';
import { ref, computed, useId } from 'vue';
import { useI18n } from 'vue-i18n';
import { Link, useForm } from '@inertiajs/vue3';
import type { FieldDefinition, BookingEligibility } from '../types/bookings';
import { VENUE_TIMEZONE } from '@/utils/datetime';
import { useVerificationGate } from '@/composables/useVerificationGate';
import { useModalDialog } from '../composables/useModalDialog';

interface Props {
    resourceId: string;
    date: string;
    startTime: string;
    endTime: string;
    fieldDefinitions: FieldDefinition[];
    eligibility: BookingEligibility | null;
    /** The eligibility check answered that the account must verify its email. */
    verificationRequired?: boolean;
    /** The first slot is past the midnight of `date`: the booking takes place the next day. */
    startsNextDay?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    verificationRequired: false,
    startsNextDay: false,
});

const emit = defineEmits<{
    close: [];
    submitted: [];
}>();

const { t, locale } = useI18n();

const loading = ref(false);

// eslint-disable-next-line no-undef
const dialog = ref<HTMLDialogElement | null>(null);
const titleId = useId();
const { isBackdropClick } = useModalDialog(dialog);

// POST /reservas is closed to accounts that must verify their email. A null eligibility (its
// endpoint failed) must never leave the form open to them.
const gate = useVerificationGate();
const verificationBlockedId = useId();
const verificationBlocked = computed(
    () => gate.blocked.value || props.verificationRequired
);

// Build initial form data from field definitions
function buildInitialData(): Record<string, string | number | boolean> {
    const data: Record<string, string | number | boolean> = {};
    props.fieldDefinitions.forEach((field) => {
        switch (field.type) {
            case 'number':
                data[field.key] = 0;
                break;
            case 'toggle':
                data[field.key] = false;
                break;
            default:
                data[field.key] = '';
        }
    });
    return data;
}

const form = useForm({
    resource_id: props.resourceId,
    date: props.date,
    start_time: props.startTime,
    end_time: props.endTime,
    field_values: buildInitialData(),
});

const canBook = computed(() => {
    if (verificationBlocked.value) return false;
    if (!props.eligibility) return true;
    return props.eligibility.can_book;
});

// Shows the day the booking takes place; the form still sends `date`, the day whose slots were picked
const formattedDate = computed(() => {
    const date = new Date(`${props.date}T12:00:00Z`);
    if (props.startsNextDay) {
        date.setUTCDate(date.getUTCDate() + 1);
    }
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
        start: props.startTime.slice(0, 5),
        end: props.endTime.slice(0, 5),
    })
);

function handleSubmit(): void {
    if (!canBook.value) return;

    loading.value = true;

    form.post('/reservas', {
        preserveScroll: true,
        onSuccess: (page) => {
            // Must verify after all (e.g. changed in another tab): the server sent us to the notice
            if (gate.isVerificationRequired(page)) {
                return;
            }
            emit('submitted');
            emit('close');
        },
        onFinish: () => {
            loading.value = false;
        },
    });
}

function handleClose(): void {
    if (!loading.value) {
        emit('close');
    }
}

function onBackdropClick(event: MouseEvent): void {
    if (isBackdropClick(event)) {
        handleClose();
    }
}

function updateField(key: string, value: string | number | boolean): void {
    form.field_values[key] = value;
}
</script>

<template>
    <Teleport to="body">
        <dialog
            ref="dialog"
            aria-modal="true"
            :aria-labelledby="titleId"
            class="fixed inset-0 m-auto max-h-[calc(100%-2rem)] w-[calc(100%-2rem)] max-w-lg overflow-y-auto rounded-xl border-0 bg-surface p-6 text-base-primary shadow-2xl backdrop:bg-black/50"
            @click="onBackdropClick"
            @cancel.prevent="handleClose"
        >
            <!-- Header -->
            <h3 :id="titleId" class="mb-4 text-lg font-semibold text-base-primary">
                {{ t('booking_calendar.book') }}
            </h3>

            <!-- Booking summary -->
            <div class="mb-4 rounded-lg bg-muted p-3">
                <div class="flex items-center gap-1.5 text-sm text-base-secondary">
                    <Calendar
                        class="h-4 w-4 flex-shrink-0 text-base-muted"
                        aria-hidden="true"
                    />
                    <span>{{ formattedDate }}</span>
                </div>
                <div class="mt-1 flex items-center gap-1.5 text-sm text-base-secondary">
                    <Clock
                        class="h-4 w-4 flex-shrink-0 text-base-muted"
                        aria-hidden="true"
                    />
                    <span>{{ timeRange }}</span>
                </div>
            </div>

            <!-- Why booking is disabled for an account that must verify its email -->
            <p
                v-if="verificationBlocked"
                :id="verificationBlockedId"
                class="mb-4 rounded-md bg-warning-light p-3 text-sm text-base-primary"
            >
                {{ gate.reason('book') }}
                <Link
                    :href="gate.noticeUrl"
                    class="font-semibold underline underline-offset-2 hover:no-underline"
                >
                    {{ gate.linkLabel.value }}
                    <span aria-hidden="true">→</span>
                </Link>
            </p>

            <!-- Eligibility warning -->
            <div
                v-else-if="eligibility && !eligibility.can_book"
                class="mb-4 rounded-lg bg-warning-light p-3"
                role="alert"
            >
                <p class="mb-1 text-sm font-medium text-warning">
                    {{ t('booking_calendar.eligibility_warning') }}
                </p>
                <ul class="list-inside list-disc text-sm text-warning">
                    <li v-for="reason in eligibility.reasons" :key="reason">
                        {{ reason }}
                    </li>
                </ul>
            </div>

            <!-- Error message -->
            <div
                v-if="form.hasErrors"
                class="mb-4 rounded-lg bg-error-light p-3 text-sm text-base-primary"
                role="alert"
            >
                <p v-for="(errorMsg, field) in form.errors" :key="field">
                    {{ errorMsg }}
                </p>
            </div>

            <!-- Dynamic fields -->
            <form
                v-if="canBook"
                class="space-y-4"
                @submit.prevent="handleSubmit"
            >
                <div
                    v-for="field in fieldDefinitions"
                    :key="field.key"
                >
                    <label
                        :for="`field-${field.key}`"
                        class="mb-1 block text-sm font-medium text-base-secondary"
                    >
                        {{ field.label }}
                        <span v-if="field.required" class="text-error">*</span>
                    </label>

                    <!-- Text input -->
                    <input
                        v-if="field.type === 'text'"
                        :id="`field-${field.key}`"
                        type="text"
                        :required="field.required"
                        :value="form.field_values[field.key]"
                        class="w-full rounded-lg border border-default bg-surface px-3 py-2 text-sm text-base-primary focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500"
                        @input="updateField(field.key, ($event.target as HTMLInputElement).value)"
                    />

                    <!-- Textarea -->
                    <textarea
                        v-else-if="field.type === 'textarea'"
                        :id="`field-${field.key}`"
                        :required="field.required"
                        :value="form.field_values[field.key] as string"
                        rows="3"
                        class="w-full rounded-lg border border-default bg-surface px-3 py-2 text-sm text-base-primary focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500"
                        @input="updateField(field.key, ($event.target as HTMLTextAreaElement).value)"
                    ></textarea>

                    <!-- Number input -->
                    <input
                        v-else-if="field.type === 'number'"
                        :id="`field-${field.key}`"
                        type="number"
                        :required="field.required"
                        :value="form.field_values[field.key]"
                        min="0"
                        class="w-full rounded-lg border border-default bg-surface px-3 py-2 text-sm text-base-primary focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500"
                        @input="updateField(field.key, Number(($event.target as HTMLInputElement).value))"
                    />

                    <!-- Select -->
                    <select
                        v-else-if="field.type === 'select'"
                        :id="`field-${field.key}`"
                        :required="field.required"
                        :value="form.field_values[field.key] as string"
                        class="w-full rounded-lg border border-default bg-surface px-3 py-2 text-sm text-base-primary focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500"
                        @change="updateField(field.key, ($event.target as HTMLSelectElement).value)"
                    >
                        <option value="" disabled></option>
                        <option
                            v-for="option in (field.options ?? [])"
                            :key="option"
                            :value="option"
                        >
                            {{ option }}
                        </option>
                    </select>

                    <!-- Toggle -->
                    <div
                        v-else-if="field.type === 'toggle'"
                        class="flex items-center gap-2"
                    >
                        <input
                            :id="`field-${field.key}`"
                            type="checkbox"
                            :checked="!!form.field_values[field.key]"
                            class="h-4 w-4 rounded border-default text-primary-600 focus:ring-primary-500"
                            @change="updateField(field.key, ($event.target as HTMLInputElement).checked)"
                        />
                    </div>
                </div>

                <!-- Buttons -->
                <div class="flex gap-3 pt-2">
                    <button
                        type="button"
                        class="flex-1 rounded-lg border border-default px-4 py-2.5 text-sm font-medium text-base-secondary transition-colors hover:bg-muted"
                        @click="handleClose"
                    >
                        {{ t('booking_calendar.cancel') }}
                    </button>
                    <button
                        type="submit"
                        :disabled="loading || form.processing"
                        class="flex-1 rounded-lg bg-primary px-4 py-2.5 text-sm font-medium text-white transition-colors hover:bg-primary-700 disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        <span
                            v-if="loading || form.processing"
                            class="flex items-center justify-center"
                        >
                            <LoadingSpinner
                                class="mr-2"
                                size="sm"
                                color="text-current"
                            />
                        </span>
                        <span v-else>{{ t('booking_calendar.book') }}</span>
                    </button>
                </div>
            </form>

            <!-- Disabled booking while the account must verify its email -->
            <div v-else-if="verificationBlocked" class="flex gap-3 pt-2">
                <button
                    type="button"
                    class="flex-1 rounded-lg border border-default px-4 py-2.5 text-sm font-medium text-base-secondary transition-colors hover:bg-muted"
                    @click="handleClose"
                >
                    {{ t('booking_calendar.cancel') }}
                </button>
                <button
                    type="button"
                    disabled
                    :aria-describedby="verificationBlockedId"
                    class="flex-1 cursor-not-allowed rounded-lg bg-primary px-4 py-2.5 text-sm font-medium text-white opacity-50"
                >
                    {{ t('booking_calendar.book') }}
                </button>
            </div>

            <!-- Close button when not eligible -->
            <div v-else class="pt-2">
                <button
                    type="button"
                    class="w-full rounded-lg border border-default px-4 py-2.5 text-sm font-medium text-base-secondary transition-colors hover:bg-muted"
                    @click="handleClose"
                >
                    {{ t('booking_calendar.cancel') }}
                </button>
            </div>
        </dialog>
    </Teleport>
</template>
