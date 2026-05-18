import {useState} from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import Modal from '@/Components/Modal.jsx';
import PrimaryButton from '@/Components/PrimaryButton.jsx';
import SecondaryButton from '@/Components/SecondaryButton.jsx';
import {Head, router} from '@inertiajs/react';
import {trans} from '@/Utils/i18n';

function CopyableField({label, value, copiedField, onCopy, dark = false}) {
  const isCopied = copiedField === value;

  return (
    <div
      className={`group flex items-center justify-between gap-3 rounded-xl px-4 py-3 cursor-pointer transition-colors ${
        dark
          ? 'bg-gray-950 hover:bg-gray-900 ring-1 ring-white/10'
          : 'bg-white hover:bg-gray-50 border border-gray-200 shadow-sm'
      }`}
      onClick={() => onCopy(value)}
      title={trans('ai.click_to_copy')}
    >
      <div className="flex min-w-0 flex-1 flex-col gap-1 sm:flex-row sm:items-center sm:gap-2">
        {label && (
          <span className="w-fit shrink-0 rounded-full bg-indigo-100 px-2.5 py-1 text-xs font-semibold text-indigo-700">{label}</span>
        )}
        <code className={`font-mono text-sm break-all ${dark ? 'text-emerald-300' : 'text-gray-900'}`}>
          {value}
        </code>
      </div>
      <span className={`shrink-0 rounded-full px-2.5 py-1 text-xs font-medium transition-colors ${
        isCopied
          ? dark ? 'bg-emerald-500/20 text-emerald-200' : 'bg-emerald-100 text-emerald-700'
          : dark ? 'text-gray-400 group-hover:bg-white/10 group-hover:text-gray-200' : 'text-gray-500 group-hover:bg-gray-100 group-hover:text-gray-700'
      }`}
      >
        {isCopied ? trans('ai.copied') : trans('ai.click_to_copy')}
      </span>
    </div>
  );
}

function StatCard({label, value, note}) {
  return (
    <div className="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm">
      <div className="text-xs font-semibold uppercase tracking-wide text-gray-500">{label}</div>
      <div className="mt-2 text-lg font-semibold text-gray-950">{value}</div>
      {note && <p className="mt-1 text-xs leading-5 text-gray-500">{note}</p>}
    </div>
  );
}

function StatusPill({status}) {
  const normalizedStatus = status ?? trans('ai.not_created');
  const isActive = normalizedStatus === 'active';

  return (
    <span className={`inline-flex rounded-full px-3 py-1 text-xs font-semibold ${
      isActive ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-100 text-gray-600'
    }`}
    >
      {normalizedStatus}
    </span>
  );
}

function formatPrice(value) {
  if (value === null || value === undefined) return '-';

  return `$${Number(value).toLocaleString(undefined, {
    minimumFractionDigits: 2,
    maximumFractionDigits: 6,
  })}`;
}

function formatMoney(value) {
  if (value === null || value === undefined || value === '') return '-';

  return `$${Number(value).toLocaleString(undefined, {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  })}`;
}

