<script setup lang="ts">
import { Users } from '@lucide/vue';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import type { BookableResource } from '../types/bookings';

interface Props {
    resource: BookableResource;
}

const props = defineProps<Props>();

const { t } = useI18n();

const description = computed(() => {
    const text = props.resource.description?.trim() ?? '';

    return text === '' ? null : text;
});

const hasDetails = computed(() => description.value !== null || props.resource.capacity !== null);
</script>

<template>
    <section
        v-if="hasDetails"
        :aria-label="t('booking_calendar.resource_details', { name: resource.name })"
        class="mb-6 rounded-lg border border-default bg-surface p-4"
    >
        <p v-if="description" class="whitespace-pre-line text-sm text-base-secondary">
            {{ description }}
        </p>
        <p
            v-if="resource.capacity !== null"
            :class="[
                'flex items-center gap-2 text-sm font-medium text-base-primary',
                { 'mt-3': description },
            ]"
        >
            <Users class="h-4 w-4 shrink-0 text-base-muted" aria-hidden="true" />
            {{ t('booking_calendar.capacity', { count: resource.capacity }, resource.capacity) }}
        </p>
    </section>
</template>
