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
    tabs: { home: 'الرئيسية', look: 'شكل موتشي', info: 'معلومات المتجر' },
    enabled: 'تشغيل موتشي في متجري',
    enabledHint: 'لما توقفه يختفي من المتجر خلال دقائق، وترجع كل إعداداته لما تشغله.',
    storeColor: 'استخدم لون متجري تلقائياً',
    color: 'لون موتشي',
    positionLabel: 'مكانه في المتجر',
    left: 'يسار الشاشة',
    right: 'يمين الشاشة',
    name: 'اسم المساعد',
    namePlaceholder: 'موتشي',
    welcome: 'الجملة الترحيبية',
    welcomePlaceholder: 'قل لي وش تدور عليه، وأساعدك تلقاه وتكمل طلبك.',
    previewTitle: 'المعاينة',
    previewAsk: (name) => `اسأل ${name}`,
    previewNote: 'لون متجرك يظهر في المتجر نفسه.',
    returnPolicy: 'سياسة الاسترجاع والاستبدال',
    returnHint: 'موتشي يرد على العملاء من هذا النص فقط.',
    returnPlaceholder: 'مثال: الاسترجاع خلال 7 أيام من الاستلام والمنتج بحالته الأصلية.',
    notes: 'معلومات أخرى تبي موتشي يعرفها',
    notesHint: 'مثل أوقات العمل أو طريقة التواصل مع فريقك. لا تكتب كلمات مرور أو بيانات خاصة.',
    notesPlaceholder: 'مثال: نرد على الواتساب من 9 صباحاً إلى 9 مساءً.',
    save: 'حفظ',
    saving: 'جاري الحفظ…',
    saved: 'تم الحفظ. قد يأخذ الشكل الجديد حتى 5 دقائق ليظهر في المتجر.',
    savedInfo: 'تم الحفظ. موتشي يرد من المعلومات الجديدة الآن.',
    saveFailed: 'ما قدرنا نحفظ. راجع الخانات وحاول مرة ثانية.',
    expired: 'انتهت الجلسة. حدّث الصفحة ثم احفظ مرة ثانية.',
    left_chars: (left) => `باقي ${left} حرف`,
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
    tabs: { home: 'Home', look: 'Mochi\'s look', info: 'Store information' },
    enabled: 'Run Mochi in my store',
    enabledHint: 'When off, it leaves the store within minutes. Its settings return when you turn it on.',
    storeColor: 'Use my store\'s color automatically',
    color: 'Mochi\'s color',
    positionLabel: 'Place in the store',
    left: 'Left of the screen',
    right: 'Right of the screen',
    name: 'Assistant name',
    namePlaceholder: 'Mochi',
    welcome: 'Welcome line',
    welcomePlaceholder: 'Tell me what you are looking for, and I will help you find it and finish your order.',
    previewTitle: 'Preview',
    previewAsk: (name) => `Ask ${name}`,
    previewNote: 'Your store\'s color shows in the store itself.',
    returnPolicy: 'Return and exchange policy',
    returnHint: 'Mochi answers customers from this text only.',
    returnPlaceholder: 'For example: returns within 7 days of delivery, in original condition.',
    notes: 'Other things Mochi should know',
    notesHint: 'Such as working hours or how to reach your team. Do not write passwords or private details.',
    notesPlaceholder: 'For example: we answer on WhatsApp from 9 am to 9 pm.',
    save: 'Save',
    saving: 'Saving…',
    saved: 'Saved. The new look can take up to 5 minutes to show in the store.',
    savedInfo: 'Saved. Mochi answers from the new information now.',
    saveFailed: 'We could not save. Check the fields and try again.',
    expired: 'Your session ended. Reload the page, then save again.',
    left_chars: (left) => `${left} characters left`,
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

const state = { tab: 'home', overview: null, settings: null };

function field(id, label, control, hint) {
    return el('div', { class: 'field' }, [
        el('label', { for: id, text: label }),
        control,
        hint ? el('p', { class: 'hint', id: `${id}-hint`, text: hint }) : null,
    ]);
}

function textArea(id, value, max, placeholder) {
    const input = el('textarea', { id, rows: '5', maxlength: String(max), placeholder, 'aria-describedby': `${id}-hint` });
    input.value = value;
    return input;
}

