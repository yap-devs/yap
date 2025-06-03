import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import DeleteUserForm from './Partials/DeleteUserForm';
import UpdatePasswordForm from './Partials/UpdatePasswordForm';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm';
import {Head, router, usePage} from '@inertiajs/react';
import {useState, useEffect} from 'react';

export default function Edit({auth, mustVerifyEmail, status, githubSponsorURL}) {
  const {errors} = usePage().props;

  const redirectToGithubOauth = () => {
    window.location.href = route('github.redirect');
  };
  const redirectToGithubSponsor = () => {
    // add amount to the query string
    const url = new URL(githubSponsorURL);
    url.searchParams.append('amount', githubAmount || 5);

    window.open(url.href, '_blank');
  }
  const redirectToAlipayScanPage = () => {
    router.visit(route('alipay.newOrder'), {
      method: 'post',
      data: {amount: alipayAmount || 5}
    });
  }
  const redirectToUSDTPage = () => {
    window.location.href = route('bepusdt.newOrder', {amount: usdtAmount || 5});
  }

  const [githubAmount, setGithubAmount] = useState(5);
  const [alipayAmount, setAlipayAmount] = useState(5);
  const [usdtAmount, setUsdtAmount] = useState(5);

  // State for validation messages
  const [alipayError, setAlipayError] = useState('');
  const [usdtError, setUsdtError] = useState('');

  // Validate initial values on component mount
  useEffect(() => {
    // Validate Alipay amount
    if (alipayAmount) {
      const numVal = parseInt(alipayAmount);
      if (numVal < 2) {
        setAlipayError('Amount must be at least $2');
      } else if (numVal > 100) {
        setAlipayError('Amount cannot exceed $100');
      }
    }

    // Validate USDT amount
    if (usdtAmount) {
      const numVal = parseInt(usdtAmount);
      if (numVal < 2) {
        setUsdtError('Amount must be at least $2');
      } else if (numVal > 100) {
        setUsdtError('Amount cannot exceed $100');
      }
    }
  }, []);

  const sponsorAmountChange = (e, setFunc, setError) => {
    const val = e.target.value;

    if (val === '') {
      setFunc(val);
      setError('');
      return;
    }

    // Allow typing any digit, but validate the pattern first
    if (!/^\d+$/.test(val)) return;

    // Set the value even if it's less than 2, to allow typing numbers like "12"
    setFunc(val);

    // Validate the amount and set appropriate error message
    const numVal = parseInt(val);
    if (numVal < 2) {
      setError('Amount must be at least $2');
    } else if (numVal > 100) {
      setError('Amount cannot exceed $100');
    } else {
      setError('');
    }
  }

  const renderGithubSponsorBlock = () => {
    if (auth.user.github_id) {
      return (
        <div>
          <div className="flex items-center">
            <svg className="h-6 w-6 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                 xmlns="http://www.w3.org/2000/svg">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2"
                    d="M5 13l4 4L19 7"/>
            </svg>
            <span className="ml-2 text-green-500">Github account is linked.</span>
            <span className="ml-2 text-gray-500">({auth.user.github_nickname})</span>
          </div>
          <div className={
            `mt-4 ${auth.user.is_valid ? 'bg-green-100 border-l-4 border-green-500 text-green-700' : 'bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700'} p-4`
          }>
            <p className="font-bold">Your account is {auth.user.is_valid ? 'valid' : 'limited'}!</p>
          </div>
          <div className="max-w-sm space-y-3 mt-4">
            <div className="relative">
              <input type="text"
                     className="py-3 px-4 ps-9 pe-20 block w-full border-gray-200 shadow-sm rounded-lg text-sm focus:z-10 focus:border-blue-500 focus:ring-blue-500 disabled:opacity-50 disabled:pointer-events-none"
                     placeholder="5" value={githubAmount} onChange={setGithubAmount}/>
              <div className="absolute inset-y-0 start-0 flex items-center pointer-events-none z-20 ps-4">
                <span className="text-gray-500">$</span>
              </div>
              <div className="absolute inset-y-0 end-0 flex items-center text-gray-500 pe-px mr-1">
                <button
                  className="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-gray-600 hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500"
                  type="button"
                  onClick={redirectToGithubSponsor}
                >
                  Sponsor Now
                </button>
              </div>
            </div>
          </div>
        </div>
      );
    }

    return (
      <button
        onClick={redirectToGithubOauth}
        className="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-gray-600 hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500"
      >
        Bind Github Account
      </button>
    );
  }

  const renderAlipayBlock = () => {
    const incrementAmount = () => {
      const newAmount = parseInt(alipayAmount || 0) + 1;
      if (newAmount <= 100) {
        setAlipayAmount(newAmount);
        // Clear error if amount is now valid
        if (newAmount >= 2) {
          setAlipayError('');
        }
      } else {
        // Set error for exceeding maximum
        setAlipayError('Amount cannot exceed $100');
      }
    };

    const decrementAmount = () => {
      const newAmount = parseInt(alipayAmount || 0) - 1;
      if (newAmount >= 2) {
        setAlipayAmount(newAmount);
        setAlipayError(''); // Clear error as amount is valid
      } else {
        setAlipayAmount(newAmount);
        // Set error for below minimum
        setAlipayError('Amount must be at least $2');
      }
    };

    return (
      <div className="rounded-xl shadow-xl overflow-hidden max-w-md my-4 transform transition-all hover:scale-105 duration-300 border border-blue-200">
        <div className="p-5 bg-gradient-to-r from-blue-500 to-blue-600 relative">
          {/* Decorative lines */}
          <div className="absolute inset-0 overflow-hidden opacity-20">
            <svg width="100%" height="100%" xmlns="http://www.w3.org/2000/svg">
              <defs>
                <pattern id="smallGrid" width="10" height="10" patternUnits="userSpaceOnUse">
                  <path d="M 10 0 L 0 0 0 10" fill="none" stroke="white" strokeWidth="0.5"/>
                </pattern>
              </defs>
              <rect width="100%" height="100%" fill="url(#smallGrid)" />
            </svg>
          </div>

          <div className="flex items-center justify-between relative z-10">
            <h3 className="text-xl font-bold text-white flex items-center">
              <svg className="w-6 h-6 mr-2 text-blue-100" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M12 22C17.5228 22 22 17.5228 22 12C22 6.47715 17.5228 2 12 2C6.47715 2 2 6.47715 2 12C2 17.5228 6.47715 22 12 22Z" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"/>
                <path d="M14.3 8L14 8.3C12.4 9.9 9.9 12.4 8.3 14L8 14.3C7.4 14.9 7.4 15.8 8 16.4V16.4C8.6 17 9.5 17 10.1 16.4L10.4 16.1C12 14.5 14.5 12 16.1 10.4L16.4 10.1C17 9.5 17 8.6 16.4 8V8C15.8 7.4 14.9 7.4 14.3 8Z" fill="currentColor"/>
              </svg>
              Alipay Recharge
            </h3>
            <div className="bg-blue-400/20 rounded-full p-1.5">
              <svg className="w-5 h-5 text-blue-100" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
              </svg>
            </div>
          </div>

          <div className="mt-2 text-blue-100 text-sm flex items-center">
            <svg className="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
            </svg>
            Fast processing, instant access
          </div>
        </div>

        <div className="p-5 bg-white text-gray-800">
          <div className="relative mb-4">
            <label className="block text-sm font-medium text-blue-600 mb-2 flex items-center">
              <svg className="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
              </svg>
              Enter Amount (USD)
            </label>
            <div className="flex items-center">
              <div className="relative flex-grow group">
                <input
                  type="text"
                  className="w-full py-3 pl-8 pr-4 rounded-lg border-2 border-blue-300 bg-white placeholder-gray-500 text-gray-800 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all text-center text-xl font-medium"
                  placeholder="5"
                  value={alipayAmount}
                  onChange={(e) => sponsorAmountChange(e, setAlipayAmount, setAlipayError)}
                />
                <span className="absolute inset-y-0 left-0 flex items-center pl-3">
                  <span className="text-blue-500 font-bold">$</span>
                </span>
                <div className="absolute inset-0 rounded-lg border border-blue-300/0 group-hover:border-blue-300/50 pointer-events-none transition-all duration-300"></div>
              </div>
              <div className="flex flex-col ml-2">
                <button
                  className="bg-blue-100 hover:bg-blue-200 text-blue-700 font-bold rounded-t-md p-1 transition-all focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-1"
                  onClick={incrementAmount}
                >
                  <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M5 15l7-7 7 7"></path>
                  </svg>
                </button>
                <button
                  className="bg-blue-100 hover:bg-blue-200 text-blue-700 font-bold rounded-b-md p-1 transition-all focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-1"
                  onClick={decrementAmount}
                >
                  <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19 9l-7 7-7-7"></path>
                  </svg>
                </button>
              </div>
            </div>
            {alipayError ? (
              <div className="mt-2 p-2 bg-red-50 border-l-4 border-red-500 text-red-700 animate-pulse">
                <div className="flex items-center">
                  <svg className="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                  </svg>
                  <span className="font-medium">{alipayError}</span>
                </div>
              </div>
            ) : (
              <div className="mt-1 text-xs text-gray-500 text-center">
                Enter an integer amount between 2-100
              </div>
            )}
          </div>

          <button
            className={`w-full py-3 px-4 font-bold text-white rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-all flex items-center justify-center ${
              !alipayAmount || parseInt(alipayAmount) < 2 || parseInt(alipayAmount) > 100
                ? 'bg-gray-400 cursor-not-allowed opacity-70'
                : 'bg-gradient-to-r from-blue-500 to-blue-600 hover:from-blue-600 hover:to-blue-700'
            }`}
            type="button"
            onClick={redirectToAlipayScanPage}
            disabled={!alipayAmount || parseInt(alipayAmount) < 2 || parseInt(alipayAmount) > 100}
          >
            <svg className="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
            </svg>
            Charge Now
          </button>

          <div className="mt-3 flex items-center justify-center text-xs text-gray-500">
            <svg className="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
            </svg>
            Secure payment processing
          </div>
        </div>
      </div>
    );
  }

  const renderUSDTBlock = () => {
    const incrementAmount = () => {
      const newAmount = parseInt(usdtAmount || 0) + 1;
      if (newAmount <= 100) {
        setUsdtAmount(newAmount);
        // Clear error if amount is now valid
        if (newAmount >= 2) {
          setUsdtError('');
        }
      } else {
        // Set error for exceeding maximum
        setUsdtError('Amount cannot exceed $100');
      }
    };

    const decrementAmount = () => {
      const newAmount = parseInt(usdtAmount || 0) - 1;
      if (newAmount >= 2) {
        setUsdtAmount(newAmount);
        setUsdtError(''); // Clear error as amount is valid
      } else {
        setUsdtAmount(newAmount);
        // Set error for below minimum
        setUsdtError('Amount must be at least $2');
      }
    };

    return (
      <div className="rounded-xl shadow-xl overflow-hidden max-w-md my-4 transform transition-all hover:scale-105 duration-300 border border-green-200">
        <div className="p-5 bg-gradient-to-r from-green-500 to-teal-500 relative">
          {/* Decorative lines */}
          <div className="absolute inset-0 overflow-hidden opacity-20">
            <svg width="100%" height="100%" xmlns="http://www.w3.org/2000/svg">
              <defs>
                <pattern id="smallGridGreen" width="10" height="10" patternUnits="userSpaceOnUse">
                  <path d="M 10 0 L 0 0 0 10" fill="none" stroke="white" strokeWidth="0.5"/>
                </pattern>
              </defs>
              <rect width="100%" height="100%" fill="url(#smallGridGreen)" />
            </svg>
          </div>

          <div className="flex items-center justify-between relative z-10">
            <h3 className="text-xl font-bold text-white flex items-center">
              <svg className="w-6 h-6 mr-2 text-green-100" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M12 22C17.5228 22 22 17.5228 22 12C22 6.47715 17.5228 2 12 2C6.47715 2 2 6.47715 2 12C2 17.5228 6.47715 22 12 22Z" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"/>
                <path d="M12 6V12L16 14" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"/>
              </svg>
              USDT Recharge (Polygon)
            </h3>
            <div className="bg-green-400/20 rounded-full p-1.5">
              <svg className="w-5 h-5 text-green-100" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
              </svg>
            </div>
          </div>

          <div className="mt-2 text-green-100 text-sm flex items-center">
            <svg className="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
            </svg>
            Fast cryptocurrency payments
          </div>
        </div>

        <div className="p-5 bg-white text-gray-800">
          <div className="relative mb-4">
            <label className="block text-sm font-medium text-green-600 mb-2 flex items-center">
              <svg className="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
              </svg>
              Enter Amount (USD)
            </label>
            <div className="flex items-center">
              <div className="relative flex-grow group">
                <input
                  type="text"
                  className="w-full py-3 pl-8 pr-4 rounded-lg border-2 border-green-300 bg-white placeholder-gray-500 text-gray-800 focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500 transition-all text-center text-xl font-medium"
                  placeholder="5"
                  value={usdtAmount}
                  onChange={(e) => sponsorAmountChange(e, setUsdtAmount, setUsdtError)}
                />
                <span className="absolute inset-y-0 left-0 flex items-center pl-3">
                  <span className="text-green-500 font-bold">$</span>
                </span>
                <div className="absolute inset-0 rounded-lg border border-green-300/0 group-hover:border-green-300/50 pointer-events-none transition-all duration-300"></div>
              </div>
              <div className="flex flex-col ml-2">
                <button
                  className="bg-green-100 hover:bg-green-200 text-green-700 font-bold rounded-t-md p-1 transition-all focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-1"
                  onClick={incrementAmount}
                >
                  <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M5 15l7-7 7 7"></path>
                  </svg>
                </button>
                <button
                  className="bg-green-100 hover:bg-green-200 text-green-700 font-bold rounded-b-md p-1 transition-all focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-1"
                  onClick={decrementAmount}
                >
                  <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19 9l-7 7-7-7"></path>
                  </svg>
                </button>
              </div>
            </div>
            {usdtError ? (
              <div className="mt-2 p-2 bg-red-50 border-l-4 border-red-500 text-red-700 animate-pulse">
                <div className="flex items-center">
                  <svg className="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                  </svg>
                  <span className="font-medium">{usdtError}</span>
                </div>
              </div>
            ) : (
              <div className="mt-1 text-xs text-gray-500 text-center">
                Enter an integer amount between 2-100
              </div>
            )}
          </div>

          <button
            className={`w-full py-3 px-4 font-bold text-white rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 transition-all flex items-center justify-center ${
              !usdtAmount || parseInt(usdtAmount) < 2 || parseInt(usdtAmount) > 100
                ? 'bg-gray-400 cursor-not-allowed opacity-70'
                : 'bg-gradient-to-r from-green-500 to-teal-500 hover:from-green-600 hover:to-teal-600'
            }`}
            type="button"
            onClick={redirectToUSDTPage}
            disabled={!usdtAmount || parseInt(usdtAmount) < 2 || parseInt(usdtAmount) > 100}
          >
            <svg className="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
            </svg>
            Charge Now
          </button>

          <div className="mt-3 flex items-center justify-center text-xs text-gray-500">
            <svg className="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
            </svg>
            Secure cryptocurrency processing
          </div>
        </div>
      </div>
    );
  }

  return (
    <AuthenticatedLayout
      user={auth.user}
      header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">Profile</h2>}
    >
      <Head title="Profile"/>

      <div className="py-12">
        <div className="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
          {
            (errors.message || errors.amount) &&
            <div className="p-4 sm:p-8 bg-red-600 bg-opacity-10 text-red-600 rounded-lg">
              <div className="flex items-center">
                <svg className="h-6 w-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                     xmlns="http://www.w3.org/2000/svg">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2"
                        d="M6 18L18 6M6 6l12 12"/>
                </svg>
                <span className="ml-2">{errors.message || errors.amount}</span>
              </div>
            </div>
          }

          <div className="p-6 sm:p-10 bg-gradient-to-r from-blue-50 to-indigo-50 shadow-xl sm:rounded-xl border border-blue-100 relative overflow-hidden">
            {/* Decorative elements */}
            <div className="absolute inset-0 opacity-10">
              <div className="absolute top-0 left-0 w-full h-full">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 800 800" className="w-full h-full">
                  <rect fill="none" stroke="#4F46E5" strokeWidth="1" x="0" y="0" width="100" height="100"></rect>
                  <rect fill="none" stroke="#4F46E5" strokeWidth="1" x="100" y="0" width="100" height="100"></rect>
                  <rect fill="none" stroke="#4F46E5" strokeWidth="1" x="200" y="0" width="100" height="100"></rect>
                  <rect fill="none" stroke="#4F46E5" strokeWidth="1" x="300" y="0" width="100" height="100"></rect>
                  <rect fill="none" stroke="#4F46E5" strokeWidth="1" x="400" y="0" width="100" height="100"></rect>
                  <rect fill="none" stroke="#4F46E5" strokeWidth="1" x="500" y="0" width="100" height="100"></rect>
                  <rect fill="none" stroke="#4F46E5" strokeWidth="1" x="600" y="0" width="100" height="100"></rect>
                  <rect fill="none" stroke="#4F46E5" strokeWidth="1" x="700" y="0" width="100" height="100"></rect>
                  <rect fill="none" stroke="#4F46E5" strokeWidth="1" x="0" y="100" width="100" height="100"></rect>
                  <rect fill="none" stroke="#4F46E5" strokeWidth="1" x="100" y="100" width="100" height="100"></rect>
                  <rect fill="none" stroke="#4F46E5" strokeWidth="1" x="200" y="100" width="100" height="100"></rect>
                  <rect fill="none" stroke="#4F46E5" strokeWidth="1" x="300" y="100" width="100" height="100"></rect>
                  <rect fill="none" stroke="#4F46E5" strokeWidth="1" x="400" y="100" width="100" height="100"></rect>
                  <rect fill="none" stroke="#4F46E5" strokeWidth="1" x="500" y="100" width="100" height="100"></rect>
                  <rect fill="none" stroke="#4F46E5" strokeWidth="1" x="600" y="100" width="100" height="100"></rect>
                  <rect fill="none" stroke="#4F46E5" strokeWidth="1" x="700" y="100" width="100" height="100"></rect>
                </svg>
              </div>
              <div className="absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2">
                <svg className="w-96 h-96" viewBox="0 0 200 200" xmlns="http://www.w3.org/2000/svg">
                  <path fill="#4F46E5" d="M45.7,-76.3C58.9,-69.9,69.2,-56.3,76.4,-41.6C83.7,-26.9,87.9,-11.2,85.7,3.4C83.5,18,74.8,31.5,65.1,43.9C55.4,56.3,44.6,67.6,31.5,73.7C18.4,79.8,3,80.7,-12.2,78.3C-27.4,75.9,-42.5,70.2,-54.3,60.2C-66.1,50.2,-74.6,35.9,-79.3,20.1C-84,4.3,-84.8,-13,-78.8,-27.4C-72.8,-41.8,-60,-53.3,-46.1,-59.6C-32.2,-65.9,-17.1,-67,-1.2,-65C14.7,-63,32.5,-82.7,45.7,-76.3Z" transform="translate(100 100)" />
                </svg>
              </div>
            </div>

            <header className="text-center mb-8 relative z-10">
              <div className="inline-block mb-3">
                <svg className="w-12 h-12 mx-auto text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
              </div>
              <h2 className="text-3xl font-bold mb-2">
                <span className="bg-clip-text text-transparent bg-gradient-to-r from-blue-500 to-indigo-500">Recharge Your Account</span>
              </h2>
              <div className="h-1 w-24 bg-gradient-to-r from-blue-500 to-indigo-500 mx-auto rounded-full mb-4"></div>
              <p className="text-gray-600 max-w-2xl mx-auto">
                Choose your preferred payment method below to add funds to your account.
              </p>
            </header>

            <div className="flex flex-col md:flex-row justify-center items-stretch gap-6 pt-4 relative z-10">
              <div className="flex-1 flex justify-center">
                {renderAlipayBlock()}
              </div>
              <div className="flex-1 flex justify-center">
                {renderUSDTBlock()}
              </div>
            </div>
          </div>

          <div className="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
            <header>
              <h2 className="text-lg font-medium text-gray-900">
                Github Profile Information
              </h2>

              <p className="mt-1 text-sm text-gray-600">
                Bind your GitHub account to your profile for limited charge-free access.
              </p>
            </header>
            <div className="pt-4">
              {renderGithubSponsorBlock()}
            </div>
          </div>

          <div className="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
            <UpdateProfileInformationForm
              mustVerifyEmail={mustVerifyEmail}
              status={status}
              className="max-w-xl"
            />
          </div>

          <div className="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
            <UpdatePasswordForm className="max-w-xl"/>
          </div>

          <div className="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
            <DeleteUserForm className="max-w-xl"/>
          </div>
        </div>
      </div>
    </AuthenticatedLayout>
  );
}
