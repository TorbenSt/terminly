export interface CustomerRecurringService {
    id: number;
    service_type_id: number;
    service_name: string;
    is_recurring: boolean;
    duration_minutes: number;
    next_due_at: string;
    is_active: boolean;
    is_due: boolean;
}

export interface Customer {
    id: number;
    name: string;
    email: string | null;
    phone: string | null;
    address: string;
    postal_code: string;
    city: string;
    notes: string | null;
    is_active: boolean;
    recurring_services?: CustomerRecurringService[];
}

export interface ServiceType {
    id: number;
    name: string;
    duration_minutes: number;
    is_recurring: boolean;
    interval_days: number | null;
    interval_months: number | null;
    description: string | null;
    is_active: boolean;
}

export interface StaffMember {
    id: number;
    name: string;
    email: string | null;
    phone: string | null;
    buffer_minutes: number;
    is_active: boolean;
    service_types?: ServiceType[];
    availabilities?: StaffAvailability[];
}

export interface StaffAvailability {
    id: number;
    day_of_week: number;
    start_time: string;
    end_time: string;
}

export interface AppointmentListItem {
    id: number;
    customer: string;
    service: string;
    status: string;
    scheduled_at: string | null;
    staff: string | null;
}
