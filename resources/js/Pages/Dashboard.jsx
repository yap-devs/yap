import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import {Head} from '@inertiajs/react';
import {useState} from "react";
import {formatBytes} from "@/Utils/formatBytes";

export default function Dashboard({auth, clashUrl, unitPrice}) {
  const [copyButton, setCopyButton] = useState('Copy');
  const copyToClipboard = async text => {
    try {
      await navigator.clipboard.writeText(text);
      setCopyButton('Copied!');
      setTimeout(() => {
        setCopyButton('Copy');
      }, 2000);
    } catch (err) {
      console.error('Failed to copy: ', err);
    }
  }

  const renderPayReminder = () => {
    if (auth.user.is_valid) {
      if (auth.user.is_low_priority) {
        return (
          <div className="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4" role="alert">
            <p className="font-bold">Your account is low priority!</p>
            <p className="mt-4">
              Please consider
              <button onClick={() => window.location.href = route('profile.edit')}
                      className="text-blue-500 hover:underline px-1" style={{cursor: 'pointer'}}>upgrading</button>
              your account to high priority to get the best experience.
            </p>
            <p className="mt-4">Your Clash URL is:</p>
            <p className="mt-2 text-blue-500">{clashUrl}</p>
            <button
              onClick={() => copyToClipboard(clashUrl)}
              className="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded mt-4"
            >
              {copyButton}
            </button>
          </div>
        )
      }

      return (<div className="bg-green-100 border-l-4 border-green-500 text-green-700 p-4" role="alert">
        <p className="mt-4">Your Clash URL is:</p>
        <p className="mt-2 text-blue-500">{clashUrl}</p>
        <button
          onClick={() => copyToClipboard(clashUrl)}
          className="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded mt-4"
        >
          {copyButton}
        </button>
      </div>)
    }

    return (<div className="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4" role="alert">
      <p className="font-bold">Your account is limited!</p>
      <p className="mt-4">
        Please
        <button onClick={() => window.location.href = route('profile.edit')}
                className="text-blue-500 hover:underline px-1" style={{cursor: 'pointer'}}>charge</button>
        your account to continue using the service.
      </p>
    </div>);
  }

  const totalTraffic = formatBytes(auth.user.traffic_downlink + auth.user.traffic_uplink);
  const renderTrafficUnpaid = () => {
    const sharedElements = (
      <>⚠️ Pending traffic: <strong>{formatBytes(auth.user.traffic_unpaid)}</strong>.</>
    );

    if (auth.user.traffic_unpaid === 0) {
      return (
        <>
          {sharedElements} &nbsp;
          No pending traffic, how dare you.
        </>
      );
    }

    return (
      <>
        {sharedElements} &nbsp;
        It will be settled soon.
      </>
    );
  };

  return (<AuthenticatedLayout
    user={auth.user}
    header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">Dashboard</h2>}
  >
    <Head title="Dashboard"/>

    <div className="py-12">
      <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg">
          <div className="p-6 bg-gradient-to-r text-white rounded-lg shadow-md from-zinc-600 to-zinc-400">
            <h1 className="text-xl font-bold underline decoration-sky-500">Welcome Back, {auth.user.name}!</h1>
            <p className="mt-4 bg-yellow-200 text-yellow-900 p-2 rounded">📧 You're logged in
              as: <strong>{auth.user.email}</strong></p>
            <p className="mt-4 bg-green-200 text-green-900 p-2 rounded">🌐 Data used: <strong>{totalTraffic}</strong></p>
            <p className="mt-4 bg-red-200 text-red-900 p-2 rounded">{renderTrafficUnpaid()}</p>
            <div className="mt-4 bg-blue-200 text-blue-900 p-2 rounded">
              💡 Rate: <span className="font-bold">${unitPrice}</span> per GB.
            </div>
            <div className="mt-4">
              {renderPayReminder()}
            </div>
          </div>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>);
}
