import './bootstrap';
import '../css/app.css';

import {createRoot} from 'react-dom/client';
import {createInertiaApp, router} from '@inertiajs/react';
import {resolvePageComponent} from 'laravel-vite-plugin/inertia-helpers';
import Toast, {showToast} from '@/Components/Toast';
import {setTranslations} from '@/Utils/i18n';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';
const vitePreloadErrorReloadKey = 'yap:vite-preload-error-reloaded-at';

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
  const message = String(error?.message || error || '');

  return message.includes('Network Error');
};

addEventListener('vite:preloadError', (event) => {
  if (reloadForStaleAssets()) {
    event.preventDefault();
  }
});

// Intercept non-Inertia responses (e.g. 429 Too Many Requests)
router.on('invalid', (event) => {
  const status = event.detail.response?.status;
  if (status === 429) {
    event.preventDefault();
    showToast(window.YAP_TRANSLATIONS?.common?.too_many_requests || 'Too many requests, please try again later.');
  }
});

router.on('exception', (event) => {
  if (isStaleAssetError(event.detail.exception) && reloadForStaleAssets()) {
    event.preventDefault();

    return;
  }

  if (isNetworkError(event.detail.exception)) {
    event.preventDefault();
    showToast(window.YAP_TRANSLATIONS?.common?.network_error || 'Network interrupted, please try again.');
  }
});

createInertiaApp({
  title: (title) => `${title} - ${appName}`,
  resolve: (name) => resolvePageComponent(`./Pages/${name}.jsx`, import.meta.glob('./Pages/**/*.jsx')),
  setup({el, App, props}) {
    const root = createRoot(el);
    setTranslations(props.initialPage.props.translations);

    router.on('success', (event) => {
      setTranslations(event.detail.page.props.translations);
    });

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