export default function Index({auth, aiKey, baseUrl, createThreshold, keepActiveThreshold, pricingGuide}) {
  const [showingCreateModal, setShowingCreateModal] = useState(false);
  const [copiedField, setCopiedField] = useState(null);
  const status = auth.user.sub2api_key_status ?? trans('ai.not_created');
  const pricingAvailable = Boolean(pricingGuide?.available);

  function copyToClipboard(text) {
    if (!navigator.clipboard) return;

    navigator.clipboard.writeText(text)
      .then(() => {
        setCopiedField(text);
        setTimeout(() => setCopiedField(null), 2000);
      })
      .catch(() => {});
  }

  function createKey(event) {
    event.preventDefault();
    router.post(route('ai.key.store'));
    setShowingCreateModal(false);
  }

  return (
    <AuthenticatedLayout
      user={auth.user}
      header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">{trans('ai.title')}</h2>}
    >
      <Head title={trans('ai.title')}/>
      <div className="py-10">
        <div className="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
          <section className="overflow-hidden bg-gray-950 shadow-sm sm:rounded-3xl">
            <div className="relative p-6 text-white sm:p-8">
              <div className="absolute inset-0 bg-[radial-gradient(circle_at_top_right,rgba(99,102,241,0.35),transparent_35%),radial-gradient(circle_at_bottom_left,rgba(16,185,129,0.22),transparent_30%)]"/>
              <div className="relative grid gap-8 lg:grid-cols-[1.1fr_0.9fr] lg:items-end">
                <div>
                  <div className="flex flex-wrap items-center gap-3">
                    <h3 className="text-2xl font-semibold tracking-tight">{trans('ai.developer_access')}</h3>
                    <StatusPill status={status}/>
                  </div>
                  <p className="mt-3 max-w-2xl text-sm leading-6 text-gray-300">
                    {trans('ai.hero_body')}
                  </p>
                  <div className="mt-5">
                    {aiKey ? (
                      <CopyableField value={aiKey} copiedField={copiedField} onCopy={copyToClipboard} dark/>
                    ) : (
                      <div className="rounded-xl bg-white/5 px-4 py-3 font-mono text-sm text-gray-500 ring-1 ring-white/10">
                        {trans('ai.no_key')}
                      </div>
                    )}
                  </div>
                </div>
                <div className="grid gap-3 sm:grid-cols-3 lg:grid-cols-1 xl:grid-cols-3">
                  <div className="rounded-2xl bg-white/10 p-4 ring-1 ring-white/10">
                    <div className="text-xs font-semibold uppercase tracking-wide text-gray-400">{trans('ai.balance')}</div>
                    <div className="mt-2 text-lg font-semibold">{formatMoney(auth.user.balance)}</div>
                  </div>
                  <div className="rounded-2xl bg-white/10 p-4 ring-1 ring-white/10">
                    <div className="text-xs font-semibold uppercase tracking-wide text-gray-400">{trans('ai.create_key_threshold')}</div>
                    <div className="mt-2 text-lg font-semibold">&gt; ${createThreshold}</div>
                  </div>
                  <div className="rounded-2xl bg-white/10 p-4 ring-1 ring-white/10">
                    <div className="text-xs font-semibold uppercase tracking-wide text-gray-400">{trans('ai.keep_active')}</div>
                    <div className="mt-2 text-lg font-semibold">&gt; ${keepActiveThreshold}</div>
                  </div>
                </div>
              </div>
            </div>
          </section>

          {!aiKey && (
            <section className="bg-white p-6 shadow-sm sm:rounded-3xl">
              <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                  <h3 className="text-lg font-semibold text-gray-950">{trans('ai.create_your_key')}</h3>
                  <p className="mt-1 text-sm text-gray-600">
                    {trans('ai.create_requirement', {amount: createThreshold})}
                  </p>
                </div>
                <PrimaryButton onClick={() => setShowingCreateModal(true)}>
                  {trans('ai.create_key')}
                </PrimaryButton>
              </div>
            </section>
          )}

          {aiKey && baseUrl && (
            <section className="bg-white p-6 shadow-sm sm:rounded-3xl">
              <div className="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                <div>
                  <h3 className="text-lg font-semibold text-gray-950">{trans('ai.api_endpoints')}</h3>
                  <p className="mt-1 text-sm text-gray-600">{trans('ai.api_endpoints_body')}</p>
                </div>
                <p className="text-xs text-gray-500">{trans('ai.clickable_fields')}</p>
              </div>
              <div className="mt-5 grid gap-3 lg:grid-cols-2">
                <CopyableField label={trans('ai.responses_api')} value={baseUrl} copiedField={copiedField} onCopy={copyToClipboard}/>
                <CopyableField label={trans('ai.chat_completions')} value={`${baseUrl}/v1`} copiedField={copiedField} onCopy={copyToClipboard}/>
              </div>
              <div className="mt-4 rounded-2xl bg-indigo-50 p-4 text-sm leading-6 text-indigo-900">
                {trans('ai.endpoint_help')}
              </div>
            </section>
          )}

          <section className="grid gap-4 md:grid-cols-3">
            <StatCard label={trans('ai.billing_unit')} value={trans('ai.billing_unit_value')} note={trans('ai.billing_unit_note')}/>
            <StatCard label={trans('ai.rounding')} value={trans('ai.rounding_value')} note={trans('ai.rounding_note')}/>
            <StatCard label={trans('ai.key_rotation')} value={trans('ai.key_rotation_value')} note={trans('ai.key_rotation_note')}/>
          </section>

          {aiKey && (
            <section className="overflow-hidden bg-white shadow-sm sm:rounded-3xl">
              <div className="border-b border-gray-200 p-6">
                <div className="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                  <div>
                    <h3 className="text-lg font-semibold text-gray-950">{trans('ai.model_pricing')}</h3>
                    <p className="mt-1 max-w-3xl text-sm leading-6 text-gray-600">
                      {trans('ai.model_pricing_body')}
                    </p>
                  </div>
                  {pricingAvailable && (
                    <div className="w-fit rounded-2xl bg-gray-950 px-4 py-3 text-sm text-white">
                      <span className="text-gray-400">{trans('ai.group')}</span> <span className="font-semibold">{pricingGuide.group_name}</span>
                      <span className="mx-2 text-gray-600">/</span>
                      <span className="text-gray-400">{trans('ai.multiplier')}</span> <span className="font-semibold">{pricingGuide.group_multiplier}x</span>
                    </div>
                  )}
                </div>
              </div>

              <div className="p-6">
                {pricingAvailable ? (
                  <div className="overflow-hidden rounded-2xl border border-gray-200">
                    <div className="overflow-x-auto">
                      <table className="min-w-full divide-y divide-gray-200 text-sm">
                        <thead className="bg-gray-50">
                          <tr>
                            <th className="px-4 py-3 text-left font-semibold text-gray-600">{trans('ai.model')}</th>
                            <th className="px-4 py-3 text-right font-semibold text-gray-600">{trans('ai.input_1m')}</th>
                            <th className="px-4 py-3 text-right font-semibold text-gray-600">{trans('ai.cached_1m')}</th>
                            <th className="px-4 py-3 text-right font-semibold text-gray-600">{trans('ai.output_1m')}</th>
                          </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-100 bg-white">
                          {pricingGuide.models.map((model) => (
                            <tr key={model.model} className="hover:bg-gray-50">
                              <td className="whitespace-nowrap px-4 py-3 font-mono text-gray-950">{model.model}</td>
                              <td className="whitespace-nowrap px-4 py-3 text-right text-gray-700">{formatPrice(model.input_per_million)}</td>
                              <td className="whitespace-nowrap px-4 py-3 text-right text-gray-700">{formatPrice(model.cache_read_per_million)}</td>
                              <td className="whitespace-nowrap px-4 py-3 text-right font-medium text-gray-950">{formatPrice(model.output_per_million)}</td>
                            </tr>
                          ))}
                        </tbody>
                      </table>
                    </div>
                    <div className="border-t border-gray-200 bg-gray-50 px-4 py-3 text-xs leading-5 text-gray-500">
                      {trans('ai.pricing_cache', {minutes: Math.round((pricingGuide.cached_for_seconds ?? 3600) / 60), count: pricingGuide.max_models ?? 100})}
                    </div>
                  </div>
                ) : (
                  <div className="rounded-2xl border border-gray-200 bg-gray-50 p-5 text-sm leading-6 text-gray-600">
                    {trans('ai.pricing_unavailable')}
                  </div>
                )}
              </div>
            </section>
          )}
        </div>
      </div>

      <Modal show={showingCreateModal} onClose={() => setShowingCreateModal(false)}>
        <form className="p-6" onSubmit={createKey}>
          <h2 className="text-lg font-medium text-gray-900">{trans('ai.create_modal_title')}</h2>
          <p className="mt-2 text-sm text-gray-600">
            {trans('ai.create_modal_body', {amount: createThreshold})}
          </p>
          <div className="mt-6 flex justify-end">
            <SecondaryButton onClick={() => setShowingCreateModal(false)}>{trans('common.cancel')}</SecondaryButton>
            <PrimaryButton className="ms-3">{trans('ai.create_key')}</PrimaryButton>
          </div>
        </form>
      </Modal>
    </AuthenticatedLayout>
  );
}