function mochiPicture(color) {
    const holder = el('span', { class: 'mochi', 'aria-hidden': 'true' });
    holder.innerHTML = '<svg viewBox="0 0 240 230" width="48" height="46"><path d="M120 42C192 42 228 104 228 154C228 198 190 216 120 216C50 216 12 198 12 154C12 104 48 42 120 42Z" fill="#EEF0F3"/>'
        + `<path d="M30 120C34 20 206 20 210 120" fill="none" stroke="${color}" stroke-width="10" stroke-linecap="round"/><rect x="14" y="110" width="26" height="46" rx="13" fill="${color}"/><rect x="200" y="110" width="26" height="46" rx="13" fill="${color}"/>`
        + '<ellipse cx="90" cy="132" rx="11" ry="15" fill="#0E0F12"/><ellipse cx="150" cy="132" rx="11" ry="15" fill="#0E0F12"/><path d="M104 162Q120 176 136 162" stroke="#0E0F12" stroke-width="5" fill="none" stroke-linecap="round"/></svg>';
    return holder;
}

/** Saves all settings together: the two forms edit parts of the same record. */
async function save(changes, button, status, message) {
    button.disabled = true;
    button.textContent = TEXT.saving;
    status.className = 'save-status';
    status.textContent = '';

    try {
        state.settings = await api('/settings', {
            method: 'PUT',
            body: JSON.stringify({ ...state.settings, ...changes }),
        });
        state.overview = await api('/overview');
        status.textContent = message;
    } catch (error) {
        status.className = 'save-status is-error';
        status.textContent = error.message === '401' ? TEXT.expired : TEXT.saveFailed;
    }

    button.disabled = false;
    button.textContent = TEXT.save;
}

function lookTab() {
    const settings = state.settings;
    const enabled = el('input', { id: 'enabled', type: 'checkbox', 'aria-describedby': 'enabled-hint' });
    enabled.checked = settings.is_enabled;
    const storeColor = el('input', { id: 'store-color', type: 'checkbox' });
    storeColor.checked = settings.use_store_color;
    const color = el('input', { id: 'color', type: 'color' });
    color.value = settings.accent || '#0F7B45';
    const position = el('select', { id: 'position' }, [
        el('option', { value: 'left', text: TEXT.left }),
        el('option', { value: 'right', text: TEXT.right }),
    ]);
    position.value = settings.position;
    const name = el('input', { id: 'name', type: 'text', maxlength: '40', placeholder: TEXT.namePlaceholder });
    name.value = settings.assistant_name;
    const welcome = textArea('welcome', settings.welcome_text, 300, TEXT.welcomePlaceholder);
    welcome.rows = 3;

    const colorRow = field('color', TEXT.color, color);
    const preview = el('div', { class: 'preview-stage' });
    const previewNote = el('p', { class: 'hint', text: TEXT.previewNote });
    const drawPreview = () => {
        colorRow.hidden = storeColor.checked;
        previewNote.hidden = !storeColor.checked;
        preview.className = `preview-stage at-${position.value}`;
        preview.replaceChildren(el('span', { class: 'preview-pill' }, [
            mochiPicture(storeColor.checked ? '#111827' : color.value),
            TEXT.previewAsk(name.value.trim() || TEXT.namePlaceholder),
        ]));
    };
    [storeColor, color, position, name].forEach((input) => input.addEventListener('input', drawPreview));
    drawPreview();

    const button = el('button', { type: 'submit', class: 'primary', text: TEXT.save });
    const status = el('p', { class: 'save-status', role: 'status' });

    return el('div', { class: 'split' }, [
        el('form', { class: 'card form', onsubmit: (event) => {
            event.preventDefault();
            save({
                is_enabled: enabled.checked,
                use_store_color: storeColor.checked,
                accent: storeColor.checked ? null : color.value,
                position: position.value,
                assistant_name: name.value,
                welcome_text: welcome.value,
            }, button, status, TEXT.saved);
        } }, [
            el('div', { class: 'check' }, [enabled, el('div', {}, [el('label', { for: 'enabled', text: TEXT.enabled }), el('p', { class: 'hint', id: 'enabled-hint', text: TEXT.enabledHint })])]),
            el('div', { class: 'check' }, [storeColor, el('label', { for: 'store-color', text: TEXT.storeColor })]),
            colorRow,
            field('position', TEXT.positionLabel, position),
            field('name', TEXT.name, name),
            field('welcome', TEXT.welcome, welcome),
            el('div', { class: 'actions' }, [button, status]),
        ]),
        el('aside', { class: 'card preview' }, [el('h2', { text: TEXT.previewTitle }), preview, previewNote]),
    ]);
}

