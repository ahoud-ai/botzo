/**
 * Makes the Mochi site widget smaller and movable on customer pages of the platform.
 *
 * The widget itself (resources/widget/widget.js) is shared with every customer's website, so it
 * is left alone. This module adjusts it from outside, only while a User/ page is showing, and
 * puts it back as it was when the visitor returns to a public page.
 */

const STORAGE_KEY = 'botzo_platform_mochi_position';
const STYLE_ID = 'bz-platform-style';
const PLATFORM_CLASS = 'bz-platform';
const EDGE_MARGIN = 8;
const DRAG_THRESHOLD = 6;
const KEY_STEP = 16;
const KEY_STEP_LARGE = 48;
const FULL_SCREEN_PANEL_WIDTH = 480;
const PANEL_WIDTH = 400;
const PANEL_HEIGHT = 680;
const DEFAULT_SIZE = { width: 56, height: 72 };

const TEXT = {
  ar: {
    hint: 'اسحب الأيقونة لنقلها، أو استخدم الأسهم. زر Home يرجعها لمكانها الأصلي.',
    reset: 'إرجاع الأيقونة لمكانها الأصلي',
    moved: 'تم نقل الأيقونة',
    restored: 'رجعت الأيقونة لمكانها الأصلي',
  },
  en: {
    hint: 'Drag the icon to move it, or use the arrow keys. Home puts it back in its original place.',
    reset: 'Put the icon back in its original place',
    moved: 'Icon moved',
    restored: 'Icon back in its original place',
  },
};

const STYLES = `
.root.${PLATFORM_CLASS} .launcher-wrap{gap:0}
.root.${PLATFORM_CLASS} .launcher{width:56px;height:56px;cursor:grab;touch-action:none;-webkit-user-select:none;user-select:none}
.root.${PLATFORM_CLASS}.bz-dragging .launcher{cursor:grabbing}
.root.${PLATFORM_CLASS} .launcher .mochi{width:56px}
.root.${PLATFORM_CLASS} .launcher-label{font-size:11px;line-height:1.3}
.root.${PLATFORM_CLASS} .bubble{font-size:11px;padding:5px 9px}
.root.${PLATFORM_CLASS}.bz-bubble-below .bubble{bottom:auto;top:100%;margin-bottom:0;margin-top:6px}
.bz-reset{position:absolute;top:-6px;width:28px;height:28px;padding:0;border-radius:999px;border:1px solid rgba(255,255,255,.25);background:#1A2333;color:#fff;display:flex;align-items:center;justify-content:center}
.bz-reset::before{content:"";position:absolute;inset:-8px}
.bz-reset[hidden]{display:none}
.bz-reset svg{width:14px;height:14px}
@media (min-width:${FULL_SCREEN_PANEL_WIDTH + 1}px){
.root.${PLATFORM_CLASS} .panel{position:fixed;bottom:auto;right:auto;width:min(${PANEL_WIDTH}px,calc(100vw - ${EDGE_MARGIN * 2}px));height:min(${PANEL_HEIGHT}px,calc(100vh - ${EDGE_MARGIN * 2}px))}
}
`;

const RESET_ICON = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 12a9 9 0 1 0 3-6.7L3 8"/><path d="M3 3v5h5"/></svg>';

const clamp = (value, min, max) => Math.min(Math.max(value, min), Math.max(min, max));

function readPosition() {
  try {
    const saved = JSON.parse(window.localStorage.getItem(STORAGE_KEY));
    const valid = (value) => typeof value === 'number' && value >= 0 && value <= 1;

    return saved && valid(saved.x) && valid(saved.y) ? saved : null;
  } catch (_error) {
    return null;
  }
}

function writePosition(position) {
  try {
    if (position) {
      window.localStorage.setItem(STORAGE_KEY, JSON.stringify(position));
    } else {
      window.localStorage.removeItem(STORAGE_KEY);
    }
  } catch (_error) {
    // Moving still works for this visit when the browser refuses to store it.
  }
}

function currentText() {
  return /^ar/i.test(document.documentElement.lang || '') ? TEXT.ar : TEXT.en;
}

function viewport() {
  return { width: document.documentElement.clientWidth, height: window.innerHeight };
}

