<script setup lang="ts">
import { ref, computed, Teleport, Transition } from 'vue';
import { useI18n } from 'vue-i18n';
import { useForm } from '@inertiajs/vue3';
import type { FieldDefinition, BookingEligibility } from '../types/bookings';

interface Props {
    resourceId: string;
    date: string;
    startTime: string;
    endTime: string;
    fieldDefinitions: FieldDefinition[];
    eligibility: BookingEligibility | null;
}

const props = defineProps<Props>();

const emit = defineEmits<{
    close: [];
    submitted: [];
}>();

const { t, locale } = useI18n();

const loading = ref(false);

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
    if (!props.eligibility) return true;
    return props.eligibility.can_book;
});

const formattedDate = computed(() => {
    const date = new Date(props.date + 'T12:00:00');
    return date.toLocaleDateString(locale.value, {
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
        onSuccess: () => {
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

function updateField(key: string, value: string | number | boolean): void {
    form.field_values[key] = value;
}
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
                @click.self="handleClose"
            >
                <Transition
                    enter-active-class="transition-all duration-200"
                    enter-from-class="scale-95 opacity-0"
                    enter-to-class="scale-100 opacity-100"
                    leave-active-class="transition-all duration-150"
                    leave-from-class="scale-100 opacity-100"
                    leave-to-class="scale-95 opacity-0"
                >
                    <div class="w-full max-w-lg rounded-xl bg-surface p-6 shadow-2xl">
                        <!-- Header -->
                        <h3 class="mb-4 text-lg font-semibold text-base-primary">
                            {{ t('booking_calendar.book') }}
                        </h3>

                        <!-- Booking summary -->
                        <div class="mb-4 rounded-lg bg-muted p-3">
                            <div class="flex items-center gap-1.5 text-sm text-base-secondary">
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
                            <div class="mt-1 flex items-center gap-1.5 text-sm text-base-secondary">
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

                        <!-- Eligibility warning -->
                        <div
                            v-if="eligibility && !eligibility.can_book"
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
                            class="mb-4 rounded-lg bg-red-50 p-3 text-sm text-error dark:bg-red-900/20"
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
                                    class="w-full rounded-lg border border-default px-3 py-2 text-sm text-base-primary focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500 dark:bg-stone-700 dark:focus:ring-offset-page"
                                    @input="updateField(field.key, ($event.target as HTMLInputElement).value)"
                                />

                                <!-- Textarea -->
                                <textarea
                                    v-else-if="field.type === 'textarea'"
                                    :id="`field-${field.key}`"
                                    :required="field.required"
                                    :value="form.field_values[field.key] as string"
                                    rows="3"
                                    class="w-full rounded-lg border border-default px-3 py-2 text-sm text-base-primary focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500 dark:bg-stone-700 dark:focus:ring-offset-page"
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
                                    class="w-full rounded-lg border border-default px-3 py-2 text-sm text-base-primary focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500 dark:bg-stone-700 dark:focus:ring-offset-page"
                                    @input="updateField(field.key, Number(($event.target as HTMLInputElement).value))"
                                />

                                <!-- Select -->
                                <select
                                    v-else-if="field.type === 'select'"
                                    :id="`field-${field.key}`"
                                    :required="field.required"
                                    :value="form.field_values[field.key] as string"
                                    class="w-full rounded-lg border border-default bg-surface px-3 py-2 text-sm text-base-primary focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500 dark:bg-stone-700"
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
                                        class="h-4 w-4 rounded border-default text-primary-600 focus:ring-primary-500 dark:bg-stone-700"
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
                                    class="flex-1 rounded-lg bg-primary-600 px-4 py-2.5 text-sm font-medium text-white transition-colors hover:bg-primary-700 disabled:cursor-not-allowed disabled:opacity-50"
                                >
                                    <span
                                        v-if="loading || form.processing"
                                        class="flex items-center justify-center"
                                    >
                                        <svg
                                            class="mr-2 h-4 w-4 animate-spin"
                                            fill="none"
                                            viewBox="0 0 24 24"
                                        >
                                            <circle
                                                class="opacity-25"
                                                cx="12"
                                                cy="12"
                                                r="10"
                                                stroke="currentColor"
                                                stroke-width="4"
                                            ></circle>
                                            <path
                                                class="opacity-75"
                                                fill="currentColor"
                                                d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                                            ></path>
                                        </svg>
                                    </span>
                                    <span v-else>{{ t('booking_calendar.book') }}</span>
                                </button>
                            </div>
                        </form>

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
                    </div>
                </Transition>
            </div>
        </Transition>
    </Teleport>
</template>
