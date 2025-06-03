import {Head, Link} from '@inertiajs/react';
import {useEffect, useState} from 'react';

export default function Welcome({auth, laravelVersion, phpVersion}) {
  const [isLoaded, setIsLoaded] = useState(false);
  const [scrolled, setScrolled] = useState(false);
  const [headlineIndex, setHeadlineIndex] = useState(0);
  const [fadeState, setFadeState] = useState('in'); // 'in' or 'out'

  const headlines = [
    { title: "Ultra-fast, global connectivity", subtitle: "Experience lightning-fast speeds with our optimized network infrastructure and strategic global routing technology." },
    { title: "Complete privacy, maximum anonymity", subtitle: "Zero logs, zero tracking, zero compromise. Your online activities remain completely private and untraceable." },
    { title: "Unrestricted, worldwide access", subtitle: "Bypass geo-restrictions and enjoy seamless streaming with our specialized media acceleration technology." }
  ];

  useEffect(() => {
    setIsLoaded(true);

    const handleScroll = () => {
      setScrolled(window.scrollY > 10);
    };

    window.addEventListener('scroll', handleScroll);

    // Headline rotation with fade effect
    const fadeInterval = setInterval(() => {
      if (fadeState === 'in') {
        setFadeState('out');
        setTimeout(() => {
          setHeadlineIndex((prevIndex) => (prevIndex + 1) % headlines.length);
          setFadeState('in');
        }, 500); // Wait for fade out to complete
      }
    }, 4500); // Total time for each headline (5000ms = 4500ms display + 500ms transition)

    return () => {
      window.removeEventListener('scroll', handleScroll);
      clearInterval(fadeInterval);
    };
  }, [fadeState, headlines.length]);

  // 提取常用的容器类名为常量
  const containerClass = "max-w-[1400px] mx-auto px-4 sm:px-6";
  const gradientTextClass = "bg-clip-text text-transparent bg-gradient-to-r from-[#F48120] to-[#F04E23]";
  const cardBgClass = "bg-gradient-to-b from-white/[0.07] to-white/[0.03] backdrop-blur-sm rounded-xl overflow-hidden border border-white/5";

  return (
    <>
      <Head title="Welcome"/>
      <div className="bg-[#0B1120] text-white min-h-screen flex flex-col">
        {/* Animated background elements - Cloudflare style */}
        <div className="fixed inset-0 overflow-hidden pointer-events-none">
          {/* Primary background gradient */}
          <div className="absolute inset-0 bg-gradient-to-b from-[#0B1120] via-[#0F1631] to-[#0B1120]"></div>

          {/* Animated orbs */}
          <div className="absolute top-0 left-1/4 w-[800px] h-[800px] bg-[#F48120] rounded-full mix-blend-soft-light opacity-[0.12] blur-[160px] animate-pulse"></div>
          <div className="absolute -top-40 right-0 w-[600px] h-[600px] bg-[#6D4AFF] rounded-full mix-blend-soft-light opacity-[0.12] blur-[120px] animate-pulse"></div>
          <div className="absolute bottom-0 right-1/4 w-[700px] h-[700px] bg-[#0051FF] rounded-full mix-blend-soft-light opacity-[0.12] blur-[140px] animate-pulse"></div>

          {/* Subtle grid overlay */}
          <div className="absolute inset-0 bg-[url('data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNjAiIGhlaWdodD0iNjAiIHZpZXdCb3g9IjAgMCA2MCA2MCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48ZyBmaWxsPSJub25lIiBmaWxsLXJ1bGU9ImV2ZW5vZGQiPjxwYXRoIGZpbGw9IiMxQTFBMUEiIGZpbGwtb3BhY2l0eT0iMC4wMiIgZD0iTTAgMGg2MHY2MEgweiIvPjxwYXRoIGQ9Ik02MCAwdjYwSDBWMGg2MHpNNTkgMUgxdjU4aDU4VjF6IiBmaWxsPSIjZmZmZmZmIiBmaWxsLW9wYWNpdHk9IjAuMDMiLz48L2c+PC9zdmc+')] opacity-[0.15]"></div>
        </div>

        {/* Header - Cloudflare style */}
        <header className={`sticky top-0 z-50 transition-all duration-300 ${scrolled ? 'bg-[#0B1120]/90 backdrop-blur-xl shadow-lg shadow-black/5' : 'bg-transparent'} ${isLoaded ? 'opacity-100' : 'opacity-0'}`}>
          <div className={containerClass + " py-4 flex justify-between items-center"}>
            <div className="flex items-center">
              <div className={`text-2xl font-bold ${gradientTextClass}`}>YAP</div>
            </div>
            <nav className="flex items-center">
              {auth.user ? (
                <Link
                  href={route('dashboard')}
                  className="text-sm font-medium text-white/80 hover:text-white transition-colors duration-200"
                >
                  Dashboard
                </Link>
              ) : (
                <div className="flex items-center">
                  <Link
                    href={route('login')}
                    className="text-sm font-medium text-white/80 hover:text-white transition-colors duration-200 mr-6"
                  >
                    Log in
                  </Link>
                  <Link
                    href={route('register')}
                    className="text-sm font-medium px-4 py-2 rounded-md bg-white text-[#0B1120] hover:bg-white/90 transition-colors duration-200 flex items-center justify-center"
                  >
                    Sign up
                  </Link>
                </div>
              )}
            </nav>
          </div>
        </header>

        <main className="flex-grow relative z-10">
          {/* Hero Section - Cloudflare style with rotating headlines */}
          <section className={`relative pt-20 md:pt-32 pb-24 md:pb-40 transition-all duration-700 ${isLoaded ? 'opacity-100 translate-y-0' : 'opacity-0 translate-y-10'}`}>
            <div className={containerClass}>
              <div className="flex flex-col lg:flex-row items-center justify-between">
                <div className="max-w-3xl lg:max-w-2xl min-h-[280px]">
                  <div className={`transition-all duration-500 ${fadeState === 'in' ? 'opacity-100 transform translate-y-0' : 'opacity-0 transform -translate-y-4'}`}>
                    <h1 className="text-4xl sm:text-5xl md:text-6xl lg:text-7xl font-extrabold mb-6 md:mb-8 leading-[1.1]">
                      {headlines[headlineIndex].title.split(',').map((part, i) =>
                        i === 0 ?
                          <span key={i}>{part},<span className={gradientTextClass}> </span></span> :
                          <span key={i} className={gradientTextClass}>{part}</span>
                      )}
                    </h1>
                    <p className="text-lg md:text-xl text-white/80 max-w-2xl mb-8 md:mb-12 leading-relaxed">
                      {headlines[headlineIndex].subtitle}
                    </p>
                  </div>
                  <div className="flex flex-wrap gap-4">
                    <Link
                      href={route('register')}
                      className="inline-flex items-center px-5 py-3 rounded-md bg-gradient-to-r from-[#F48120] to-[#F04E23] text-white font-medium hover:shadow-lg hover:shadow-[#F48120]/20 transition-all duration-300"
                    >
                      <span className="inline-block">Get Started</span>
                      <svg xmlns="http://www.w3.org/2000/svg" className="h-5 w-5 ml-2 inline-block" viewBox="0 0 20 20" fill="currentColor">
                        <path fillRule="evenodd" d="M10.293 5.293a1 1 0 011.414 0l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414-1.414L12.586 11H5a1 1 0 110-2h7.586l-2.293-2.293a1 1 0 010-1.414z" clipRule="evenodd" />
                      </svg>
                    </Link>
                  </div>
                </div>

                {/* Hero illustration - changes based on current headline */}
                <div className="hidden lg:block w-full max-w-xl mt-12 lg:mt-0">
                  <div className={`transition-all duration-500 ${fadeState === 'in' ? 'opacity-100 transform scale-100' : 'opacity-0 transform scale-95'}`}>
                    {headlineIndex === 0 && (
                      <div className="relative flex items-center justify-center">
                        <div className="absolute inset-0 bg-gradient-to-r from-[#F48120]/20 to-[#F04E23]/20 rounded-full blur-3xl animate-pulse"></div>
                        <svg xmlns="http://www.w3.org/2000/svg" className="h-72 w-72 text-gradient-to-r from-[#F48120] to-[#F04E23] animate-float" viewBox="0 0 20 20" fill="currentColor">
                          <defs>
                            <linearGradient id="gradient1" x1="0%" y1="0%" x2="100%" y2="0%">
                              <stop offset="0%" stopColor="#F48120" />
                              <stop offset="100%" stopColor="#F04E23" />
                            </linearGradient>
                          </defs>
                          <path fillRule="evenodd" fill="url(#gradient1)" d="M5.05 3.636a1 1 0 010 1.414 7 7 0 000 9.9 1 1 0 11-1.414 1.414 9 9 0 010-12.728 1 1 0 011.414 0zm9.9 0a1 1 0 011.414 0 9 9 0 010 12.728 1 1 0 11-1.414-1.414 7 7 0 000-9.9 1 1 0 010-1.414zM7.879 6.464a1 1 0 010 1.414 3 3 0 000 4.243 1 1 0 11-1.415 1.414 5 5 0 010-7.07 1 1 0 011.415 0zm4.242 0a1 1 0 011.415 0 5 5 0 010 7.072 1 1 0 01-1.415-1.415 3 3 0 000-4.242 1 1 0 010-1.415z" clipRule="evenodd" />
                        </svg>
                      </div>
                    )}
                    {headlineIndex === 1 && (
                      <div className="relative flex items-center justify-center">
                        <div className="absolute inset-0 bg-gradient-to-r from-[#F48120]/20 to-[#F04E23]/20 rounded-full blur-3xl animate-pulse"></div>
                        <svg xmlns="http://www.w3.org/2000/svg" className="h-72 w-72 text-gradient-to-r from-[#F48120] to-[#F04E23] animate-float" viewBox="0 0 20 20" fill="currentColor">
                          <defs>
                            <linearGradient id="gradient2" x1="0%" y1="0%" x2="100%" y2="0%">
                              <stop offset="0%" stopColor="#F48120" />
                              <stop offset="100%" stopColor="#F04E23" />
                            </linearGradient>
                          </defs>
                          <path fillRule="evenodd" fill="url(#gradient2)" d="M2.166 4.999A11.954 11.954 0 0010 1.944 11.954 11.954 0 0017.834 5c.11.65.166 1.32.166 2.001 0 5.225-3.34 9.67-8 11.317C5.34 16.67 2 12.225 2 7c0-.682.057-1.35.166-2.001zm11.541 3.708a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clipRule="evenodd" />
                        </svg>
                      </div>
                    )}
                    {headlineIndex === 2 && (
                      <div className="relative flex items-center justify-center">
                        <div className="absolute inset-0 bg-gradient-to-r from-[#F48120]/20 to-[#F04E23]/20 rounded-full blur-3xl animate-pulse"></div>
                        <svg xmlns="http://www.w3.org/2000/svg" className="h-72 w-72 text-gradient-to-r from-[#F48120] to-[#F04E23] animate-float" viewBox="0 0 20 20" fill="currentColor">
                          <defs>
                            <linearGradient id="gradient3" x1="0%" y1="0%" x2="100%" y2="0%">
                              <stop offset="0%" stopColor="#F48120" />
                              <stop offset="100%" stopColor="#F04E23" />
                            </linearGradient>
                          </defs>
                          <path fillRule="evenodd" fill="url(#gradient3)" d="M10 18a8 8 0 100-16 8 8 0 000 16zM9.555 7.168A1 1 0 008 8v4a1 1 0 001.555.832l3-2a1 1 0 000-1.664l-3-2z" clipRule="evenodd" />
                        </svg>
                      </div>
                    )}
                  </div>
                </div>
              </div>

              {/* Hero decoration - increased visibility */}
              <div className="absolute right-0 top-1/2 -translate-y-1/2 w-1/3 h-96 bg-gradient-to-r from-transparent to-[#F48120]/20 blur-3xl rounded-l-full hidden lg:block"></div>
            </div>
          </section>

          {/* Features Section - Cloudflare style */}
          <section className="py-20 md:py-32 relative">
            <div className="absolute inset-0 bg-[#0F1631]/50 pointer-events-none"></div>
            <div className={containerClass + " relative"}>
              <div className="flex flex-col md:flex-row md:items-center md:justify-between mb-12 md:mb-20">
                <h2 className="text-3xl md:text-4xl font-bold mb-6 md:mb-0 max-w-lg">
                  Professional
                  <span className={gradientTextClass}> network acceleration </span>
                </h2>
                <p className="text-base md:text-lg text-white/70 max-w-lg">
                  Our service uses advanced tunneling technology and smart routing systems to provide stable, high-speed, and secure global network access.
                </p>
              </div>
              <div className="grid grid-cols-1 md:grid-cols-2 gap-6 md:gap-8">
                {[
                  {
                    title: "Complete Privacy Protection",
                    description: "Zero logs, zero auditing, and zero tracking. Our service is designed with privacy as the top priority, ensuring your online activities remain completely anonymous and untraceable.",
                    icon: (
                      <svg xmlns="http://www.w3.org/2000/svg" className="h-8 w-8" viewBox="0 0 20 20" fill="currentColor">
                        <path fillRule="evenodd" d="M2.166 4.999A11.954 11.954 0 0010 1.944 11.954 11.954 0 0017.834 5c.11.65.166 1.32.166 2.001 0 5.225-3.34 9.67-8 11.317C5.34 16.67 2 12.225 2 7c0-.682.057-1.35.166-2.001zm11.541 3.708a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clipRule="evenodd" />
                      </svg>
                    )
                  },
                  {
                    title: "Flexible Pricing",
                    description: "Choose from various subscription plans and traffic-based billing models. Pay only for what you use with no wasted resources. Economical and value for money.",
                    icon: (
                      <svg xmlns="http://www.w3.org/2000/svg" className="h-8 w-8" viewBox="0 0 20 20" fill="currentColor">
                        <path d="M8.433 7.418c.155-.103.346-.196.567-.267v1.698a2.305 2.305 0 01-.567-.267C8.07 8.34 8 8.114 8 8c0-.114.07-.34.433-.582zM11 12.849v-1.698c.22.071.412.164.567.267.364.243.433.468.433.582 0 .114-.07.34-.433.582a2.305 2.305 0 01-.567.267z" />
                        <path fillRule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-13a1 1 0 10-2 0v.092a4.535 4.535 0 00-1.676.662C6.602 6.234 6 7.009 6 8c0 .99.602 1.765 1.324 2.246.48.32 1.054.545 1.676.662v1.941c-.391-.127-.68-.317-.843-.504a1 1 0 10-1.51 1.31c.562.649 1.413 1.076 2.353 1.253V15a1 1 0 102 0v-.092a4.535 4.535 0 001.676-.662C13.398 13.766 14 12.991 14 12c0-.99-.602-1.765-1.324-2.246A4.535 4.535 0 0011 9.092V7.151c.391.127.68.317.843.504a1 1 0 101.511-1.31c-.563-.649-1.413-1.076-2.354-1.253V5z" clipRule="evenodd" />
                      </svg>
                    )
                  },
                  {
                    title: "Smart Routing",
                    description: "Employs intelligent routing technology: direct connection for domestic sites, acceleration for international sites. Faster access speeds and lower latency optimize your browsing experience.",
                    icon: (
                      <svg xmlns="http://www.w3.org/2000/svg" className="h-8 w-8" viewBox="0 0 20 20" fill="currentColor">
                        <path fillRule="evenodd" d="M5 2a1 1 0 011 1v1h1a1 1 0 010 2H6v1a1 1 0 01-2 0V6H3a1 1 0 010-2h1V3a1 1 0 011-1zm0 10a1 1 0 011 1v1h1a1 1 0 110 2H6v1a1 1 0 11-2 0v-1H3a1 1 0 110-2h1v-1a1 1 0 011-1zM12 2a1 1 0 01.967.744L14.146 7.2 17.5 9.134a1 1 0 010 1.732l-3.354 1.935-1.18 4.455a1 1 0 01-1.933 0L9.854 12.8 6.5 10.866a1 1 0 010-1.732l3.354-1.935 1.18-4.455A1 1 0 0112 2z" clipRule="evenodd" />
                      </svg>
                    )
                  },
                  {
                    title: "Streaming Media Unlock",
                    description: "Dedicated nodes unlock Netflix, Disney+, HBO, Hulu and other streaming services. Watch HD videos without buffering and enjoy global content without restrictions.",
                    icon: (
                      <svg xmlns="http://www.w3.org/2000/svg" className="h-8 w-8" viewBox="0 0 20 20" fill="currentColor">
                        <path fillRule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM9.555 7.168A1 1 0 008 8v4a1 1 0 001.555.832l3-2a1 1 0 000-1.664l-3-2z" clipRule="evenodd" />
                      </svg>
                    )
                  }
                ].map((feature, index) => (
                  <div
                    key={index}
                    className={`group relative ${cardBgClass} transition-all duration-300 hover:-translate-y-1 ${isLoaded ? 'opacity-100 translate-y-0' : 'opacity-0 translate-y-10'}`}
                    style={{ transitionDelay: `${index * 100}ms` }}
                  >
                    <div className="absolute inset-0 bg-gradient-to-r from-[#F48120]/5 to-[#F04E23]/5 opacity-0 group-hover:opacity-100 transition-opacity duration-300"></div>
                    <div className="relative p-6 md:p-8">
                      <div className="w-12 h-12 rounded-lg flex items-center justify-center mb-5 md:mb-6 bg-gradient-to-r from-[#F48120] to-[#F04E23] text-white shadow-lg shadow-[#F48120]/10">
                        {feature.icon}
                      </div>
                      <h3 className="text-xl font-bold mb-3 md:mb-4 text-white">{feature.title}</h3>
                      <p className="text-white/70 leading-relaxed text-sm md:text-base">{feature.description}</p>
                    </div>
                  </div>
                ))}
              </div>
            </div>
          </section>

          {/* Stats Section - Cloudflare style */}
          <section className="py-20 md:py-32 relative overflow-hidden">
            <div className="absolute inset-0 bg-gradient-to-b from-[#0B1120] to-[#0F1631]/30 pointer-events-none"></div>
            <div className={containerClass + " relative"}>
              <div className="text-center mb-16 md:mb-20">
                <h2 className="text-3xl md:text-4xl font-bold mb-4 md:mb-6">
                  Premium
                  <span className={gradientTextClass}> service quality </span>
                </h2>
                <p className="text-base md:text-lg text-white/70 max-w-2xl mx-auto">
                  We are committed to providing stable, high-speed, and secure network acceleration services for seamless global internet access.
                </p>
              </div>
              <div className="grid grid-cols-1 md:grid-cols-3 gap-8 md:gap-12">
                {[
                  { value: "99.9%", label: "Uptime", description: "Stable and reliable service guarantee" },
                  { value: "24/7", label: "Support", description: "Professional technical team available" },
                  { value: "10 Gbps", label: "Network Capacity", description: "High-bandwidth backbone connections" }
                ].map((stat, index) => (
                  <div
                    key={index}
                    className={`text-center transition-all duration-500 ${isLoaded ? 'opacity-100 translate-y-0' : 'opacity-0 translate-y-10'}`}
                    style={{ transitionDelay: `${index * 150}ms` }}
                  >
                    <div className={`text-4xl md:text-5xl font-bold ${gradientTextClass} mb-3 md:mb-4`}>{stat.value}</div>
                    <div className="text-lg md:text-xl font-semibold text-white mb-2">{stat.label}</div>
                    <div className="text-sm md:text-base text-white/70">{stat.description}</div>
                  </div>
                ))}
              </div>
            </div>
          </section>

          {/* CTA Section - Cloudflare style */}
          <section className="py-16 md:py-20 relative">
            <div className="absolute inset-0 bg-gradient-to-r from-[#0F1631] to-[#0B1120] pointer-events-none"></div>
            <div className={containerClass + " relative"}>
              <div className={`${cardBgClass} p-6 md:p-12`}>
                <div className="max-w-3xl mx-auto text-center">
                  <h2 className="text-2xl md:text-3xl font-bold mb-4 md:mb-6">Ready for seamless global connectivity?</h2>
                  <p className="text-base md:text-lg text-white/70 mb-6 md:mb-8">
                    Join thousands of users enjoying high-speed, stable global network access services.
                  </p>
                  <Link
                    href={route('register')}
                    className="inline-flex items-center px-5 py-3 rounded-md bg-gradient-to-r from-[#F48120] to-[#F04E23] text-white font-medium hover:shadow-lg hover:shadow-[#F48120]/20 transition-all duration-300"
                  >
                    <span className="inline-block">Sign Up Now</span>
                  </Link>
                </div>
              </div>
            </div>
          </section>
        </main>

        <footer className="border-t border-white/5 py-8 md:py-12 bg-[#0B1120]/80 backdrop-blur-sm relative z-10">
          <div className={containerClass}>
            <div className="flex flex-col md:flex-row justify-between items-center">
              <div className="text-white/50 text-sm mb-4 md:mb-0">
                Laravel v{laravelVersion} (PHP v{phpVersion})
              </div>
              <div className="flex space-x-6">
                <a href="https://t.me/yap_devs" className="text-white/50 hover:text-white transition-colors" target="_blank">
                  <span className="sr-only">Telegram</span>
                  <svg className="h-5 w-5" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M11.944 0A12 12 0 0 0 0 12a12 12 0 0 0 12 12 12 12 0 0 0 12-12A12 12 0 0 0 12 0a12 12 0 0 0-.056 0zm4.962 7.224c.1-.002.321.023.346.14a.506.506 0 0 1 .171.325c.016.093.036.306.02.472-.18 1.898-.96 6.502-1.36 8.627-.168.9-.499 1.201-.82 1.23-.696.065-1.225-.46-1.9-.902-1.056-.693-1.653-1.124-2.678-1.8-1.185-.78-.417-1.21.258-1.91.177-.184 3.247-2.977 3.307-3.23.007-.032.014-.15-.056-.212s-.174-.041-.249-.024c-.106.024-1.793 1.14-5.061 3.345-.48.33-.913.49-1.302.48-.428-.008-1.252-.241-1.865-.44-.752-.245-1.349-.374-1.297-.789.027-.216.325-.437.893-.663 3.498-1.524 5.83-2.529 6.998-3.014 3.332-1.386 4.025-1.627 4.476-1.635z"/>
                  </svg>
                </a>
                <a href="https://github.com/yap-devs/yap" className="text-white/50 hover:text-white transition-colors" target="_blank">
                  <span className="sr-only">GitHub</span>
                  <svg className="h-5 w-5" fill="currentColor" viewBox="0 0 24 24">
                    <path fillRule="evenodd" d="M12 2C6.477 2 2 6.484 2 12.017c0 4.425 2.865 8.18 6.839 9.504.5.092.682-.217.682-.483 0-.237-.008-.868-.013-1.703-2.782.605-3.369-1.343-3.369-1.343-.454-1.158-1.11-1.466-1.11-1.466-.908-.62.069-.608.069-.608 1.003.07 1.531 1.032 1.531 1.032.892 1.53 2.341 1.088 2.91.832.092-.647.35-1.088.636-1.338-2.22-.253-4.555-1.113-4.555-4.951 0-1.093.39-1.988 1.029-2.688-.103-.253-.446-1.272.098-2.65 0 0 .84-.27 2.75 1.026A9.564 9.564 0 0112 6.844c.85.004 1.705.115 2.504.337 1.909-1.296 2.747-1.027 2.747-1.027.546 1.379.202 2.398.1 2.651.64.7 1.028 1.595 1.028 2.688 0 3.848-2.339 4.695-4.566 4.943.359.309.678.92.678 1.855 0 1.338-.012 2.419-.012 2.747 0 .268.18.58.688.482A10.019 10.019 0 0022 12.017C22 6.484 17.522 2 12 2z" clipRule="evenodd" />
                  </svg>
                </a>
              </div>
            </div>
          </div>
        </footer>
      </div>
    </>
  );
}
