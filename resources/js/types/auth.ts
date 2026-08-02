export type User = {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    email_verified_at: string | null;
    two_factor_enabled?: boolean;
    created_at: string;
    updated_at: string;
    [key: string]: unknown;
};

export type Auth = {
    user: User;
    /**
     * The signed-in tenant user's permission names (e.g. `suppliers.view`). Used
     * by the UI to hide what a person can't do; enforcement still lives on the
     * server. Empty on the central `/admin` pages (no tenant permissions there).
     */
    permissions: string[];
    /** True when the tenant user holds the built-in Administrator role. */
    is_admin: boolean;
};

/* @chisel-passkeys */
export type Passkey = {
    id: number;
    name: string;
    authenticator: string | null;
    created_at_diff: string;
    last_used_at_diff: string | null;
};
/* @end-chisel-passkeys */

export type TwoFactorSetupData = {
    svg: string;
    url: string;
};

export type TwoFactorSecretKey = {
    secretKey: string;
};
