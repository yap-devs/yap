import './bootstrap';
import '../css/app.css';

import {createRoot} from 'react-dom/client';
import {createInertiaApp, router} from '@inertiajs/react';
import {resolvePageComponent} from 'laravel-vite-plugin/inertia-helpers';
import Toast, {showToast} from '@/Components/Toast';
import {setTranslations} from '@/Utils/i18n';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';
const vitePreloadErrorReloadKey = 'yap:vite-preload-error-reloaded-at';

addEventListener('vite:preloadError', (event) => {
  event.preventDefault();

  try {
    const lastReloadedAt = Number(sessionStorage.getItem(vitePreloadErrorReloadKey) || 0);
    if (Date.now() - lastReloadedAt < 10000) {
      return;
    }

    sessionStorage.setItem(vitePreloadErrorReloadKey, String(Date.now()));
  } catch {
    // Storage can fail in locked-down browsers; still reload to recover stale assets.
  }

  window.location.reload();
});

// Intercept non-Inertia responses (e.g. 429 Too Many Requests)
router.on('invalid', (event) => {
  const status = event.detail.response?.status;
  if (status === 429) {
    event.preventDefault();
    showToast(window.YAP_TRANSLATIONS?.common?.too_many_requests || 'Too many requests, please try again later.');
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
});
