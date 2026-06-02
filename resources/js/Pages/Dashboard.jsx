import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import {Head, Link, router} from '@inertiajs/react';
import {useState} from "react";
import {formatBytes} from "@/Utils/formatBytes";
import {trans} from '@/Utils/i18n';

export default function Dashboard({auth, clashUrl, unitPrice, servers, todayTraffic}) {
  const [copyButton, setCopyButton] = useState(trans('dashboard.copy_url'));
  const [showTooltip, setShowTooltip] = useState(false);

  const copyToClipboard = async text => {
    try {
      await navigator.clipboard.writeText(text);
      setCopyButton(trans('dashboard.copied'));
      setShowTooltip(true);
      setTimeout(() => {
        setCopyButton(trans('dashboard.copy_url'));
        setShowTooltip(false);
      }, 2000);
    } catch (err) {
      console.error('Failed to copy: ', err);
    }
  }

  const totalBytes = auth.user.traffic_downlink + auth.user.traffic_uplink;
  const totalTraffic = formatBytes(totalBytes);

  const isNewUser = auth.user.balance <= 0 && totalBytes === 0;
  const needsRecharge = !auth.user.is_valid;

  // Determine which step the user is on
  const getOnboardingStep = () => {
    if (needsRecharge) return 1;
    if (totalBytes === 0) return 2;
    return 3; // All set
  }
  const currentStep = getOnboardingStep();

  const renderStepIndicator = () => {
    const steps = [
      {num: 1, label: trans('dashboard.add_funds')},
      {num: 2, label: trans('dashboard.connect')},
      {num: 3, label: trans('dashboard.online')},
    ];

    return (
      <div className="flex items-center justify-center mb-8">
        {steps.map((step, idx) => (
          <div key={step.num} className="flex items-center">
            <div className="flex flex-col items-center">
              <div className={`w-10 h-10 rounded-full flex items-center justify-center text-sm font-bold border-2 transition-all ${
                currentStep > step.num
                  ? 'bg-green-500 border-green-500 text-white'
                  : currentStep === step.num
                    ? 'bg-blue-600 border-blue-600 text-white'
                    : 'bg-gray-100 border-gray-300 text-gray-400'
              }`}>
                {currentStep > step.num ? (
                  <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M5 13l4 4L19 7"/>
                  </svg>
                ) : step.num}
              </div>
              <span className={`mt-1 text-xs font-medium ${
                currentStep >= step.num ? 'text-gray-800' : 'text-gray-400'
              }`}>{step.label}</span>
            </div>
            {idx < steps.length - 1 && (
              <div className={`w-16 sm:w-24 h-0.5 mx-2 mb-5 ${
                currentStep > step.num ? 'bg-green-500' : 'bg-gray-200'
              }`}/>
            )}
          </div>
        ))}
      </div>
    );
  }

  const renderNextStepGuide = () => {
    const guideItems = [
      {
        num: 1,
        title: trans('dashboard.guide_step_recharge_title'),
        body: trans('dashboard.guide_step_recharge_body'),
        active: currentStep === 1,
        done: currentStep > 1,
      },
      {
        num: 2,
        title: trans('dashboard.guide_step_import_title'),
        body: trans('dashboard.guide_step_import_body'),
        active: currentStep === 2,
        done: currentStep > 2,
      },
      {
        num: 3,
        title: trans('dashboard.guide_step_use_title'),
        body: trans('dashboard.guide_step_use_body'),
        active: currentStep === 3,
        done: currentStep > 3,
      },
    ];

    return (
      <div className="mb-6 overflow-hidden rounded-2xl border border-blue-100 bg-linear-to-br from-blue-50 via-white to-indigo-50 shadow-sm">
        <div className="grid gap-6 p-5 lg:grid-cols-[1.15fr_0.85fr] lg:p-6">
          <div>
            <span className="inline-flex rounded-full bg-blue-100 px-3 py-1 text-xs font-semibold text-blue-700">
              {trans('dashboard.guide_badge')}
            </span>
            <h3 className="mt-3 text-2xl font-bold text-gray-900">
              {needsRecharge ? trans('dashboard.guide_title_inactive') : trans('dashboard.guide_title_active')}
            </h3>
            <p className="mt-2 max-w-2xl text-sm leading-6 text-gray-600">
              {needsRecharge ? trans('dashboard.guide_body_inactive') : trans('dashboard.guide_body_active')}
            </p>
            <div className="mt-5 flex flex-col gap-3 sm:flex-row">
              {needsRecharge ? (
                <button
                  onClick={() => router.get(route('recharge'))}
                  className="inline-flex items-center justify-center rounded-lg bg-blue-600 px-5 py-3 text-sm font-semibold text-white shadow-sm transition duration-200 hover:bg-blue-700"
                >
                  {trans('dashboard.guide_primary_recharge')}
                </button>
              ) : (
                <button
                  onClick={() => window.location.href = 'clash://install-config?url=' + encodeURIComponent(clashUrl)}
                  className="inline-flex items-center justify-center rounded-lg bg-blue-600 px-5 py-3 text-sm font-semibold text-white shadow-sm transition duration-200 hover:bg-blue-700"
                >
                  {trans('dashboard.guide_primary_import')}
                </button>
              )}
              <button
                onClick={() => copyToClipboard(clashUrl)}
                disabled={needsRecharge}
                className={`inline-flex items-center justify-center rounded-lg px-5 py-3 text-sm font-semibold transition duration-200 ${
                  needsRecharge
                    ? 'cursor-not-allowed bg-gray-100 text-gray-400'
                    : 'border border-gray-300 bg-white text-gray-700 hover:bg-gray-50'
                }`}
              >
                {copyButton}
              </button>
              <Link
                href={route('customer.service')}
                className="inline-flex items-center justify-center rounded-lg px-5 py-3 text-sm font-semibold text-blue-700 transition duration-200 hover:bg-blue-100"
              >
                {trans('dashboard.guide_help')}
              </Link>
            </div>
          </div>

          <div className="space-y-3">
            {guideItems.map((item) => (
              <div
                key={item.num}
                className={`rounded-xl border p-4 ${
                  item.active
                    ? 'border-blue-300 bg-white shadow-sm'
                    : item.done
                      ? 'border-green-200 bg-green-50'
                      : 'border-gray-200 bg-white/70'
                }`}
              >
                <div className="flex gap-3">
                  <div className={`flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-sm font-bold ${
                    item.done
                      ? 'bg-green-500 text-white'
                      : item.active
                        ? 'bg-blue-600 text-white'
                        : 'bg-gray-100 text-gray-400'
                  }`}>
                    {item.done ? '✓' : item.num}
                  </div>
                  <div>
                    <p className="font-semibold text-gray-900">{item.title}</p>
                    <p className="mt-1 text-sm leading-5 text-gray-600">{item.body}</p>
                  </div>
                </div>
              </div>
            ))}
          </div>
        </div>
      </div>
    );
  }

  const renderBalanceCard = () => (
    <div className={`rounded-xl p-5 border ${
      auth.user.balance > 0
        ? 'bg-green-50 border-green-200'
        : auth.user.balance < 0
          ? 'bg-red-50 border-red-200'
          : 'bg-gray-50 border-gray-200'
    }`}>
      <div className="flex items-center justify-between">
        <div>
          <p className="text-sm text-gray-500 mb-1">{trans('dashboard.balance')}</p>
          <p className={`text-3xl font-bold ${
            auth.user.balance > 0 ? 'text-green-600' : auth.user.balance < 0 ? 'text-red-600' : 'text-gray-800'
          }`}>
            ${auth.user.balance}
          </p>
        </div>
        <button
          onClick={() => router.get(route('recharge'))}
          className={`flex items-center font-medium py-2.5 px-5 rounded-lg transition duration-200 ${
            needsRecharge
              ? 'bg-blue-600 hover:bg-blue-700 text-white shadow-md'
              : 'bg-white hover:bg-gray-50 text-gray-700 border border-gray-300'
          }`}
        >
          <svg xmlns="http://www.w3.org/2000/svg" className="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
          </svg>
          {trans('dashboard.add_funds')}
        </button>
      </div>
      <div className="mt-3 pt-3 border-t border-gray-200">
        <p className="text-xs text-gray-500">
          {trans('dashboard.payg', {price: unitPrice})}
        </p>
      </div>
    </div>
  );

  const renderSubscriptionButtons = () => {
    if (!auth.user.is_valid) {
      return (
        <div className="bg-amber-50 border border-amber-200 rounded-xl p-5">
          <div className="flex items-start">
            <svg xmlns="http://www.w3.org/2000/svg" className="h-5 w-5 text-amber-500 mr-3 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
            </svg>
            <div>
              <p className="font-medium text-amber-800">{trans('dashboard.activate_title')}</p>
              <p className="text-sm text-amber-700 mt-1">
                {trans('dashboard.activate_body')}
              </p>
              <button
                onClick={() => router.get(route('recharge'))}
                className="mt-3 bg-amber-500 hover:bg-amber-600 text-white font-medium py-2 px-4 rounded-lg transition duration-200"
              >
                {trans('dashboard.recharge_now')}
              </button>
            </div>
          </div>
        </div>
      );
    }

    return (
      <div>
        {auth.user.is_low_priority && (
          <div className="bg-amber-50 border border-amber-200 rounded-lg p-4 mb-4">
            <div className="flex items-center">
              <svg xmlns="http://www.w3.org/2000/svg" className="h-5 w-5 text-amber-500 mr-2 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
              </svg>
              <p className="text-sm text-amber-800">
                <span className="font-medium">{trans('dashboard.low_priority_title')}</span>
                {' '}{trans('dashboard.low_priority_body')}{' '}
                <button
                  onClick={() => router.get(route('recharge'))}
                  className="text-blue-600 hover:text-blue-800 underline font-medium"
                >
                  {trans('dashboard.add_funds')}
                </button>
                {' '}{trans('dashboard.low_priority_suffix')}
              </p>
            </div>
          </div>
        )}

        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
          <button
            onClick={() => copyToClipboard(clashUrl)}
            className="relative flex items-center justify-center bg-gray-800 hover:bg-gray-900 text-white font-medium py-3 px-4 rounded-lg shadow-sm transition duration-200"
          >
            <svg xmlns="http://www.w3.org/2000/svg" className="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3"/>
            </svg>
            {copyButton}
            {showTooltip && (
              <span className="absolute -top-10 left-1/2 transform -translate-x-1/2 bg-gray-900 text-white text-xs px-2 py-1 rounded">
                {trans('dashboard.copied')}
              </span>
            )}
          </button>
          <button
            onClick={() => window.location.href = 'clash://install-config?url=' + encodeURIComponent(clashUrl)}
            className="flex items-center justify-center bg-blue-600 hover:bg-blue-700 text-white font-medium py-3 px-4 rounded-lg shadow-sm transition duration-200"
          >
            <svg xmlns="http://www.w3.org/2000/svg" className="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
            </svg>
            {trans('dashboard.import_clash')}
          </button>
          <button
            onClick={() => window.location.href = 'shadowrocket://add/sub://' + btoa(clashUrl)}
            className="flex items-center justify-center bg-purple-600 hover:bg-purple-700 text-white font-medium py-3 px-4 rounded-lg shadow-sm transition duration-200"
          >
            <svg xmlns="http://www.w3.org/2000/svg" className="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 10V3L4 14h7v7l9-11h-7z"/>
            </svg>
            {trans('dashboard.import_shadowrocket')}
          </button>
          <button
            onClick={() => window.location.href = 'stash://install-config?url=' + encodeURIComponent(clashUrl)}
            className="flex items-center justify-center bg-orange-500 hover:bg-orange-600 text-white font-medium py-3 px-4 rounded-lg shadow-sm transition duration-200"
          >
            <svg xmlns="http://www.w3.org/2000/svg" className="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
            </svg>
            {trans('dashboard.import_stash')}
          </button>
        </div>
      </div>
    );
  }

  const renderServers = () => {
    if (servers.length === 0) {
      return (
        <div className="p-6 bg-gray-50 text-gray-500 rounded-lg flex items-center justify-center">
          <svg xmlns="http://www.w3.org/2000/svg" className="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/>
          </svg>
          <span>{trans('dashboard.no_servers')}</span>
        </div>
      );
    }

    const isUnavailable = (user, server) => (user.is_low_priority && !server.for_low_priority) || !user.is_valid;

    return (
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
        {servers.map((server, index) => {
          const unavailable = isUnavailable(auth.user, server);
          return (
            <div
              key={server.id}
              className={`p-4 rounded-lg border transition-all duration-200 ${unavailable
                ? "border-gray-200 bg-gray-50 opacity-50"
                : "border-green-200 bg-green-50"}`}
            >
              <div className="flex items-center justify-between">
                <div className="flex items-center">
                  <div className={`w-2.5 h-2.5 rounded-full mr-2.5 ${unavailable ? "bg-gray-400" : "bg-green-500"}`}></div>
                  <h3 className="font-medium text-gray-900 text-sm">{server.name}</h3>
                </div>
                <span className="text-xs text-gray-500">{server.rate}x</span>
              </div>
              {unavailable && (
                <div className="mt-2 text-xs text-gray-400 flex items-center">
                  <svg xmlns="http://www.w3.org/2000/svg" className="h-3.5 w-3.5 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                  </svg>
                  {needsRecharge ? trans('dashboard.requires_active') : trans('dashboard.requires_priority')}
                </div>
              )}
            </div>
          );
        })}
      </div>
    );
  }

  return (
    <AuthenticatedLayout
      user={auth.user}
      header={
        <h2 className="font-semibold text-xl text-gray-800 leading-tight">{trans('dashboard.title')}</h2>
      }
    >
      <Head title={trans('dashboard.title')}/>

      <div className="py-8">
        <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">

          {/* Onboarding steps - show for new/inactive users */}
          {(isNewUser || needsRecharge) && renderStepIndicator()}

          {renderNextStepGuide()}

          {/* Balance + Usage row */}
          <div className="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
            {renderBalanceCard()}

            {/* Usage stats card */}
            <div className="bg-white rounded-xl p-5 border border-gray-200">
              <p className="text-sm text-gray-500 mb-1">{trans('dashboard.traffic_usage')}</p>
              <div className="grid grid-cols-2 gap-4">
                <div>
                  <p className="text-2xl font-bold text-gray-800">{totalTraffic}</p>
                  <p className="text-xs text-gray-400">{trans('dashboard.total')}</p>
                </div>
                <div>
                  <p className="text-2xl font-bold text-gray-800">{formatBytes(todayTraffic)}</p>
                  <p className="text-xs text-gray-400">{trans('dashboard.today')}</p>
                </div>
              </div>
              <div className="mt-3 pt-3 border-t border-gray-100">
                <Link
                  href={route('stat')}
                  className="text-sm text-blue-600 hover:text-blue-800 font-medium transition-colors"
                >
                  {trans('dashboard.view_stats')}
                </Link>
              </div>
            </div>
          </div>

          {/* Subscription / Connect section */}
          <div className="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6">
            <h3 className="text-lg font-semibold text-gray-800 mb-4 flex items-center">
              <svg xmlns="http://www.w3.org/2000/svg" className="h-5 w-5 mr-2 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/>
              </svg>
              {trans('dashboard.subscription')}
            </h3>
            {renderSubscriptionButtons()}
          </div>

          {/* Servers section */}
          <div className="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <div className="flex items-center justify-between mb-4">
              <h3 className="text-lg font-semibold text-gray-800 flex items-center">
                <svg xmlns="http://www.w3.org/2000/svg" className="h-5 w-5 mr-2 text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2m-2-4h.01M17 16h.01"/>
                </svg>
                {trans('dashboard.servers_available', {
                  available: servers.filter(s => !((auth.user.is_low_priority && !s.for_low_priority) || !auth.user.is_valid)).length,
                  total: servers.length,
                })}
              </h3>
            </div>
            {renderServers()}
          </div>

        </div>
      </div>
    </AuthenticatedLayout>
  );
}