function infoTab() {
    const settings = state.settings;
    const policy = textArea('policy', settings.return_policy, 2000, TEXT.returnPlaceholder);
    const notes = textArea('notes', settings.store_notes, 2000, TEXT.notesPlaceholder);
    const button = el('button', { type: 'submit', class: 'primary', text: TEXT.save });
    const status = el('p', { class: 'save-status', role: 'status' });

    return el('form', { class: 'card form', onsubmit: (event) => {
        event.preventDefault();
        save({ return_policy: policy.value, store_notes: notes.value }, button, status, TEXT.savedInfo);
    } }, [
        field('policy', TEXT.returnPolicy, policy, TEXT.returnHint),
        field('notes', TEXT.notes, notes, TEXT.notesHint),
        el('div', { class: 'actions' }, [button, status]),
    ]);
}

function nav() {
    return el('nav', { class: 'tabs', 'aria-label': TEXT.title }, Object.entries(TEXT.tabs).map(([key, label]) => el('button', {
        type: 'button',
        class: state.tab === key ? 'tab is-active' : 'tab',
        ...(state.tab === key ? { 'aria-current': 'page' } : {}),
        text: label,
        onclick: () => {
            state.tab = key;
            draw();
        },
    })));
}

function homeTab(data) {
    return el('div', { class: 'stack' }, [
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
    ]);
}

function draw() {
    const data = state.overview;
    const plan = data.subscription ? (AR ? data.subscription.plan : data.subscription.plan_en) : null;
    const tab = { home: () => homeTab(data), look: lookTab, info: infoTab }[state.tab];

    root.replaceChildren(
        el('header', { class: 'top' }, [
            el('h1', { text: data.store_name || TEXT.title }),
            plan ? el('p', { text: `${TEXT.plan}: ${plan}${data.subscription.valid_until ? ` · ${TEXT.until} ${date(data.subscription.valid_until)}` : ''}` }) : null,
        ]),
        nav(),
        tab(),
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
button:focus-visible,input:focus-visible,select:focus-visible,textarea:focus-visible{outline:3px solid var(--fill);outline-offset:2px}
button[disabled]{opacity:.6;cursor:default}
button.primary{background:var(--text);color:var(--surface)}
.stack{display:flex;flex-direction:column;gap:16px}
.tabs{display:flex;flex-wrap:wrap;gap:8px;border-bottom:1px solid var(--line);padding-bottom:12px}
.tab{border:0;border-radius:10px}
.tab:hover{background:var(--track)}
.tab.is-active{background:var(--text);color:var(--surface)}
.split{display:grid;grid-template-columns:minmax(0,3fr) minmax(240px,2fr);gap:16px;align-items:start}
.form{display:flex;flex-direction:column;gap:18px}
.field{display:flex;flex-direction:column;gap:6px}
.field[hidden]{display:none}
label{font-size:14px;font-weight:600}
input[type=text],select,textarea{width:100%;min-height:44px;padding:10px 12px;border:1px solid #6B7280;border-radius:10px;background:var(--surface);color:var(--text);font:inherit}
input[type=color]{width:64px;height:44px;padding:4px;border:1px solid #6B7280;border-radius:10px;background:var(--surface);cursor:pointer}
textarea{resize:vertical}
::placeholder{color:var(--muted)}
.check{display:flex;gap:12px;align-items:flex-start}
.check input{width:22px;height:22px;margin:2px 0 0;flex-shrink:0;accent-color:var(--fill)}
.field .hint,.check .hint{margin-top:0}
.actions{display:flex;flex-wrap:wrap;align-items:center;gap:12px}
.save-status{font-size:14px;color:var(--on)}
.save-status.is-error{color:var(--off)}
.preview h2{font-size:14px;font-weight:500;color:var(--muted)}
.preview-stage{display:flex;align-items:flex-end;height:180px;margin-top:12px;padding:16px;border-radius:10px;background:var(--bg);direction:ltr}
.preview-stage.at-right{justify-content:flex-end}
.preview-pill{display:inline-flex;align-items:center;gap:8px;height:52px;padding:0 16px 0 8px;border-radius:26px;background:#fff;color:#111827;font-size:14px;font-weight:600;box-shadow:0 10px 28px rgba(17,24,39,.2)}
.mochi{display:inline-block;margin-top:-16px;line-height:0}
.preview .hint[hidden]{display:none}
@media (max-width:640px){.split{grid-template-columns:minmax(0,1fr)}.value{font-size:24px}}
`;

async function start() {
    document.head.appendChild(el('style', { text: STYLE }));
    root.replaceChildren(el('p', { role: 'status', text: TEXT.loading }));

    try {
        await embedded.init();
        const session = await api('/session', { method: 'POST', body: JSON.stringify({ token: embedded.auth.getToken() }) });
        panelToken = session.token;
        [state.overview, state.settings] = await Promise.all([api('/overview'), api('/settings')]);
        draw();
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
