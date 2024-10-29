import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import DeleteUserForm from './Partials/DeleteUserForm';
import UpdatePasswordForm from './Partials/UpdatePasswordForm';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm';
import {Head, router, usePage} from '@inertiajs/react';
import {useState} from 'react';

export default function Edit({auth, mustVerifyEmail, status, githubSponsorURL}) {
  const { errors } = usePage().props;

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

  const [githubAmount, setGithubAmount] = useState(5);
  const [alipayAmount, setAlipayAmount] = useState(5);

  const sponsorAmountChange = (e, setFunc) => {
    const val = e.target.value;

    if (val === '') return setFunc(val);
    if (val < 2) return;
    if (!/^\d+$/.test(val)) return;

    setFunc(val);
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
                     placeholder="5" value={githubAmount} onChange={(e) => sponsorAmountChange(e, setGithubAmount)}/>
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
    return (
      <div className="rounded-lg shadow-lg overflow-hidden max-w-xs my-3">
        {
          errors.amount && (
            <div className="bg-red-500 text-white py-2 px-4">
              <p className="font-bold text-xl text-center">{errors.amount}</p>
            </div>
          )
        }
        <div className="p-4 bg-gray-50">
          <div className="relative">
            <input
              type="number"
              className="w-full py-2 pl-6 pr-20 rounded-lg border-2 placeholder-gray-500 text-gray-700 focus:outline-none focus:ring-2 focus:ring-purple-600"
              placeholder="Amount" value={alipayAmount} onChange={(e) => sponsorAmountChange(e, setAlipayAmount)}
              min={5}
            />
            <span className="absolute inset-y-0 left-0 flex items-center pl-2">
            <span className="text-gray-500">$</span>
          </span>
            <button
              className="absolute inset-y-0 right-0 flex items-center px-4 font-bold text-white bg-purple-600 rounded-r-lg hover:bg-purple-700 focus:outline-none focus:ring-2 focus:ring-purple-500"
              type="button"
              onClick={redirectToAlipayScanPage}
            >
              Charge Now
            </button>
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
            errors.message &&
            <div className="p-4 sm:p-8 bg-red-600 bg-opacity-10 text-red-600 rounded-lg">
              <div className="flex items-center">
                <svg className="h-6 w-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                     xmlns="http://www.w3.org/2000/svg">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2"
                        d="M6 18L18 6M6 6l12 12"/>
                </svg>
                <span className="ml-2">{errors.message}</span>
              </div>
            </div>
          }

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
            <header>
              <h2 className="text-lg font-medium text-gray-900">
                Alipay
              </h2>

              <p className="mt-1 text-sm text-gray-600">
                Charge your account with Alipay.
              </p>
            </header>
            <div className="pt-4">
              {renderAlipayBlock()}
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
