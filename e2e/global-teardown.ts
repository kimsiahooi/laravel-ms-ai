import { execSync } from 'node:child_process';

/** Drops the isolated e2e databases after the suite finishes. */
export default function globalTeardown(): void {
    execSync('bash scripts/e2e.sh down', { stdio: 'inherit' });
}
