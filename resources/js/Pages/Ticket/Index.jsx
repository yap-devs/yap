import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import {Head, Link, useForm, usePage} from '@inertiajs/react';
import {trans} from '@/Utils/i18n';
import {formatTicketDate, TicketStatus} from './Parts';

export default function Index({auth, tickets}) {
  const {data, setData, post, processing, errors} = useForm({subject: '', body: ''});
  const {locale} = usePage().props;

  function submit(event) {
    event.preventDefault();
    post(route('tickets.store'));
  }

  return <AuthenticatedLayout user={auth.user} header={<h2 className="text-xl font-semibold text-gray-900">{trans('tickets.title')}</h2>}>
    <Head title={trans('tickets.title')} />
    <div className="mx-auto max-w-6xl px-4 py-8 sm:px-6 lg:px-8">
      <Link href={route('customer.service')} className="text-sm font-medium text-gray-500 hover:text-gray-900">← {trans('tickets.back_to_support')}</Link>
      <div className="mt-7 flex flex-wrap items-end justify-between gap-4">
        <div>
          <p className="text-xs font-bold tracking-widest text-blue-600">{trans('tickets.eyebrow')}</p>
          <h1 className="mt-2 text-3xl font-semibold tracking-tight text-gray-950">{trans('tickets.your_tickets')}</h1>
          <p className="mt-2 text-sm leading-6 text-gray-600">{trans('tickets.list_intro')}</p>
        </div>
        <a href="#new-ticket" className="inline-flex items-center gap-2 rounded-lg bg-gray-900 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-gray-700"><span aria-hidden="true">＋</span>{trans('tickets.new')}</a>
      </div>

      <div className="mt-7 grid gap-6 lg:grid-cols-[minmax(0,1fr)_22rem] lg:items-start">
        <section aria-label={trans('tickets.your_tickets')} className="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
          <div className="flex items-center justify-between border-b border-gray-100 px-5 py-4 sm:px-6">
            <h2 className="font-semibold text-gray-900">{trans('tickets.your_tickets')}</h2>
            <span className="rounded-full bg-gray-100 px-2.5 py-1 text-xs font-semibold text-gray-600">{tickets.total}</span>
          </div>
          {tickets.data.length === 0 ? <div className="px-6 py-16 text-center">
            <div className="mx-auto flex h-12 w-12 items-center justify-center rounded-2xl bg-blue-50 text-blue-600" aria-hidden="true"><svg className="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth="1.5"><path strokeLinecap="round" strokeLinejoin="round" d="M8 10h8M8 14h5m-9 6 1.5-3A8 8 0 1 1 8 19l-4 1Z" /></svg></div>
            <h3 className="mt-4 text-sm font-semibold text-gray-900">{trans('tickets.empty_title')}</h3><p className="mt-1 text-sm text-gray-500">{trans('tickets.empty_description')}</p>
          </div> : <ul className="divide-y divide-gray-100">
            {tickets.data.map(ticket => <li key={ticket.id}>
              <Link href={route('tickets.show', ticket.id)} className="group block px-5 py-5 transition hover:bg-gray-50 focus-visible:outline-2 focus-visible:outline-offset-[-2px] focus-visible:outline-blue-600 sm:px-6">
                <div className="flex items-start justify-between gap-3"><div className="min-w-0"><p className="text-xs font-medium text-gray-500">{trans('tickets.ticket_number', {number: ticket.id})}</p><h3 className="mt-1 break-words text-base font-semibold text-gray-900 group-hover:text-blue-700">{ticket.subject}</h3></div><TicketStatus status={ticket.status} /></div>
                <div className="mt-4 flex flex-wrap items-center justify-between gap-2 text-xs text-gray-500"><span>{trans('tickets.last_activity')} · <time dateTime={ticket.updated_at}>{formatTicketDate(ticket.updated_at, locale)}</time></span><span className="font-semibold text-blue-700">{trans('tickets.view_ticket')} <span aria-hidden="true">→</span></span></div>
              </Link>
            </li>)}
          </ul>}
          {(tickets.prev_page_url || tickets.next_page_url) && <nav aria-label={trans('common.pagination')} className="flex justify-between border-t border-gray-100 px-6 py-4 text-sm font-medium text-blue-700"><span>{tickets.prev_page_url && <Link href={tickets.prev_page_url}>← {trans('tickets.previous')}</Link>}</span><span>{tickets.next_page_url && <Link href={tickets.next_page_url}>{trans('tickets.next')} →</Link>}</span></nav>}
        </section>

        <section id="new-ticket" className="scroll-mt-6 rounded-2xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
          <h2 className="text-lg font-semibold text-gray-950">{trans('tickets.new')}</h2>
          <p className="mt-1 text-sm leading-6 text-gray-500">{trans('tickets.new_intro')}</p>
          <form onSubmit={submit} className="mt-5 space-y-4">
            <div><label htmlFor="ticket-subject" className="block text-sm font-medium text-gray-700">{trans('tickets.subject')}</label><input id="ticket-subject" className="mt-1.5 block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500" value={data.subject} onChange={e => setData('subject', e.target.value)} placeholder={trans('tickets.subject_placeholder')} maxLength={255} required aria-invalid={Boolean(errors.subject)} />{errors.subject && <p role="alert" className="mt-1 text-sm text-red-600">{errors.subject}</p>}</div>
            <div><label htmlFor="ticket-body" className="block text-sm font-medium text-gray-700">{trans('tickets.message')}</label><textarea id="ticket-body" className="mt-1.5 block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500" rows={6} value={data.body} onChange={e => setData('body', e.target.value)} placeholder={trans('tickets.message_placeholder')} maxLength={10000} required aria-invalid={Boolean(errors.body)} />{errors.body && <p role="alert" className="mt-1 text-sm text-red-600">{errors.body}</p>}</div>
            <button disabled={processing} className="w-full rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-blue-700 disabled:opacity-50">{processing ? trans('tickets.sending') : trans('tickets.submit')}</button>
          </form>
        </section>
      </div>
    </div>
  </AuthenticatedLayout>;
}
