(function () {
  const payload = window.RASTRO_I18N || {};
  const state = {
    lang: payload.current || 'pt',
    fallback: payload.fallback || 'pt',
    translations: payload.translations || {}
  };

  function translate(key, vars) {
    const currentDict = state.translations[state.lang] || {};
    const fallbackDict = state.translations[state.fallback] || {};
    let value = currentDict[key];
    if (value == null) {
      value = fallbackDict[key];
    }
    if (value == null) {
      return key;
    }
    if (!vars) {
      return value;
    }
    return value.replace(/\{\{(.*?)\}\}/g, function (_, name) {
      if (!Object.prototype.hasOwnProperty.call(vars, name)) {
        return '';
      }
      return String(vars[name]);
    });
  }

  const endpoint = typeof window.RASTRO_SET_LANGUAGE_URL === 'string'
    ? window.RASTRO_SET_LANGUAGE_URL
    : 'set_language.php';

  function changeLanguage(lang) {
    if (!lang || lang === state.lang) {
      return Promise.resolve();
    }
    return fetch(endpoint, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      credentials: 'same-origin',
      body: 'lang=' + encodeURIComponent(lang)
    }).then(res => {
      if (!res.ok) {
        throw new Error('HTTP ' + res.status);
      }
      state.lang = lang;
      return res.json();
    });
  }

  function bindLanguageSelectors() {
    const selects = document.querySelectorAll('[data-language-select]');
    selects.forEach(select => {
      if (!(select instanceof HTMLSelectElement)) return;
      if (!select.dataset.languageInit) {
        select.value = state.lang;
        select.dataset.languageInit = '1';
      }
      select.addEventListener('change', (evt) => {
        const value = evt.target.value;
        changeLanguage(value)
          .then(() => window.location.reload())
          .catch(err => console.error('Failed to change language', err));
      });
    });
  }

  document.addEventListener('DOMContentLoaded', bindLanguageSelectors);

  window.RastroI18n = {
    get lang() {
      return state.lang;
    },
    get fallback() {
      return state.fallback;
    },
    t: translate,
    changeLanguage
  };

  window.rastroT = translate;
})();
