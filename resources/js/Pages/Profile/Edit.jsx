import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import DeleteUserForm from './Partials/DeleteUserForm';
import UpdatePasswordForm from './Partials/UpdatePasswordForm';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm';
import {Head} from '@inertiajs/react';

export default function Edit({auth, mustVerifyEmail, status, githubSponsorURL}) {
  const redirectToGithub = () => {
    window.location.href = route('github.redirect');
  };
  const redirectToCharge = () => {
    window.open(githubSponsorURL, '_blank');
  }

  const renderGithubButton = () => {
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
            <button className={`ml-auto bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded`}
                    onClick={redirectToCharge}>
              Sponsor on Github to {auth.user.is_valid ? 'support' : 'upgrade'}!
            </button>
          </div>
        </div>
      );
    }

    return (
      <button
        onClick={redirectToGithub}
        className="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-gray-600 hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500"
      >
        Bind Github Account
      </button>
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
              {renderGithubButton()}
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
