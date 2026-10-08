import {sentryEnabled} from './bootstrap';
import '../css/app.css';

import {createRoot} from 'react-dom/client';
import * as Sentry from '@sentry/react';
import {createInertiaApp, router, usePage} from '@inertiajs/react';
import {resolvePageComponent} from 'laravel-vite-plugin/inertia-helpers';
import Toast, {showToast} from '@/Components/Toast';
import {setTranslations} from '@/Utils/i18n';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';
const vitePreloadErrorReloadKey = 'yap:vite-preload-error-reloaded-at';

function TranslatedPage({children}) {
  setTranslations(usePage().props.translations);

  return children;
}

const reloadForStaleAssets = () => {
  try {
    const lastReloadedAt = Number(sessionStorage.getItem(vitePreloadErrorReloadKey) || 0);
    if (Date.now() - lastReloadedAt < 10000) {
      return false;
    }

    sessionStorage.setItem(vitePreloadErrorReloadKey, String(Date.now()));
  } catch {
    // Storage can fail in locked-down browsers; still reload to recover stale assets.
  }

  window.location.reload();
  return true;
};

const errorMessage = (error) => String(error?.message || error || '');

const isStaleAssetError = (error) => {
  const message = errorMessage(error);

  return [
    'Failed to fetch dynamically imported module',
    'Importing a module script failed',
    'Unable to preload CSS',
    'Page not found:',
    "Cannot read properties of undefined (reading 'default')",
  ].some((needle) => message.includes(needle));
};

const isNetworkError = (error) => {
  if (error?.code === 'ERR_NETWORK') {
    return true;
  }

  const message = String(error?.message || error || '');

  return ['Network Error', 'Failed to fetch', 'Load failed', 'Network request failed'].some((needle) => message.includes(needle));
};

addEventListener('vite:preloadError', (event) => {
  if (reloadForStaleAssets()) {
    event.preventDefault();
  }
});

// Intercept non-Inertia responses (e.g. 429 Too Many Requests)
router.on('httpException', (event) => {
  const status = event.detail.response?.status;
  if (status === 429) {
    event.preventDefault();
    showToast(window.YAP_TRANSLATIONS?.common?.too_many_requests || 'Too many requests, please try again later.');
  }
});

router.on('networkError', (event) => {
  if (isStaleAssetError(event.detail.error) && reloadForStaleAssets()) {
    event.preventDefault();

    return;
  }

  if (isNetworkError(event.detail.error)) {
    event.preventDefault();
    showToast(window.YAP_TRANSLATIONS?.common?.network_error || 'Network interrupted, please try again.');
  }
});

createInertiaApp({
  layout: () => TranslatedPage,
  title: (title) => `${title} - ${appName}`,
  resolve: (name) => resolvePageComponent(`./Pages/${name}.jsx`, import.meta.glob('./Pages/**/*.jsx')),
  setup({el, App, props}) {
    const root = createRoot(el, sentryEnabled ? {
      onUncaughtError: Sentry.reactErrorHandler(),
      onCaughtError: Sentry.reactErrorHandler(),
      onRecoverableError: Sentry.reactErrorHandler(),
    } : undefined);
    setTranslations(props.initialPage.props.translations);

    root.render(
      <>
        <App {...props} />
        <Toast />
      </>
    );
  },
  progress: {
    color: '#4B5563',
  },
}).catch((error) => {
  if (isStaleAssetError(error) && reloadForStaleAssets()) {
    return;
  }

  throw error;
});
