import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import {Head, Link, router} from '@inertiajs/react';
import {useState} from "react";
import {formatBytes} from "@/Utils/formatBytes";

export default function Dashboard({auth, clashUrl, unitPrice, servers, todayTraffic}) {
  const [copyButton, setCopyButton] = useState('Copy URL');
  const [showTooltip, setShowTooltip] = useState(false);

  const copyToClipboard = async text => {
    try {
      await navigator.clipboard.writeText(text);
      setCopyButton('Copied!');
      setShowTooltip(true);
      setTimeout(() => {
        setCopyButton('Copy');
        setShowTooltip(false);
      }, 2000);
    } catch (err) {
      console.error('Failed to copy: ', err);
    }
  }

  // Calculate total traffic
  const totalBytes = auth.user.traffic_downlink + auth.user.traffic_uplink;
  const totalTraffic = formatBytes(totalBytes);

  const renderClashUrl = () => {
    const clashUrlBlock = (
      <div className="mt-6">
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
          <button
            onClick={() => copyToClipboard(clashUrl)}
            className="relative flex items-center justify-center bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-700 hover:to-blue-800 text-white font-medium py-3 px-4 rounded-lg shadow-md transition duration-300 ease-in-out transform hover:-translate-y-1"
          >
            <svg xmlns="http://www.w3.org/2000/svg" className="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3" />
            </svg>
            {copyButton}
            {showTooltip && (
              <span className="absolute -top-10 left-1/2 transform -translate-x-1/2 bg-gray-900 text-white text-xs px-2 py-1 rounded">
                URL copied to clipboard!
              </span>
            )}
          </button>
          <button
            onClick={() => window.location.href = 'clash://install-config?url=' + encodeURIComponent(clashUrl)}
            className="flex items-center justify-center bg-gradient-to-r from-blue-500 to-blue-600 hover:from-blue-600 hover:to-blue-700 text-white font-medium py-3 px-4 rounded-lg shadow-md transition duration-300 ease-in-out transform hover:-translate-y-1"
          >
            <svg xmlns="http://www.w3.org/2000/svg" className="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
            </svg>
            Import to Clash
          </button>
          <button
            onClick={() => window.location.href = 'shadowrocket://add/sub://' + btoa(clashUrl)}
            className="flex items-center justify-center bg-gradient-to-r from-purple-600 to-purple-700 hover:from-purple-700 hover:to-purple-800 text-white font-medium py-3 px-4 rounded-lg shadow-md transition duration-300 ease-in-out transform hover:-translate-y-1"
          >
            <svg xmlns="http://www.w3.org/2000/svg" className="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 10V3L4 14h7v7l9-11h-7z" />
            </svg>
            Import to Shadowrocket
          </button>
          <button
            onClick={() => window.location.href = 'stash://install-config?url=' + encodeURIComponent(clashUrl)}
            className="flex items-center justify-center bg-gradient-to-r from-orange-500 to-orange-600 hover:from-orange-600 hover:to-orange-700 text-white font-medium py-3 px-4 rounded-lg shadow-md transition duration-300 ease-in-out transform hover:-translate-y-1"
          >
            <svg xmlns="http://www.w3.org/2000/svg" className="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
            Import to Stash
          </button>
        </div>
      </div>
    );

    if (auth.user.is_valid) {
      if (auth.user.is_low_priority) {
        return (
          <div className="bg-amber-50 border-l-4 border-amber-400 p-5 rounded-lg shadow-sm mt-4" role="alert">
            <div className="flex items-center">
              <svg xmlns="http://www.w3.org/2000/svg" className="h-6 w-6 text-amber-500 mr-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
              </svg>
              <p className="font-semibold text-amber-800 text-lg">Your account is low priority</p>
            </div>
            <p className="mt-3 text-amber-700">
              Please consider
              <button onClick={() => window.location.href = route('profile.edit')}
                      className="text-blue-600 hover:text-blue-800 font-medium px-1 underline transition-colors duration-200" style={{cursor: 'pointer'}}>upgrading</button>
              your account to high priority to get the best experience.
            </p>
            {clashUrlBlock}
          </div>
        )
      }

      return clashUrlBlock;
    }

    return (
      <div className="bg-red-50 border-l-4 border-red-400 p-5 rounded-lg shadow-sm mt-4" role="alert">
        <div className="flex items-center">
          <svg xmlns="http://www.w3.org/2000/svg" className="h-6 w-6 text-red-500 mr-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
          <p className="font-semibold text-red-800 text-lg">Your account is limited!</p>
        </div>
        <p className="mt-3 text-red-700">
          Please
          <button onClick={() => window.location.href = route('profile.edit')}
                  className="text-blue-600 hover:text-blue-800 font-medium px-1 underline transition-colors duration-200" style={{cursor: 'pointer'}}>charge</button>
          your account to continue using the service.
        </p>
      </div>
    );
  }

  const renderServers = () => {
    if (servers.length === 0) {
      return (
        <div className="p-6 bg-gray-50 text-red-600 rounded-lg flex items-center justify-center">
          <svg xmlns="http://www.w3.org/2000/svg" className="h-6 w-6 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
          <span className="font-medium">No servers found.</span>
        </div>
      );
    }

    const isUnavailable = (user, server) => (user.is_low_priority && !server.for_low_priority) || !user.is_valid;
    return (
      <div className="bg-white rounded-lg shadow-sm overflow-hidden">
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 p-4">
          {servers.map((server, index) => (
            <div
              key={server.id}
              className={`p-4 rounded-lg border ${isUnavailable(auth.user, server)
                ? "border-gray-200 bg-gray-50 opacity-60"
                : "border-blue-100 bg-blue-50"} transition-all duration-200 hover:shadow-md`}
            >
              <div className="flex items-center justify-between">
                <div className="flex items-center">
                  <div className={`w-3 h-3 rounded-full mr-3 ${isUnavailable(auth.user, server) ? "bg-gray-400" : "bg-green-500"}`}></div>
                  <h3 className="font-medium text-gray-900">Server {index}</h3>
                </div>
                <span className={`px-2 py-1 text-xs rounded-full ${isUnavailable(auth.user, server) ? "bg-gray-200 text-gray-700" : "bg-blue-100 text-blue-800"}`}>
                  Rate: {server.rate}x
                </span>
              </div>
              <p className="mt-2 text-gray-700 font-medium">{server.name}</p>
              {isUnavailable(auth.user, server) && (
                <div className="mt-2 text-xs text-gray-500 flex items-center">
                  <svg xmlns="http://www.w3.org/2000/svg" className="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                  </svg>
                  Unavailable with current plan
                </div>
              )}
            </div>
          ))}
        </div>
      </div>
    );
  }

  return (
    <AuthenticatedLayout
      user={auth.user}
      header={
        <div className="flex items-center">
          <h2 className="font-semibold text-xl text-gray-800 leading-tight">Dashboard</h2>
        </div>
      }
    >
      <Head title="Dashboard" />

      <div className="py-12">
        <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
          <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg">
            <div className="p-6 bg-white rounded-lg">
              <div className="flex items-center mb-6">
                <div className="w-12 h-12 rounded-full bg-gradient-to-r from-blue-500 to-purple-600 flex items-center justify-center text-white text-xl font-bold mr-4">
                  {auth.user.name.charAt(0).toUpperCase()}
                </div>
                <div>
                  <h1 className="text-2xl font-bold text-gray-800">Welcome Back, {auth.user.name}!</h1>
                  <p className="text-gray-600">Here's an overview of your account</p>
                </div>
              </div>

              <div className="grid grid-cols-1 lg:grid-cols-2 gap-6 mt-6">
                <div className="bg-gradient-to-br from-blue-50 to-indigo-50 p-6 rounded-xl shadow-sm border border-blue-100">
                  <h2 className="text-xl font-semibold text-gray-800 flex items-center">
                    <svg xmlns="http://www.w3.org/2000/svg" className="h-5 w-5 mr-2 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                    </svg>
                    Usage Statistics
                  </h2>

                  <div className="mt-4 space-y-4">
                    <div className="grid grid-cols-2 gap-4">
                      <div className="bg-white p-4 rounded-lg shadow-sm border border-blue-100">
                        <div className="flex items-center text-blue-600 mb-2">
                          <svg xmlns="http://www.w3.org/2000/svg" className="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M20 12V6a2 2 0 00-2-2h-12a2 2 0 00-2 2v10a2 2 0 002 2h8" />
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M18 18v-6M15 18h6" />
                          </svg>
                          <span className="font-medium">Total Data Used</span>
                        </div>
                        <div className="text-2xl font-bold text-gray-800">{totalTraffic}</div>
                      </div>

                      <div className="bg-white p-4 rounded-lg shadow-sm border border-green-100">
                        <div className="flex items-center text-green-600 mb-2">
                          <svg xmlns="http://www.w3.org/2000/svg" className="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                          </svg>
                          <span className="font-medium">Today's Usage</span>
                        </div>
                        <div className="text-2xl font-bold text-gray-800">{formatBytes(todayTraffic)}</div>
                      </div>
                    </div>

                    <div className="pt-2">
                      <Link
                        href={route('stat')}
                        className="inline-flex items-center text-blue-600 hover:text-blue-800 font-medium transition-colors duration-200"
                      >
                        View detailed statistics
                        <svg xmlns="http://www.w3.org/2000/svg" className="h-4 w-4 ml-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5l7 7-7 7" />
                        </svg>
                      </Link>
                    </div>

                    <div className="pt-2 border-t border-gray-200 mt-4">
                      <p className="text-sm text-gray-700"><span className="font-medium">Rate:</span> ${unitPrice} per GB by default</p>
                      <p className="text-sm text-gray-700 mt-1 font-medium">Purchase traffic packages for better rates!</p>
                    </div>

                    <div className="flex flex-wrap gap-3 mt-4">
                      <button
                        onClick={() => router.get(route('profile.edit'))}
                        className="flex items-center bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-lg transition duration-200"
                      >
                        <svg xmlns="http://www.w3.org/2000/svg" className="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        Charge Account
                      </button>
                      <button
                        onClick={() => router.get(route('package'))}
                        className="flex items-center bg-gradient-to-r from-pink-500 to-orange-500 hover:from-pink-600 hover:to-orange-600 text-white font-medium py-2 px-4 rounded-lg shadow-md transition duration-200 animate-pulse"
                      >
                        <svg xmlns="http://www.w3.org/2000/svg" className="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 10V3L4 14h7v7l9-11h-7z" />
                        </svg>
                        Limited Time Offer!
                      </button>
                    </div>
                  </div>
                </div>

                <div className="bg-gradient-to-br from-amber-50 to-orange-50 p-6 rounded-xl shadow-sm border border-amber-100">
                  <h2 className="text-xl font-semibold text-gray-800 flex items-center">
                    <svg xmlns="http://www.w3.org/2000/svg" className="h-5 w-5 mr-2 text-amber-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z" />
                    </svg>
                    Subscription Information
                  </h2>
                  {renderClashUrl()}
                </div>
              </div>

              <div className="mt-8">
                <h2 className="text-xl font-semibold text-gray-800 mb-4 flex items-center">
                  <svg xmlns="http://www.w3.org/2000/svg" className="h-5 w-5 mr-2 text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2m-2-4h.01M17 16h.01" />
                  </svg>
                  Available Servers
                </h2>
                {renderServers()}
              </div>
            </div>
          </div>
        </div>
      </div>
    </AuthenticatedLayout>
  );
}
