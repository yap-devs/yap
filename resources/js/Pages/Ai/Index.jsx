import {useState} from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import Modal from '@/Components/Modal.jsx';
import PrimaryButton from '@/Components/PrimaryButton.jsx';
import SecondaryButton from '@/Components/SecondaryButton.jsx';
import {Head, router} from '@inertiajs/react';

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
      title="Click to copy"
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
        {isCopied ? 'Copied!' : 'Click to copy'}
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
  const normalizedStatus = status ?? 'not created';
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
  const status = auth.user.sub2api_key_status ?? 'not created';
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
      header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">AI Key</h2>}
    >
      <Head title="AI Key"/>
      <div className="py-10">
        <div className="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
          <section className="overflow-hidden bg-gray-950 shadow-sm sm:rounded-3xl">
            <div className="relative p-6 text-white sm:p-8">
              <div className="absolute inset-0 bg-[radial-gradient(circle_at_top_right,rgba(99,102,241,0.35),transparent_35%),radial-gradient(circle_at_bottom_left,rgba(16,185,129,0.22),transparent_30%)]"/>
              <div className="relative grid gap-8 lg:grid-cols-[1.1fr_0.9fr] lg:items-end">
                <div>
                  <div className="flex flex-wrap items-center gap-3">
                    <h3 className="text-2xl font-semibold tracking-tight">Developer AI access</h3>
                    <StatusPill status={status}/>
                  </div>
                  <p className="mt-3 max-w-2xl text-sm leading-6 text-gray-300">
                    Use one private key with your YAP balance for coding assistants and OpenAI-compatible clients. Usage is charged from your account balance after it is synced.
                  </p>
                  <div className="mt-5">
                    {aiKey ? (
                      <CopyableField value={aiKey} copiedField={copiedField} onCopy={copyToClipboard} dark/>
                    ) : (
                      <div className="rounded-xl bg-white/5 px-4 py-3 font-mono text-sm text-gray-500 ring-1 ring-white/10">
                        No AI key created yet
                      </div>
                    )}
                  </div>
                </div>
                <div className="grid gap-3 sm:grid-cols-3 lg:grid-cols-1 xl:grid-cols-3">
                  <div className="rounded-2xl bg-white/10 p-4 ring-1 ring-white/10">
                    <div className="text-xs font-semibold uppercase tracking-wide text-gray-400">Balance</div>
                    <div className="mt-2 text-lg font-semibold">{formatMoney(auth.user.balance)}</div>
                  </div>
                  <div className="rounded-2xl bg-white/10 p-4 ring-1 ring-white/10">
                    <div className="text-xs font-semibold uppercase tracking-wide text-gray-400">Create key</div>
                    <div className="mt-2 text-lg font-semibold">&gt; ${createThreshold}</div>
                  </div>
                  <div className="rounded-2xl bg-white/10 p-4 ring-1 ring-white/10">
                    <div className="text-xs font-semibold uppercase tracking-wide text-gray-400">Keep active</div>
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
                  <h3 className="text-lg font-semibold text-gray-950">Create your AI key</h3>
                  <p className="mt-1 text-sm text-gray-600">
                    Your balance must be above ${createThreshold}. Each account can have one active AI key.
                  </p>
                </div>
                <PrimaryButton onClick={() => setShowingCreateModal(true)}>
                  Create AI Key
                </PrimaryButton>
              </div>
            </section>
          )}

          {aiKey && baseUrl && (
            <section className="bg-white p-6 shadow-sm sm:rounded-3xl">
              <div className="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                <div>
                  <h3 className="text-lg font-semibold text-gray-950">API endpoints</h3>
                  <p className="mt-1 text-sm text-gray-600">Pick the endpoint style your client expects.</p>
                </div>
                <p className="text-xs text-gray-500">Both fields are clickable.</p>
              </div>
              <div className="mt-5 grid gap-3 lg:grid-cols-2">
                <CopyableField label="Responses API" value={baseUrl} copiedField={copiedField} onCopy={copyToClipboard}/>
                <CopyableField label="Chat Completions" value={`${baseUrl}/v1`} copiedField={copiedField} onCopy={copyToClipboard}/>
              </div>
              <div className="mt-4 rounded-2xl bg-indigo-50 p-4 text-sm leading-6 text-indigo-900">
                Use <strong>Responses API</strong> for Codex CLI. Use <strong>Chat Completions</strong> for OpenCode, Cursor, and other OpenAI-compatible clients.
              </div>
            </section>
          )}

          <section className="grid gap-4 md:grid-cols-3">
            <StatCard label="Billing unit" value="Model + tokens" note="Input, cached input, and output tokens can have different prices."/>
            <StatCard label="Rounding" value="Grouped, then rounded up" note="Usage synced together is charged once. $0.011 total usage becomes $0.02."/>
            <StatCard label="Key rotation" value="Linked to UUID" note="Resetting your subscription UUID also rotates this AI key."/>
          </section>

          {aiKey && (
            <section className="overflow-hidden bg-white shadow-sm sm:rounded-3xl">
              <div className="border-b border-gray-200 p-6">
                <div className="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                  <div>
                    <h3 className="text-lg font-semibold text-gray-950">Model pricing</h3>
                    <p className="mt-1 max-w-3xl text-sm leading-6 text-gray-600">
                      Prices are shown per 1M tokens after the current billing multiplier. Final charges come from actual usage records and are deducted from your balance during sync.
                    </p>
                  </div>
                  {pricingAvailable && (
                    <div className="w-fit rounded-2xl bg-gray-950 px-4 py-3 text-sm text-white">
                      <span className="text-gray-400">Group</span> <span className="font-semibold">{pricingGuide.group_name}</span>
                      <span className="mx-2 text-gray-600">/</span>
                      <span className="text-gray-400">Multiplier</span> <span className="font-semibold">{pricingGuide.group_multiplier}x</span>
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
                            <th className="px-4 py-3 text-left font-semibold text-gray-600">Model</th>
                            <th className="px-4 py-3 text-right font-semibold text-gray-600">Input / 1M</th>
                            <th className="px-4 py-3 text-right font-semibold text-gray-600">Cached / 1M</th>
                            <th className="px-4 py-3 text-right font-semibold text-gray-600">Output / 1M</th>
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
                      Pricing is cached for about {Math.round((pricingGuide.cached_for_seconds ?? 3600) / 60)} minutes. Up to {pricingGuide.max_models ?? 100} available models are shown.
                    </div>
                  </div>
                ) : (
                  <div className="rounded-2xl border border-gray-200 bg-gray-50 p-5 text-sm leading-6 text-gray-600">
                    Model pricing is temporarily unavailable. Key usage still works, and charges are based on the actual synced usage records.
                  </div>
                )}
              </div>
            </section>
          )}
        </div>
      </div>

      <Modal show={showingCreateModal} onClose={() => setShowingCreateModal(false)}>
        <form className="p-6" onSubmit={createKey}>
          <h2 className="text-lg font-medium text-gray-900">Create AI key</h2>
          <p className="mt-2 text-sm text-gray-600">
            You can only create an AI key when your balance is above ${createThreshold}.
          </p>
          <div className="mt-6 flex justify-end">
            <SecondaryButton onClick={() => setShowingCreateModal(false)}>Cancel</SecondaryButton>
            <PrimaryButton className="ms-3">Create AI Key</PrimaryButton>
          </div>
        </form>
      </Modal>
    </AuthenticatedLayout>
  );
}
