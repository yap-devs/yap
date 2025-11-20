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
              <input type="number"
                     className="py-3 px-4 ps-9 pe-20 block w-full border-gray-200 shadow-sm rounded-lg text-sm focus:z-10 focus:border-blue-500 focus:ring-blue-500 disabled:opacity-50 disabled:pointer-events-none"
                     placeholder="5" value={githubAmount} onChange={(e) => setGithubAmount(e.target.value)}/>
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
      <div className="rounded-lg shadow-md overflow-hidden max-w-md my-2 border border-blue-200 hover:shadow-lg transition-shadow duration-200">
        <div className="p-4 bg-blue-500 text-white">
          <div className="flex items-center justify-between">
            <h3 className="text-lg font-semibold flex items-center">
              <svg className="w-5 h-5 mr-2" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M12 22C17.5228 22 22 17.5228 22 12C22 6.47715 17.5228 2 12 2C6.47715 2 2 6.47715 2 12C2 17.5228 6.47715 22 12 22Z" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"/>
                <path d="M14.3 8L14 8.3C12.4 9.9 9.9 12.4 8.3 14L8 14.3C7.4 14.9 7.4 15.8 8 16.4V16.4C8.6 17 9.5 17 10.1 16.4L10.4 16.1C12 14.5 14.5 12 16.1 10.4L16.4 10.1C17 9.5 17 8.6 16.4 8V8C15.8 7.4 14.9 7.4 14.3 8Z" fill="currentColor"/>
              </svg>
              Alipay
            </h3>
            <span className="text-xs bg-blue-400/30 px-2 py-1 rounded">Fast</span>
          </div>
        </div>

        <div className="p-4 bg-white text-gray-800">
          <div className="mb-4">
            <label className="block text-sm font-medium text-gray-700 mb-2">
              Amount (USD)
            </label>
            <div className="flex items-center">
              <div className="relative flex-grow">
                <input
                  type="text"
                  className="w-full py-2 pl-6 pr-4 rounded-md border border-gray-300 bg-white placeholder-gray-400 text-gray-800 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-center font-medium"
                  placeholder="5"
                  value={alipayAmount}
                  onChange={(e) => sponsorAmountChange(e, setAlipayAmount, setAlipayError)}
                />
                <span className="absolute inset-y-0 left-0 flex items-center pl-2">
                  <span className="text-gray-500">$</span>
                </span>
              </div>
              <div className="flex flex-col ml-2">
                <button
                  className="bg-gray-100 hover:bg-gray-200 text-gray-600 rounded-t p-1 transition-colors"
                  onClick={incrementAmount}
                >
                  <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M5 15l7-7 7 7"></path>
                  </svg>
                </button>
                <button
                  className="bg-gray-100 hover:bg-gray-200 text-gray-600 rounded-b p-1 transition-colors"
                  onClick={decrementAmount}
                >
                  <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19 9l-7 7-7-7"></path>
                  </svg>
                </button>
              </div>
            </div>
            {alipayError ? (
              <div className="mt-2 p-2 bg-red-50 border-l-4 border-red-500 text-red-700">
                <span className="text-sm">{alipayError}</span>
              </div>
            ) : (
              <div className="mt-1 text-xs text-gray-500 text-center">
                Enter amount between $2-$100
              </div>
            )}
          </div>

          <button
            className={`w-full py-2 px-4 font-medium text-white rounded-md transition-colors ${
              !alipayAmount || parseInt(alipayAmount) < 2 || parseInt(alipayAmount) > 100
                ? 'bg-gray-400 cursor-not-allowed'
                : 'bg-blue-500 hover:bg-blue-600'
            }`}
            type="button"
            onClick={redirectToAlipayScanPage}
            disabled={!alipayAmount || parseInt(alipayAmount) < 2 || parseInt(alipayAmount) > 100}
          >
            Charge Now
          </button>
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
      <div className="rounded-lg shadow-md overflow-hidden max-w-md my-2 border border-green-200 hover:shadow-lg transition-shadow duration-200">
        <div className="p-4 bg-green-500 text-white">
          <div className="flex items-center justify-between">
            <h3 className="text-lg font-semibold flex items-center">
              <svg className="w-5 h-5 mr-2" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M12 22C17.5228 22 22 17.5228 22 12C22 6.47715 17.5228 2 12 2C6.47715 2 2 6.47715 2 12C2 17.5228 6.47715 22 12 22Z" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"/>
                <path d="M12 6V12L16 14" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"/>
              </svg>
              USDT
            </h3>
            <span className="text-xs bg-green-400/30 px-2 py-1 rounded">Crypto</span>
          </div>
        </div>

        <div className="p-4 bg-white text-gray-800">
          <div className="mb-4">
            <label className="block text-sm font-medium text-gray-700 mb-2">
              Amount (USD)
            </label>
            <div className="flex items-center">
              <div className="relative flex-grow">
                <input
                  type="text"
                  className="w-full py-2 pl-6 pr-4 rounded-md border border-gray-300 bg-white placeholder-gray-400 text-gray-800 focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500 text-center font-medium"
                  placeholder="5"
                  value={usdtAmount}
                  onChange={(e) => sponsorAmountChange(e, setUsdtAmount, setUsdtError)}
                />
                <span className="absolute inset-y-0 left-0 flex items-center pl-2">
                  <span className="text-gray-500">$</span>
                </span>
              </div>
              <div className="flex flex-col ml-2">
                <button
                  className="bg-gray-100 hover:bg-gray-200 text-gray-600 rounded-t p-1 transition-colors"
                  onClick={incrementAmount}
                >
                  <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M5 15l7-7 7 7"></path>
                  </svg>
                </button>
                <button
                  className="bg-gray-100 hover:bg-gray-200 text-gray-600 rounded-b p-1 transition-colors"
                  onClick={decrementAmount}
                >
                  <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19 9l-7 7-7-7"></path>
                  </svg>
                </button>
              </div>
            </div>
            {usdtError ? (
              <div className="mt-2 p-2 bg-red-50 border-l-4 border-red-500 text-red-700">
                <span className="text-sm">{usdtError}</span>
              </div>
            ) : (
              <div className="mt-1 text-xs text-gray-500 text-center">
                Enter amount between $2-$100
              </div>
            )}
          </div>

          <button
            className={`w-full py-2 px-4 font-medium text-white rounded-md transition-colors ${
              !usdtAmount || parseInt(usdtAmount) < 2 || parseInt(usdtAmount) > 100
                ? 'bg-gray-400 cursor-not-allowed'
                : 'bg-green-500 hover:bg-green-600'
            }`}
            type="button"
            onClick={redirectToUSDTPage}
            disabled={!usdtAmount || parseInt(usdtAmount) < 2 || parseInt(usdtAmount) > 100}
          >
            Charge Now
          </button>
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

          <div className="p-6 sm:p-8 bg-gradient-to-r from-blue-50 to-indigo-50 shadow-lg sm:rounded-lg border border-blue-200">
            <header className="text-center mb-6">
              <div className="inline-block mb-2">
                <svg className="w-8 h-8 mx-auto text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
              </div>
              <h2 className="text-2xl font-bold text-gray-800 mb-2">Account Recharge</h2>
              <p className="text-gray-600 text-sm">
                Choose your preferred payment method to add funds to your account.
              </p>
            </header>

            <div className="flex flex-col md:flex-row justify-center items-stretch gap-4">
              <div className="flex-1 flex justify-center">
                {renderAlipayBlock()}
              </div>
              <div className="flex-1 flex justify-center">
                {renderUSDTBlock()}
              </div>
            </div>
          </div>

          <div className="p-6 sm:p-8 bg-white shadow-lg sm:rounded-lg border border-gray-200">
            <header className="border-b border-gray-100 pb-4 mb-6">
              <div className="flex items-center mb-2">
                <svg className="w-6 h-6 text-gray-700 mr-2" fill="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                  <path d="M12 0c-6.626 0-12 5.373-12 12 0 5.302 3.438 9.8 8.207 11.387.599.111.793-.261.793-.577v-2.234c-3.338.726-4.033-1.416-4.033-1.416-.546-1.387-1.333-1.756-1.333-1.756-1.089-.745.083-.729.083-.729 1.205.084 1.839 1.237 1.839 1.237 1.07 1.834 2.807 1.304 3.492.997.107-.775.418-1.305.762-1.604-2.665-.305-5.467-1.334-5.467-5.931 0-1.311.469-2.381 1.236-3.221-.124-.303-.535-1.524.117-3.176 0 0 1.008-.322 3.301 1.23.957-.266 1.983-.399 3.003-.404 1.02.005 2.047.138 3.006.404 2.291-1.552 3.297-1.23 3.297-1.23.653 1.653.242 2.874.118 3.176.77.84 1.235 1.911 1.235 3.221 0 4.609-2.807 5.624-5.479 5.921.43.372.823 1.102.823 2.222v3.293c0 .319.192.694.801.576 4.765-1.589 8.199-6.086 8.199-11.386 0-6.627-5.373-12-12-12z"/>
                </svg>
                <h2 className="text-xl font-semibold text-gray-900">
                  GitHub Integration
                </h2>
              </div>
              <p className="text-sm text-gray-600">
                Connect your GitHub account to unlock additional features and charge-free access.
              </p>
            </header>
            <div>
              {renderGithubSponsorBlock()}
            </div>
          </div>

          <div className="p-6 sm:p-8 bg-white shadow-lg sm:rounded-lg border border-gray-200">
            <UpdateProfileInformationForm
              mustVerifyEmail={mustVerifyEmail}
              status={status}
              className="max-w-xl"
            />
          </div>

          <div className="p-6 sm:p-8 bg-white shadow-lg sm:rounded-lg border border-gray-200">
            <UpdatePasswordForm className="max-w-xl"/>
          </div>

          <div className="p-6 sm:p-8 bg-white shadow-lg sm:rounded-lg border border-gray-200 border-red-200">
            <DeleteUserForm className="max-w-xl"/>
          </div>
        </div>
      </div>
    </AuthenticatedLayout>
  );
}
