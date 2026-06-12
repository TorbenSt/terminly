export interface AuthUser {
    id: number;
    name: string;
    email: string;
    email_verified_at?: string | null;
    company_id: number | null;
    roles: string[];
    is_super_admin: boolean;
}

export interface BillingUsageEntry {
    limit: number | null;
    usage: number;
    overage: number;
    extra_price_cents: number;
}

export interface BillingUsage {
    staff: BillingUsageEntry;
    customers: BillingUsageEntry;
    extra_total_cents: number;
}

export interface BillingStatus {
    exempt: boolean;
    on_trial: boolean;
    trial_ends_at: string | null;
    subscribed: boolean;
    read_only: boolean;
    usage: BillingUsage | null;
}

export type PageProps<
    T extends Record<string, unknown> = Record<string, unknown>,
> = T & {
    auth: {
        user: AuthUser | null;
    };
    flash?: {
        success?: string;
        error?: string;
    };
    billing?: BillingStatus | null;
};
