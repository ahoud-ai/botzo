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
        theme: 'mochi',
        inCall: false,
        resumeCall: false,
        revealing: false,
        revealTimer: null,
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
            state.theme = ['classic', 'botzo'].indexOf(state.settings.theme) !== -1 ? state.settings.theme : 'mochi';
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
    function addMessages(list, live) {
        var fresh = 0;
        list.forEach(function (message) {
            if (!message || typeof message.id !== 'number') {
                return;
            }
            var exists = state.messages.some(function (item) {
                return item.id === message.id;
            });
            if (!exists) {
                if (live && state.theme === 'mochi' && message.from !== 'visitor') {
                    message.revealed = false;
                }
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
        if (state.theme === 'mochi') {
            mochi.draw();
            mochi.reveal();
            return;
        }

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

    function toggle(force) {
        if (state.theme === 'mochi') {
            toggleMochi(force === true);
            return;
        }

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

        request('POST', '/messages', {
            text: text,
            page_url: window.location.href.slice(0, 2000),
            page_title: (document.title || '').slice(0, 300)
        }).then(function (result) {
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
            addMessages(result.data.messages || [], true);
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
            if (document.hidden || state.sending || state.inCall || !state.token || state.lastId === 0) {
                return;
            }
            request('GET', '/messages?after=' + state.lastId).then(function (result) {
                if (result.status !== 200 || !result.data.messages || !result.data.messages.length) {
                    return;
                }
                var fresh = addMessages(result.data.messages, true);
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

    // ---------------------------------------------------------------------------------------
    // Mochi look: the animated mascot, a dark glass panel with a welcome and a conversation
    // state, typed-out replies, the pricing card and page navigation.
    // ---------------------------------------------------------------------------------------

    var MOCHI_TEXT = {
        ar: {
            bubble: 'هلا! عندك سؤال؟ 👋',
            talkToMe: 'تكلّم معي',
            welcomeTitle: 'هلا وغلا في {business} 👋',
            welcomeSub: 'أنا {name}، مساعدك الذكي — أعرّفك على خدماتنا وأرد على كل أسئلتك',
            pressTalk: 'اضغط وتكلم',
            placeholder: 'اكتب سؤالك…',
            replyByText: 'رد بالكتابة',
            end: 'إنهاء',
            assistantOf: '{name} — مساعد {business}',
            online: 'متصل الآن',
            typing: 'يكتب الآن…',
            speaking: 'يتكلم…',
            newChat: 'محادثة جديدة',
            mostRequested: 'الأكثر طلباً',
            perMonth: 'ر.س / شهر',
            startNow: 'ابدأ الآن',
            failed: 'النت فيه شوية مشكلة، جرّب مرة ثانية',
            mic: 'تكلّم مع المساعد',
            voiceSoon: 'المكالمة الصوتية جاية قريب، اكتب لي الحين 👇'
        },
        en: {
            bubble: 'Hi! Got a question? 👋',
            talkToMe: 'Talk to me',
            welcomeTitle: 'Welcome to {business} 👋',
            welcomeSub: "I'm {name}, your smart assistant. Ask me anything.",
            pressTalk: 'Press and talk',
            placeholder: 'Type your question…',
            replyByText: 'Reply by typing',
            end: 'End',
            assistantOf: '{name} — {business} assistant',
            online: 'Online now',
            typing: 'Typing…',
            speaking: 'Speaking…',
            newChat: 'New chat',
            mostRequested: 'Most popular',
            perMonth: 'SAR / month',
            startNow: 'Start now',
            failed: 'Connection problem, please try again',
            mic: 'Talk to the assistant',
            voiceSoon: 'Voice calls are coming soon. Type to me for now 👇'
        }
    }[LOCALE];

    var MOCHI_SEEN_KEY = 'botzo_widget_mochi_bubble_' + KEY;
    var OPEN_KEY = 'botzo_widget_open_' + KEY;
    var REDUCED_MOTION = !!(window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches);

    function fill(template, values) {
        return template.replace(/\{(\w+)\}/g, function (all, key) {
            return values[key] !== undefined ? values[key] : all;
        });
    }

    // botzo-mochi.svg, used as supplied; a customer's two colours replace the headset gradient.
    var mochiCount = 0;
    function mochiMarkup(primary, secondary) {
        var uid = 'bzm' + (++mochiCount);
        var custom = /^#[0-9a-fA-F]{6}$/.test(primary) && /^#[0-9a-fA-F]{6}$/.test(secondary)
            && (primary.toUpperCase() !== '#22D366' || secondary.toUpperCase() !== '#2F7BFF');
        var start = custom ? primary : '#3CF28E';
        var end = custom ? secondary : '#2F7BFF';
        var tip = custom ? primary : '#22D366';
        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 240 230" aria-hidden="true" class="mochi-svg">'
            + '<defs><radialGradient id="' + uid + 'b" cx="0.4" cy="0.3" r="0.8"><stop offset="0" stop-color="#FFFFFF"/><stop offset="0.55" stop-color="#F1F3F6"/><stop offset="0.85" stop-color="#D3D8DF"/><stop offset="1" stop-color="#B9C0CA"/></radialGradient>'
            + '<linearGradient id="' + uid + 'g" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="' + start + '"/><stop offset="1" stop-color="' + end + '"/></linearGradient></defs>'
            + '<ellipse cx="120" cy="220" rx="80" ry="8" fill="#000" opacity="0.45"/>'
            + '<path d="M120 42 C192 42 228 104 228 154 C228 198 190 216 120 216 C50 216 12 198 12 154 C12 104 48 42 120 42 Z" fill="url(#' + uid + 'b)"/>'
            + '<ellipse cx="86" cy="78" rx="30" ry="14" fill="#FFFFFF" opacity="0.9" transform="rotate(-20 86 78)"/>'
            + '<path d="M30 120 C 34 20 206 20 210 120" fill="none" stroke="url(#' + uid + 'g)" stroke-width="9" stroke-linecap="round"/>'
            + '<rect x="14" y="110" width="26" height="46" rx="13" fill="url(#' + uid + 'g)"/>'
            + '<rect x="200" y="110" width="26" height="46" rx="13" fill="url(#' + uid + 'g)"/>'
            + '<path d="M214 156 Q214 190 170 192" stroke="#9AA6B6" stroke-width="4" fill="none" stroke-linecap="round"/>'
            + '<circle cx="166" cy="192" r="6" fill="' + tip + '"/>'
            + '<g class="mochi-face"><g class="mochi-eyes"><ellipse cx="90" cy="132" rx="11" ry="15" fill="#0E0F12"/><ellipse cx="150" cy="132" rx="11" ry="15" fill="#0E0F12"/><circle cx="94" cy="126" r="3.5" fill="#FFFFFF"/><circle cx="154" cy="126" r="3.5" fill="#FFFFFF"/></g>'
            + '<path class="mochi-mouth" d="M104 162 Q120 176 136 162" stroke="#0E0F12" stroke-width="5" fill="none" stroke-linecap="round"/></g></svg>';
    }

    var mochis = [];
    function mochiEl(className) {
        var wrap = el('span', { 'class': 'mochi ' + className, 'aria-hidden': 'true' });
        var breath = el('span', { 'class': 'mochi-breath' });
        breath.innerHTML = mochiMarkup(state.settings.accent || '#22D366', state.settings.accent_secondary || '#2F7BFF');
        wrap.appendChild(breath);
        mochis.push(wrap);
        return wrap;
    }

    // Eyes follow the cursor (rAF-throttled); the body tilts a little toward it.
    var eyeFrame = null;
    var lastPointer = null;
    function trackEyes(event) {
        lastPointer = event ? { x: event.clientX, y: event.clientY } : null;
        if (eyeFrame || REDUCED_MOTION) {
            return;
        }
        eyeFrame = requestAnimationFrame(function () {
            eyeFrame = null;
            mochis.forEach(function (wrap) {
                var svgNode = wrap.querySelector('svg');
                var face = wrap.querySelector('.mochi-face');
                if (!svgNode || !face || !wrap.isConnected) {
                    return;
                }
                if (!lastPointer) {
                    face.style.transform = '';
                    svgNode.style.transform = '';
                    return;
                }
                var box = wrap.getBoundingClientRect();
                var nx = Math.max(-1, Math.min(1, (lastPointer.x - (box.left + box.width / 2)) / 320));
                var ny = Math.max(-1, Math.min(1, (lastPointer.y - (box.top + box.height / 2)) / 320));
                face.style.transform = 'translate(' + (nx * 10).toFixed(2) + 'px,' + (ny * 7).toFixed(2) + 'px)';
                svgNode.style.transform = 'rotate(' + (nx * 5).toFixed(2) + 'deg)';
            });
        });
    }

    function setSpeaking(on) {
        mochis.forEach(function (wrap) {
            wrap.classList.toggle('is-speaking', !!on);
        });
    }

    function mochiStyles(accent) {
        var side = state.settings.position === 'right' ? 'right' : 'left';
        return [
            ':host{all:initial}',
            '*{box-sizing:border-box}',
            '.root{position:fixed;bottom:20px;' + side + ':20px;z-index:2147483000;font-family:"IBM Plex Sans Arabic",-apple-system,BlinkMacSystemFont,"Segoe UI",Tahoma,Arial,sans-serif;color:#F2F5F9;font-size:14px;line-height:1.7;--accent:' + accent + '}',
            'button{font:inherit;cursor:pointer;color:inherit}',
            'button:focus-visible,a:focus-visible{outline:3px solid #5AA8FF;outline-offset:2px}',
            '.sr{position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0 0 0 0);white-space:nowrap}',
            // mascot and its motion
            '.mochi{display:inline-block;line-height:0}',
            '.mochi-breath{display:block;transform-origin:50% 100%;animation:bz-breath 3s ease-in-out infinite}',
            '.mochi-svg{display:block;width:100%;height:auto;overflow:visible;transition:transform .12s ease-out}',
            '.mochi-face{transition:transform .12s ease-out}',
            '.mochi-eyes{transform-box:fill-box;transform-origin:center;animation:bz-blink 4.2s infinite}',
            '.mochi-mouth{transform-box:fill-box;transform-origin:center}',
            '.mochi.is-speaking .mochi-mouth{animation:bz-talk .28s ease-in-out infinite alternate}',
            '@keyframes bz-breath{0%,100%{transform:none}45%{transform:scale(1.04,.95) translateY(4px)}70%{transform:scale(.98,1.03) translateY(-6px)}}',
            '@keyframes bz-blink{0%,94%,100%{transform:scaleY(1)}96%{transform:scaleY(.1)}}',
            '@keyframes bz-talk{from{transform:scaleY(1)}to{transform:scaleY(1.9)}}',
            // closed state
            '.launcher-wrap{position:relative;display:flex;flex-direction:column;align-items:center;gap:2px}',
            '.launcher{width:84px;height:84px;padding:0;border:0;background:transparent;display:flex;align-items:flex-end;justify-content:center;transition:transform .15s ease}',
            '.launcher:hover{transform:translateY(-2px)}',
            '.launcher .mochi{width:84px}',
            '.launcher-label{font-size:13px;color:#DCE3EC;text-shadow:0 1px 3px rgba(0,0,0,.55);pointer-events:none}',
            '.bubble{position:absolute;bottom:100%;margin-bottom:6px;' + side + ':-4px;white-space:nowrap;background:#fff;color:#0A0E16;font-size:12px;font-weight:700;border:0;border-radius:12px 12px 12px 4px;padding:8px 12px;box-shadow:0 8px 24px rgba(0,0,0,.25)}',
            '.rtl .bubble{border-radius:12px 12px 4px 12px}',
            '.bubble[hidden],.launcher-wrap[hidden]{display:none}',
            '.badge{position:absolute;top:2px;' + (side === 'left' ? 'right' : 'left') + ':4px;min-width:22px;height:22px;padding:0 6px;border-radius:999px;background:#FF4D4F;color:#fff;font-size:12px;font-weight:700;line-height:18px;text-align:center;border:2px solid #0A0E16}',
            '.badge[hidden]{display:none}',
            // panel
            '.panel{position:absolute;bottom:0;' + side + ':0;width:400px;height:min(680px,calc(100vh - 40px));border-radius:28px;'
                + 'background:linear-gradient(rgba(255,255,255,.07),rgba(255,255,255,.07)),rgba(12,15,21,.9);'
                + '-webkit-backdrop-filter:blur(24px);backdrop-filter:blur(24px);border:1px solid rgba(255,255,255,.16);box-shadow:0 30px 80px rgba(0,0,0,.6);'
                + 'display:flex;flex-direction:column;overflow:hidden;opacity:0;transform:scale(.9);transform-origin:bottom ' + side + ';transition:opacity .2s ease,transform .2s ease}',
            '.panel.show{opacity:1;transform:scale(1)}',
            '.panel[hidden]{display:none}',
            '.glow{position:absolute;inset:-30% -20% auto;height:70%;background:radial-gradient(closest-side,rgba(34,211,102,.35),rgba(47,123,255,.18),transparent);pointer-events:none;filter:blur(10px)}',
            '.icon-btn{width:32px;height:32px;border-radius:999px;border:0;background:rgba(255,255,255,.08);display:flex;align-items:center;justify-content:center;flex-shrink:0}',
            '.icon-btn:hover{background:rgba(255,255,255,.14)}',
            // welcome
            '.welcome{position:relative;flex:1;display:flex;flex-direction:column;align-items:center;text-align:center;padding:22px 22px 0;overflow-y:auto}',
            '.welcome[hidden],.convo[hidden]{display:none}',
            '.welcome .icon-btn{position:absolute;top:16px;inset-inline-end:16px}',
            '.w-title{margin:18px 0 0;font-size:24px;font-weight:700;line-height:1.4}',
            '.w-sub{margin:8px 0 0;font-size:14px;color:#A3ADBD;max-width:320px}',
            '.w-mochi{margin:auto 0 0;width:230px;max-width:62%;filter:drop-shadow(0 0 40px rgba(34,211,102,.28))}',
            '.w-talk{margin-top:10px;display:inline-flex;align-items:center;gap:6px;font-size:13px;color:#A3ADBD}',
            '.chips{display:flex;flex-wrap:wrap;justify-content:center;gap:8px;margin:12px 0 0}',
            '.chip{min-height:34px;padding:6px 14px;border-radius:18px;border:1px solid rgba(255,255,255,.18);background:rgba(255,255,255,.06);font-size:13px;transition:border-color .15s}',
            '.chip:hover{border-color:#22D366}',
            // conversation
            '.convo{position:relative;flex:1;display:flex;flex-direction:column;min-height:0}',
            '.c-head{position:relative;display:flex;align-items:center;gap:10px;padding:14px 14px 12px;border-bottom:1px solid rgba(255,255,255,.08)}',
            '.c-head .mochi{width:46px;flex-shrink:0}',
            '.c-who{flex:1;min-width:0}.c-name{font-weight:700;font-size:15px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.c-status{font-size:12px;color:#22D366}',
            '.c-body{position:relative;flex:1;overflow-y:auto;padding:16px;display:flex;flex-direction:column;gap:10px;overscroll-behavior:contain}',
            '.row{display:flex;flex-direction:column;max-width:100%}',
            '.row.user{align-self:flex-end;align-items:flex-end}',
            '.row.bot{align-self:flex-start;align-items:flex-start}',
            '.msg{max-width:290px;padding:10px 14px;white-space:pre-wrap;overflow-wrap:anywhere;font-size:14px;line-height:1.7}',
            '.user .msg{background:var(--user-bg);color:#0A0E16;font-weight:500;border-radius:20px 20px 20px 6px}',
            '.rtl .user .msg{border-radius:20px 20px 6px 20px}',
            '.bot .msg{background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.12);border-radius:20px 20px 6px 20px}',
            '.rtl .bot .msg{border-radius:20px 20px 20px 6px}',
            '.agent-label{display:block;font-size:12px;font-weight:600;color:#A3ADBD;margin-bottom:2px}',
            '.meta{font-size:11px;color:#7C8799;margin-top:3px}',
            '.pending{opacity:.65}',
            '.note{align-self:center;text-align:center;font-size:12.5px;color:#A3ADBD;background:rgba(255,255,255,.06);border-radius:12px;padding:6px 12px;max-width:90%}',
            '.w-note{margin:12px 0 0}.w-note[hidden]{display:none}',
            '.retry{display:block;margin-top:8px;border:1px solid #22D366;color:#22D366;background:transparent;border-radius:16px;padding:4px 12px;font-size:13px}',
            '.typing{display:flex;gap:5px;padding:13px 16px}',
            '.dot{width:7px;height:7px;border-radius:999px;animation:bz-pulse 1.2s infinite ease-in-out}',
            '.dot:nth-child(1){background:#22D366}.dot:nth-child(2){background:#5AA8FF;animation-delay:.2s}.dot:nth-child(3){background:#2F7BFF;animation-delay:.4s}',
            '@keyframes bz-pulse{0%,80%,100%{opacity:.35;transform:scale(.85)}40%{opacity:1;transform:scale(1)}}',
            '.card{margin-top:10px;border-radius:16px;border:1px solid rgba(34,211,102,.4);background:linear-gradient(135deg,rgba(34,211,102,.2),rgba(47,123,255,.2));padding:12px;white-space:normal}',
            '.card-top{display:flex;align-items:center;justify-content:space-between;gap:8px;font-weight:700}',
            '.card-badge{font-size:11px;font-weight:700;color:#0A0E16;background:#22D366;border-radius:8px;padding:2px 8px;white-space:nowrap}',
            '.card-price{margin-top:8px;font-size:20px;font-weight:700}.card-price small{font-size:12px;font-weight:400;color:#A3ADBD;margin-inline-start:6px}',
            '.card-cta{display:block;margin-top:10px;text-align:center;border-radius:12px;background:#22D366;color:#0A0E16;font-weight:700;padding:9px;text-decoration:none}',
            '.c-chips{display:flex;flex-wrap:wrap;justify-content:flex-end;gap:8px;padding:8px 14px 0}',
            '.rtl .c-chips{justify-content:flex-end}',
            // input pill
            '.form{display:flex;align-items:center;gap:8px;margin:10px 14px 0;padding:4px;border-radius:26px;border:1px solid rgba(255,255,255,.16);background:rgba(255,255,255,.05)}',
            '.field{flex:1;min-width:0;height:40px;border:0;outline:none;background:transparent;padding:0 10px;font:inherit;font-size:16px;color:#F2F5F9}',
            '.form:focus-within{border-color:rgba(34,211,102,.7);box-shadow:0 0 0 3px rgba(34,211,102,.18)}',
            '.field::placeholder{color:#7C8799}',
            '.round{width:40px;height:40px;border-radius:999px;border:0;display:flex;align-items:center;justify-content:center;flex-shrink:0}',
            '.send{background:var(--accent);color:#0A0E16;box-shadow:0 0 16px rgba(34,211,102,.6)}',
            '.send:disabled{opacity:.45;cursor:not-allowed;box-shadow:none}',
            '.mic{background:rgba(255,255,255,.1);color:#F2F5F9}',
            '.mic.green{background:var(--accent);color:#0A0E16;box-shadow:0 0 16px rgba(34,211,102,.6)}',
            '.foot{display:flex;align-items:center;justify-content:space-between;gap:8px;padding:8px 18px 12px;font-size:12px;color:#A3ADBD}',
            '.foot.center{justify-content:center}',
            '.foot[hidden]{display:none}',
            '.foot button{border:0;background:transparent;color:#A3ADBD;padding:4px}',
            '.foot button:hover{color:#F2F5F9}',
            '.foot a{color:var(--accent);font-weight:700;text-decoration:none}',
            '@media (max-width:480px){.panel{position:fixed;inset:0;width:auto;height:auto;border-radius:0;border:0;transform-origin:center}}',
            '@media (prefers-reduced-motion:reduce){.mochi-breath,.mochi-eyes,.dot,.mochi.is-speaking .mochi-mouth{animation:none}.panel,.launcher,.mochi-svg,.mochi-face{transition:none}}'
        ].join('');
    }

    var mochi = {
        render: function () {
            var settings = state.settings;
            var accent = /^#[0-9a-fA-F]{6}$/.test(settings.accent || '') ? settings.accent : '#22D366';
            var host = el('div', { id: 'botzo-widget' });
            var shadow = host.attachShadow({ mode: 'open' });
            var name = settings.assistant_name || '';
            var business = settings.business_label || name;
            var rtl = LOCALE === 'ar';

            shadow.appendChild(el('style', { text: mochiStyles(accent) + voiceStyles() }));
            ui.root = el('div', { 'class': 'root mochi-theme' + (rtl ? ' rtl' : ''), dir: rtl ? 'rtl' : 'ltr', lang: LOCALE });
            ui.root.style.setProperty('--user-bg', accent);

            // ① closed: Mochi, the label and a one-time bubble
            ui.badge = el('span', { 'class': 'badge', hidden: '' });
            ui.launcher = el('button', { type: 'button', 'class': 'launcher', 'aria-label': TEXT.open, 'aria-expanded': 'false', onclick: toggle }, [mochiEl('launcher-mochi'), ui.badge]);
            ui.teaser = el('button', { type: 'button', 'class': 'bubble', text: MOCHI_TEXT.bubble, hidden: '', onclick: toggle });
            ui.launcherWrap = el('div', { 'class': 'launcher-wrap' }, [ui.teaser, ui.launcher, el('span', { 'class': 'launcher-label', text: MOCHI_TEXT.talkToMe })]);

            // ② welcome
            ui.welcome = el('div', { 'class': 'welcome' }, [
                el('button', { type: 'button', 'class': 'icon-btn', 'aria-label': TEXT.close, onclick: toggle }, [svg(['M6 6l12 12M18 6L6 18'], 16)]),
                el('h2', { 'class': 'w-title', text: fill(MOCHI_TEXT.welcomeTitle, { business: business }) }),
                el('p', { 'class': 'w-sub', text: settings.welcome_text || fill(MOCHI_TEXT.welcomeSub, { name: name }) }),
                mochiEl('w-mochi'),
                settings.voice ? el('span', { 'class': 'w-talk' }, [svg(['M12 3a3 3 0 0 0-3 3v5a3 3 0 0 0 6 0V6a3 3 0 0 0-3-3z', 'M5 11a7 7 0 0 0 14 0M12 18v3'], 14), MOCHI_TEXT.pressTalk]) : null
            ]);
            ui.welcomeChips = el('div', { 'class': 'chips' });
            ui.welcomeNote = el('p', { 'class': 'note w-note', role: 'status', hidden: '' });
            ui.welcome.appendChild(ui.welcomeChips);
            ui.welcome.appendChild(ui.welcomeNote);

            // ③ conversation
            ui.status = el('div', { 'class': 'c-status', text: MOCHI_TEXT.online });
            ui.body = el('div', { 'class': 'c-body', 'aria-live': 'polite' });
            ui.convChips = el('div', { 'class': 'c-chips' });
            ui.convo = el('div', { 'class': 'convo', hidden: '' }, [
                el('div', { 'class': 'c-head' }, [
                    el('span', { 'class': 'glow', 'aria-hidden': 'true' }),
                    mochiEl('head-mochi'),
                    el('div', { 'class': 'c-who' }, [
                        el('div', { 'class': 'c-name', text: fill(MOCHI_TEXT.assistantOf, { name: name, business: business }) }),
                        ui.status
                    ]),
                    el('button', { type: 'button', 'class': 'icon-btn', 'aria-label': MOCHI_TEXT.newChat, onclick: mochi.newChat }, [svg(['M12 5v14M5 12h14'], 16)]),
                    el('button', { type: 'button', 'class': 'icon-btn', 'aria-label': TEXT.close, onclick: toggle }, [svg(['M6 6l12 12M18 6L6 18'], 16)])
                ]),
                ui.body,
                ui.convChips
            ]);

            // input pill (shared by both states)
            ui.field = el('input', { 'class': 'field', id: 'bz-field', type: 'text', maxlength: String(settings.max_length || 500), placeholder: MOCHI_TEXT.placeholder, autocomplete: 'off', oninput: syncSend });
            ui.send = el('button', { type: 'submit', 'class': 'round send', 'aria-label': TEXT.send, disabled: '' }, [svg(['M12 19V5M5 12l7-7 7 7'], 18)]);
            ui.mic = settings.voice ? el('button', { type: 'button', 'class': 'round mic', 'aria-label': MOCHI_TEXT.mic, onclick: mochi.micPressed }, [svg(['M12 3a3 3 0 0 0-3 3v5a3 3 0 0 0 6 0V6a3 3 0 0 0-3-3z', 'M5 11a7 7 0 0 0 14 0M12 18v3'], 18)]) : null;
            ui.form = el('form', { 'class': 'form', onsubmit: function (event) {
                event.preventDefault();
                send(ui.field.value);
            } }, [el('label', { 'for': 'bz-field', 'class': 'sr', text: TEXT.input }), ui.mic, ui.field, ui.send]);

            ui.footWelcome = el('div', { 'class': 'foot' }, [
                el('button', { type: 'button', text: MOCHI_TEXT.replyByText, onclick: function () {
                    ui.field.focus();
                } }),
                settings.branding === false ? el('span') : el('span', { dir: 'ltr' }, ['Powered by ', el('a', { href: 'https://botzo.net', target: '_blank', rel: 'noopener', text: 'botzo' })]),
                el('button', { type: 'button', text: MOCHI_TEXT.end, onclick: toggle })
            ]);
            ui.footConvo = el('div', { 'class': 'foot center', hidden: '' }, settings.branding === false ? [] : [
                el('span', { dir: 'ltr' }, ['Powered by ', el('a', { href: 'https://botzo.net', target: '_blank', rel: 'noopener', text: 'botzo' })])
            ]);

            ui.panel = el('section', { 'class': 'panel', role: 'dialog', 'aria-label': TEXT.label, hidden: '', onkeydown: function (event) {
                if (event.key === 'Escape') {
                    toggle();
                }
            } }, [el('span', { 'class': 'glow', 'aria-hidden': 'true' }), ui.welcome, ui.convo, ui.callView = callView(), ui.form, ui.footWelcome, ui.footConvo]);

            ui.root.appendChild(ui.panel);
            ui.root.appendChild(ui.launcherWrap);
            shadow.appendChild(ui.root);
            document.body.appendChild(host);

            window.addEventListener('mousemove', trackEyes, { passive: true });
            document.addEventListener('mouseleave', function () {
                trackEyes(null);
            });

            if (!readStore('sessionStorage', MOCHI_SEEN_KEY)) {
                setTimeout(function () {
                    if (!state.open) {
                        ui.teaser.removeAttribute('hidden');
                    }
                }, 5000);
            }

            state.resumeCall = readStore('sessionStorage', RESUME_KEY) === '1' && !!settings.voice;
            mochi.draw();
            if (readStore('sessionStorage', OPEN_KEY) === '1' || state.resumeCall) {
                toggle(true);
            }
            poll();
        },

        // The welcome state until the visitor sends something, then the conversation.
        draw: function () {
            if (state.inCall) {
                return;
            }
            var conversation = state.messages.length > 0 || state.sending;
            if (ui.mic) {
                ui.mic.classList.toggle('green', !conversation);
            }
            ui.welcome.hidden = conversation;
            ui.convo.hidden = !conversation;
            ui.footWelcome.hidden = conversation;
            ui.footConvo.hidden = !conversation;

            var suggestions = (state.settings.suggestions || []).filter(function (item) {
                return typeof item === 'string' && item.trim() !== '';
            });
            var asked = state.messages.filter(function (m) {
                return m.from === 'visitor';
            }).map(function (m) {
                return m.text;
            });
            var chips = function (list) {
                return list.map(function (item) {
                    return el('button', { type: 'button', 'class': 'chip', text: item, onclick: function () {
                        send(item);
                        ui.field.focus();
                    } });
                });
            };

            ui.welcomeChips.textContent = '';
            chips(suggestions).forEach(function (chip) {
                ui.welcomeChips.appendChild(chip);
            });

            ui.convChips.textContent = '';
            if (!state.sending) {
                chips(suggestions.filter(function (item) {
                    return asked.indexOf(item) === -1;
                }).slice(0, 3)).forEach(function (chip) {
                    ui.convChips.appendChild(chip);
                });
            }

            ui.status.textContent = state.revealing ? MOCHI_TEXT.speaking : (state.typing ? MOCHI_TEXT.typing : MOCHI_TEXT.online);

            if (!conversation) {
                ui.welcomeNote.textContent = state.note || '';
                ui.welcomeNote.hidden = !state.note;
                return;
            }

            var body = ui.body;
            body.textContent = '';
            state.messages.forEach(function (message) {
                var date = message.at ? new Date(message.at) : new Date();
                if (message.from === 'visitor') {
                    body.appendChild(el('div', { 'class': 'row user' }, [
                        el('div', { 'class': 'msg' + (message.pending ? ' pending' : ''), text: message.text }),
                        el('span', { 'class': 'meta' }, [timeLabel(date) + (message.pending ? '' : ' ✓✓')])
                    ]));
                    return;
                }

                var bubble = el('div', { 'class': 'msg' });
                if (message.from === 'agent') {
                    bubble.appendChild(el('span', { 'class': 'agent-label', text: (message.name ? message.name + ' · ' : '') + TEXT.team }));
                }
                var textNode = document.createTextNode(message.revealed === false ? (message.shown || '') : message.text);
                bubble.appendChild(textNode);
                message.node = textNode;
                if (message.revealed !== false && message.action && message.action.type === 'pricing') {
                    bubble.appendChild(mochi.card(message.action));
                }
                body.appendChild(el('div', { 'class': 'row bot' }, [bubble]));
            });

            if (state.typing) {
                body.appendChild(el('div', { 'class': 'row bot' }, [
                    el('div', { 'class': 'msg typing', role: 'status', 'aria-label': TEXT.typing }, [
                        el('span', { 'class': 'dot' }), el('span', { 'class': 'dot' }), el('span', { 'class': 'dot' })
                    ])
                ]));
            }

            if (state.failedText) {
                var failed = el('div', { 'class': 'msg' }, [MOCHI_TEXT.failed]);
                failed.appendChild(el('button', { type: 'button', 'class': 'retry', text: TEXT.retry, onclick: function () {
                    var text = state.failedText;
                    state.failedText = null;
                    send(text);
                } }));
                body.appendChild(el('div', { 'class': 'row bot' }, [failed]));
            } else if (state.note) {
                body.appendChild(el('div', { 'class': 'note', text: state.note }));
            }

            if (state.resumeCall && state.settings.voice) {
                body.appendChild(el('div', { 'class': 'note' }, [el('button', { type: 'button', 'class': 'resume', text: VOICE_TEXT.resume, onclick: mochi.micPressed })]));
            }

            body.scrollTop = body.scrollHeight;
        },

        card: function (card) {
            var price = Number(card.price || 0).toLocaleString(LOCALE === 'ar' ? 'ar-SA' : 'en-US', { maximumFractionDigits: 0 });
            var name = LOCALE === 'ar' ? card.name : (card.name_en || card.name);
            var href = safeUrl(card.cta_url || '/signup');
            return el('div', { 'class': 'card' }, [
                el('div', { 'class': 'card-top' }, [el('span', { text: name }), el('span', { 'class': 'card-badge', text: MOCHI_TEXT.mostRequested })]),
                el('div', { 'class': 'card-price' }, [price, el('small', { text: MOCHI_TEXT.perMonth })]),
                href ? el('a', { 'class': 'card-cta', href: href, text: MOCHI_TEXT.startNow }) : null
            ]);
        },

        // Reveal new assistant replies a few characters at a time, then run their action.
        reveal: function () {
            var next = state.messages.filter(function (m) {
                return m.revealed === false;
            })[0];
            if (!next || state.revealTimer) {
                return;
            }

            state.revealing = true;
            setSpeaking(true);
            next.shown = '';
            mochi.draw();

            var finish = function () {
                clearInterval(state.revealTimer);
                state.revealTimer = null;
                next.revealed = true;
                next.shown = next.text;
                state.revealing = false;
                setSpeaking(false);
                mochi.draw();
                if (next.action && next.action.type === 'navigate') {
                    setTimeout(function () {
                        mochi.navigate(next.action);
                    }, 900);
                }
                mochi.reveal();
            };

            if (REDUCED_MOTION) {
                finish();
                return;
            }

            var chars = Array.from(next.text);
            var step = Math.max(1, Math.ceil(chars.length / 100));
            var index = 0;
            state.revealTimer = setInterval(function () {
                index = Math.min(chars.length, index + step);
                next.shown = chars.slice(0, index).join('');
                if (next.node) {
                    next.node.nodeValue = next.shown;
                    ui.body.scrollTop = ui.body.scrollHeight;
                }
                if (index >= chars.length) {
                    finish();
                }
            }, 25);
        },

        // Only links the server resolved from the widget's own page list are followed.
        navigate: function (action) {
            var href = safeUrl(action.url);
            if (!href) {
                return;
            }
            var target = new URL(href);
            var samePage = target.origin === window.location.origin && target.pathname === window.location.pathname;
            if (samePage && target.hash) {
                var section = document.getElementById(decodeURIComponent(target.hash.slice(1)));
                if (section) {
                    section.scrollIntoView({ behavior: REDUCED_MOTION ? 'auto' : 'smooth', block: 'start' });
                    var previous = section.style.boxShadow;
                    section.style.transition = 'box-shadow .3s ease';
                    section.style.boxShadow = '0 0 0 3px rgba(34,211,102,.7), 0 0 30px rgba(34,211,102,.45)';
                    setTimeout(function () {
                        section.style.boxShadow = previous;
                    }, 1500);
                    return;
                }
            }
            if (samePage && !target.hash) {
                return;
            }
            writeStore('sessionStorage', OPEN_KEY, '1');
            window.location.assign(target.href);
        },

        newChat: function () {
            state.token = null;
            writeStore('localStorage', TOKEN_KEY, null);
            state.messages = [];
            state.lastId = 0;
            state.note = null;
            state.failedText = null;
            openSession().then(function () {
                mochi.draw();
                ui.field.focus();
            });
        },

        micPressed: function () {
            state.resumeCall = false;
            state.note = null;
            voice.start();
        }
    };

    function safeUrl(url) {
        try {
            var parsed = new URL(String(url || ''), window.location.href);
            return /^https?:$/.test(parsed.protocol) ? parsed.href : null;
        } catch (error) {
            return null;
        }
    }

    // ---------------------------------------------------------------------------------------
    // Voice calls (Mochi look): WebRTC straight to the OpenAI Realtime API with a short-lived
    // secret from our server. Live captions for both sides, lip-sync from the reply audio,
    // barge-in, tools (knowledge, handover, page navigation) and the call's own time limit.
    // ---------------------------------------------------------------------------------------

    var VOICE_TEXT = {
        ar: {
            connecting: 'لحظة أوصلك…',
            listening: 'أسمعك…',
            thinking: 'لحظة أفكر…',
            speaking: 'يتكلم…',
            endCall: 'إنهاء المكالمة',
            mute: 'كتم المايك',
            unmute: 'تشغيل المايك',
            toText: 'كمّل كتابة',
            noMic: 'ما قدرت أوصل للمايك. تقدر تكلمني كتابة 👇',
            insecure: 'المكالمة تحتاج موقع آمن (https). كلمني كتابة 👇',
            minutesUsed: 'خلصت دقايق المكالمات لهالشهر، بس أنا معك كتابة 👇',
            busy: 'المكالمات زحمة اليوم، كلمني كتابة 👇',
            failed: 'النت فيه شوية مشكلة، جرّب مرة ثانية أو كلمني كتابة 👇',
            ended: 'خلصت المكالمة. يعطيك العافية 👋',
            resume: 'تابع المكالمة 🎙️',
            stillThere: 'لسا معي؟'
        },
        en: {
            connecting: 'Connecting…',
            listening: 'Listening…',
            thinking: 'Thinking…',
            speaking: 'Speaking…',
            endCall: 'End call',
            mute: 'Mute',
            unmute: 'Unmute',
            toText: 'Switch to text',
            noMic: "I couldn't reach your microphone. You can type to me 👇",
            insecure: 'Voice needs a secure (https) site. Type to me 👇',
            minutesUsed: 'Voice minutes are used up for this month, but I can chat by text 👇',
            busy: 'Voice is busy today, please type to me 👇',
            failed: 'Connection problem. Try again or type to me 👇',
            ended: 'Call ended. Thanks! 👋',
            resume: 'Continue the call 🎙️',
            stillThere: 'Are you still there?'
        }
    }[LOCALE];

    var RESUME_KEY = 'botzo_widget_resume_call_' + KEY;
    var IDLE_SECONDS = 60;

    var call = null;

    var voice = {
        supported: function () {
            return !!(window.RTCPeerConnection && navigator.mediaDevices && navigator.mediaDevices.getUserMedia);
        },

        start: function () {
            if (call) {
                return;
            }
            writeStore('sessionStorage', RESUME_KEY, null);
            if (!window.isSecureContext) {
                return voice.fallback(VOICE_TEXT.insecure);
            }
            if (!voice.supported()) {
                return voice.fallback(VOICE_TEXT.noMic);
            }

            call = { id: null, pc: null, dc: null, mic: null, audio: null, startedAt: 0, muted: false, timers: [], lines: [], userLine: null, botLine: null, pendingNav: null, ended: false };
            state.inCall = true;
            voice.status(VOICE_TEXT.connecting);
            voice.show();

            navigator.mediaDevices.getUserMedia({ audio: { echoCancellation: true, noiseSuppression: true, autoGainControl: true, channelCount: 1 } })
                .then(function (stream) {
                    if (!call || call.ended) {
                        stream.getTracks().forEach(function (track) { track.stop(); });
                        return null;
                    }
                    call.mic = stream;
                    voice.meter(stream, 'mic');
                    return request('POST', '/voice/start', {
                        page_url: window.location.href.slice(0, 2000),
                        page_title: (document.title || '').slice(0, 300),
                        locale: LOCALE
                    });
                }, function () {
                    voice.stop(true);
                    voice.fallback(VOICE_TEXT.noMic);
                    return null;
                })
                .then(function (result) {
                    if (!result || !call || call.ended) {
                        return;
                    }
                    if (result.status !== 200) {
                        var reason = result.data && result.data.error;
                        voice.stop(true);
                        voice.fallback(reason === 'plan_minutes_used' ? VOICE_TEXT.minutesUsed : (reason === 'platform_daily_cap' ? VOICE_TEXT.busy : VOICE_TEXT.failed));
                        return;
                    }
                    call.id = result.data.call_id;
                    return voice.connect(result.data);
                })
                .catch(function () {
                    voice.stop(true);
                    voice.fallback(VOICE_TEXT.failed);
                });
        },

        connect: function (session) {
            var pc = new RTCPeerConnection();
            call.pc = pc;
            call.audio = document.createElement('audio');
            call.audio.autoplay = true;
            pc.ontrack = function (event) {
                call.audio.srcObject = event.streams[0];
                voice.meter(event.streams[0], 'reply');
            };
            call.mic.getTracks().forEach(function (track) {
                pc.addTrack(track, call.mic);
            });
            pc.onconnectionstatechange = function () {
                if (call && ['failed', 'disconnected', 'closed'].indexOf(pc.connectionState) !== -1 && !call.ended) {
                    voice.stop();
                    voice.fallback(VOICE_TEXT.failed);
                }
            };

            var dc = pc.createDataChannel('oai-events');
            call.dc = dc;
            dc.onmessage = function (event) {
                try {
                    voice.event(JSON.parse(event.data));
                } catch (error) {
                    // Ignore malformed events.
                }
            };
            dc.onopen = function () {
                call.startedAt = Date.now();
                voice.status(VOICE_TEXT.listening);
                // Mochi says hello first.
                voice.send({ type: 'response.create' });
                call.timers.push(setTimeout(function () {
                    voice.stop();
                }, Math.max(30, session.max_seconds || 300) * 1000));
                voice.idle();
            };

            return pc.createOffer().then(function (offer) {
                return pc.setLocalDescription(offer).then(function () {
                    return fetch(session.calls_url, {
                        method: 'POST',
                        body: offer.sdp,
                        headers: { 'Authorization': 'Bearer ' + session.client_secret, 'Content-Type': 'application/sdp' }
                    });
                });
            }).then(function (response) {
                if (!response.ok) {
                    throw new Error('sdp');
                }
                return response.text();
            }).then(function (answer) {
                return pc.setRemoteDescription({ type: 'answer', sdp: answer });
            });
        },

        send: function (payload) {
            if (call && call.dc && call.dc.readyState === 'open') {
                call.dc.send(JSON.stringify(payload));
            }
        },

        event: function (data) {
            switch (data.type) {
                case 'input_audio_buffer.speech_started':
                    voice.idle();
                    if (call.botLine && !call.botLine.done) {
                        // Barge-in: stop Mochi right away and keep what was said so far.
                        voice.send({ type: 'output_audio_buffer.clear' });
                        call.botLine.done = true;
                        call.botLine.interrupted = true;
                        voice.saveLine('assistant', call.botLine.text);
                        call.botLine = null;
                    }
                    call.userLine = { text: '', partial: true };
                    voice.status(VOICE_TEXT.listening);
                    voice.captions();
                    break;
                case 'input_audio_buffer.speech_stopped':
                    voice.status(VOICE_TEXT.thinking);
                    break;
                case 'conversation.item.input_audio_transcription.delta':
                    call.userLine = call.userLine || { text: '', partial: true };
                    call.userLine.text += data.delta || '';
                    voice.captions();
                    break;
                case 'conversation.item.input_audio_transcription.completed':
                    var heard = String(data.transcript || (call.userLine && call.userLine.text) || '').trim();
                    call.userLine = null;
                    if (heard) {
                        call.lines.push({ from: 'visitor', text: heard });
                        voice.saveLine('user', heard);
                    }
                    voice.captions();
                    break;
                case 'response.output_audio_transcript.delta':
                    if (!call.botLine || call.botLine.done) {
                        call.botLine = { text: '', done: false };
                        call.lines.push({ from: 'assistant', line: call.botLine });
                    }
                    call.botLine.text += data.delta || '';
                    voice.status(VOICE_TEXT.speaking);
                    voice.captions();
                    break;
                case 'response.output_audio_transcript.done':
                    if (call.botLine && !call.botLine.done) {
                        call.botLine.text = String(data.transcript || call.botLine.text);
                        call.botLine.done = true;
                        voice.saveLine('assistant', call.botLine.text);
                    }
                    voice.captions();
                    break;
                case 'output_audio_buffer.stopped':
                    voice.status(VOICE_TEXT.listening);
                    voice.idle();
                    if (call.pendingNav) {
                        var action = call.pendingNav;
                        call.pendingNav = null;
                        voice.navigate(action);
                    }
                    break;
                case 'response.done':
                    ((data.response && data.response.output) || []).forEach(function (item) {
                        if (item.type === 'function_call') {
                            voice.tool(item);
                        }
                    });
                    break;
            }
        },

        tool: function (item) {
            var args = {};
            try {
                args = JSON.parse(item.arguments || '{}');
            } catch (error) {
                args = {};
            }
            var reply = function (output, speakAgain) {
                voice.send({ type: 'conversation.item.create', item: { type: 'function_call_output', call_id: item.call_id, output: JSON.stringify(output) } });
                if (speakAgain) {
                    voice.send({ type: 'response.create' });
                }
            };

            if (item.name === 'navigate_to') {
                // Only pages from the widget's own list; the link comes from the server's settings.
                var page = (state.settings.pages || []).filter(function (p) {
                    return p.key === args.page;
                })[0];
                reply({ ok: !!page }, false);
                if (page) {
                    call.pendingNav = { type: 'navigate', url: page.url, label: page.label };
                }
                return;
            }

            request('POST', '/voice/' + encodeURIComponent(call.id) + '/tool', { name: item.name, arguments: args }).then(function (result) {
                reply(result.status === 200 ? result.data : { result: 'Tool unavailable.' }, true);
            }).catch(function () {
                reply({ result: 'Tool unavailable.' }, true);
            });
        },

        // Same-page sections scroll during the call; another page ends the call first and offers
        // to continue it after the page loads (browsers need a tap before audio can start again).
        navigate: function (action) {
            var href = safeUrl(action.url);
            if (!href) {
                return;
            }
            var target = new URL(href);
            var samePage = target.origin === window.location.origin && target.pathname === window.location.pathname;
            if (samePage) {
                mochi.navigate(action);
                return;
            }
            writeStore('sessionStorage', RESUME_KEY, '1');
            voice.stop();
            mochi.navigate(action);
        },

        saveLine: function (role, text) {
            text = String(text || '').trim();
            if (!text || !call || !call.id) {
                return;
            }
            request('POST', '/voice/' + encodeURIComponent(call.id) + '/transcript', { role: role, text: text }).catch(function () {});
        },

        idle: function () {
            if (!call) {
                return;
            }
            clearTimeout(call.idleTimer);
            call.idleTimer = setTimeout(function () {
                if (!call) {
                    return;
                }
                voice.send({ type: 'response.create', response: { instructions: 'Ask the visitor briefly, in the same language, whether they are still there (for example: ' + VOICE_TEXT.stillThere + ').' } });
                call.idleTimer = setTimeout(function () {
                    voice.stop();
                }, 15000);
            }, IDLE_SECONDS * 1000);
        },

        // Mic level drives the ring; reply level drives Mochi's mouth.
        meter: function (stream, kind) {
            var AudioCtx = window.AudioContext || window.webkitAudioContext;
            if (!AudioCtx || REDUCED_MOTION) {
                return;
            }
            try {
                var ctx = new AudioCtx();
                var analyser = ctx.createAnalyser();
                analyser.fftSize = 512;
                ctx.createMediaStreamSource(stream).connect(analyser);
                var samples = new Uint8Array(analyser.fftSize);
                var loop = function () {
                    if (!call || call.ended) {
                        ctx.close().catch(function () {});
                        voice.mouth(0);
                        return;
                    }
                    analyser.getByteTimeDomainData(samples);
                    var sum = 0;
                    for (var i = 0; i < samples.length; i++) {
                        var v = (samples[i] - 128) / 128;
                        sum += v * v;
                    }
                    var level = Math.min(1, Math.sqrt(sum / samples.length) * 4);
                    if (kind === 'mic') {
                        if (ui.ring) {
                            ui.ring.style.transform = 'scale(' + (1 + (call.muted ? 0 : level) * 0.18).toFixed(3) + ')';
                            ui.ring.style.opacity = (0.35 + level * 0.65).toFixed(2);
                        }
                    } else {
                        voice.mouth(level);
                    }
                    requestAnimationFrame(loop);
                };
                loop();
            } catch (error) {
                // No metering: the call still works.
            }
        },

        mouth: function (level) {
            mochis.forEach(function (wrap) {
                var mouth = wrap.querySelector('.mochi-mouth');
                if (mouth) {
                    mouth.style.transform = level > 0.04 ? 'scaleY(' + (1 + level * 1.6).toFixed(2) + ')' : '';
                }
            });
        },

        toggleMute: function () {
            if (!call || !call.mic) {
                return;
            }
            call.muted = !call.muted;
            call.mic.getAudioTracks().forEach(function (track) {
                track.enabled = !call.muted;
            });
            ui.muteBtn.setAttribute('aria-pressed', call.muted ? 'true' : 'false');
            ui.muteBtn.setAttribute('aria-label', call.muted ? VOICE_TEXT.unmute : VOICE_TEXT.mute);
            ui.muteBtn.classList.toggle('is-muted', call.muted);
        },

        stop: function (silent) {
            if (!call) {
                return;
            }
            var ending = call;
            ending.ended = true;
            clearTimeout(ending.idleTimer);
            ending.timers.forEach(function (timer) { clearTimeout(timer); });
            if (ending.botLine && !ending.botLine.done) {
                voice.saveLine('assistant', ending.botLine.text);
            }
            try { ending.dc && ending.dc.close(); } catch (error) {}
            try { ending.pc && ending.pc.close(); } catch (error) {}
            if (ending.mic) {
                ending.mic.getTracks().forEach(function (track) { track.stop(); });
            }
            if (ending.audio) {
                ending.audio.srcObject = null;
            }
            if (ending.id) {
                var seconds = ending.startedAt ? Math.round((Date.now() - ending.startedAt) / 1000) : 0;
                request('POST', '/voice/' + encodeURIComponent(ending.id) + '/end', { seconds: seconds }).catch(function () {});
            }
            call = null;
            state.inCall = false;
            voice.mouth(0);
            voice.hide();
            if (!silent && ending.id) {
                state.note = VOICE_TEXT.ended;
            }
            // The call's lines come back from the server as normal messages.
            setTimeout(function () {
                request('GET', '/messages?after=' + state.lastId).then(function (result) {
                    if (result.status === 200 && result.data.messages) {
                        addMessages(result.data.messages);
                    }
                    drawMessages();
                }).catch(function () {
                    drawMessages();
                });
            }, 800);
        },

        fallback: function (message) {
            state.note = message;
            voice.hide();
            drawMessages();
            ui.field.focus();
        },

        status: function (text) {
            if (ui.callStatus) {
                ui.callStatus.textContent = text;
            }
            if (ui.status) {
                ui.status.textContent = text;
            }
        },

        // Live captions: the visitor's words as they speak (faint), then Mochi's reply.
        captions: function () {
            if (!ui.captions || !call) {
                return;
            }
            ui.captions.textContent = '';
            var recent = call.lines.slice(-2);
            recent.forEach(function (line) {
                var text = line.from === 'visitor' ? line.text : line.line.text;
                ui.captions.appendChild(el('p', { 'class': 'cap ' + (line.from === 'visitor' ? 'cap-user' : 'cap-bot') + (line.line && line.line.interrupted ? ' cap-cut' : ''), text: text }));
            });
            if (call.userLine && call.userLine.text) {
                ui.captions.appendChild(el('p', { 'class': 'cap cap-user cap-partial', text: call.userLine.text }));
            }
            ui.captions.scrollTop = ui.captions.scrollHeight;
        },

        show: function () {
            ui.callView.hidden = false;
            ui.welcome.hidden = true;
            ui.convo.hidden = true;
            ui.form.hidden = true;
            ui.footWelcome.hidden = true;
            ui.footConvo.hidden = true;
            ui.captions.textContent = '';
            ui.muteBtn.classList.remove('is-muted');
        },

        hide: function () {
            if (ui.callView) {
                ui.callView.hidden = true;
                ui.form.hidden = false;
            }
        }
    };

    function callView() {
        ui.ring = el('span', { 'class': 'call-ring', 'aria-hidden': 'true' });
        ui.callStatus = el('div', { 'class': 'call-status', role: 'status', text: VOICE_TEXT.connecting });
        ui.captions = el('div', { 'class': 'captions', 'aria-live': 'polite' });
        ui.muteBtn = el('button', { type: 'button', 'class': 'call-btn', 'aria-label': VOICE_TEXT.mute, 'aria-pressed': 'false', onclick: voice.toggleMute },
            [svg(['M12 3a3 3 0 0 0-3 3v5a3 3 0 0 0 6 0V6a3 3 0 0 0-3-3z', 'M5 11a7 7 0 0 0 14 0M12 18v3'], 20)]);
        return el('div', { 'class': 'call', hidden: '' }, [
            el('div', { 'class': 'call-stage' }, [ui.ring, mochiEl('call-mochi')]),
            ui.callStatus,
            ui.captions,
            el('div', { 'class': 'call-controls' }, [
                ui.muteBtn,
                el('button', { type: 'button', 'class': 'call-btn call-end', 'aria-label': VOICE_TEXT.endCall, onclick: function () {
                    voice.stop();
                } }, [svg(['M3 15c5-5 13-5 18 0l-2.5 2.5-3-1.5v-2.5a10 10 0 0 0-7 0V16l-3 1.5z'], 22)]),
                el('button', { type: 'button', 'class': 'call-btn call-text', onclick: function () {
                    voice.stop();
                    ui.field.focus();
                } }, [VOICE_TEXT.toText])
            ])
        ]);
    }

    function voiceStyles() {
        return [
            '.call{position:relative;flex:1;display:flex;flex-direction:column;align-items:center;padding:30px 20px 18px;min-height:0}',
            '.call[hidden],.form[hidden]{display:none}',
            '.call-stage{position:relative;width:230px;max-width:66%;margin-top:10px;display:flex;align-items:center;justify-content:center}',
            '.call-ring{position:absolute;inset:-12%;border-radius:999px;border:2px solid rgba(34,211,102,.55);box-shadow:0 0 40px rgba(34,211,102,.35);opacity:.4;transition:transform .08s linear,opacity .08s linear}',
            '.call-mochi{position:relative;width:100%}',
            '.call-status{margin-top:22px;font-size:14px;color:#22D366;font-weight:600}',
            '.captions{margin-top:12px;width:100%;max-height:calc(18px * 1.6 * 2 + 8px);overflow:hidden;text-align:center}',
            '.cap{margin:0;font-size:18px;line-height:1.6;color:#F2F5F9;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}',
            '.cap-user{color:#A3ADBD}.cap-partial{opacity:.65}.cap-cut::after{content:" …";color:#7C8799}',
            '.call-controls{margin-top:auto;display:flex;align-items:center;gap:14px}',
            '.call-btn{min-width:52px;height:52px;border-radius:999px;border:0;background:rgba(255,255,255,.1);display:flex;align-items:center;justify-content:center;padding:0 16px}',
            '.call-btn.is-muted{background:#FF4D4F;color:#fff}',
            '.call-end{background:#FF4D4F;color:#fff;width:64px;height:64px}',
            '.call-text{font-size:13px}',
            '.mic{position:relative}',
            '.resume{display:inline-block;margin-top:8px;border:0;border-radius:16px;background:var(--accent);color:#0A0E16;font-weight:700;padding:6px 14px}'
        ].join('');
    }

    function toggleMochi(restoring) {
        state.open = !state.open;
        writeStore('sessionStorage', OPEN_KEY, state.open ? '1' : null);
        writeStore('sessionStorage', MOCHI_SEEN_KEY, '1');
        ui.teaser.setAttribute('hidden', '');
        ui.launcher.setAttribute('aria-expanded', state.open ? 'true' : 'false');
        clearTimeout(state.hideTimer);

        if (state.open) {
            setUnread(0);
            ui.launcherWrap.setAttribute('hidden', '');
            ui.panel.removeAttribute('hidden');
            drawMessages();
            if (restoring || REDUCED_MOTION) {
                ui.panel.classList.add('show');
            } else {
                requestAnimationFrame(function () {
                    ui.panel.classList.add('show');
                });
            }
            ui.field.focus();
        } else {
            if (state.inCall) {
                voice.stop(true);
            }
            ui.panel.classList.remove('show');
            state.hideTimer = setTimeout(function () {
                ui.panel.setAttribute('hidden', '');
                ui.launcherWrap.removeAttribute('hidden');
                ui.launcher.focus();
            }, REDUCED_MOTION ? 0 : 200);
        }
        poll();
    }

    function start() {
        openSession().then(function (ok) {
            if (ok) {
                (state.theme === 'mochi' ? mochi.render : render)();
            }
        });
    }

    if (document.readyState === 'complete') {
        start();
    } else {
        window.addEventListener('load', start);
    }
})();
