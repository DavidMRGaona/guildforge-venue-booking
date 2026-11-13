<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { FormSelect } from '@/components/form';
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

const options = computed(() =>
    props.resources.map((resource) => ({ value: resource.id, label: resource.name }))
);

// The placeholder is only a prompt: picking it never chooses an empty space
function handleSelect(value: string | number | null): void {
    if (value === null || value === '') return;
    emit('select', String(value));
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
        <FormSelect
            v-if="useDropdown"
            id="resource-selector"
            :model-value="selectedId"
            :options="options"
            :placeholder="t('booking_calendar.select_resource')"
            @update:model-value="handleSelect"
        />

        <!-- Tab buttons for 2-4 resources -->
        <div v-else role="tablist" class="flex gap-2">
            <button
                v-for="resource in resources"
                :key="resource.id"
                role="tab"
                type="button"
                :aria-selected="resource.id === selectedId"
                :class="[
                    'rounded-lg px-4 py-2 text-sm font-medium transition-colors focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 focus:ring-offset-surface',
                    resource.id === selectedId
                        ? 'bg-primary text-white'
                        : 'border border-default bg-surface text-base-secondary hover:bg-muted',
                ]"
                @click="handleTabClick(resource.id)"
            >
                {{ resource.name }}
            </button>
        </div>
    </div>
</template>
