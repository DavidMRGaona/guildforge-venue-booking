<script setup lang="ts">
import { computed } from 'vue';

interface Props {
    status: string;
    label: string;
    color: string;
    size?: 'sm' | 'md';
}

const props = withDefaults(defineProps<Props>(), {
    size: 'sm',
});

const sizeClasses: Record<NonNullable<typeof props.size>, string> = {
    sm: 'px-2 py-0.5 text-xs',
    md: 'px-3 py-1 text-sm',
};

const colorClasses: Record<string, string> = {
    success: 'bg-success-light text-success',
    info: 'bg-info-light text-info',
    warning: 'bg-warning-light text-warning',
    danger: 'bg-error-light text-error',
    gray: 'bg-muted text-base-secondary',
    primary: 'bg-primary-light text-primary',
};

const badgeClasses = computed(() => [
    'inline-flex items-center rounded-full font-medium',
    sizeClasses[props.size],
    colorClasses[props.color] || 'bg-muted text-base-secondary',
]);
</script>

<template>
    <span :class="badgeClasses">
        {{ label }}
    </span>
</template>
