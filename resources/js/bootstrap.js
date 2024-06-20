import axios from 'axios';
// telemetry
import * as Sentry from "@sentry/react";

window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

Sentry.init({
  dsn: import.meta.env.VITE_SENTRY_DSN_PUBLIC,
});
