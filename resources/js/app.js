import { createApp, h, watch, defineAsyncComponent } from 'vue';
import { createInertiaApp, router } from '@inertiajs/vue3';
import { createI18n } from 'vue-i18n';
import axios from 'axios';
import { resolveMessageByCandidates } from '@/Utils/i18nLookup';

function applyDocumentLocaleDirection(pageProps = {}) {
  const locale = (pageProps.currentLanguage || 'en').toLowerCase();
  const isRtl = Boolean(pageProps.isRtl) || locale === 'ar';

  document.documentElement.setAttribute('lang', locale);
  document.documentElement.setAttribute('dir', isRtl ? 'rtl' : 'ltr');
  document.body.classList.toggle('direction-rtl', isRtl);
  document.body.classList.toggle('direction-ltr', !isRtl);
}

function normalizeLocale(locale, fallback = 'en') {
  const normalized = String(locale || '').toLowerCase();
  return normalized || fallback;
}

function publicFrontendComponent(component = '') {
  return String(component).startsWith('Frontend/')
    || String(component).startsWith('FrontendPremium/');
}

function extractAvailableLocales(initialPageProps = {}, fallbackLocale = 'en') {
  const localesFromProps = Array.isArray(initialPageProps.languages)
    ? initialPageProps.languages
      .map((language) => normalizeLocale(language?.code, fallbackLocale))
      .filter(Boolean)
    : [];

  return Array.from(new Set([
    fallbackLocale,
    normalizeLocale(initialPageProps.currentLanguage, fallbackLocale),
    ...localesFromProps,
  ]));
}

const AsyncApexChart = defineAsyncComponent(() =>
  import('vue3-apexcharts').then((module) => module.default)
);

const AsyncVueTelInput = defineAsyncComponent(() =>
  import('vue-tel-input').then((module) => module.VueTelInput ?? module.default)
);

function registerDeferredGlobalComponents(app) {
  // Keep public entry light while ensuring charts and phone input still work
  // after navigating into authenticated screens inside the same SPA session.
  app.component('apexchart', AsyncApexChart);
  app.component('vue-tel-input', AsyncVueTelInput);
}

function createMissingTranslationResolver(getLocaleMessages, fallbackLocale = 'en') {
  return (locale, key) => {
    const normalizedLocale = normalizeLocale(locale, fallbackLocale);
    const localeMessages = getLocaleMessages(normalizedLocale);
    const resolvedFromLocale = resolveMessageByCandidates(localeMessages, key);

    if (resolvedFromLocale !== null && resolvedFromLocale !== undefined) {
      return resolvedFromLocale;
    }

    if (normalizedLocale !== fallbackLocale) {
      const fallbackMessages = getLocaleMessages(fallbackLocale);
      const resolvedFromFallback = resolveMessageByCandidates(fallbackMessages, key);

      if (resolvedFromFallback !== null && resolvedFromFallback !== undefined) {
        return resolvedFromFallback;
      }
    }

    return key;
  };
}

// Function to load locale messages via API
async function loadLocaleMessages(locale) {
  const normalizedLocale = normalizeLocale(locale);
  const response = await axios.get(`/translations/${normalizedLocale}`);
  return response.data;
}

createInertiaApp({
  resolve: async (name) => {
    // Define paths to the components
    const pages = import.meta.glob('./Pages/**/*.vue');
    const modulePages = import.meta.glob('../../modules/**/Pages/**/*.vue');
    
    // Check if the name refers to a module component
    const [moduleName, pageName] = name.split('::');
    
    if (pageName) {
      const key = `../../modules/${moduleName}/Pages/${pageName}.vue`;
      const component = modulePages[key];
      
      if (component) {
        const resolvedComponent = await component();
        return resolvedComponent.default || resolvedComponent;
      }
    }
    
    // Otherwise, resolve from the standard Pages directory
    const component = pages[`./Pages/${name}.vue`];
    if (component) {
      const resolvedComponent = await component();
      return resolvedComponent.default || resolvedComponent;
    }
    
    throw new Error(`Page not found: ${name}`);
  },
  setup({ el, App, props, plugin }) {
    applyDocumentLocaleDirection(props?.initialPage?.props || {});

    const boot = async () => {
      const initialPageProps = props?.initialPage?.props || {};
      const currentLocale = normalizeLocale(initialPageProps.currentLanguage, 'en');
      const initialComponent = props?.initialPage?.component || '';
      const isPublicFrontend = publicFrontendComponent(initialComponent);
      const availableLocales = new Set([
        ...extractAvailableLocales(initialPageProps, 'en'),
      ]);

      const messages = {};
      const loadMessagesIntoStore = async (locale) => {
        const normalizedLocale = normalizeLocale(locale);
        if (!availableLocales.has(normalizedLocale) || messages[normalizedLocale]) {
          return;
        }

        try {
          messages[normalizedLocale] = await loadLocaleMessages(normalizedLocale);
        } catch (_error) {
          // Keep boot process resilient if a locale payload is unavailable.
        }
      };

      await loadMessagesIntoStore(currentLocale);

      if (!isPublicFrontend && currentLocale !== 'en') {
        await loadMessagesIntoStore('en');
      }

      const i18n = createI18n({
        previous: false,
        locale: currentLocale,
        fallbackLocale: 'en',
        messages,
        missingWarn: false,
        fallbackWarn: false,
        missing: createMissingTranslationResolver(
          (locale) => i18n.global.getLocaleMessage(normalizeLocale(locale, 'en')),
          'en'
        ),
      });

      const ensureLocaleMessages = async (locale) => {
        const normalizedLocale = normalizeLocale(locale);
        if (!availableLocales.has(normalizedLocale) || i18n.global.availableLocales.includes(normalizedLocale)) {
          return;
        }

        try {
          const localeMessages = await loadLocaleMessages(normalizedLocale);
          i18n.global.setLocaleMessage(normalizedLocale, localeMessages);
        } catch (_error) {
          // Keep UI functional when a locale payload cannot be loaded.
        }
      };

      const app = createApp({ render: () => h(App, props) });

      app.use(plugin);
      registerDeferredGlobalComponents(app);
      app.use(i18n).mount(el);

      router.on('navigate', async (event) => {
        const pageProps = event?.detail?.page?.props || {};
        applyDocumentLocaleDirection(pageProps);

        const nextLocales = extractAvailableLocales(pageProps, 'en');
        nextLocales.forEach((locale) => availableLocales.add(locale));

        const pageLocale = normalizeLocale(pageProps.currentLanguage, i18n.global.locale.value);
        if (i18n.global.locale.value !== pageLocale) {
          i18n.global.locale.value = pageLocale;
        }

        await ensureLocaleMessages(pageLocale);
      });

      watch(
        () => i18n.global.locale.value,
        async (newLocale) => {
          await ensureLocaleMessages(newLocale);
        }
      );
    };

    boot().catch((_error) => {
      // Fallback app boot without external locale payloads.
      const i18n = createI18n({
        previous: false,
        locale: 'en',
        fallbackLocale: 'en',
        messages: {},
        missingWarn: false,
        fallbackWarn: false,
        missing: (_locale, key) => key,
      });

      const app = createApp({ render: () => h(App, props) });

      registerDeferredGlobalComponents(app);
      app.use(plugin)
        .use(i18n)
        .mount(el);

      applyDocumentLocaleDirection(props?.initialPage?.props || {});
    });
  },
  progress: {
    delay: 250,
    color: '#198754',
    includeCSS: true,
    showSpinner: false,
  },
});
