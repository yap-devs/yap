import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import {Head} from '@inertiajs/react';
import {formatPrice} from '@/Utils/formatPrice';
import {trans} from '@/Utils/i18n';
import {useState} from 'react';

export default function Index({auth, affiliate}) {
  const [copied, setCopied] = useState(false);

  const copyLink = async () => {
    await navigator.clipboard.writeText(affiliate.promoter.url);
    setCopied(true);
    setTimeout(() => setCopied(false), 1800);
  };

  const rate = (affiliate.current_level.commission_rate * 100).toFixed(0);

  const prompt = (referral) => {
    return trans(`affiliate.prompts.${referral.prompt_key}`, {
      amount: formatPrice(affiliate.rules.minimum_referred_first_payment_amount),
      referrer_amount: formatPrice(affiliate.rules.minimum_referrer_paid_amount),
      date: referral.commission_expires_at || '-',
      days: affiliate.rules.pending_days,
    });
  };

  return (
    <AuthenticatedLayout
      user={auth.user}
      header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">{trans('affiliate.title')}</h2>}
    >
      <Head title={trans('affiliate.title')}/>

      <div className="py-12">
        <div className="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
          <div className="bg-gradient-to-br from-slate-900 via-indigo-900 to-slate-800 text-white shadow-sm sm:rounded-lg p-6">
            <p className="text-sm uppercase tracking-wide text-indigo-200">{trans('affiliate.hero_label')}</p>
            <h3 className="mt-2 text-3xl font-bold">{trans('affiliate.hero_title')}</h3>
            <p className="mt-3 max-w-3xl text-indigo-100">{trans('affiliate.hero_body')}</p>
            <div className="mt-6 flex flex-col gap-3 sm:flex-row">
              <input
                readOnly
                value={affiliate.promoter.url}
                className="flex-1 rounded-lg border-0 bg-white/10 px-4 py-3 text-white placeholder:text-white/60 focus:ring-2 focus:ring-white/50"
              />
              <button
                type="button"
                onClick={copyLink}
                className="rounded-lg bg-white px-5 py-3 font-semibold text-indigo-900 hover:bg-indigo-50"
              >
                {copied ? trans('affiliate.copied') : trans('affiliate.copy_link')}
              </button>
            </div>
          </div>

          <div className="grid gap-4 md:grid-cols-4">
            <StatCard label={trans('affiliate.current_level')} value={affiliate.current_level.name}/>
            <StatCard label={trans('affiliate.current_rate')} value={`${rate}%`}/>
            <StatCard label={trans('affiliate.valid_referrals')} value={affiliate.stats.valid_referral_count}/>
            <StatCard label={trans('affiliate.pending_commission')} value={formatPrice(affiliate.stats.pending_commission)}/>
          </div>

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
      <p className="mt-1 text-sm text-indigo-600">{trans('affiliate.progress_remaining', {remaining})}</p>
    </div>
  );
}
