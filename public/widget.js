/*!
 * Botzo website chat widget.
 * Embed: <script src="https://botzo.net/widget.js" data-key="wgt_..." async></script>
 * Renders inside its own Shadow DOM so the host page's styles and the widget never clash.
 * Visitor text is always inserted as text, never as HTML.
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

    var TEXT = {
        ar: {
            open: 'افتح المحادثة',
            close: 'أغلق المحادثة',
            teaser: 'تحتاج مساعدة؟ اسألني',
            online: 'متصل · يرد فوراً',
            placeholder: 'اكتب سؤالك هنا…',
            send: 'إرسال',
            notice: 'يرد عليك مساعد ذكي',
            welcome: 'مرحباً! كيف أقدر أساعدك؟',
            typing: 'يكتب الآن',
            awaiting: 'وصلت رسالتك، وسيرد عليك فريقنا هنا قريباً.',
            tooFast: 'انتظر لحظة ثم أرسل مرة أخرى.',
            hourly: 'أرسلت رسائل كثيرة. حاول مرة أخرى بعد قليل.',
            failed: 'لم تصل الرسالة. تأكد من الاتصال وحاول مرة أخرى.',
            team: 'من الفريق',
            label: 'محادثة مع المساعد',
            input: 'رسالتك'
        },
        en: {
            open: 'Open chat',
            close: 'Close chat',
            teaser: 'Need help? Ask me',
            online: 'Online · replies instantly',
            placeholder: 'Type your question…',
            send: 'Send',
            notice: 'An AI assistant replies to you',
            welcome: 'Hi! How can I help you?',
            typing: 'Typing',
            awaiting: 'We got your message. Our team will reply here soon.',
            tooFast: 'Please wait a moment and send again.',
            hourly: 'You sent many messages. Please try again later.',
            failed: 'Your message was not sent. Check your connection and try again.',
            team: 'Team',
            label: 'Chat with the assistant',
            input: 'Your message'
        }
    }[LOCALE];

    function store(kind) {
        try {
            return window[kind];
        } catch (error) {
            return null;
        }
    }

    function readStore(kind, key) {
        try {
            var target = store(kind);
            return target ? target.getItem(key) : null;
        } catch (error) {
            return null;
        }
    }

    function writeStore(kind, key, value) {
        try {
            var target = store(kind);
            if (!target) {
                return;
            }
            if (value === null) {
                target.removeItem(key);
            } else {
                target.setItem(key, value);
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
                node.appendChild(child);
            }
        });
        return node;
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

    function request(method, path, body) {
        var headers = { 'Accept': 'application/json' };
        var token = state.token;
        if (body) {
            headers['Content-Type'] = 'application/json';
        }
        if (token) {
            headers['X-Widget-Token'] = token;
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

    var state = {
        token: readStore('localStorage', TOKEN_KEY),
        settings: null,
        messages: [],
        lastId: 0,
        open: false,
        sending: false,
        typing: false,
        note: null,
        pollTimer: null
    };

    var ui = {};

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
            state.messages = [];
            state.lastId = 0;
            addMessages(result.data.messages || []);
            return true;
        }).catch(function () {
            return false;
        });
    }

    function addMessages(list) {
        list.forEach(function (message) {
            if (!message || typeof message.id !== 'number') {
                return;
            }
            var exists = state.messages.some(function (item) {
                return item.id === message.id;
            });
            if (!exists) {
                state.messages.push(message);
            }
            state.lastId = Math.max(state.lastId, message.id);
        });
        state.messages = state.messages.filter(function (item) {
            return typeof item.id === 'number' || item.pending;
        });
    }

    function styles(accent) {
        return [
            ':host{all:initial}',
            '*{box-sizing:border-box}',
            '.root{position:fixed;bottom:20px;z-index:2147483000;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Tahoma,"IBM Plex Sans Arabic",Arial,sans-serif;color:#1f2933;font-size:15px;line-height:1.6}',
            '.root.right{right:20px}.root.left{left:20px}',
            'button{font:inherit;cursor:pointer}',
            '.launcher{width:60px;height:60px;border-radius:999px;border:0;background:' + accent + ';color:#fff;display:flex;align-items:center;justify-content:center;box-shadow:0 6px 20px rgba(16,24,40,.28)}',
            '.launcher:focus-visible,.icon:focus-visible,.chip:focus-visible,.send:focus-visible,.field:focus-visible{outline:3px solid #2563eb;outline-offset:2px}',
            '.teaser{position:absolute;bottom:12px;white-space:nowrap;background:#fff;color:#1f2933;border:0;border-radius:14px;padding:10px 14px;font-size:14px;font-weight:500;box-shadow:0 4px 16px rgba(16,24,40,.14)}',
            '.right .teaser{right:72px}.left .teaser{left:72px}',
            '.panel{position:absolute;bottom:76px;width:372px;height:min(600px,calc(100vh - 120px));background:#fff;border-radius:20px;box-shadow:0 12px 40px rgba(16,24,40,.22);display:flex;flex-direction:column;overflow:hidden}',
            '.right .panel{right:0}.left .panel{left:0}',
            '.panel[hidden]{display:none}',
            '.head{background:' + accent + ';color:#fff;display:flex;align-items:center;gap:12px;padding:12px 12px 12px 16px}',
            '.avatar{width:40px;height:40px;border-radius:999px;background:rgba(255,255,255,.2);display:flex;align-items:center;justify-content:center;font-weight:700;font-size:17px;flex-shrink:0}',
            '.who{flex:1;min-width:0}.name{font-weight:600;font-size:16px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.status{font-size:12.5px;opacity:.92}',
            '.icon{width:44px;height:44px;border-radius:999px;border:0;background:transparent;color:#fff;display:flex;align-items:center;justify-content:center;flex-shrink:0}',
            '.body{flex:1;overflow-y:auto;padding:16px;display:flex;flex-direction:column;gap:10px;background:#f7f8f7;overscroll-behavior:contain}',
            '.msg{max-width:85%;padding:9px 13px;border-radius:16px;white-space:pre-wrap;overflow-wrap:anywhere}',
            '.visitor{align-self:flex-end;background:' + accent + ';color:#fff}',
            '.assistant,.agent{align-self:flex-start;background:#fff;border:1px solid #e3e7e5}',
            '.agent{background:#fff6e5;border-color:#f1ddb4}',
            '.pending{opacity:.65}',
            '.who-label{font-size:12px;font-weight:600;color:#4a5560;margin-bottom:2px}',
            '.note{align-self:center;text-align:center;font-size:13px;color:#4a5560;background:#eceeed;border-radius:12px;padding:6px 12px;max-width:90%}',
            '.typing{align-self:flex-start;background:#fff;border:1px solid #e3e7e5;border-radius:16px;padding:12px 16px;display:flex;gap:5px}',
            '.dot{width:7px;height:7px;border-radius:999px;background:#6b7680;animation:bz-blink 1.2s infinite}',
            '.dot:nth-child(2){animation-delay:.2s}.dot:nth-child(3){animation-delay:.4s}',
            '@keyframes bz-blink{0%,80%,100%{opacity:.3}40%{opacity:1}}',
            '.chips{display:flex;flex-wrap:wrap;gap:8px}',
            '.chip{min-height:40px;padding:8px 14px;border-radius:999px;border:1px solid ' + accent + ';background:#fff;color:' + accent + ';font-size:14px;font-weight:500}',
            '.form{display:flex;align-items:center;gap:8px;padding:12px;border-top:1px solid #e3e7e5;background:#fff}',
            '.field{flex:1;min-width:0;height:46px;border:1px solid #cbd2cf;border-radius:999px;padding:0 16px;font:inherit;font-size:16px;color:#1f2933;background:#fff}',
            '.send{width:46px;height:46px;border-radius:999px;border:0;background:' + accent + ';color:#fff;display:flex;align-items:center;justify-content:center;flex-shrink:0}',
            '.send:disabled{opacity:.45;cursor:not-allowed}',
            '.rtl .send svg{transform:scaleX(-1)}',
            '.foot{padding:4px 12px 10px;text-align:center;font-size:11.5px;color:#5b6670;background:#fff}',
            '.foot a{color:#1f2933;font-weight:600;text-decoration:none}',
            '.sr{position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0 0 0 0);white-space:nowrap}',
            '@media (max-width:480px){.panel{position:fixed;inset:0;width:auto;height:auto;border-radius:0}.root.open .launcher,.root.open .teaser{display:none}}',
            '@media (prefers-reduced-motion:reduce){.dot{animation:none}}'
        ].join('');
    }

    function render() {
        var settings = state.settings;
        var accent = /^#[0-9a-fA-F]{6}$/.test(settings.accent || '') ? settings.accent : '#034737';
        var host = el('div', { id: 'botzo-widget' });
        var shadow = host.attachShadow({ mode: 'open' });
        var name = settings.assistant_name || '';

        shadow.appendChild(el('style', { text: styles(accent) }));

        ui.root = el('div', { 'class': 'root ' + (settings.position === 'left' ? 'left' : 'right') + (LOCALE === 'ar' ? ' rtl' : ''), dir: LOCALE === 'ar' ? 'rtl' : 'ltr', lang: LOCALE });

        ui.launcher = el('button', { type: 'button', 'class': 'launcher', 'aria-label': TEXT.open, 'aria-expanded': 'false', onclick: toggle });
        ui.launcher.appendChild(svg(['M21 12a8 8 0 0 1-11.6 7.1L4 20l1-4.6A8 8 0 1 1 21 12z', 'M8.5 11h.01M12 11h.01M15.5 11h.01'], 28));

        ui.teaser = el('button', { type: 'button', 'class': 'teaser', text: TEXT.teaser, onclick: toggle, hidden: '' });

        ui.close = el('button', { type: 'button', 'class': 'icon', 'aria-label': TEXT.close, onclick: toggle });
        ui.close.appendChild(svg(['M6 6l12 12M18 6L6 18'], 22));

        ui.body = el('div', { 'class': 'body', 'aria-live': 'polite' });
        ui.field = el('input', { 'class': 'field', id: 'bz-field', type: 'text', maxlength: String(settings.max_length || 500), placeholder: TEXT.placeholder, autocomplete: 'off', oninput: syncSend });
        ui.send = el('button', { type: 'submit', 'class': 'send', 'aria-label': TEXT.send, disabled: '' });
        ui.send.appendChild(svg(['M5 12h14M13 6l6 6-6 6'], 20));

        var footLink = el('a', { href: 'https://botzo.net', target: '_blank', rel: 'noopener', text: 'Botzo' });
        var foot = el('div', { 'class': 'foot' }, [document.createTextNode(TEXT.notice + ' · Powered by '), footLink]);

        ui.panel = el('section', { 'class': 'panel', role: 'dialog', 'aria-label': TEXT.label, hidden: '', onkeydown: function (event) {
            if (event.key === 'Escape') {
                toggle();
            }
        } }, [
            el('div', { 'class': 'head' }, [
                el('div', { 'class': 'avatar', 'aria-hidden': 'true', text: (name.trim().charAt(0) || 'B').toUpperCase() }),
                el('div', { 'class': 'who' }, [
                    el('div', { 'class': 'name', text: name }),
                    el('div', { 'class': 'status', text: TEXT.online })
                ]),
                ui.close
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
    }

    function drawMessages() {
        var body = ui.body;
        body.textContent = '';

        body.appendChild(el('div', { 'class': 'msg assistant', text: state.settings.welcome_text || TEXT.welcome }));

        state.messages.forEach(function (message) {
            var cls = message.from === 'visitor' ? 'visitor' : (message.from === 'agent' ? 'agent' : 'assistant');
            var bubble = el('div', { 'class': 'msg ' + cls + (message.pending ? ' pending' : '') });
            if (message.from === 'agent') {
                bubble.appendChild(el('div', { 'class': 'who-label', text: (message.name ? message.name + ' · ' : '') + TEXT.team }));
            }
            bubble.appendChild(document.createTextNode(message.text));
            body.appendChild(bubble);
        });

        var hasVisitorMessage = state.messages.some(function (message) {
            return message.from === 'visitor';
        });
        var suggestions = (state.settings.suggestions || []).filter(function (item) {
            return typeof item === 'string' && item.trim() !== '';
        });
        if (!hasVisitorMessage && !state.sending && suggestions.length) {
            body.appendChild(el('div', { 'class': 'chips' }, suggestions.map(function (item) {
                return el('button', { type: 'button', 'class': 'chip', text: item, onclick: function () {
                    send(item);
                } });
            })));
        }

        if (state.typing) {
            body.appendChild(el('div', { 'class': 'typing', role: 'status', 'aria-label': TEXT.typing }, [
                el('span', { 'class': 'dot' }), el('span', { 'class': 'dot' }), el('span', { 'class': 'dot' })
            ]));
        }

        if (state.note) {
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

    function toggle() {
        state.open = !state.open;
        ui.root.classList.toggle('open', state.open);
        ui.launcher.setAttribute('aria-expanded', state.open ? 'true' : 'false');
        ui.launcher.setAttribute('aria-label', state.open ? TEXT.close : TEXT.open);
        ui.teaser.setAttribute('hidden', '');
        writeStore('sessionStorage', TEASER_KEY, '1');

        if (state.open) {
            ui.panel.removeAttribute('hidden');
            drawMessages();
            ui.field.focus();
            poll();
        } else {
            ui.panel.setAttribute('hidden', '');
            stopPolling();
            ui.launcher.focus();
        }
    }

    function send(raw, retried) {
        var text = String(raw || '').trim().slice(0, state.settings.max_length || 500);
        if (text === '' || state.sending) {
            return;
        }

        state.sending = true;
        state.typing = true;
        state.note = null;
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
                        state.messages.push({ from: 'visitor', text: text, pending: true });
                        send(text, true);
                        return;
                    } else {
                        fail(TEXT.failed, text);
                    }
                });
            }

            if (result.status === 429) {
                fail(result.data.error === 'hourly' ? TEXT.hourly : TEXT.tooFast, text);
                return;
            }

            if (result.status !== 200) {
                fail(TEXT.failed, text);
                return;
            }

            state.messages = state.messages.filter(function (item) {
                return !item.pending;
            });
            addMessages(result.data.messages || []);
            state.note = result.data.awaiting_team ? TEXT.awaiting : null;
            done();
        }).catch(function () {
            fail(TEXT.failed, text);
        });
    }

    function fail(note, text) {
        state.messages = state.messages.filter(function (item) {
            return !item.pending;
        });
        if (ui.field.value === '') {
            ui.field.value = text;
        }
        state.note = note;
        done();
    }

    function done() {
        state.sending = false;
        state.typing = false;
        syncSend();
        drawMessages();
    }

    function poll() {
        stopPolling();
        if (!state.open) {
            return;
        }

        var run = function () {
            if (document.hidden || state.sending) {
                return;
            }
            request('GET', '/messages?after=' + state.lastId).then(function (result) {
                if (result.status === 200 && result.data.messages && result.data.messages.length) {
                    addMessages(result.data.messages);
                    var answered = result.data.messages.some(function (message) {
                        return message.from !== 'visitor';
                    });
                    if (answered) {
                        state.note = null;
                    }
                    drawMessages();
                }
            }).catch(function () {});
        };

        if (state.token && state.lastId > 0) {
            run();
        }
        state.pollTimer = setInterval(run, Math.max(5, state.settings.poll_interval || 15) * 1000);
    }

    function stopPolling() {
        if (state.pollTimer) {
            clearInterval(state.pollTimer);
            state.pollTimer = null;
        }
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
