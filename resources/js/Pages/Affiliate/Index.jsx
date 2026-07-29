import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import {Head, router, useForm} from '@inertiajs/react';
import {formatPrice} from '@/Utils/formatPrice';
import {trans} from '@/Utils/i18n';
import {useState} from 'react';

export default function Index({auth, affiliate}) {
  const [copiedCode, setCopiedCode] = useState(null);
  const [actionCode, setActionCode] = useState(null);
  const {data, setData, post, processing, errors, reset} = useForm({code: ''});

  const copyLink = async (url, code) => {
    await navigator.clipboard.writeText(url);
    setCopiedCode(code);
    setTimeout(() => setCopiedCode(null), 1800);
  };

  const createCode = (event) => {
    event.preventDefault();
    post(route('affiliate.codes.store'), {
      preserveScroll: true,
      onSuccess: () => reset('code'),
    });
  };

  const updateCode = (code, action) => {
    if (action === 'disable' && !window.confirm(trans('affiliate.deactivate_code_confirm', {code: code.code}))) {
      return;
    }

    setActionCode(code.id);
    router.patch(route(`affiliate.codes.${action}`, code.id), {}, {
      preserveScroll: true,
      onFinish: () => setActionCode(null),
    });
  };

  const rate = (affiliate.current_level.commission_rate * 100).toFixed(0);
  const activeCodes = affiliate.codes.filter((code) => code.status === 'active');
  const inactiveCodes = affiliate.codes.filter((code) => code.status === 'disabled');

  const prompt = (referral) => {
    return trans(`affiliate.prompts.${referral.prompt_key}`, {
      amount: formatPrice(affiliate.rules.minimum_referred_first_payment_amount),
      referrer_amount: formatPrice(affiliate.rules.minimum_referrer_paid_amount),
      date: referral.commission_expires_at || '-',
      days: affiliate.rules.pending_days,
    });
  };

  const codeAvailabilityMessage = () => {
    if (affiliate.promoter.status !== 'active') {
      return trans('affiliate.code_account_blocked');
    }

    if (affiliate.code_quota.creation_available_at) {
      return trans('affiliate.code_cooldown_until', {date: affiliate.code_quota.creation_available_at});
    }

    if (affiliate.next_level) {
      return trans('affiliate.code_unlock_next_level', {
        level: affiliate.next_level.name,
        maximum: affiliate.next_level.maximum_referral_codes,
      });
    }

    return trans('affiliate.code_limit_reached');
  };

  return (
    <AuthenticatedLayout
      user={auth.user}
      header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">{trans('affiliate.title')}</h2>}
    >
      <Head title={trans('affiliate.title')}/>

      <div className="py-12">
        <div className="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
          <div className="bg-gray-900 text-white shadow-sm sm:rounded-lg p-6">
            <p className="text-sm uppercase tracking-wide text-emerald-300">{trans('affiliate.hero_label')}</p>
            <h3 className="mt-2 text-3xl font-bold">{trans('affiliate.hero_title')}</h3>
            <p className="mt-3 max-w-3xl text-gray-200">{trans('affiliate.hero_body')}</p>
            <div className="mt-6 flex flex-col gap-3 sm:flex-row">
              <input
                readOnly
                value={affiliate.promoter.url}
                className="flex-1 rounded-lg border-0 bg-white/10 px-4 py-3 text-white placeholder:text-white/60 focus:ring-2 focus:ring-white/50"
              />
              <button
                type="button"
                onClick={() => copyLink(affiliate.promoter.url, 'system')}
                className="rounded-lg bg-white px-5 py-3 font-semibold text-gray-900 hover:bg-gray-100"
              >
                {copiedCode === 'system' ? trans('affiliate.copied') : trans('affiliate.copy_link')}
              </button>
            </div>
          </div>

          <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
            <StatCard label={trans('affiliate.current_level')} value={affiliate.current_level.name}/>
            <StatCard label={trans('affiliate.current_rate')} value={`${rate}%`}/>
            <StatCard label={trans('affiliate.valid_referrals')} value={affiliate.stats.valid_referral_count}/>
            <StatCard label={trans('affiliate.pending_commission')} value={formatPrice(affiliate.stats.pending_commission)}/>
            <StatCard
              label={trans('affiliate.code_slots')}
              value={`${affiliate.code_quota.active_count} / ${affiliate.code_quota.maximum}`}
            />
          </div>

          <section className="bg-white shadow-sm sm:rounded-lg">
            <div className="flex flex-col gap-4 border-b border-gray-200 p-6 lg:flex-row lg:items-end lg:justify-between">
              <div>
                <h3 className="text-lg font-semibold text-gray-900">{trans('affiliate.referral_codes_title')}</h3>
                <p className="mt-1 text-sm text-gray-600">
                  {trans('affiliate.referral_codes_quota', {
                    active: affiliate.code_quota.active_count,
                    maximum: affiliate.code_quota.maximum,
                  })}
                </p>
              </div>

              <form className="flex w-full flex-col gap-2 sm:flex-row lg:max-w-xl" onSubmit={createCode}>
                <div className="min-w-0 flex-1">
                  <label className="sr-only" htmlFor="referral-code">{trans('affiliate.custom_code')}</label>
                  <input
                    id="referral-code"
                    type="text"
                    value={data.code}
                    onChange={(event) => setData('code', event.target.value.toLowerCase())}
                    placeholder={trans('affiliate.code_placeholder')}
                    disabled={!affiliate.code_quota.can_create || processing}
                    className="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500 disabled:bg-gray-100"
                  />
                  {errors.code && <p className="mt-1 text-sm text-red-600">{errors.code}</p>}
                </div>
                <button
                  type="submit"
                  disabled={!affiliate.code_quota.can_create || processing}
                  className="h-10 rounded-md bg-emerald-700 px-4 text-sm font-semibold text-white hover:bg-emerald-800 disabled:cursor-not-allowed disabled:bg-gray-300"
                >
                  {trans('affiliate.create_code')}
                </button>
              </form>
            </div>

            {!affiliate.code_quota.can_create && (
              <div className="border-b border-gray-200 bg-gray-50 px-6 py-3 text-sm text-gray-700">
                {codeAvailabilityMessage()}
              </div>
            )}

            <ReferralCodeList
              codes={activeCodes}
              copiedCode={copiedCode}
              actionCode={actionCode}
              onCopy={copyLink}
              onUpdate={updateCode}
            />

            {inactiveCodes.length > 0 && (
              <details className="border-t border-gray-200">
                <summary className="cursor-pointer px-6 py-4 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                  {trans('affiliate.inactive_codes', {count: inactiveCodes.length})}
                </summary>
                <ReferralCodeList
                  codes={inactiveCodes}
                  copiedCode={copiedCode}
                  actionCode={actionCode}
                  onCopy={copyLink}
                  onUpdate={updateCode}
                />
              </details>
            )}
          </section>

          {affiliate.next_level && (
            <div className="bg-white shadow-sm sm:rounded-lg p-6">
              <h3 className="text-lg font-semibold text-gray-900">{trans('affiliate.next_level', {level: affiliate.next_level.name})}</h3>
              <div className="mt-4 grid gap-4 md:grid-cols-2">
                <ProgressItem
                  label={trans('affiliate.self_paid_progress')}
                  current={formatPrice(affiliate.stats.self_paid_total)}
                  target={formatPrice(affiliate.next_level.minimum_self_paid_amount)}
                  remaining={formatPrice(affiliate.next_level.remaining_self_paid_amount)}
                />
                <ProgressItem
                  label={trans('affiliate.referral_progress')}
                  current={affiliate.stats.valid_referral_count}
                  target={affiliate.next_level.minimum_valid_referrals}
                  remaining={affiliate.next_level.remaining_valid_referrals}
                />
              </div>
            </div>
          )}

          <div className="grid gap-6 lg:grid-cols-2">
            <div className="bg-white shadow-sm sm:rounded-lg p-6">
              <h3 className="text-lg font-semibold text-gray-900">{trans('affiliate.rules_title')}</h3>
              <ul className="mt-4 space-y-3 text-sm text-gray-700">
                <li>{trans('affiliate.rules.referrer', {amount: formatPrice(affiliate.rules.minimum_referrer_paid_amount)})}</li>
                <li>{trans('affiliate.rules.referred', {amount: formatPrice(affiliate.rules.minimum_referred_first_payment_amount)})}</li>
                <li>{trans('affiliate.rules.package')}</li>
                <li>{trans('affiliate.rules.pending', {days: affiliate.rules.pending_days})}</li>
                <li>{trans('affiliate.rules.expires', {days: affiliate.rules.commission_expires_days})}</li>
              </ul>
            </div>

            <div className="bg-white shadow-sm sm:rounded-lg p-6">
              <h3 className="text-lg font-semibold text-gray-900">{trans('affiliate.levels_title')}</h3>
              <div className="mt-4 overflow-x-auto">
                <table className="min-w-full divide-y divide-gray-200 text-sm">
                  <thead>
                  <tr className="text-left text-gray-500">
                    <th className="py-2">{trans('affiliate.level')}</th>
                    <th className="py-2">{trans('affiliate.requirement')}</th>
                    <th className="py-2">{trans('affiliate.rate')}</th>
                    <th className="py-2">{trans('affiliate.code_slots')}</th>
                  </tr>
                  </thead>
                  <tbody className="divide-y divide-gray-100">
                  {affiliate.levels.map((level) => (
                    <tr key={level.level}>
                      <td className="py-2 font-medium text-gray-900">{level.name}</td>
                      <td className="py-2 text-gray-700">
                        {trans('affiliate.level_requirement', {
                          amount: formatPrice(level.minimum_self_paid_amount),
                          count: level.minimum_valid_referrals,
                        })}
                      </td>
                      <td className="py-2 text-gray-700">{(level.commission_rate * 100).toFixed(0)}%</td>
                      <td className="py-2 text-gray-700">{level.maximum_referral_codes}</td>
                    </tr>
                  ))}
                  </tbody>
                </table>
              </div>
            </div>
          </div>

          <div className="bg-white shadow-sm sm:rounded-lg p-6">
            <h3 className="text-lg font-semibold text-gray-900">{trans('affiliate.referrals_title')}</h3>
            <div className="mt-4 overflow-x-auto">
              <table className="min-w-full divide-y divide-gray-200 text-sm">
                <thead>
                <tr className="text-left text-gray-500">
                  <th className="py-2">{trans('affiliate.friend')}</th>
                  <th className="py-2">{trans('common.status')}</th>
                  <th className="py-2">{trans('affiliate.prompt')}</th>
                  <th className="py-2">{trans('affiliate.expires_at')}</th>
                  <th className="py-2">{trans('affiliate.commission')}</th>
                </tr>
                </thead>
                <tbody className="divide-y divide-gray-100">
                {affiliate.referrals.length === 0 && (
                  <tr>
                    <td className="py-6 text-gray-500" colSpan="5">{trans('common.no_records')}</td>
                  </tr>
                )}
                {affiliate.referrals.map((referral) => (
                  <tr key={referral.id}>
                    <td className="py-3 font-medium text-gray-900">{referral.user_label}</td>
                    <td className="py-3 text-gray-700">{trans(`affiliate.status.${referral.status}`)}</td>
                    <td className="py-3 text-gray-700">{prompt(referral)}</td>
                    <td className="py-3 text-gray-700">{referral.commission_expires_at || '-'}</td>
                    <td className="py-3 text-gray-700">
                      {formatPrice(referral.pending_commission)} / {formatPrice(referral.credited_commission)}
                    </td>
                  </tr>
                ))}
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </AuthenticatedLayout>
  );
}

