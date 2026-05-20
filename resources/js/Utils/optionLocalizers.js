const COUNTRY_NAME_TO_REGION_ALIAS = Object.freeze({
  "Antigua and Barbuda": "AG",
  "Bosnia and Herzegovina": "BA",
  "Brunei Darussalam": "BN",
  "Cabo Verde": "CV",
  Congo: "CG",
  "Congo (Democratic Republic of the)": "CD",
  "Czech Republic": "CZ",
  "Korea (Democratic People's Republic of)": "KP",
  "Korea (Republic of)": "KR",
  "Lao People's Democratic Republic": "LA",
  "Russian Federation": "RU",
  "Saint Kitts and Nevis": "KN",
  "Saint Lucia": "LC",
  "Saint Vincent and the Grenadines": "VC",
  "Sao Tome and Principe": "ST",
  "Syrian Arab Republic": "SY",
  "Trinidad and Tobago": "TT",
  Turkey: "TR",
  "United States of America": "US",
  "Viet Nam": "VN",
});

const ENGLISH_REGION_DISPLAY = createDisplayNames("en", "region");
const ARABIC_REGION_DISPLAY = createDisplayNames("ar", "region");
const ARABIC_LANGUAGE_DISPLAY = createDisplayNames("ar", "language");
const ENGLISH_LANGUAGE_DISPLAY = createDisplayNames("en", "language");
const ARABIC_CURRENCY_DISPLAY = createDisplayNames("ar", "currency");
const ENGLISH_CURRENCY_DISPLAY = createDisplayNames("en", "currency");

const REGION_CODE_BY_ENGLISH_NAME = buildRegionCodeIndex();
const TIMEZONE_OFFSET_CACHE = new Map();
const ARABIC_TIMEZONE_LABELS_BY_VALUE = Object.freeze({
  "Asia/Riyadh": "آسيا/الرياض",
});

export function localizeTimezoneOptions(options = [], locale = "en") {
  const isArabic = isArabicLocale(locale);

  return options.map((option) => {
    const value = asString(option?.value || option?.label);
    const rawLabel = asString(option?.rawLabel || option?.label || value);
    const localizedRawLabel = isArabic
      ? localizeArabicTimezoneLabel(value, rawLabel)
      : rawLabel;
    const offsetLabel = getTimezoneOffsetLabel(value);
    const label = `${offsetLabel} — ${localizedRawLabel}`;

    return {
      ...option,
      rawLabel,
      label,
      searchText: [rawLabel, localizedRawLabel, offsetLabel, value].filter(Boolean).join(" "),
    };
  });
}

export function localizeTemplateLanguageOptions(options = [], locale = "en") {
  const isArabic = isArabicLocale(locale);

  return options.map((option) => {
    const value = asString(option?.value);
    const rawLabel = asString(option?.rawLabel || option?.label || value);
    const arabicLabel = buildArabicLanguageLabel(value);
    const englishLabel = buildEnglishLanguageLabel(value);

    const label = isArabic && arabicLabel ? `${rawLabel} — ${arabicLabel}` : rawLabel;

    return {
      ...option,
      rawLabel,
      label,
      searchText: [rawLabel, arabicLabel, englishLabel, value].filter(Boolean).join(" "),
    };
  });
}

export function localizeCountryOptions(options = [], locale = "en") {
  const isArabic = isArabicLocale(locale);

  return options.map((option) => {
    const rawLabel = asString(option?.rawLabel || option?.label || option?.value);
    const regionCode = resolveCountryRegionCode(rawLabel);
    const localizedLabel = isArabic ? buildArabicCountryLabel(regionCode, rawLabel) : rawLabel;

    return {
      ...option,
      rawLabel,
      label: localizedLabel,
      searchText: [rawLabel, localizedLabel, regionCode].filter(Boolean).join(" "),
    };
  });
}

export function localizeCurrencyOptions(options = [], locale = "en") {
  const isArabic = isArabicLocale(locale);
  const currencyDisplay = isArabic ? ARABIC_CURRENCY_DISPLAY : ENGLISH_CURRENCY_DISPLAY;

  return options.map((option) => {
    const currencyCode = asString(option?.value).toUpperCase();
    const rawLabel = normalizeCurrencyRawLabel(asString(option?.rawLabel || option?.label || currencyCode));
    const localizedByCode = buildCurrencyLabel(currencyDisplay, currencyCode);
    const localizedLabel = localizedByCode || rawLabel;

    return {
      ...option,
      rawLabel,
      label: localizedLabel,
      searchText: [rawLabel, localizedLabel, currencyCode].filter(Boolean).join(" "),
    };
  });
}

function createDisplayNames(locale, type) {
  try {
    return new Intl.DisplayNames([locale], { type });
  } catch (_error) {
    return null;
  }
}

function buildRegionCodeIndex() {
  const index = new Map();

  if (ENGLISH_REGION_DISPLAY) {
    for (let first = 65; first <= 90; first += 1) {
      for (let second = 65; second <= 90; second += 1) {
        const code = `${String.fromCharCode(first)}${String.fromCharCode(second)}`;
        const name = ENGLISH_REGION_DISPLAY.of(code);
        if (!name || name === code || name === "Unknown Region") {
          continue;
        }

        index.set(normalizeLookup(name), code);
      }
    }
  }

  for (const [name, code] of Object.entries(COUNTRY_NAME_TO_REGION_ALIAS)) {
    index.set(normalizeLookup(name), code);
  }

  return index;
}

