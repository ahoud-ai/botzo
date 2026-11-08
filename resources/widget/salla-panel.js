/*
 * The merchant's panel inside Salla's dashboard. Built into public/salla-panel.js by
 * scripts/build-widget.mjs. Server text is always inserted as text, never as HTML.
 */
import { embedded } from '@salla.sa/embedded-sdk';

const AR = document.documentElement.lang !== 'en';
const TEXT = AR ? {
    title: 'لوحة موتشي',
    running: 'موتشي يعمل في متجرك',
    runningHint: 'يرد على زوار متجرك الآن.',
    off: 'موتشي متوقف',
    offNoPlan: 'ما عندك اشتراك فعال. فعّل اشتراكك من صفحة التطبيق في سلة ليرجع موتشي.',
    offDisabled: 'أوقفت موتشي من الإعدادات.',
    replies: 'الردود هذا الشهر',
    repliesOf: (used, limit) => `${used} من ${limit}`,
    repliesLeft: (left) => `باقي ${left} رد`,
    repliesOut: 'انتهت ردود هذا الشهر، وموتشي لن يرد حتى يبدأ الشهر الجديد أو ترقّي باقتك.',
    unlimited: 'غير محدود',
    conversations: 'محادثات آخر 7 أيام',
    products: 'المنتجات عند موتشي',
    productsWaiting: 'نجهز منتجاتك الآن، وعادة ياخذ دقائق.',
    syncedAt: 'آخر تحديث',
    plan: 'الباقة',
    until: 'حتى',
    failedTitle: 'ما قدرنا نفتح اللوحة',
    failedBody: 'حدّث الصفحة وحاول مرة ثانية. إذا استمرت المشكلة تواصل مع دعم بوتزو.',
    retry: 'إعادة المحاولة',
    loading: 'جاري التحميل…',
} : {
    title: 'Mochi panel',
    running: 'Mochi is running in your store',
    runningHint: 'It is answering your visitors now.',
    off: 'Mochi is off',
    offNoPlan: 'You have no active subscription. Activate it from the app page in Salla to bring Mochi back.',
    offDisabled: 'You turned Mochi off in the settings.',
    replies: 'Replies this month',
    repliesOf: (used, limit) => `${used} of ${limit}`,
    repliesLeft: (left) => `${left} left`,
    repliesOut: 'This month\'s replies are used up. Mochi stops replying until next month or a plan upgrade.',
    unlimited: 'Unlimited',
    conversations: 'Conversations, last 7 days',
    products: 'Products Mochi knows',
    productsWaiting: 'We are preparing your products. This usually takes minutes.',
    syncedAt: 'Last update',
    plan: 'Plan',
    until: 'until',
    failedTitle: 'We could not open the panel',
    failedBody: 'Reload the page and try again. If it continues, contact Botzo support.',
    retry: 'Try again',
    loading: 'Loading…',
};

const API = '/api/salla/panel';
const root = document.getElementById('panel');
const LOCALE = AR ? 'ar-SA' : 'en-US';
let panelToken = null;

function el(tag, attrs, children) {
    const node = document.createElement(tag);
    Object.entries(attrs || {}).forEach(([name, value]) => {
        if (name === 'text') {
            node.textContent = value;
        } else if (name.startsWith('on')) {
            node.addEventListener(name.slice(2), value);
        } else {
            node.setAttribute(name, value);
        }
    });
    (children || []).forEach((child) => {
        if (child) {
            node.appendChild(typeof child === 'string' ? document.createTextNode(child) : child);
        }
    });
    return node;
}

function number(value) {
    return Number(value).toLocaleString(LOCALE);
}

function date(value, withTime) {
    return new Date(value).toLocaleString(LOCALE, withTime
        ? { dateStyle: 'medium', timeStyle: 'short' }
        : { dateStyle: 'medium' });
}

async function api(path, options) {
    const response = await fetch(API + path, {
        ...options,
        headers: {
            Accept: 'application/json',
            ...(options && options.body ? { 'Content-Type': 'application/json' } : {}),
            ...(panelToken ? { Authorization: `Bearer ${panelToken}` } : {}),
        },
    });
    if (!response.ok) {
        throw new Error(String(response.status));
    }
    return response.json();
}

function stat(label, value, hint) {
    return el('section', { class: 'card stat' }, [
        el('h2', { text: label }),
        el('p', { class: 'value', text: value }),
        hint ? el('p', { class: 'hint', text: hint }) : null,
    ]);
}

function statusCard(data) {
    const running = data.assistant.running;
    const hint = running
        ? TEXT.runningHint
        : (data.assistant.has_subscription ? TEXT.offDisabled : TEXT.offNoPlan);

    return el('section', { class: `card status ${running ? 'is-on' : 'is-off'}` }, [
        el('span', { class: 'dot', 'aria-hidden': 'true' }),
        el('div', {}, [
            el('h2', { text: running ? TEXT.running : TEXT.off }),
            el('p', { text: hint }),
        ]),
    ]);
}

