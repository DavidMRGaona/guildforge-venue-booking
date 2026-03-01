export interface BookableResource {
    id: string;
    name: string;
    slug: string;
    description: string | null;
    capacity: number | null;
    status: string;
    status_label: string;
    status_color: string;
    is_active: boolean;
    sort_order: number;
    scheduling_mode: 'time_slots' | 'time_blocks';
    field_definitions: FieldDefinition[];
    min_consecutive_slots: number;
    max_consecutive_slots: number;
}

export interface BookingListItem {
    id: string;
    resource_id: string;
    resource_name: string;
    user_id: string;
    user_name: string;
    date: string;
    start_time: string;
    end_time: string;
    status: string;
    status_label: string;
    status_color: string;
    is_cancellable: boolean;
    field_values: Array<{
        field_key: string;
        field_label: string;
        value: string | number | boolean | null;
        visibility: string;
    }>;
    cancellation_reason: string | null;
}

export interface BookingSlot {
    start_time: string;
    end_time: string;
    is_available: boolean;
    label?: string | null;
}

export interface BookingCalendarEvent {
    id: string;
    title: string;
    start: string;
    end: string;
    color: string;
    status: string;
    status_label: string;
    user_id: string | null;
}

export interface BookingTooltipData {
    id: string;
    title: string;
    start: string;
    end: string;
    status: string;
    statusLabel: string;
}

export type BookingFieldType = 'text' | 'textarea' | 'number' | 'select' | 'toggle';

export interface FieldDefinition {
    key: string;
    label: string;
    type: BookingFieldType;
    required: boolean;
    options: string[] | null;
}

export interface BookingEligibility {
    can_book: boolean;
    reasons: string[];
}

export interface BookingIndexProps {
    resources: BookableResource[];
    selectedResourceId: string | null;
}

export interface MyBookingsProps {
    bookings: BookingListItem[];
}

export interface BookingDetail {
    id: string;
    resource_name: string;
    date: string;
    start_time: string;
    end_time: string;
    status: string;
    status_label: string;
    status_color: string;
    field_values: Array<{
        field_key: string;
        field_label: string;
        value: string | number | boolean | null;
        visibility: string;
    }>;
    cancellation_reason: string | null;
    game_table_name?: string | null;
    campaign_name?: string | null;
    user_name?: string;
    admin_notes?: string;
}