function ReferralCodeList({codes, copiedCode, actionCode, onCopy, onUpdate}) {
  return (
    <div className="divide-y divide-gray-100">
      {codes.map((code) => (
        <div key={code.id} className="p-6">
          <div className="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
            <div className="min-w-0 flex-1">
              <div className="flex flex-wrap items-center gap-2">
                <span className="break-all font-mono text-base font-semibold text-gray-900">{code.code}</span>
                <span className={`rounded px-2 py-0.5 text-xs font-medium ${code.type === 'system' ? 'bg-gray-900 text-white' : 'bg-emerald-100 text-emerald-800'}`}>
                  {trans(code.type === 'system' ? 'affiliate.default_code' : 'affiliate.custom_code')}
                </span>
                <span className={`rounded px-2 py-0.5 text-xs font-medium ${code.status === 'active' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600'}`}>
                  {trans(`affiliate.code_status.${code.status}`)}
                </span>
              </div>
              <div className="mt-3 flex min-w-0 flex-col gap-2 sm:flex-row">
                <input
                  readOnly
                  value={code.url}
                  className="min-w-0 flex-1 rounded-md border-gray-300 bg-gray-50 text-sm text-gray-700"
                />
                <button
                  type="button"
                  onClick={() => onCopy(code.url, code.id)}
                  className="h-10 rounded-md border border-gray-300 bg-white px-4 text-sm font-semibold text-gray-700 hover:bg-gray-50"
                >
                  {copiedCode === code.id ? trans('affiliate.copied') : trans('affiliate.copy_link')}
                </button>
                {code.type === 'custom' && (
                  <button
                    type="button"
                    onClick={() => onUpdate(code, code.status === 'active' ? 'disable' : 'enable')}
                    disabled={actionCode === code.id}
                    className={`h-10 rounded-md px-4 text-sm font-semibold disabled:cursor-wait disabled:opacity-50 ${code.status === 'active' ? 'border border-red-200 bg-white text-red-700 hover:bg-red-50' : 'bg-gray-900 text-white hover:bg-gray-800'}`}
                  >
                    {trans(code.status === 'active' ? 'affiliate.deactivate_code' : 'affiliate.reactivate_code')}
                  </button>
                )}
              </div>
            </div>

            <dl className="grid grid-cols-2 gap-x-6 gap-y-3 sm:grid-cols-4 xl:w-auto xl:min-w-[32rem]">
              <CodeStat label={trans('affiliate.registrations')} value={code.registration_count}/>
              <CodeStat label={trans('affiliate.valid_referrals')} value={code.valid_referral_count}/>
              <CodeStat label={trans('affiliate.pending_commission')} value={formatPrice(code.pending_commission)}/>
              <CodeStat label={trans('affiliate.credited_commission')} value={formatPrice(code.credited_commission)}/>
            </dl>
          </div>
        </div>
      ))}
    </div>
  );
}

function CodeStat({label, value}) {
  return (
    <div className="min-w-0">
      <dt className="text-xs text-gray-500">{label}</dt>
      <dd className="mt-1 truncate text-sm font-semibold text-gray-900">{value}</dd>
    </div>
  );
}

function StatCard({label, value}) {
  return (
    <div className="bg-white shadow-sm sm:rounded-lg p-5">
      <p className="text-sm text-gray-500">{label}</p>
      <p className="mt-2 text-2xl font-semibold text-gray-900">{value}</p>
    </div>
  );
}

function ProgressItem({label, current, target, remaining}) {
  return (
    <div className="rounded-lg border border-gray-200 p-4">
      <p className="font-medium text-gray-900">{label}</p>
      <p className="mt-2 text-sm text-gray-600">{trans('affiliate.progress_current', {current, target})}</p>
      <p className="mt-1 text-sm text-emerald-700">{trans('affiliate.progress_remaining', {remaining})}</p>
    </div>
  );
}
