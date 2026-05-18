import GuestLayout from '@/Layouts/GuestLayout';
import PrimaryButton from '@/Components/PrimaryButton';
import {Head, Link, useForm} from '@inertiajs/react';
import {trans} from '@/Utils/i18n';

export default function VerifyEmail({status}) {
  const {post, processing} = useForm({});

  const submit = (e) => {
    e.preventDefault();

    post(route('verification.send'));
  };

  return (
    <GuestLayout>
      <Head title={trans('auth.email_verification')}/>

      <div className="mb-4 text-sm text-gray-600">
        {trans('auth.email_verification_body')}
      </div>

      {status === 'verification-link-sent' && (
        <div className="mb-4 font-medium text-sm text-green-600">
          {trans('auth.verification_link_sent')}
        </div>
      )}

      <form onSubmit={submit}>
        <div className="mt-4 flex items-center justify-between">
          <PrimaryButton disabled={processing}>{trans('auth.resend_verification_email')}</PrimaryButton>

          <Link
            href={route('logout')}
            method="post"
            as="button"
            className="underline text-sm text-gray-600 hover:text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
          >
            {trans('nav.log_out')}
          </Link>
        </div>
      </form>
    </GuestLayout>
  );
}
