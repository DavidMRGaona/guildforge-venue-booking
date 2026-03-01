<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import type { BookableResource } from '../types/bookings';

interface Props {
    resources: BookableResource[];
    selectedId: string | null;
}

const props = defineProps<Props>();

const emit = defineEmits<{
    select: [id: string];
}>();

const { t } = useI18n();

const useDropdown = computed(() => props.resources.length >= 5);

function handleSelect(event: Event): void {
    const target = event.target as HTMLSelectElement;
    emit('select', target.value);
}

function handleTabClick(id: string): void {
    emit('select', id);
}
</script>

<template>
    <div class="mb-6">
        <label
            v-if="useDropdown"
            for="resource-selector"
            class="mb-2 block text-sm font-medium text-base-secondary"
        >
            {{ t('booking_calendar.resource') }}
        </label>

        <!-- Dropdown for 5+ resources -->
        <select
            v-if="useDropdown"
            id="resource-selector"
            :value="selectedId ?? ''"
            class="w-full rounded-lg border border-default bg-surface px-3 py-2 text-sm text-base-primary focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500 dark:bg-stone-700"
            @change="handleSelect"
        >
            <option value="" disabled>
                {{ t('booking_calendar.select_resource') }}
            </option>
            <option
                v-for="resource in resources"
                :key="resource.id"
                :value="resource.id"
            >
                {{ resource.name }}
            </option>
        </select>

        <!-- Tab buttons for 2-4 resources -->
        <div
            v-else
            role="tablist"
            class="flex gap-2"
        >
            <button
                v-for="resource in resources"
                :key="resource.id"
                role="tab"
                type="button"
                :aria-selected="resource.id === selectedId"
                :class="[
                    'rounded-lg px-4 py-2 text-sm font-medium transition-colors focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 dark:focus:ring-offset-page',
                    resource.id === selectedId
                        ? 'bg-primary-600 text-white'
                        : 'border border-default bg-surface text-base-secondary hover:bg-muted',
                ]"
                @click="handleTabClick(resource.id)"
            >
                {{ resource.name }}
            </button>
        </div>
    </div>
</template>
