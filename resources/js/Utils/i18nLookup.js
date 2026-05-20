const HAS_OWN = Object.prototype.hasOwnProperty;

export function normalizeI18nLookupKey(value) {
  return String(value ?? "")
    .trim()
    .replace(/[_\-.]+/g, " ")
    .replace(/\s+/g, " ")
    .toLowerCase();
}

export function buildI18nLookupCandidates(value) {
  const raw = String(value ?? "").trim();
  if (!raw) {
    return [];
  }

  const collapsed = raw.replace(/\s+/g, " ");
  const spaced = collapsed.replace(/[_\-.]+/g, " ").replace(/\s+/g, " ");
  const lower = spaced.toLowerCase();
  const sentence = lower ? lower.charAt(0).toUpperCase() + lower.slice(1) : lower;
  const title = spaced
    .split(" ")
    .filter(Boolean)
    .map((word) => word.charAt(0).toUpperCase() + word.slice(1).toLowerCase())
    .join(" ");

  const candidates = [
    raw,
    collapsed,
    spaced,
    sentence,
    title,
    lower,
    raw.toLowerCase(),
    raw.toUpperCase(),
  ];

  return Array.from(new Set(candidates.filter(Boolean)));
}

function buildAliasMap(messages) {
  const aliasMap = new Map();

  if (!messages || typeof messages !== "object") {
    return aliasMap;
  }

  for (const key of Object.keys(messages)) {
    const normalized = normalizeI18nLookupKey(key);
    if (normalized && !aliasMap.has(normalized)) {
      aliasMap.set(normalized, key);
    }
  }

  return aliasMap;
}

export function resolveMessageByCandidates(messages, key) {
  if (!messages || typeof messages !== "object") {
    return null;
  }

  const aliasMap = buildAliasMap(messages);

  for (const candidate of buildI18nLookupCandidates(key)) {
    if (HAS_OWN.call(messages, candidate)) {
      return messages[candidate];
    }

    const normalized = normalizeI18nLookupKey(candidate);
    if (normalized && aliasMap.has(normalized)) {
      const aliasedKey = aliasMap.get(normalized);
      if (HAS_OWN.call(messages, aliasedKey)) {
        return messages[aliasedKey];
      }
    }
  }

  return null;
}

