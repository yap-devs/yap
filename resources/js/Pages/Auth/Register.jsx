import {useEffect, useRef} from 'react';
import GuestLayout from '@/Layouts/GuestLayout';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import {Head, Link, useForm} from '@inertiajs/react';
import {trans} from '@/Utils/i18n';

const TURNSTILE_SCRIPT_ID = 'cloudflare-turnstile-script';

export default function Register({turnstileSiteKey}) {
  const turnstileContainerRef = useRef(null);
  const turnstileWidgetIdRef = useRef(null);
  const {data, setData, post, processing, errors, reset} = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    'cf-turnstile-response': '',
  });

  useEffect(() => {
    let isCancelled = false;

    const renderTurnstile = () => {
      if (
        isCancelled
        || !turnstileSiteKey
        || !window.turnstile
        || !turnstileContainerRef.current
        || turnstileWidgetIdRef.current !== null
      ) {
        return;
      }

      turnstileWidgetIdRef.current = window.turnstile.render(turnstileContainerRef.current, {
        sitekey: turnstileSiteKey,
        action: 'register',
        callback: (token) => setData('cf-turnstile-response', token),
        'expired-callback': () => setData('cf-turnstile-response', ''),
        'error-callback': () => setData('cf-turnstile-response', ''),
      });
    };

    let script = document.getElementById(TURNSTILE_SCRIPT_ID);
    if (!script) {
      script = document.createElement('script');
      script.id = TURNSTILE_SCRIPT_ID;
      script.src = 'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit';
      script.async = true;
      script.defer = true;
      document.head.appendChild(script);
    }

    if (window.turnstile) {
      renderTurnstile();
    } else {
      script.addEventListener('load', renderTurnstile);
    }

    return () => {
      isCancelled = true;
      script.removeEventListener('load', renderTurnstile);

      if (window.turnstile && turnstileWidgetIdRef.current !== null) {
        window.turnstile.remove(turnstileWidgetIdRef.current);
        turnstileWidgetIdRef.current = null;
      }

      reset('password', 'password_confirmation');
    };
  }, [turnstileSiteKey]);

  const submit = (e) => {
    e.preventDefault();

    post(route('register'), {
      onFinish: () => {
        setData('cf-turnstile-response', '');

        if (window.turnstile && turnstileWidgetIdRef.current !== null) {
          window.turnstile.reset(turnstileWidgetIdRef.current);
        }
      },
    });
  };

  return (
    <GuestLayout>
      <Head title={trans('auth.register')}/>

      <form onSubmit={submit}>
        <div>
          <InputLabel htmlFor="name" value={trans('common.name')}/>

          <TextInput
            id="name"
            name="name"
            value={data.name}
            className="mt-1 block w-full"
            autoComplete="name"
            isFocused={true}
            onChange={(e) => setData('name', e.target.value)}
            required
          />

          <InputError message={errors.name} className="mt-2"/>
        </div>

        <div className="mt-4">
          <InputLabel htmlFor="email" value={trans('common.email')}/>

          <TextInput
            id="email"
            type="email"
            name="email"
            value={data.email}
            className="mt-1 block w-full"
            autoComplete="username"
            onChange={(e) => setData('email', e.target.value)}
            required
          />

          <InputError message={errors.email} className="mt-2"/>
        </div>

        <div className="mt-4">
          <InputLabel htmlFor="password" value={trans('common.password')}/>

          <TextInput
            id="password"
            type="password"
            name="password"
            value={data.password}
            className="mt-1 block w-full"
            autoComplete="new-password"
            onChange={(e) => setData('password', e.target.value)}
            required
          />

          <InputError message={errors.password} className="mt-2"/>
        </div>

        <div className="mt-4">
          <InputLabel htmlFor="password_confirmation" value={trans('common.confirm_password')}/>

          <TextInput
            id="password_confirmation"
            type="password"
            name="password_confirmation"
            value={data.password_confirmation}
            className="mt-1 block w-full"
            autoComplete="new-password"
            onChange={(e) => setData('password_confirmation', e.target.value)}
            required
          />

          <InputError message={errors.password_confirmation} className="mt-2"/>
        </div>

        <div
          ref={turnstileContainerRef}
          className="cf-turnstile mt-4"
          data-sitekey={turnstileSiteKey}
          data-action="turnstile-spin-v2"
        />

        <InputError message={errors['cf-turnstile-response']} className="mt-2"/>

        <div className="flex items-center justify-end mt-4">
          <Link
            href={route('login')}
            className="underline text-sm text-gray-600 hover:text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
          >
            {trans('auth.already_registered')}
          </Link>

          <PrimaryButton className="ms-4" disabled={processing || !data['cf-turnstile-response']}>
            {trans('auth.register')}
          </PrimaryButton>
        </div>
      </form>
    </GuestLayout>
  );
}
