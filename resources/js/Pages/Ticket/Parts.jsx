import {trans} from '@/Utils/i18n';

export function TicketStatus({status}) {
  const open = status === 'open';
  return <span className={`inline-flex shrink-0 items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold ${open ? 'bg-emerald-50 text-emerald-700' : 'bg-gray-100 text-gray-600'}`}>
    <span className={`h-1.5 w-1.5 rounded-full ${open ? 'bg-emerald-500' : 'bg-gray-400'}`} />
    {trans(`tickets.status_${status}`)}
  </span>;
}

export function formatTicketDate(value, locale) {
  return new Date(value).toLocaleString(locale === 'zh_CN' ? 'zh-CN' : locale, {year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit'});
}
