// Legacy CEPS tracking IDs. This file is included only in production.
(() => {
  function load(src) {
    const script = document.createElement('script');
    script.async = true;
    script.src = src;
    document.head.append(script);
  }
  window.dataLayer = window.dataLayer || [];
  window.gtag = window.gtag || function () { window.dataLayer.push(arguments); };
  window.gtag('js', new Date());
  window.gtag('config', 'AW-741559107');
  load('https://www.googletagmanager.com/gtag/js?id=AW-741559107');

  if (!window.fbq) {
    const fbq = window.fbq = function () {
      fbq.callMethod ? fbq.callMethod.apply(fbq, arguments) : fbq.queue.push(arguments);
    };
    window._fbq = window._fbq || fbq;
    fbq.push = fbq;
    fbq.loaded = true;
    fbq.version = '2.0';
    fbq.queue = [];
    load('https://connect.facebook.net/en_US/fbevents.js');
  }
  window.fbq('init', '2497887736890880');
  window.fbq('track', 'PageView');

  // Retained at the owner's request; Universal Analytics is discontinued.
  window.GoogleAnalyticsObject = 'ga';
  window.ga = window.ga || function () { (window.ga.q = window.ga.q || []).push(arguments); };
  window.ga.l = +new Date();
  window.ga('create', 'UA-4996045-48', 'ceps.edu.ba');
  window.ga('send', 'pageview');
  load('https://www.google-analytics.com/analytics.js');
})();
