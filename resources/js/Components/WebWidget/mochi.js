/**
 * The Mochi mascot (botzo-mochi.svg) as markup. Botzo's colours keep the original artwork exactly;
 * a customer's two colours replace the headset gradient and the mic tip. `uid` keeps gradient ids
 * unique when several mascots share a page.
 */
export const MOCHI_DEFAULT_PRIMARY = '#22D366';
export const MOCHI_DEFAULT_SECONDARY = '#2F7BFF';

const HEX = /^#[0-9a-fA-F]{6}$/;

export function mochiSvg({ primary = MOCHI_DEFAULT_PRIMARY, secondary = MOCHI_DEFAULT_SECONDARY, uid = 'm' } = {}) {
    const custom = HEX.test(primary) && HEX.test(secondary)
        && (primary.toUpperCase() !== MOCHI_DEFAULT_PRIMARY || secondary.toUpperCase() !== MOCHI_DEFAULT_SECONDARY);
    const brandStart = custom ? primary : '#3CF28E';
    const brandEnd = custom ? secondary : '#2F7BFF';
    const micTip = custom ? primary : '#22D366';

    return `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 240 230" aria-hidden="true">
  <defs>
    <radialGradient id="${uid}Body" cx="0.4" cy="0.3" r="0.8">
      <stop offset="0" stop-color="#FFFFFF"/>
      <stop offset="0.55" stop-color="#F1F3F6"/>
      <stop offset="0.85" stop-color="#D3D8DF"/>
      <stop offset="1" stop-color="#B9C0CA"/>
    </radialGradient>
    <linearGradient id="${uid}Brand" x1="0" y1="0" x2="1" y2="1">
      <stop offset="0" stop-color="${brandStart}"/>
      <stop offset="1" stop-color="${brandEnd}"/>
    </linearGradient>
  </defs>
  <ellipse class="mochi-shadow" cx="120" cy="220" rx="80" ry="8" fill="#000" opacity="0.45"/>
  <path class="mochi-body" d="M120 42 C192 42 228 104 228 154 C228 198 190 216 120 216 C50 216 12 198 12 154 C12 104 48 42 120 42 Z" fill="url(#${uid}Body)"/>
  <ellipse cx="86" cy="78" rx="30" ry="14" fill="#FFFFFF" opacity="0.9" transform="rotate(-20 86 78)"/>
  <path d="M30 120 C 34 20 206 20 210 120" fill="none" stroke="url(#${uid}Brand)" stroke-width="9" stroke-linecap="round"/>
  <rect x="14" y="110" width="26" height="46" rx="13" fill="url(#${uid}Brand)"/>
  <rect x="200" y="110" width="26" height="46" rx="13" fill="url(#${uid}Brand)"/>
  <path d="M214 156 Q214 190 170 192" stroke="#9AA6B6" stroke-width="4" fill="none" stroke-linecap="round"/>
  <circle cx="166" cy="192" r="6" fill="${micTip}"/>
  <g class="mochi-face">
    <g class="mochi-eyes">
      <ellipse cx="90" cy="132" rx="11" ry="15" fill="#0E0F12"/>
      <ellipse cx="150" cy="132" rx="11" ry="15" fill="#0E0F12"/>
      <circle cx="94" cy="126" r="3.5" fill="#FFFFFF"/>
      <circle cx="154" cy="126" r="3.5" fill="#FFFFFF"/>
    </g>
    <path class="mochi-mouth" d="M104 162 Q120 176 136 162" stroke="#0E0F12" stroke-width="5" fill="none" stroke-linecap="round"/>
  </g>
</svg>`;
}
