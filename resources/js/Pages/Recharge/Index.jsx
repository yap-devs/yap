import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import {Head, router, usePage} from '@inertiajs/react';
import {useState, useEffect} from 'react';

export default function Index({auth, githubSponsorURL, stripeSandbox}) {
  const {errors} = usePage().props;

  const redirectToGithubOauth = () => {
    window.location.href = route('github.redirect');
  };
  const redirectToGithubSponsor = () => {
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
    router.visit(route('bepusdt.newOrder'), {
      method: 'post',
      data: {amount: usdtAmount || 5}
    });
  }
  const redirectToStripePage = () => {
    router.post(route('stripe.newOrder'), {amount: stripeAmount || 5});
  }

  const [githubAmount, setGithubAmount] = useState(5);
  const [alipayAmount, setAlipayAmount] = useState(5);
  const [usdtAmount, setUsdtAmount] = useState(5);
  const [stripeAmount, setStripeAmount] = useState(5);

  const [alipayError, setAlipayError] = useState('');
  const [usdtError, setUsdtError] = useState('');
  const [stripeError, setStripeError] = useState('');

  useEffect(() => {
    if (alipayAmount) {
      const numVal = parseInt(alipayAmount);
      if (numVal < 2) {
        setAlipayError('Amount must be at least $2');
      } else if (numVal > 100) {
        setAlipayError('Amount cannot exceed $100');
      }
    }

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

    if (!/^\d+$/.test(val)) return;

    setFunc(val);

    const numVal = parseInt(val);
    if (numVal < 2) {
      setError('Amount must be at least $2');
    } else if (numVal > 100) {
      setError('Amount cannot exceed $100');
    } else {
      setError('');
    }
  }

  // Use static class maps so Tailwind JIT can detect all class names
  const colorStyles = {
    blue: {
      border: 'border-blue-200',
      bg: 'bg-blue-500',
      bgHover: 'hover:bg-blue-600',
      ring: 'focus:ring-blue-500 focus:border-blue-500',
    },
    green: {
      border: 'border-green-200',
      bg: 'bg-green-500',
      bgHover: 'hover:bg-green-600',
      ring: 'focus:ring-green-500 focus:border-green-500',
    },
    purple: {
      border: 'border-purple-200',
      bg: 'bg-purple-500',
      bgHover: 'hover:bg-purple-600',
      ring: 'focus:ring-purple-500 focus:border-purple-500',
    },
  };

  const renderPaymentCard = (config) => {
    const {title, badge, color, amount, setAmount, error, setError, onSubmit, children} = config;
    const styles = colorStyles[color] || colorStyles.blue;

    const incrementAmount = () => {
      const newAmount = parseInt(amount || 0) + 1;
      if (newAmount <= 100) {
        setAmount(newAmount);
        if (newAmount >= 2) setError('');
      } else {
        setError('Amount cannot exceed $100');
      }
    };

    const decrementAmount = () => {
      const newAmount = parseInt(amount || 0) - 1;
      if (newAmount >= 2) {
        setAmount(newAmount);
        setError('');
      } else {
        setAmount(newAmount);
        setError('Amount must be at least $2');
      }
    };

    const isDisabled = !amount || parseInt(amount) < 2 || parseInt(amount) > 100;

    return (
      <div className={`rounded-lg shadow-md overflow-hidden border ${styles.border} hover:shadow-lg transition-shadow duration-200`}>
        <div className={`p-4 ${styles.bg} text-white`}>
          <div className="flex items-center justify-between">
            <h3 className="text-lg font-semibold">{title}</h3>
            {badge}
          </div>
        </div>

        <div className="p-4 bg-white text-gray-800">
          {children}
          <div className="mb-4">
            <label className="block text-sm font-medium text-gray-700 mb-2">
              Amount (USD)
            </label>
            <div className="flex items-center">
              <div className="relative flex-grow">
                <input
                  type="text"
                  className={`w-full py-2 pl-6 pr-4 rounded-md border border-gray-300 bg-white placeholder-gray-400 text-gray-800 focus:outline-none focus:ring-2 ${styles.ring} text-center font-medium`}
                  placeholder="5"
                  value={amount}
                  onChange={(e) => sponsorAmountChange(e, setAmount, setError)}
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
            {error ? (
              <div className="mt-2 p-2 bg-red-50 border-l-4 border-red-500 text-red-700">
                <span className="text-sm">{error}</span>
              </div>
            ) : (
              <div className="mt-1 text-xs text-gray-500 text-center">
                Enter amount between $2-$100
              </div>
            )}
          </div>

          <button
            className={`w-full py-2 px-4 font-medium text-white rounded-md transition-colors ${
              isDisabled
                ? 'bg-gray-400 cursor-not-allowed'
                : `${styles.bg} ${styles.bgHover}`
            }`}
            type="button"
            onClick={onSubmit}
            disabled={isDisabled}
          >
            Recharge Now
          </button>
        </div>
      </div>
    );
  }

  const renderGithubSection = () => {
    if (auth.user.github_id) {
      return (
        <div className="rounded-lg shadow-md overflow-hidden border border-gray-200 hover:shadow-lg transition-shadow duration-200">
          <div className="p-4 bg-gray-800 text-white">
            <div className="flex items-center justify-between">
              <h3 className="text-lg font-semibold flex items-center">
                <svg className="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 24 24">
                  <path d="M12 0c-6.626 0-12 5.373-12 12 0 5.302 3.438 9.8 8.207 11.387.599.111.793-.261.793-.577v-2.234c-3.338.726-4.033-1.416-4.033-1.416-.546-1.387-1.333-1.756-1.333-1.756-1.089-.745.083-.729.083-.729 1.205.084 1.839 1.237 1.839 1.237 1.07 1.834 2.807 1.304 3.492.997.107-.775.418-1.305.762-1.604-2.665-.305-5.467-1.334-5.467-5.931 0-1.311.469-2.381 1.236-3.221-.124-.303-.535-1.524.117-3.176 0 0 1.008-.322 3.301 1.23.957-.266 1.983-.399 3.003-.404 1.02.005 2.047.138 3.006.404 2.291-1.552 3.297-1.23 3.297-1.23.653 1.653.242 2.874.118 3.176.77.84 1.235 1.911 1.235 3.221 0 4.609-2.807 5.624-5.479 5.921.43.372.823 1.102.823 2.222v3.293c0 .319.192.694.801.576 4.765-1.589 8.199-6.086 8.199-11.386 0-6.627-5.373-12-12-12z"/>
                </svg>
                GitHub Sponsors
              </h3>
              <span className="text-xs text-gray-300">({auth.user.github_nickname})</span>
            </div>
          </div>
          <div className="p-4 bg-white text-gray-800">
            <p className="text-sm text-gray-600 mb-3">Sponsor via GitHub to add funds to your account.</p>
            <div className="flex items-center">
              <div className="relative flex-grow">
                <input
                  type="number"
                  className="w-full py-2 pl-6 pr-4 rounded-md border border-gray-300 bg-white placeholder-gray-400 text-gray-800 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:border-gray-500 text-center font-medium"
                  placeholder="5"
                  value={githubAmount}
                  onChange={(e) => setGithubAmount(e.target.value)}
                />
                <span className="absolute inset-y-0 left-0 flex items-center pl-2">
                  <span className="text-gray-500">$</span>
                </span>
              </div>
              <button
                className="ml-2 px-4 py-2 bg-gray-800 hover:bg-gray-900 text-white font-medium rounded-md transition-colors"
                onClick={redirectToGithubSponsor}
              >
                Sponsor
              </button>
            </div>
          </div>
        </div>
      );
    }

    return (
      <div className="rounded-lg shadow-md overflow-hidden border border-gray-200 hover:shadow-lg transition-shadow duration-200">
        <div className="p-4 bg-gray-800 text-white">
          <h3 className="text-lg font-semibold flex items-center">
            <svg className="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 24 24">
              <path d="M12 0c-6.626 0-12 5.373-12 12 0 5.302 3.438 9.8 8.207 11.387.599.111.793-.261.793-.577v-2.234c-3.338.726-4.033-1.416-4.033-1.416-.546-1.387-1.333-1.756-1.333-1.756-1.089-.745.083-.729.083-.729 1.205.084 1.839 1.237 1.839 1.237 1.07 1.834 2.807 1.304 3.492.997.107-.775.418-1.305.762-1.604-2.665-.305-5.467-1.334-5.467-5.931 0-1.311.469-2.381 1.236-3.221-.124-.303-.535-1.524.117-3.176 0 0 1.008-.322 3.301 1.23.957-.266 1.983-.399 3.003-.404 1.02.005 2.047.138 3.006.404 2.291-1.552 3.297-1.23 3.297-1.23.653 1.653.242 2.874.118 3.176.77.84 1.235 1.911 1.235 3.221 0 4.609-2.807 5.624-5.479 5.921.43.372.823 1.102.823 2.222v3.293c0 .319.192.694.801.576 4.765-1.589 8.199-6.086 8.199-11.386 0-6.627-5.373-12-12-12z"/>
            </svg>
            GitHub Sponsors
          </h3>
        </div>
        <div className="p-4 bg-white text-gray-800">
          <p className="text-sm text-gray-600 mb-3">Link your GitHub account first to use this payment method.</p>
          <button
            onClick={redirectToGithubOauth}
            className="w-full py-2 px-4 font-medium text-white bg-gray-800 hover:bg-gray-900 rounded-md transition-colors"
          >
            Link GitHub Account
          </button>
        </div>
      </div>
    );
  }

  return (
    <AuthenticatedLayout
      user={auth.user}
      header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">Recharge</h2>}
    >
      <Head title="Recharge"/>

      <div className="py-12">
        <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
          {(errors.message || errors.amount) && (
            <div className="mb-6 p-4 bg-red-50 border-l-4 border-red-500 text-red-700 rounded-lg">
              <div className="flex items-center">
                <svg className="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
                <span>{errors.message || errors.amount}</span>
              </div>
            </div>
          )}

          {/* Current Balance */}
          <div className="mb-8 bg-white rounded-lg shadow-sm p-6 border border-gray-200">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-sm text-gray-500">Current Balance</p>
                <p className={`text-3xl font-bold ${auth.user.balance > 0 ? 'text-green-600' : auth.user.balance < 0 ? 'text-red-600' : 'text-gray-800'}`}>
                  ${auth.user.balance}
                </p>
              </div>
              <div className="text-right">
                <p className="text-sm text-gray-500">Account Status</p>
                <span className={`inline-flex items-center px-3 py-1 rounded-full text-sm font-medium ${
                  auth.user.is_valid
                    ? auth.user.is_low_priority
                      ? 'bg-amber-100 text-amber-800'
                      : 'bg-green-100 text-green-800'
                    : 'bg-red-100 text-red-800'
                }`}>
                  {auth.user.is_valid
                    ? auth.user.is_low_priority ? 'Low Priority' : 'Active'
                    : 'Limited'}
                </span>
              </div>
            </div>
          </div>

          {/* How it works */}
          <div className="mb-8 bg-blue-50 rounded-lg p-4 border border-blue-100">
            <p className="text-sm text-blue-800">
              <span className="font-semibold">How billing works:</span> You are billed per GB of traffic used (pay-as-you-go).
              Simply add funds to your account and start using the service immediately.
              Your balance will be deducted based on actual usage.
            </p>
          </div>

          {/* Payment Methods */}
          <h3 className="text-lg font-semibold text-gray-800 mb-4">Choose a payment method</h3>
          <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
            {renderPaymentCard({
              title: 'Alipay',
              badge: <span className="text-xs bg-blue-400/30 px-2 py-1 rounded">Fast</span>,
              color: 'blue',
              amount: alipayAmount,
              setAmount: setAlipayAmount,
              error: alipayError,
              setError: setAlipayError,
              onSubmit: redirectToAlipayScanPage,
            })}

            {renderPaymentCard({
              title: 'USDT',
              badge: <span className="text-xs bg-green-400/30 px-2 py-1 rounded">Crypto</span>,
              color: 'green',
              amount: usdtAmount,
              setAmount: setUsdtAmount,
              error: usdtError,
              setError: setUsdtError,
              onSubmit: redirectToUSDTPage,
              children: (
                <div className="mb-3 px-2 py-1.5 bg-yellow-50 border border-yellow-200 rounded text-xs text-yellow-700 text-center">
                  Only <span className="font-semibold">Polygon (MATIC)</span> chain is supported
                </div>
              ),
            })}

            {renderPaymentCard({
              title: 'Stripe',
              badge: (
                <div className="flex items-center gap-2">
                  {stripeSandbox && (
                    <span className="text-xs bg-yellow-400 text-yellow-900 font-bold px-2 py-1 rounded">TEST MODE</span>
                  )}
                  <span className="text-xs bg-purple-400/30 px-2 py-1 rounded">Card & more</span>
                </div>
              ),
              color: 'purple',
              amount: stripeAmount,
              setAmount: setStripeAmount,
              error: stripeError,
              setError: setStripeError,
              onSubmit: redirectToStripePage,
            })}

            {renderGithubSection()}
          </div>
        </div>
      </div>
    </AuthenticatedLayout>
  );
}
