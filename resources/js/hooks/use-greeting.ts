import { useEffect, useState } from 'react';

/**
 * A time-of-day greeting ("Good morning/afternoon/evening"). Computed in an effect
 * (after mount) so the server and the client's first render agree — reading the local
 * hour during render would be an SSR/timezone hydration mismatch. Starts at a neutral
 * "Welcome back" until the effect runs.
 */
export function useTimeOfDayGreeting(): string {
    const [greeting, setGreeting] = useState('Welcome back');

    useEffect(() => {
        const hour = new Date().getHours();
        setGreeting(
            hour < 12
                ? 'Good morning'
                : hour < 18
                  ? 'Good afternoon'
                  : 'Good evening',
        );
    }, []);

    return greeting;
}

/** The first word of a name (for a friendly greeting), or a fallback. */
export function firstName(
    name: string | null | undefined,
    fallback: string,
): string {
    return name?.trim().split(/\s+/)[0] || fallback;
}
