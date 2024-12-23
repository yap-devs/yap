import {Head, Link} from '@inertiajs/react';

export default function Welcome({auth, laravelVersion, phpVersion}) {
  return (
    <>
      <Head title="Welcome"/>
      <div
        className="bg-gradient-to-r from-red-100 via-red-300 to-yellow-100 text-white min-h-screen flex flex-col items-center justify-center">
        <div className="max-w-4xl w-full px-6 py-12">
          <header className="flex justify-between items-center py-6">
            <div className="text-3xl font-bold">YAP</div>
            <nav className="space-x-4">
              {auth.user ? (
                <Link href={route('dashboard')} className="text-lg font-semibold hover:underline">
                  Dashboard
                </Link>
              ) : (
                <>
                  <Link href={route('login')} className="text-lg font-semibold hover:underline">
                    Log in
                  </Link>
                  <Link href={route('register')} className="text-lg font-semibold hover:underline">
                    Register
                  </Link>
                </>
              )}
            </nav>
          </header>

          <main className="mt-12 text-center">
            <h1 className="text-5xl font-extrabold mb-6">Welcome to YAP</h1>
            <p className="text-xl mb-8">
              Discover the best features and experience the ultimate performance.
            </p>
            <Link
              href={route('register')}
              className="inline-block bg-white text-purple-600 font-bold py-3 px-6 rounded-full shadow-lg hover:bg-gray-100 transition duration-300"
            >
              Get Started
            </Link>
          </main>

          <section className="mt-16 grid grid-cols-1 md:grid-cols-2 gap-8">
            <div
              className="bg-white bg-opacity-20 border border-gray-200 p-6 rounded-lg shadow-md transform transition duration-300 hover:scale-105 hover:shadow-xl">
              <h2 className="text-2xl font-bold text-gray-800 mb-4">Privacy Focused</h2>
              <p className="text-lg text-gray-600">
                We take your privacy seriously—no intrusive audits, no censorship. Surf the web freely and make the
                internet truly yours. You're in control, not the platform.
              </p>
            </div>

            <div
              className="bg-white bg-opacity-20 border border-gray-200 p-6 rounded-lg shadow-md transform transition duration-300 hover:scale-105 hover:shadow-xl">
              <h2 className="text-2xl font-bold text-gray-800 mb-4">Flexible Pay-As-You-Go</h2>
              <p className="text-lg text-gray-600">
                Only pay for what you use! Our transparent pay-as-you-go model gives you the freedom to manage your
                costs. Want even more savings? Grab discounted data packages—the bigger the pack, the better the deal!
              </p>
            </div>

            <div
              className="bg-white bg-opacity-20 border border-gray-200 p-6 rounded-lg shadow-md transform transition duration-300 hover:scale-105 hover:shadow-xl">
              <h2 className="text-2xl font-bold text-gray-800 mb-4">Premium Nodes</h2>
              <p className="text-lg text-gray-600">
                Experience top-notch stability and lightning-fast speeds with our premium multi-line entry nodes and
                advanced forwarding routes. Smooth and reliable, always.
              </p>
            </div>

            <div
              className="bg-white bg-opacity-20 border border-gray-200 p-6 rounded-lg shadow-md transform transition duration-300 hover:scale-105 hover:shadow-xl">
              <h2 className="text-2xl font-bold text-gray-800 mb-4">Streaming Support</h2>
              <p className="text-lg text-gray-600">
                Stream your favorite shows on platforms like Netflix with ease! Our high-quality residential landing
                nodes ensure seamless and unrestricted streaming wherever you are.
              </p>
            </div>
          </section>

          <footer className="mt-16 text-center text-sm">
            Laravel v{laravelVersion} (PHP v{phpVersion})
          </footer>
        </div>
      </div>
    </>
  );
}
