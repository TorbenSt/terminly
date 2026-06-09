export interface AuthUser {
    id: number;
    name: string;
    email: string;
    email_verified_at?: string | null;
    company_id: number | null;
    roles: string[];
    is_super_admin: boolean;
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
};
