import './bootstrap';
import '../css/app.css';

import {createRoot} from 'react-dom/client';
import {createInertiaApp, router} from '@inertiajs/react';
import {resolvePageComponent} from 'laravel-vite-plugin/inertia-helpers';
import Toast, {showToast} from '@/Components/Toast';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

// Intercept non-Inertia responses (e.g. 429 Too Many Requests)
router.on('invalid', (event) => {
  const status = event.detail.response?.status;
  if (status === 429) {
    event.preventDefault();
    showToast('Too many requests, please try again later.');
  }
});

createInertiaApp({
  title: (title) => `${title} - ${appName}`,
  resolve: (name) => resolvePageComponent(`./Pages/${name}.jsx`, import.meta.glob('./Pages/**/*.jsx')),
  setup({el, App, props}) {
    const root = createRoot(el);

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