function repliesCard(replies) {
    const unlimited = replies.limit < 0;
    const out = !unlimited && replies.used >= replies.limit;
    const percent = unlimited || replies.limit === 0 ? 0 : Math.min(100, Math.round((replies.used / replies.limit) * 100));
    const bar = el('div', { class: 'bar-fill' });
    bar.style.width = `${percent}%`;

    return el('section', { class: `card replies ${out ? 'is-out' : ''}` }, [
        el('h2', { text: TEXT.replies }),
        el('p', { class: 'value', text: unlimited ? TEXT.unlimited : TEXT.repliesOf(number(replies.used), number(replies.limit)) }),
        unlimited ? null : el('div', { class: 'bar', role: 'progressbar', 'aria-valuemin': '0', 'aria-valuemax': '100', 'aria-valuenow': String(percent), 'aria-label': TEXT.replies }, [bar]),
        unlimited ? null : el('p', { class: 'hint', text: out ? TEXT.repliesOut : TEXT.repliesLeft(number(replies.limit - replies.used)) }),
    ]);
}

function render(data) {
    const plan = data.subscription ? (AR ? data.subscription.plan : data.subscription.plan_en) : null;
    root.replaceChildren(
        el('header', { class: 'top' }, [
            el('h1', { text: data.store_name || TEXT.title }),
            plan ? el('p', { text: `${TEXT.plan}: ${plan}${data.subscription.valid_until ? ` · ${TEXT.until} ${date(data.subscription.valid_until)}` : ''}` }) : null,
        ]),
        statusCard(data),
        el('div', { class: 'grid' }, [
            repliesCard(data.replies),
            stat(TEXT.conversations, number(data.conversations_last_7_days)),
            stat(
                TEXT.products,
                data.products.synced_at ? number(data.products.count) : '—',
                data.products.synced_at ? `${TEXT.syncedAt}: ${date(data.products.synced_at, true)}` : TEXT.productsWaiting,
            ),
        ]),
    );
}

function renderFailure() {
    root.replaceChildren(el('section', { class: 'card failure', role: 'alert' }, [
        el('h1', { text: TEXT.failedTitle }),
        el('p', { text: TEXT.failedBody }),
        el('button', { type: 'button', text: TEXT.retry, onclick: () => window.location.reload() }),
    ]));
}

const STYLE = `
:root{--bg:#F6F7F8;--surface:#fff;--text:#111827;--muted:#4B5563;--line:#E5E7EB;--on:#15803D;--off:#B42318;--fill:#0F7B45;--track:#E5E7EB}
[data-theme=dark]{--bg:#111827;--surface:#1F2937;--text:#F9FAFB;--muted:#D1D5DB;--line:#374151;--on:#4ADE80;--off:#FCA5A5;--fill:#4ADE80;--track:#374151}
*{box-sizing:border-box}
body{margin:0;background:var(--bg);color:var(--text);font:16px/1.6 "IBM Plex Sans Arabic",-apple-system,"Segoe UI",Tahoma,Arial,sans-serif}
main{max-width:960px;margin:0 auto;padding:24px 16px;display:flex;flex-direction:column;gap:16px}
h1,h2,p{margin:0}
.top h1{font-size:24px;font-weight:700}
.top p{color:var(--muted);font-size:14px}
.card{background:var(--surface);border:1px solid var(--line);border-radius:12px;padding:20px}
.status{display:flex;align-items:center;gap:14px}
.status h2{font-size:17px;font-weight:700}
.status p{color:var(--muted);font-size:14px}
.dot{width:12px;height:12px;flex-shrink:0;border-radius:50%}
.is-on .dot{background:var(--on)}
.is-off .dot{background:var(--off)}
.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:16px}
.grid h2{font-size:14px;font-weight:500;color:var(--muted)}
.value{margin-top:6px;font-size:28px;font-weight:700;font-variant-numeric:tabular-nums}
.hint{margin-top:8px;color:var(--muted);font-size:13px}
.bar{height:8px;margin-top:12px;border-radius:999px;background:var(--track);overflow:hidden}
.bar-fill{height:100%;background:var(--fill)}
.is-out .bar-fill{background:var(--off)}
.is-out .hint{color:var(--off)}
.failure{display:flex;flex-direction:column;gap:12px;align-items:flex-start}
.failure h1{font-size:20px;font-weight:700}
.failure p{color:var(--muted)}
button{min-height:44px;padding:0 20px;border:1px solid var(--text);border-radius:10px;background:transparent;color:var(--text);font:inherit;font-weight:600;cursor:pointer}
button:focus-visible{outline:3px solid var(--fill);outline-offset:2px}
`;

async function start() {
    document.head.appendChild(el('style', { text: STYLE }));
    root.replaceChildren(el('p', { role: 'status', text: TEXT.loading }));

    try {
        await embedded.init();
        const session = await api('/session', { method: 'POST', body: JSON.stringify({ token: embedded.auth.getToken() }) });
        panelToken = session.token;
        render(await api('/overview'));
        embedded.ready();
    } catch (error) {
        renderFailure();
        // Clears Salla's loading overlay so the merchant can read the message.
        try {
            embedded.ready();
        } catch (ignored) {
            // Opened outside Salla's dashboard: there is no overlay to clear.
        }
    }
}

start();
