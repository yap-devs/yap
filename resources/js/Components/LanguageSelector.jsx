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
    <label className={`inline-flex items-center gap-2 text-sm text-gray-500 ${className}`}>
      <span>{trans('common.language')}</span>
      <select
        value={locale}
        onChange={changeLocale}
        className="rounded-md border-gray-300 py-1 pl-2 pr-8 text-sm text-gray-700 focus:border-indigo-500 focus:ring-indigo-500"
      >
        {Object.keys(locales || labels).map((code) => (
          <option key={code} value={code}>{labels[code] || code}</option>
        ))}
      </select>
    </label>
  );
}
