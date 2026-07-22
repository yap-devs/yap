import axios from 'axios';
import * as Sentry from '@sentry/react';

window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

const sentryDsn = import.meta.env.VITE_SENTRY_DSN_PUBLIC;
const sentryEnvironment = document.querySelector('meta[name="sentry-environment"]')?.content;
const sentryRelease = document.querySelector('meta[name="sentry-release"]')?.content || undefined;

if (sentryDsn && !['local', 'testing'].includes(sentryEnvironment)) {
  Sentry.init({
    dsn: sentryDsn,
    environment: sentryEnvironment,
    release: sentryRelease,
  });
}
