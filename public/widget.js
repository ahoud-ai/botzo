/*!
 * Botzo website chat widget.
 * Embed: <script src="https://botzo.net/widget.js" data-key="wgt_..." async></script>
 * Renders inside its own Shadow DOM so the host page's styles and the widget never clash.
 * Two looks: "botzo" (dark, Botzo brand icons, the default) and "classic" (light, the
 * customer's own colour). Visitor and server text is always inserted as text, never as HTML;
 * only the fixed brand icons below are parsed as markup.
 */
(function () {
    'use strict';

    if (window.__botzoWidgetLoaded) {
        return;
    }
    window.__botzoWidgetLoaded = true;

    var script = document.currentScript || document.querySelector('script[data-key][src*="widget.js"]');
    if (!script) {
        return;
    }

    var KEY = script.getAttribute('data-key') || '';
    if (!/^wgt_[A-Za-z0-9]{40}$/.test(KEY)) {
        return;
    }

    var API = new URL(script.src, window.location.href).origin + '/api/widget/' + KEY;
    var TOKEN_KEY = 'botzo_widget_token_' + KEY;
    var TEASER_KEY = 'botzo_widget_teaser_' + KEY;
    var LOCALE = /^ar/i.test(document.documentElement.lang || navigator.language || '') ? 'ar' : 'en';
    var CLOSED_POLL_SECONDS = 30;

    var TEXT = {
        ar: {
            open: 'افتح المحادثة',
            close: 'أغلق المحادثة',
            teaser: 'تحتاج مساعدة؟ اسألني',
            online: 'متصل الآن · يرد خلال ثوانٍ',
            placeholder: 'اكتب رسالتك…',
            send: 'إرسال',
            notice: 'مساعد ذكي',
            poweredBy: 'مدعوم بـ',
            welcome: 'مرحباً! كيف أقدر أساعدك؟',
            typing: 'يكتب الآن',
            awaiting: 'وصلت رسالتك، وسيرد عليك فريقنا هنا قريباً.',
            tooFast: 'انتظر لحظة ثم أرسل مرة أخرى.',
            hourly: 'أرسلت رسائل كثيرة. حاول مرة أخرى بعد قليل.',
            failed: 'صار خطأ بسيط، جرّب مرة ثانية',
            retry: 'إعادة المحاولة',
            team: 'من الفريق',
            label: 'محادثة مع المساعد',
            input: 'رسالتك',
            today: 'اليوم',
            yesterday: 'أمس',
            unread: 'رسائل جديدة',
            sent: 'تم الإرسال'
        },
        en: {
            open: 'Open chat',
            close: 'Close chat',
            teaser: 'Need help? Ask me',
            online: 'Online now · replies in seconds',
            placeholder: 'Type your message…',
            send: 'Send',
            notice: 'AI assistant',
            poweredBy: 'Powered by',
            welcome: 'Hi! How can I help you?',
            typing: 'Typing',
            awaiting: 'We got your message. Our team will reply here soon.',
            tooFast: 'Please wait a moment and send again.',
            hourly: 'You sent many messages. Please try again later.',
            failed: 'Something went wrong, please try again',
            retry: 'Try again',
            team: 'Team',
            label: 'Chat with the assistant',
            input: 'Your message',
            today: 'Today',
            yesterday: 'Yesterday',
            unread: 'new messages',
            sent: 'Sent'
        }
    }[LOCALE];

    // Botzo brand icons, used exactly as designed (botzo look only).
    var ICONS = {
        launcher: '<svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" viewBox="0 0 64 64" aria-hidden="true"><circle cx="32" cy="32" r="32" fill="#22D366"/><g transform="translate(11 11) scale(0.35)"><path d="M18 62a42 42 0 0 1 84 0" fill="none" stroke="#0A0E16" stroke-width="8" stroke-linecap="round"/><rect x="24" y="30" width="72" height="66" rx="26" fill="#0A0E16"/><rect x="8" y="54" width="18" height="30" rx="9" fill="#0A0E16"/><rect x="94" y="54" width="18" height="30" rx="9" fill="#0A0E16"/><circle cx="48" cy="60" r="8" fill="#22D366"/><circle cx="72" cy="60" r="8" fill="#22D366"/><path d="M50 76q10 6 20 0" stroke="#22D366" stroke-width="6" fill="none" stroke-linecap="round"/></g></svg>',
        header: '<svg xmlns="http://www.w3.org/2000/svg" width="44" height="44" viewBox="0 0 44 44" aria-hidden="true"><circle cx="22" cy="22" r="22" fill="#0A0E16"/><g transform="translate(6 6) scale(0.2667)"><path d="M18 62a42 42 0 0 1 84 0" fill="none" stroke="#F2F5F9" stroke-width="8" stroke-linecap="round"/><rect x="24" y="30" width="72" height="66" rx="26" fill="#22D366"/><rect x="8" y="54" width="18" height="30" rx="9" fill="#F2F5F9"/><rect x="94" y="54" width="18" height="30" rx="9" fill="#F2F5F9"/><circle cx="48" cy="60" r="8" fill="#0A0E16"/><circle cx="72" cy="60" r="8" fill="#0A0E16"/><path d="M50 76q10 6 20 0" stroke="#0A0E16" stroke-width="6" fill="none" stroke-linecap="round"/></g><circle cx="7" cy="37" r="5" fill="#22D366" stroke="#0A0E16" stroke-width="2"/></svg>',
        bot: '<svg xmlns="http://www.w3.org/2000/svg" width="120" height="120" viewBox="0 0 120 120" aria-hidden="true"><defs><linearGradient id="botzoGrad" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#22D366"/><stop offset="1" stop-color="#2F7BFF"/></linearGradient></defs><path d="M18 62a42 42 0 0 1 84 0" fill="none" stroke="#F2F5F9" stroke-width="7" stroke-linecap="round"/><rect x="24" y="30" width="72" height="66" rx="26" fill="url(#botzoGrad)"/><rect x="8" y="54" width="18" height="30" rx="9" fill="#F2F5F9"/><rect x="94" y="54" width="18" height="30" rx="9" fill="#F2F5F9"/><path d="M103 84q0 20-28 22" fill="none" stroke="#F2F5F9" stroke-width="5" stroke-linecap="round"/><circle cx="72" cy="106" r="6" fill="#F2F5F9"/><circle cx="48" cy="60" r="7" fill="#0A0E16"/><circle cx="72" cy="60" r="7" fill="#0A0E16"/><path d="M50 76q10 6 20 0" stroke="#0A0E16" stroke-width="5" fill="none" stroke-linecap="round"/></svg>'
    };

    function readStore(kind, key) {
        try {
            return window[kind] ? window[kind].getItem(key) : null;
        } catch (error) {
            return null;
        }
    }

    function writeStore(kind, key, value) {
        try {
            if (!window[kind]) {
                return;
            }
            if (value === null) {
                window[kind].removeItem(key);
            } else {
                window[kind].setItem(key, value);
            }
        } catch (error) {
            // Private mode or blocked storage: the conversation simply restarts on the next page.
        }
    }

    function el(tag, attrs, children) {
        var node = document.createElement(tag);
        Object.keys(attrs || {}).forEach(function (name) {
            if (name === 'text') {
                node.textContent = attrs[name];
            } else if (name.indexOf('on') === 0) {
                node.addEventListener(name.slice(2), attrs[name]);
            } else {
                node.setAttribute(name, attrs[name]);
            }
        });
        (children || []).forEach(function (child) {
            if (child) {
                node.appendChild(typeof child === 'string' ? document.createTextNode(child) : child);
            }
        });
        return node;
    }

    function icon(name) {
        var holder = el('span', { 'class': 'icon-' + name, 'aria-hidden': 'true' });
        holder.innerHTML = ICONS[name];
        return holder;
    }

    function svg(paths, size) {
        var ns = 'http://www.w3.org/2000/svg';
        var node = document.createElementNS(ns, 'svg');
        node.setAttribute('width', size || 24);
        node.setAttribute('height', size || 24);
        node.setAttribute('viewBox', '0 0 24 24');
        node.setAttribute('fill', 'none');
        node.setAttribute('stroke', 'currentColor');
        node.setAttribute('stroke-width', '2');
        node.setAttribute('stroke-linecap', 'round');
        node.setAttribute('stroke-linejoin', 'round');
        node.setAttribute('aria-hidden', 'true');
        paths.forEach(function (d) {
            var path = document.createElementNS(ns, 'path');
            path.setAttribute('d', d);
            node.appendChild(path);
        });
        return node;
    }

    var state = {
        token: readStore('localStorage', TOKEN_KEY),
        settings: null,
        theme: 'botzo',
        messages: [],
        lastId: 0,
        open: false,
        sending: false,
        typing: false,
        note: null,
        failedText: null,
        unread: 0,
        pollTimer: null,
        hideTimer: null
    };

    var ui = {};

    function request(method, path, body) {
        var headers = { 'Accept': 'application/json' };
        if (body) {
            headers['Content-Type'] = 'application/json';
        }
        if (state.token) {
            headers['X-Widget-Token'] = state.token;
        }

        return fetch(API + path, {
            method: method,
            headers: headers,
            body: body ? JSON.stringify(body) : undefined,
            credentials: 'omit',
            mode: 'cors'
        }).then(function (response) {
            return response.json().catch(function () {
                return {};
            }).then(function (data) {
                return { status: response.status, data: data };
            });
        });
    }

    function openSession() {
        return request('POST', '/session', {
            token: state.token,
            page_url: window.location.href.slice(0, 2000),
            locale: LOCALE
        }).then(function (result) {
            if (result.status !== 200 || !result.data.settings) {
                return false;
            }
            if (result.data.token) {
                state.token = result.data.token;
                writeStore('localStorage', TOKEN_KEY, state.token);
            }
            state.settings = result.data.settings;
            state.theme = state.settings.theme === 'classic' ? 'classic' : 'botzo';
            state.messages = [];
            state.lastId = 0;
            addMessages(result.data.messages || []);
            return true;
        }).catch(function () {
            return false;
        });
    }

    /**
     * @return {number} how many of the given messages were new and not written by the visitor
     */
    function addMessages(list) {
        var fresh = 0;
        list.forEach(function (message) {
            if (!message || typeof message.id !== 'number') {
                return;
            }
            var exists = state.messages.some(function (item) {
                return item.id === message.id;
            });
            if (!exists) {
                state.messages.push(message);
                if (message.from !== 'visitor') {
                    fresh++;
                }
            }
            state.lastId = Math.max(state.lastId, message.id);
        });
        return fresh;
    }

    function themeVars(accent) {
        if (state.theme === 'classic') {
            return [
                '--panel:#ffffff', '--body:#f7f8f7', '--panel-border:#e3e7e5', '--panel-shadow:0 12px 40px rgba(16,24,40,.22)',
                '--head-bg:' + accent, '--head-text:#ffffff', '--head-close:rgba(255,255,255,.16)',
                '--bot-bg:#ffffff', '--bot-border:#e3e7e5', '--bot-text:#1f2933',
                '--agent-bg:#fff6e5', '--agent-border:#f1ddb4',
                '--user-bg:' + accent, '--user-text:#ffffff', '--user-weight:400',
                '--chip-border:' + accent, '--chip-text:' + accent,
                '--input-bg:#ffffff', '--input-border:#cbd2cf', '--input-text:#1f2933', '--placeholder:#6b7680',
                '--send-bg:' + accent, '--send-icon:#ffffff',
                '--launcher-bg:' + accent, '--launcher-icon:#ffffff', '--launcher-shadow:0 8px 24px rgba(16,24,40,.28)',
                '--launcher-open-bg:#ffffff', '--launcher-open-border:#e3e7e5', '--launcher-open-icon:#1f2933',
                '--meta:#5b6670', '--date-bg:#eceeed', '--date-text:#4a5560', '--note-bg:#eceeed', '--note-text:#4a5560',
                '--brand:#1f2933', '--dot1:#6b7680', '--dot2:#6b7680', '--dot3:#6b7680',
                '--teaser-bg:#ffffff', '--teaser-text:#1f2933', '--badge-ring:#ffffff', '--avatar-bg:' + accent, '--head-avatar-bg:rgba(255,255,255,.2)'
            ].join(';');
        }

        return [
            '--panel:#0F1520', '--body:#0F1520', '--panel-border:#1E2838', '--panel-shadow:0 24px 60px rgba(0,0,0,.5)',
            '--head-bg:linear-gradient(135deg,#22D366,#2F7BFF)', '--head-text:#0A0E16', '--head-close:rgba(10,14,22,.18)',
            '--bot-bg:#1A2333', '--bot-border:#1A2333', '--bot-text:#F2F5F9',
            '--agent-bg:#161E2C', '--agent-border:#263044',
            '--user-bg:#22D366', '--user-text:#0A0E16', '--user-weight:500',
            '--chip-border:#22D366', '--chip-text:#22D366',
            '--input-bg:#161E2C', '--input-border:#263044', '--input-text:#F2F5F9', '--placeholder:#7C8799',
            '--send-bg:#22D366', '--send-icon:#0A0E16',
            '--launcher-bg:transparent', '--launcher-icon:#0A0E16', '--launcher-shadow:0 8px 24px rgba(34,211,102,.35)',
            '--launcher-open-bg:#1A2333', '--launcher-open-border:#2A3548', '--launcher-open-icon:#ffffff',
            '--meta:#7C8799', '--date-bg:#161E2C', '--date-text:#7C8799', '--note-bg:#161E2C', '--note-text:#A3ADBD',
            '--brand:#22D366', '--dot1:#22D366', '--dot2:#5AA8FF', '--dot3:#2F7BFF',
            '--teaser-bg:#1A2333', '--teaser-text:#F2F5F9', '--badge-ring:#0A0E16', '--avatar-bg:#0A0E16', '--head-avatar-bg:#0A0E16'
        ].join(';');
    }

    function styles(accent, side) {
        var other = side === 'left' ? 'right' : 'left';
        return [
            ':host{all:initial}',
            '*{box-sizing:border-box}',
            '.root{' + themeVars(accent) + ';position:fixed;bottom:24px;' + side + ':24px;z-index:2147483000;font-family:"IBM Plex Sans Arabic",-apple-system,BlinkMacSystemFont,"Segoe UI",Tahoma,Arial,sans-serif;color:var(--bot-text);font-size:14px;line-height:1.6}',
            'button{font:inherit;cursor:pointer}',
            'button:focus-visible,.field:focus-visible{outline:3px solid #5AA8FF;outline-offset:2px}',
            '.launcher{position:relative;width:64px;height:64px;padding:0;border-radius:999px;border:0;background:var(--launcher-bg);color:var(--launcher-icon);display:flex;align-items:center;justify-content:center;box-shadow:var(--launcher-shadow);transition:transform .15s ease,background-color .2s ease}',
            '.launcher:hover{transform:scale(1.05)}',
            '.launcher .icon-launcher,.launcher .icon-launcher svg{display:block;width:64px;height:64px}',
            '.launcher.is-open{background:var(--launcher-open-bg);border:1px solid var(--launcher-open-border);color:var(--launcher-open-icon);box-shadow:0 8px 24px rgba(0,0,0,.25)}',
            '.badge{position:absolute;top:-2px;' + other + ':-2px;min-width:22px;height:22px;padding:0 6px;border-radius:999px;background:#FF4D4F;color:#fff;font-size:12px;font-weight:700;line-height:18px;text-align:center;border:2px solid var(--badge-ring)}',
            '.badge[hidden]{display:none}',
            '.teaser{position:absolute;bottom:14px;' + side + ':76px;white-space:nowrap;background:var(--teaser-bg);color:var(--teaser-text);border:0;border-radius:14px;padding:10px 14px;font-size:14px;font-weight:500;box-shadow:0 6px 20px rgba(0,0,0,.25)}',
            '.teaser[hidden]{display:none}',
            '.panel{position:absolute;bottom:80px;' + side + ':0;width:380px;height:min(640px,calc(100vh - 120px));background:var(--panel);border:1px solid var(--panel-border);border-radius:24px;box-shadow:var(--panel-shadow);display:flex;flex-direction:column;overflow:hidden;opacity:0;transform:scale(.9);transform-origin:bottom ' + side + ';transition:opacity .2s ease,transform .2s ease}',
            '.panel.show{opacity:1;transform:scale(1)}',
            '.panel[hidden]{display:none}',
            '.head{background:var(--head-bg);color:var(--head-text);display:flex;align-items:center;gap:12px;padding:16px 18px}',
            '.head-avatar{position:relative;width:44px;height:44px;flex-shrink:0;border-radius:999px;display:flex;align-items:center;justify-content:center;background:var(--head-avatar-bg);color:#fff;font-weight:700;font-size:17px}',
            '.head-avatar .icon-header,.head-avatar svg{display:block;width:44px;height:44px}',
            '.who{flex:1;min-width:0}.name{font-weight:700;font-size:16px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.status{font-size:12px;opacity:.85}',
            '.close{width:36px;height:36px;border-radius:999px;border:0;background:var(--head-close);color:var(--head-text);display:flex;align-items:center;justify-content:center;flex-shrink:0}',
            '.body{flex:1;overflow-y:auto;padding:16px;display:flex;flex-direction:column;gap:10px;background:var(--body);overscroll-behavior:contain}',
            '.date{align-self:center;font-size:11px;background:var(--date-bg);color:var(--date-text);border-radius:999px;padding:3px 10px}',
            '.row{display:flex;align-items:flex-end;gap:8px;max-width:100%}',
            '.row.bot{align-self:flex-start}',
            '.row.user{align-self:flex-end;flex-direction:column;align-items:flex-end;gap:2px}',
            '.mini{width:28px;height:28px;flex-shrink:0;border-radius:999px;display:flex;align-items:center;justify-content:center;background:var(--avatar-bg);color:#fff;font-size:12px;font-weight:700;overflow:hidden}',
            '.mini .icon-bot,.mini svg{display:block;width:28px;height:28px}',
            '.mini.blank{visibility:hidden}',
            '.mini.person{background:#2F7BFF;color:#ffffff}',
            '.msg{max-width:250px;padding:10px 14px;border-radius:18px;white-space:pre-wrap;overflow-wrap:anywhere}',
            '.bot .msg{background:var(--bot-bg);border:1px solid var(--bot-border);color:var(--bot-text);border-end-start-radius:6px}',
            '.bot .msg.agent{background:var(--agent-bg);border-color:var(--agent-border)}',
            '.user .msg{background:var(--user-bg);color:var(--user-text);font-weight:var(--user-weight);border-end-end-radius:6px}',
            '.pending{opacity:.65}',
            '.who-label{display:block;font-size:12px;font-weight:600;color:var(--meta);margin-bottom:2px}',
            '.meta{font-size:11px;color:var(--meta)}',
            '.note{align-self:center;text-align:center;font-size:12.5px;color:var(--note-text);background:var(--note-bg);border-radius:12px;padding:6px 12px;max-width:90%}',
            '.retry{display:block;margin-top:8px;border:1px solid var(--chip-border);color:var(--chip-text);background:transparent;border-radius:16px;padding:4px 12px;font-size:13px}',
            '.typing{display:flex;gap:5px;padding:13px 16px}',
            '.dot{width:7px;height:7px;border-radius:999px;animation:bz-pulse 1.2s infinite ease-in-out}',
            '.dot:nth-child(1){background:var(--dot1)}.dot:nth-child(2){background:var(--dot2);animation-delay:.2s}.dot:nth-child(3){background:var(--dot3);animation-delay:.4s}',
            '@keyframes bz-pulse{0%,80%,100%{opacity:.35;transform:scale(.85)}40%{opacity:1;transform:scale(1)}}',
            '.chips{display:flex;flex-wrap:wrap;gap:8px;padding-inline-start:36px}',
            '.chip{min-height:32px;padding:6px 12px;border-radius:16px;border:1px solid var(--chip-border);background:transparent;color:var(--chip-text);font-size:13px;font-weight:500}',
            '.form{display:flex;align-items:center;gap:8px;padding:12px;border-top:1px solid var(--panel-border);background:var(--panel)}',
            '.field{flex:1;min-width:0;height:44px;border:1px solid var(--input-border);border-radius:22px;padding:11px 16px;font:inherit;font-size:16px;color:var(--input-text);background:var(--input-bg)}',
            '.field::placeholder{color:var(--placeholder)}',
            '.send{width:44px;height:44px;border-radius:999px;border:0;background:var(--send-bg);color:var(--send-icon);display:flex;align-items:center;justify-content:center;flex-shrink:0}',
            '.send:disabled{opacity:.45;cursor:not-allowed}',
            '.rtl .send svg{transform:scaleX(-1)}',
            '.foot{padding:0 12px 10px;text-align:center;font-size:11px;color:var(--meta);background:var(--panel)}',
            '.foot a{color:var(--brand);font-weight:700;text-decoration:none}',
            '.sr{position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0 0 0 0);white-space:nowrap}',
            '@media (max-width:480px){.panel{position:fixed;inset:0;width:auto;height:auto;border-radius:0;border:0;transform-origin:center}.root.open .launcher,.root.open .teaser{display:none}}',
            '@media (prefers-reduced-motion:reduce){.dot{animation:none}.panel,.launcher{transition:none}}'
        ].join('');
    }

    function render() {
        var settings = state.settings;
        var accent = /^#[0-9a-fA-F]{6}$/.test(settings.accent || '') ? settings.accent : '#034737';
        var side = settings.position === 'left' ? 'left' : 'right';
        var name = settings.assistant_name || '';
        var botzo = state.theme === 'botzo';
        var host = el('div', { id: 'botzo-widget' });
        var shadow = host.attachShadow({ mode: 'open' });

        shadow.appendChild(el('style', { text: styles(accent, side) }));

        ui.root = el('div', { 'class': 'root ' + state.theme + (LOCALE === 'ar' ? ' rtl' : ''), dir: LOCALE === 'ar' ? 'rtl' : 'ltr', lang: LOCALE });

        ui.launcherIcon = botzo ? icon('launcher') : svg(['M21 12a8 8 0 0 1-11.6 7.1L4 20l1-4.6A8 8 0 1 1 21 12z', 'M8.5 11h.01M12 11h.01M15.5 11h.01'], 28);
        ui.closeIcon = svg(['M6 6l12 12M18 6L6 18'], 24);
        ui.badge = el('span', { 'class': 'badge', hidden: '' });
        ui.launcher = el('button', { type: 'button', 'class': 'launcher', 'aria-label': TEXT.open, 'aria-expanded': 'false', onclick: toggle }, [ui.launcherIcon, ui.badge]);

        ui.teaser = el('button', { type: 'button', 'class': 'teaser', text: TEXT.teaser, onclick: toggle, hidden: '' });

        var headAvatar = el('div', { 'class': 'head-avatar', 'aria-hidden': 'true' }, [
            botzo ? icon('header') : (name.trim().charAt(0) || 'B').toUpperCase()
        ]);
        var close = el('button', { type: 'button', 'class': 'close', 'aria-label': TEXT.close, onclick: toggle }, [svg(['M6 6l12 12M18 6L6 18'], 20)]);

        ui.body = el('div', { 'class': 'body', 'aria-live': 'polite' });
        ui.field = el('input', { 'class': 'field', id: 'bz-field', type: 'text', maxlength: String(settings.max_length || 500), placeholder: TEXT.placeholder, autocomplete: 'off', oninput: syncSend });
        ui.send = el('button', { type: 'submit', 'class': 'send', 'aria-label': TEXT.send, disabled: '' }, [svg(['M5 12h14M13 6l6 6-6 6'], 20)]);

        var foot = el('div', { 'class': 'foot' }, [
            TEXT.notice + ' · ' + TEXT.poweredBy + ' ',
            el('a', { href: 'https://botzo.net', target: '_blank', rel: 'noopener', text: 'botzo' })
        ]);

        ui.panel = el('section', { 'class': 'panel', role: 'dialog', 'aria-label': TEXT.label, hidden: '', onkeydown: function (event) {
            if (event.key === 'Escape') {
                toggle();
            }
        } }, [
            el('div', { 'class': 'head' }, [
                headAvatar,
                el('div', { 'class': 'who' }, [
                    el('div', { 'class': 'name', text: name }),
                    el('div', { 'class': 'status', text: TEXT.online })
                ]),
                close
            ]),
            ui.body,
            el('form', { 'class': 'form', onsubmit: function (event) {
                event.preventDefault();
                send(ui.field.value);
            } }, [
                el('label', { 'for': 'bz-field', 'class': 'sr', text: TEXT.input }),
                ui.field,
                ui.send
            ]),
            foot
        ]);

        ui.root.appendChild(ui.panel);
        ui.root.appendChild(ui.teaser);
        ui.root.appendChild(ui.launcher);
        shadow.appendChild(ui.root);
        document.body.appendChild(host);

        if (!readStore('sessionStorage', TEASER_KEY) && state.messages.length === 0) {
            setTimeout(function () {
                if (!state.open) {
                    ui.teaser.removeAttribute('hidden');
                }
            }, 4000);
        }

        drawMessages();
        poll();
    }

    function dayLabel(date) {
        var today = new Date();
        var yesterday = new Date(today.getFullYear(), today.getMonth(), today.getDate() - 1);
        if (date.toDateString() === today.toDateString()) {
            return TEXT.today;
        }
        if (date.toDateString() === yesterday.toDateString()) {
            return TEXT.yesterday;
        }
        return date.toLocaleDateString(LOCALE === 'ar' ? 'ar' : 'en', { day: 'numeric', month: 'long' });
    }

    function timeLabel(date) {
        return date.toLocaleTimeString(LOCALE === 'ar' ? 'ar' : 'en', { hour: '2-digit', minute: '2-digit' });
    }

    function botAvatar(show, agentName) {
        var avatar = el('span', { 'class': 'mini' + (show ? '' : ' blank') + (agentName !== undefined ? ' person' : ''), 'aria-hidden': 'true' });
        if (show) {
            if (agentName !== undefined) {
                // A team member is a person: their initial, never the bot icon.
                avatar.textContent = ((agentName || '').trim().charAt(0) || '•').toUpperCase();
            } else if (state.theme === 'botzo') {
                avatar.appendChild(icon('bot'));
            } else {
                avatar.textContent = ((state.settings.assistant_name || '').trim().charAt(0) || 'B').toUpperCase();
            }
        }
        return avatar;
    }

    function drawMessages() {
        var body = ui.body;
        var lastDay = null;
        var previousSide = null;
        body.textContent = '';

        var items = [{ from: 'assistant', text: state.settings.welcome_text || TEXT.welcome, welcome: true }].concat(state.messages);

        items.forEach(function (message) {
            var date = message.at ? new Date(message.at) : new Date();
            var day = date.toDateString();
            if (day !== lastDay) {
                body.appendChild(el('div', { 'class': 'date', text: dayLabel(date) }));
                lastDay = day;
                previousSide = null;
            }

            if (message.from === 'visitor') {
                body.appendChild(el('div', { 'class': 'row user' }, [
                    el('div', { 'class': 'msg' + (message.pending ? ' pending' : ''), text: message.text }),
                    el('span', { 'class': 'meta' }, [timeLabel(date) + (message.pending ? '' : ' ✓✓')])
                ]));
                previousSide = 'user';
                return;
            }

            var bubble = el('div', { 'class': 'msg' + (message.from === 'agent' ? ' agent' : '') });
            if (message.from === 'agent') {
                bubble.appendChild(el('span', { 'class': 'who-label', text: (message.name ? message.name + ' · ' : '') + TEXT.team }));
            }
            bubble.appendChild(document.createTextNode(message.text));
            var speaker = message.from === 'agent' ? 'agent:' + (message.name || '') : 'bot';
            body.appendChild(el('div', { 'class': 'row bot' }, [
                botAvatar(previousSide !== speaker, message.from === 'agent' ? (message.name || '') : undefined),
                bubble
            ]));
            previousSide = speaker;

            if (message.welcome) {
                var hasVisitorMessage = state.messages.some(function (item) {
                    return item.from === 'visitor';
                });
                var suggestions = (state.settings.suggestions || []).filter(function (item) {
                    return typeof item === 'string' && item.trim() !== '';
                });
                if (!hasVisitorMessage && !state.sending && suggestions.length) {
                    body.appendChild(el('div', { 'class': 'chips' }, suggestions.map(function (item) {
                        return el('button', { type: 'button', 'class': 'chip', text: item, onclick: function () {
                            send(item);
                            // The chips disappear once sent; keep focus (and Esc) inside the panel.
                            ui.field.focus();
                        } });
                    })));
                }
            }
        });

        if (state.typing) {
            body.appendChild(el('div', { 'class': 'row bot' }, [
                botAvatar(previousSide !== 'bot'),
                el('div', { 'class': 'msg typing', role: 'status', 'aria-label': TEXT.typing }, [
                    el('span', { 'class': 'dot' }), el('span', { 'class': 'dot' }), el('span', { 'class': 'dot' })
                ])
            ]));
        }

        if (state.failedText) {
            var failed = el('div', { 'class': 'msg' }, [TEXT.failed]);
            failed.appendChild(el('button', { type: 'button', 'class': 'retry', text: TEXT.retry, onclick: function () {
                var text = state.failedText;
                state.failedText = null;
                send(text);
            } }));
            body.appendChild(el('div', { 'class': 'row bot' }, [botAvatar(previousSide !== 'bot'), failed]));
        } else if (state.note) {
            body.appendChild(el('div', { 'class': 'note', text: state.note }));
        }

        body.scrollTop = body.scrollHeight;
    }

    function syncSend() {
        if (state.sending || ui.field.value.trim() === '') {
            ui.send.setAttribute('disabled', '');
        } else {
            ui.send.removeAttribute('disabled');
        }
    }

    function setUnread(count) {
        state.unread = count;
        if (count > 0) {
            ui.badge.textContent = count > 9 ? '9+' : String(count);
            ui.badge.removeAttribute('hidden');
            ui.launcher.setAttribute('aria-label', TEXT.open + ' (' + count + ' ' + TEXT.unread + ')');
        } else {
            ui.badge.setAttribute('hidden', '');
        }
    }

    function toggle() {
        state.open = !state.open;
        ui.root.classList.toggle('open', state.open);
        ui.launcher.classList.toggle('is-open', state.open);
        ui.launcher.setAttribute('aria-expanded', state.open ? 'true' : 'false');
        ui.launcher.setAttribute('aria-label', state.open ? TEXT.close : TEXT.open);
        ui.launcher.replaceChild(state.open ? ui.closeIcon : ui.launcherIcon, ui.launcher.firstChild);
        ui.teaser.setAttribute('hidden', '');
        writeStore('sessionStorage', TEASER_KEY, '1');
        clearTimeout(state.hideTimer);

        if (state.open) {
            setUnread(0);
            ui.panel.removeAttribute('hidden');
            drawMessages();
            requestAnimationFrame(function () {
                ui.panel.classList.add('show');
            });
            ui.field.focus();
        } else {
            ui.panel.classList.remove('show');
            state.hideTimer = setTimeout(function () {
                ui.panel.setAttribute('hidden', '');
            }, 200);
            ui.launcher.focus();
        }
        poll();
    }

    function send(raw, retried) {
        var text = String(raw || '').trim().slice(0, state.settings.max_length || 500);
        if (text === '' || state.sending) {
            return;
        }

        state.sending = true;
        state.typing = true;
        state.note = null;
        state.failedText = null;
        if (!retried) {
            state.messages.push({ from: 'visitor', text: text, pending: true });
            ui.field.value = '';
        }
        syncSend();
        drawMessages();

        request('POST', '/messages', { text: text }).then(function (result) {
            if (result.status === 401 && !retried) {
                state.token = null;
                writeStore('localStorage', TOKEN_KEY, null);
                state.sending = false;
                return openSession().then(function (ok) {
                    if (ok) {
                        // The visitor's pending bubble is still shown; resend without adding another.
                        send(text, true);
                        return;
                    }
                    fail(text, null);
                });
            }

            if (result.status === 429) {
                fail(null, result.data.error === 'hourly' ? TEXT.hourly : TEXT.tooFast, text);
                return;
            }

            if (result.status !== 200) {
                fail(text, null);
                return;
            }

            state.messages = state.messages.filter(function (item) {
                return !item.pending;
            });
            addMessages(result.data.messages || []);
            state.note = result.data.awaiting_team ? TEXT.awaiting : null;
            done();
        }).catch(function () {
            fail(text, null);
        });
    }

    /**
     * A send that did not go through: offer a retry (failedText) or explain a limit (note),
     * and give the visitor's text back in the input for limits.
     */
    function fail(failedText, note, keepText) {
        state.messages = state.messages.filter(function (item) {
            return !item.pending;
        });
        state.failedText = failedText;
        state.note = note;
        if (keepText && ui.field.value === '') {
            ui.field.value = keepText;
        }
        done();
    }

    function done() {
        state.sending = false;
        state.typing = false;
        syncSend();
        drawMessages();
    }

    // Open: check for team replies every poll interval. Closed: check slowly, only once a
    // conversation exists, to show the unread badge.
    function poll() {
        if (state.pollTimer) {
            clearInterval(state.pollTimer);
            state.pollTimer = null;
        }

        var run = function () {
            if (document.hidden || state.sending || !state.token || state.lastId === 0) {
                return;
            }
            request('GET', '/messages?after=' + state.lastId).then(function (result) {
                if (result.status !== 200 || !result.data.messages || !result.data.messages.length) {
                    return;
                }
                var fresh = addMessages(result.data.messages);
                if (fresh > 0) {
                    state.note = null;
                }
                if (state.open) {
                    drawMessages();
                } else if (fresh > 0) {
                    setUnread(state.unread + fresh);
                }
            }).catch(function () {});
        };

        if (state.open) {
            run();
        }
        var seconds = state.open ? Math.max(5, state.settings.poll_interval || 15) : CLOSED_POLL_SECONDS;
        state.pollTimer = setInterval(run, seconds * 1000);
    }

    function start() {
        openSession().then(function (ok) {
            if (ok) {
                render();
            }
        });
    }

    if (document.readyState === 'complete') {
        start();
    } else {
        window.addEventListener('load', start);
    }
})();
