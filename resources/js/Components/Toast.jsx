import {useState, useEffect, useCallback} from 'react';
import {Transition} from '@headlessui/react';

const toastEventTarget = new EventTarget();

export function showToast(message, type = 'error') {
  toastEventTarget.dispatchEvent(
    new CustomEvent('toast', {detail: {message, type, id: Date.now()}})
  );
}

export default function Toast() {
  const [toasts, setToasts] = useState([]);

  const removeToast = useCallback((id) => {
    setToasts((prev) => prev.filter((t) => t.id !== id));
  }, []);

  useEffect(() => {
    const handler = (e) => {
      const toast = e.detail;
      setToasts((prev) => [...prev, toast]);
      setTimeout(() => removeToast(toast.id), 3000);
    };

    toastEventTarget.addEventListener('toast', handler);
    return () => toastEventTarget.removeEventListener('toast', handler);
  }, [removeToast]);

  return (
    <div className="fixed top-4 right-4 z-50 flex flex-col gap-2">
      {toasts.map((toast) => (
        <Transition
          key={toast.id}
          show={true}
          appear={true}
          enter="transition ease-out duration-300"
          enterFrom="opacity-0 translate-y-[-8px]"
          enterTo="opacity-100 translate-y-0"
          leave="transition ease-in duration-200"
          leaveFrom="opacity-100"
          leaveTo="opacity-0"
        >
          <div
            className={`rounded-lg px-4 py-3 shadow-lg text-sm font-medium ${
              toast.type === 'error'
                ? 'bg-red-600 text-white'
                : 'bg-green-600 text-white'
            }`}
          >
            {toast.message}
          </div>
        </Transition>
      ))}
    </div>
  );
}
