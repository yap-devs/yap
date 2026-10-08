import * as Sentry from '@sentry/react';

const sentryDsn = import.meta.env.VITE_SENTRY_DSN_PUBLIC;
const sentryEnvironment = document.querySelector('meta[name="sentry-environment"]')?.content;
const sentryRelease = document.querySelector('meta[name="sentry-release"]')?.content || undefined;
export const sentryEnabled = !!sentryDsn && !['local', 'testing'].includes(sentryEnvironment);

if (sentryEnabled) {
  Sentry.init({
    dsn: sentryDsn,
    environment: sentryEnvironment,
    release: sentryRelease,
  });
}
