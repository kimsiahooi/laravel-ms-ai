// `tsc` type-checks resources/js/** (including *.test.tsx) but not the root
// vitest.setup.ts, so the jest-dom matcher augmentation is referenced here — inside
// the tsconfig include — to make matchers like toBeInTheDocument() type-check.
import '@testing-library/jest-dom/vitest';
