import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import {Head, Link, useForm, usePage} from '@inertiajs/react';
import {trans} from '@/Utils/i18n';
import {formatTicketDate, TicketStatus} from './Parts';

export default function Show({auth, ticket}) {
  const {data, setData, post, processing, errors, reset} = useForm({body: ''});
  const {locale} = usePage().props;

  function submit(event) {
    event.preventDefault();
    post(route('tickets.reply', ticket.id), {onSuccess: () => reset()});
  }

  return <AuthenticatedLayout user={auth.user} header={<h2 className="text-xl font-semibold text-gray-900">{trans('tickets.title')}</h2>}>
    <Head title={ticket.subject} />
    <div className="mx-auto max-w-4xl px-4 py-8 sm:px-6 lg:px-8">
      <Link href={route('tickets.index')} className="text-sm font-medium text-gray-500 hover:text-gray-900">← {trans('tickets.back_to_tickets')}</Link>
      <div className="mt-7 flex flex-wrap items-start justify-between gap-4">
        <div className="min-w-0"><p className="text-xs font-semibold tracking-wide text-blue-600">{trans('tickets.ticket_number', {number: ticket.id})}</p><h1 className="mt-2 break-words text-2xl font-semibold tracking-tight text-gray-950 sm:text-3xl">{ticket.subject}</h1><p className="mt-2 text-sm text-gray-500">{trans('tickets.created')} · <time dateTime={ticket.created_at}>{formatTicketDate(ticket.created_at, locale)}</time></p></div>
        <TicketStatus status={ticket.status} />
      </div>
      <section className="mt-7 overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
        <div className="border-b border-gray-100 px-5 py-5 sm:px-6"><h2 className="font-semibold text-gray-900">{trans('tickets.conversation')}</h2><p className="mt-1 text-sm text-gray-500">{trans('tickets.conversation_intro')}</p></div>
        <div className="space-y-6 bg-gray-50/70 px-5 py-6 sm:px-6">
          {ticket.messages.map(message => <article key={message.id} className="flex items-start gap-3">
            <div aria-hidden="true" className={`flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-sm font-bold ${message.is_admin ? 'bg-blue-100 text-blue-700' : 'bg-gray-200 text-gray-700'}`}>{message.is_admin ? 'S' : auth.user.name.slice(0, 1).toUpperCase()}</div>
            <div className="min-w-0 flex-1"><div className="mb-2 flex flex-wrap items-baseline gap-x-2 gap-y-1"><span className="text-sm font-semibold text-gray-900">{trans(message.is_admin ? 'tickets.support' : 'tickets.you')}</span><time className="text-xs text-gray-500" dateTime={message.created_at}>{formatTicketDate(message.created_at, locale)}</time></div><p className="whitespace-pre-wrap break-words rounded-xl rounded-tl-sm bg-white px-4 py-3 text-sm leading-6 text-gray-700 shadow-sm ring-1 ring-gray-200/70">{message.body}</p></div>
          </article>)}
        </div>
        <div className="border-t border-gray-100 p-5 sm:p-6">
          {ticket.status === 'open' ? <form onSubmit={submit} className="space-y-3"><div><label htmlFor="ticket-reply" className="block text-sm font-semibold text-gray-900">{trans('tickets.reply')}</label><p className="mt-1 text-xs text-gray-500">{trans('tickets.reply_intro')}</p></div><textarea id="ticket-reply" className="block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500" rows={5} value={data.body} onChange={e => setData('body', e.target.value)} maxLength={10000} required aria-invalid={Boolean(errors.body)} />{errors.body && <p role="alert" className="text-sm text-red-600">{errors.body}</p>}<div className="flex justify-end"><button disabled={processing} className="rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-blue-700 disabled:opacity-50">{processing ? trans('tickets.sending') : trans('tickets.send_reply')}</button></div></form> : <p className="rounded-lg bg-gray-50 px-4 py-3 text-sm leading-6 text-gray-600">{trans('tickets.closed_message')}</p>}
        </div>
      </section>
    </div>
  </AuthenticatedLayout>;
}
