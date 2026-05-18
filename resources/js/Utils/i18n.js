export const trans = (key, replacements = {}, fallback = key) => {
  const translations = window.YAP_TRANSLATIONS || {};
  const value = key.split('.').reduce((current, part) => current?.[part], translations) || fallback;

  if (typeof value !== 'string') {
    return value;
  }

  return Object.entries(replacements).reduce(
    (text, [name, replacement]) => text.replaceAll(`:${name}`, replacement),
    value,
  );
};

export const setTranslations = (translations) => {
  window.YAP_TRANSLATIONS = translations || {};
};
