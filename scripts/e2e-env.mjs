// Generates .env.e2e from .env with the E2E overrides — a dedicated *_e2e central DB,
// an `e2e_tenant_` DB prefix (so E2E never touches dev data), a local serve URL,
// client-only Inertia (no SSR node server), and cheap bcrypt. Run by scripts/e2e.sh.
import { readFileSync, writeFileSync } from 'node:fs';

const overrides = {
    APP_ENV: 'e2e',
    APP_URL: 'http://localhost:8123',
    DB_DATABASE: 'laravel_ms_ai_central_e2e',
    TENANCY_DB_PREFIX: 'e2e_tenant_',
    INERTIA_SSR_ENABLED: 'false',
    BCRYPT_ROUNDS: '4',
};

let env = readFileSync('.env', 'utf8');
for (const [key, value] of Object.entries(overrides)) {
    const re = new RegExp(`^${key}=.*$`, 'm');
    env = re.test(env)
        ? env.replace(re, `${key}=${value}`)
        : `${env}\n${key}=${value}\n`;
}
writeFileSync('.env.e2e', env);
console.log('Generated .env.e2e');