function resolveCountryRegionCode(countryName) {
  if (!countryName) {
    return null;
  }

  const normalized = normalizeLookup(countryName);
  return REGION_CODE_BY_ENGLISH_NAME.get(normalized) || null;
}

function buildArabicCountryLabel(regionCode, fallbackLabel) {
  if (!regionCode || !ARABIC_REGION_DISPLAY) {
    return fallbackLabel;
  }

  const translated = ARABIC_REGION_DISPLAY.of(regionCode);
  if (!translated || translated === regionCode || translated === "Unknown Region") {
    return fallbackLabel;
  }

  return translated;
}

function buildArabicLanguageLabel(languageValue) {
  const parsed = parseLanguageCode(languageValue);
  if (!parsed || !ARABIC_LANGUAGE_DISPLAY) {
    return null;
  }

  const languageLabel = ARABIC_LANGUAGE_DISPLAY.of(parsed.language);
  if (!languageLabel || languageLabel === parsed.language) {
    return null;
  }

  if (parsed.region && ARABIC_REGION_DISPLAY) {
    const regionLabel = ARABIC_REGION_DISPLAY.of(parsed.region);
    if (regionLabel && regionLabel !== parsed.region && regionLabel !== "Unknown Region") {
      return `${languageLabel} (${regionLabel})`;
    }
  }

  return languageLabel;
}

function buildEnglishLanguageLabel(languageValue) {
  const parsed = parseLanguageCode(languageValue);
  if (!parsed || !ENGLISH_LANGUAGE_DISPLAY) {
    return null;
  }

  const languageLabel = ENGLISH_LANGUAGE_DISPLAY.of(parsed.language);
  if (!languageLabel || languageLabel === parsed.language) {
    return null;
  }

  if (parsed.region && ENGLISH_REGION_DISPLAY) {
    const regionLabel = ENGLISH_REGION_DISPLAY.of(parsed.region);
    if (regionLabel && regionLabel !== parsed.region && regionLabel !== "Unknown Region") {
      return `${languageLabel} (${regionLabel})`;
    }
  }

  return languageLabel;
}

function buildCurrencyLabel(currencyDisplay, currencyCode) {
  if (!currencyDisplay || !currencyCode) {
    return null;
  }

  try {
    const translated = currencyDisplay.of(currencyCode);
    if (!translated || translated === currencyCode || translated === "Unknown Currency") {
      return null;
    }

    return translated;
  } catch (_error) {
    return null;
  }
}

function parseLanguageCode(languageValue) {
  const normalized = asString(languageValue).replace(/_/g, "-").trim();
  if (!normalized) {
    return null;
  }

  const [languagePart, regionPart] = normalized.split("-");
  const language = languagePart ? languagePart.toLowerCase() : null;
  const region = regionPart ? regionPart.toUpperCase() : null;

  if (!language) {
    return null;
  }

  return { language, region };
}

function getTimezoneOffsetLabel(zoneId) {
  if (!zoneId) {
    return "UTC+00:00";
  }

  if (TIMEZONE_OFFSET_CACHE.has(zoneId)) {
    return TIMEZONE_OFFSET_CACHE.get(zoneId);
  }

  let offsetLabel = "UTC+00:00";

  try {
    const formatter = new Intl.DateTimeFormat("en-US", {
      timeZone: zoneId,
      hour: "2-digit",
      minute: "2-digit",
      hourCycle: "h23",
      timeZoneName: "shortOffset",
    });
    const timezonePart =
      formatter.formatToParts(new Date()).find((part) => part.type === "timeZoneName")?.value || "GMT";
    offsetLabel = normalizeOffsetLabel(timezonePart);
  } catch (_error) {
    offsetLabel = "UTC+00:00";
  }

  TIMEZONE_OFFSET_CACHE.set(zoneId, offsetLabel);
  return offsetLabel;
}

function normalizeOffsetLabel(rawOffset) {
  let offset = asString(rawOffset).replace("GMT", "UTC").replace("UTC−", "UTC-").trim();
  if (!offset || offset === "UTC") {
    return "UTC+00:00";
  }

  const match = offset.match(/^UTC([+-])(\d{1,2})(?::?(\d{2}))?$/);
  if (!match) {
    return offset;
  }

  const sign = match[1];
  const hours = String(match[2]).padStart(2, "0");
  const minutes = String(match[3] || "00").padStart(2, "0");

  return `UTC${sign}${hours}:${minutes}`;
}

function isArabicLocale(locale) {
  return asString(locale).toLowerCase().startsWith("ar");
}

function normalizeLookup(value) {
  return asString(value)
    .toLowerCase()
    .replace(/\([^)]*\)/g, " ")
    .replace(/[^a-z0-9]+/g, " ")
    .trim();
}

function localizeArabicTimezoneLabel(zoneId, fallbackLabel) {
  const direct = ARABIC_TIMEZONE_LABELS_BY_VALUE[zoneId];
  if (direct) {
    return direct;
  }

  const fallback = asString(fallbackLabel);
  if (ARABIC_TIMEZONE_LABELS_BY_VALUE[fallback]) {
    return ARABIC_TIMEZONE_LABELS_BY_VALUE[fallback];
  }

  return fallback;
}

function normalizeCurrencyRawLabel(value) {
  const labels = asString(value)
    .split("|")
    .map((segment) => segment.trim())
    .filter(Boolean);

  return labels[0] || "";
}

function asString(value) {
  if (value === null || value === undefined) {
    return "";
  }

  return String(value);
}