function attach(host) {
  const shadow = host.shadowRoot;
  const root = shadow.querySelector('.root.mochi-theme');
  const wrap = shadow.querySelector('.launcher-wrap');
  const launcher = shadow.querySelector('.launcher');
  const panel = shadow.querySelector('.panel');
  const bubble = shadow.querySelector('.bubble');

  if (!root || !wrap || !launcher || !panel || !bubble) {
    return null;
  }

  const listeners = new AbortController();
  const { signal } = listeners;
  let position = readPosition();
  let size = { ...DEFAULT_SIZE };
  let drag = null;
  let suppressClick = false;

  const style = document.createElement('style');
  style.id = STYLE_ID;
  style.textContent = STYLES;
  shadow.appendChild(style);
  root.classList.add(PLATFORM_CLASS);

  const hint = document.createElement('span');
  hint.id = 'bz-hint';
  hint.className = 'sr';
  const status = document.createElement('span');
  status.className = 'sr';
  status.setAttribute('role', 'status');
  const reset = document.createElement('button');
  reset.type = 'button';
  reset.className = 'bz-reset';
  reset.hidden = true;
  reset.innerHTML = RESET_ICON;
  wrap.append(reset, hint, status);
  launcher.setAttribute('aria-describedby', hint.id);

  const applyText = () => {
    const text = currentText();
    hint.textContent = text.hint;
    reset.setAttribute('aria-label', text.reset);
    reset.title = text.reset;
  };

  const isPanelOpen = () => !panel.hasAttribute('hidden');

  const measure = () => {
    const rect = wrap.getBoundingClientRect();
    if (rect.width > 0) {
      size = { width: rect.width, height: rect.height };
    }
  };

  const travel = () => {
    const screen = viewport();

    return {
      width: Math.max(0, screen.width - size.width - EDGE_MARGIN * 2),
      height: Math.max(0, screen.height - size.height - EDGE_MARGIN * 2),
    };
  };

  const placeRoot = (left, top) => {
    root.style.left = `${left}px`;
    root.style.top = `${top}px`;
    root.style.right = 'auto';
    root.style.bottom = 'auto';
  };

  const placeFromPosition = () => {
    if (!position) {
      root.style.removeProperty('left');
      root.style.removeProperty('top');
      root.style.removeProperty('right');
      root.style.removeProperty('bottom');
      return;
    }

    const room = travel();
    placeRoot(EDGE_MARGIN + position.x * room.width, EDGE_MARGIN + position.y * room.height);
  };

  // Keeps the bubble and the reset button on the side of the icon that has room.
  const placeDecorations = () => {
    const rect = wrap.getBoundingClientRect();
    if (rect.width === 0) {
      return;
    }

    const onRightHalf = rect.left + rect.width / 2 > viewport().width / 2;
    bubble.style.left = onRightHalf ? 'auto' : '0';
    bubble.style.right = onRightHalf ? '0' : 'auto';
    reset.style.left = onRightHalf ? '-16px' : 'auto';
    reset.style.right = onRightHalf ? 'auto' : '-16px';
    reset.hidden = !position;
    root.classList.toggle('bz-bubble-below', rect.top < 44);
  };

  const clearPanelPlacement = () => {
    ['left', 'top', 'transform-origin'].forEach((property) => panel.style.removeProperty(property));
  };

  const placePanel = () => {
    const screen = viewport();
    if (!isPanelOpen() || screen.width <= FULL_SCREEN_PANEL_WIDTH) {
      clearPanelPlacement();
      return;
    }

    const anchor = root.getBoundingClientRect();
    const anchorBottom = position ? anchor.top + size.height : anchor.bottom;
    const panelWidth = Math.min(PANEL_WIDTH, screen.width - EDGE_MARGIN * 2);
    const panelHeight = Math.min(PANEL_HEIGHT, screen.height - EDGE_MARGIN * 2);
    const onRightHalf = anchor.left + size.width / 2 > screen.width / 2;
    const left = onRightHalf ? anchor.left + size.width - panelWidth : anchor.left;

    panel.style.left = `${clamp(left, EDGE_MARGIN, screen.width - panelWidth - EDGE_MARGIN)}px`;
    panel.style.top = `${clamp(anchorBottom - panelHeight, EDGE_MARGIN, screen.height - panelHeight - EDGE_MARGIN)}px`;
    panel.style.transformOrigin = `bottom ${onRightHalf ? 'right' : 'left'}`;
  };

  const layout = () => {
    measure();
    placeFromPosition();
    placeDecorations();
    placePanel();
  };

  const moveTo = (left, top) => {
    const screen = viewport();
    placeRoot(
      clamp(left, EDGE_MARGIN, screen.width - size.width - EDGE_MARGIN),
      clamp(top, EDGE_MARGIN, screen.height - size.height - EDGE_MARGIN)
    );
    placeDecorations();
  };

  const rememberPlace = () => {
    const rect = wrap.getBoundingClientRect();
    const room = travel();
    position = {
      x: room.width > 0 ? clamp((rect.left - EDGE_MARGIN) / room.width, 0, 1) : 0,
      y: room.height > 0 ? clamp((rect.top - EDGE_MARGIN) / room.height, 0, 1) : 0,
    };
    writePosition(position);
    placeDecorations();
  };

  const backToOriginalPlace = () => {
    position = null;
    writePosition(null);
    layout();
    launcher.focus();
    status.textContent = currentText().restored;
  };

  launcher.addEventListener('pointerdown', (event) => {
    if (event.button !== 0) {
      return;
    }

    // A new press is its own action, however soon it follows a drag.
    suppressClick = false;
    measure();
    const rect = wrap.getBoundingClientRect();
    drag = { id: event.pointerId, startX: event.clientX, startY: event.clientY, left: rect.left, top: rect.top, moving: false };
    // Held from the press: a fast mouse can leave the icon before the first move event reaches it.
    launcher.setPointerCapture(event.pointerId);
  }, { signal });

  launcher.addEventListener('pointermove', (event) => {
    if (!drag || event.pointerId !== drag.id) {
      return;
    }

    const deltaX = event.clientX - drag.startX;
    const deltaY = event.clientY - drag.startY;

    if (!drag.moving) {
      if (Math.hypot(deltaX, deltaY) < DRAG_THRESHOLD) {
        return;
      }
      drag.moving = true;
      root.classList.add('bz-dragging');
    }

    moveTo(drag.left + deltaX, drag.top + deltaY);
  }, { signal });

  const endDrag = (event, keep) => {
    if (!drag || event.pointerId !== drag.id) {
      return;
    }

    const moved = drag.moving;
    drag = null;
    root.classList.remove('bz-dragging');

    if (!moved) {
      return;
    }

    if (keep) {
      rememberPlace();
      status.textContent = currentText().moved;
      // The click that ends a drag must not open the chat. Touch may not send one, so it expires.
      suppressClick = true;
      window.setTimeout(() => {
        suppressClick = false;
      }, 400);
    } else {
      layout();
    }
  };

  // Over a page link or image the browser can start its own drag, which cancels this one.
  window.addEventListener('dragstart', (event) => {
    if (drag) {
      event.preventDefault();
    }
  }, { capture: true, signal });

  launcher.addEventListener('pointerup', (event) => endDrag(event, true), { signal });
  launcher.addEventListener('pointercancel', (event) => endDrag(event, false), { signal });

  wrap.addEventListener('click', (event) => {
    if (suppressClick) {
      suppressClick = false;
      event.preventDefault();
      event.stopPropagation();
    }
  }, { capture: true, signal });

  launcher.addEventListener('keydown', (event) => {
    if (event.key === 'Home') {
      if (position) {
        event.preventDefault();
        backToOriginalPlace();
      }
      return;
    }

    const step = event.shiftKey ? KEY_STEP_LARGE : KEY_STEP;
    const moves = { ArrowLeft: [-step, 0], ArrowRight: [step, 0], ArrowUp: [0, -step], ArrowDown: [0, step] };
    const move = moves[event.key];
    if (!move) {
      return;
    }

    event.preventDefault();
    measure();
    const rect = wrap.getBoundingClientRect();
    moveTo(rect.left + move[0], rect.top + move[1]);
    rememberPlace();
    status.textContent = currentText().moved;
  }, { signal });

  reset.addEventListener('click', backToOriginalPlace, { signal });

  window.addEventListener('resize', layout, { signal });
  window.addEventListener('orientationchange', layout, { signal });
  window.visualViewport?.addEventListener('resize', layout, { signal });

  const panelWatcher = new MutationObserver(layout);
  panelWatcher.observe(panel, { attributes: true, attributeFilter: ['hidden'] });

  applyText();
  layout();

  return {
    applyText,
    detach() {
      listeners.abort();
      panelWatcher.disconnect();
      [style, reset, hint, status].forEach((element) => element.remove());
      launcher.removeAttribute('aria-describedby');
      root.classList.remove(PLATFORM_CLASS, 'bz-dragging', 'bz-bubble-below');
      ['left', 'top', 'right', 'bottom'].forEach((property) => root.style.removeProperty(property));
      ['left', 'right'].forEach((property) => bubble.style.removeProperty(property));
      clearPanelPlacement();
    },
  };
}

let attached = null;
let hostWatcher = null;

function stopWatchingForHost() {
  hostWatcher?.disconnect();
  hostWatcher = null;
}

function attachWhenReady() {
  const host = document.getElementById('botzo-widget');
  if (!host?.shadowRoot) {
    return false;
  }

  attached = attach(host);

  return true;
}

/**
 * Call with the Inertia component of the page now showing.
 */
export function syncPlatformMochi(component) {
  const isCustomerPage = typeof component === 'string' && component.startsWith('User/');

  if (!isCustomerPage) {
    stopWatchingForHost();
    attached?.detach();
    attached = null;
    return;
  }

  if (attached) {
    attached.applyText();
    return;
  }

  if (attachWhenReady() || hostWatcher) {
    return;
  }

  // The widget script loads on its own schedule and adds its element to the body when ready.
  hostWatcher = new MutationObserver(() => {
    if (attachWhenReady()) {
      stopWatchingForHost();
    }
  });
  hostWatcher.observe(document.body, { childList: true });
}
