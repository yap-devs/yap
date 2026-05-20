import {router, usePage} from '@inertiajs/react';
import {trans} from '@/Utils/i18n';

export default function LanguageSelector({className = ''}) {
  const {locale, locales} = usePage().props;

  const labels = {
    en: trans('common.english'),
    ja: trans('common.japanese'),
  };

  const changeLocale = (e) => {
    router.post(route('locale.update'), {locale: e.target.value}, {
      preserveScroll: true,
      onSuccess: () => {
        localStorage.setItem('locale', e.target.value);
      },
    });
  };

  return (
    <label className={`relative inline-flex shrink-0 items-center text-sm ${className}`}>
      <span className="sr-only">{trans('common.language')}</span>
      <svg
        className="pointer-events-none absolute left-3 h-4 w-4 text-gray-400"
        xmlns="http://www.w3.org/2000/svg"
        fill="none"
        viewBox="0 0 24 24"
        strokeWidth="1.75"
        stroke="currentColor"
      >
        <path
          strokeLinecap="round"
          strokeLinejoin="round"
          d="M12 21a9 9 0 100-18 9 9 0 000 18zM3.6 9h16.8M3.6 15h16.8M11.5 3.2c-2.1 2.5-3.1 5.4-3.1 8.8s1 6.3 3.1 8.8M12.5 3.2c2.1 2.5 3.1 5.4 3.1 8.8s-1 6.3-3.1 8.8"
        />
      </svg>
      <select
        value={locale}
        onChange={changeLocale}
        className="h-9 min-w-32 appearance-none rounded-full border border-gray-200 bg-white/95 py-1 pl-9 pr-9 text-sm font-medium text-gray-700 shadow-sm transition hover:border-gray-300 hover:bg-white focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20"
      >
        {Object.keys(locales || labels).map((code) => (
          <option key={code} value={code}>{labels[code] || code}</option>
        ))}
      </select>
      <svg
        className="pointer-events-none absolute right-3 h-4 w-4 text-gray-400"
        xmlns="http://www.w3.org/2000/svg"
        viewBox="0 0 20 20"
        fill="currentColor"
      >
        <path
          fillRule="evenodd"
          d="M5.23 7.21a.75.75 0 011.06.02L10 11.17l3.71-3.94a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z"
          clipRule="evenodd"
        />
      </svg>
    </label>
  );
}
