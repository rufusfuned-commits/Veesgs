<?php
// Shared launch popup system. Include before </body> on every page.
//
// Popup behaviour by mode:
//   - every_launch   -> full-screen modal, blocks interaction, OK button closes
//   - once_on_launch -> full-screen modal, blocks interaction, OK button closes
//   - show_now       -> top-of-screen banner, NON-blocking, auto-dismisses after 5s, no button
//
// Polls popup_api.php every 30s and on page load.
?>
<style>
  /* ===== LAUNCH POPUP (full-screen, blocks interaction) — every_launch + once_on_launch ===== */
  #launchPopupBackdrop {
    position: fixed;
    inset: 0;
    z-index: 2147483647; /* above everything, including admin dock */
    display: none;
    align-items: center;
    justify-content: center;
    background: rgba(8, 13, 20, 0.86);
    backdrop-filter: blur(8px);
    -webkit-backdrop-filter: blur(8px);
    /* Block ALL interaction with anything behind the backdrop */
    pointer-events: auto;
  }
  #launchPopupBackdrop.active { display: flex; animation: launchPopupFade 220ms ease both; }
  @keyframes launchPopupFade { from { opacity: 0; } to { opacity: 1; } }

  .launch-popup-card {
    background: rgba(24,30,41,0.98);
    border-radius: 22px;
    box-shadow: 0 18px 60px rgba(0,0,0,0.45);
    border: 1px solid rgba(255,255,255,0.12);
    padding: 38px 34px 26px;
    max-width: min(92vw, 420px);
    width: 100%;
    color: #eaf0fd;
    text-align: center;
    font-family: Inter, Arial, Helvetica, sans-serif;
    font-size: 1.15rem;
    font-weight: 800;
    animation: launchPopupSlide 350ms cubic-bezier(0.34, 1.56, 0.64, 1) both;
    position: relative;
    pointer-events: auto;
  }
  @keyframes launchPopupSlide { from { opacity: 0; transform: translateY(20px) scale(0.95); } to { opacity: 1; transform: translateY(0) scale(1); } }

  .launch-popup-card h2 {
    font-size: 1.5rem;
    margin: 0 0 10px 0;
    letter-spacing: -0.04em;
    font-weight: 950;
    color: #eef3ff;
    word-wrap: break-word;
  }
  .launch-popup-card p {
    color: rgba(234,240,253,0.85);
    font-weight: 750;
    line-height: 1.4;
    margin: 0 0 8px 0;
    word-wrap: break-word;
    overflow-wrap: anywhere;
  }
  .launch-popup-card img {
    max-width: 100%;
    max-height: 240px;
    border-radius: 14px;
    border: 1px solid rgba(255,255,255,0.12);
    margin: 10px 0;
    object-fit: cover;
  }
  .launch-popup-card .launch-popup-btn {
    margin-top: 20px;
    padding: 12px 38px;
    background: linear-gradient(110deg, #a2c4ff 20%, #6c91c2 80%);
    color: #111;
    font-weight: 950;
    border: none;
    border-radius: 14px;
    font-size: 1rem;
    cursor: pointer;
    outline: none;
    box-shadow: 0 4px 16px rgba(100,150,210,0.2);
    font-family: inherit;
    transition: transform 120ms ease, box-shadow 120ms ease;
    min-width: 140px;
  }
  .launch-popup-card .launch-popup-btn:hover { transform: translateY(-1px); box-shadow: 0 6px 22px rgba(100,150,210,0.32); }
  .launch-popup-card .launch-popup-btn:active { transform: translateY(0); }
  .launch-popup-card .launch-popup-btn:focus-visible { outline: 3px solid rgba(162,196,255,0.6); outline-offset: 2px; }

  /* ===== SHOW NOW BANNER (top of screen, non-blocking, 5s auto-dismiss) ===== */
  #showNowBanner {
    position: fixed;
    left: 50%;
    top: calc(14px + env(safe-area-inset-top));
    z-index: 2147483646; /* just below the full-screen modal backdrop */
    transform: translateX(-50%) translateY(-20px);
    width: min(520px, calc(100vw - 24px));
    opacity: 0;
    /* pointer-events: none on the wrapper so clicks pass THROUGH to the page behind */
    pointer-events: none;
    transition: opacity .25s ease, transform .25s cubic-bezier(0.34, 1.56, 0.64, 1);
  }
  #showNowBanner.active { opacity: 1; transform: translateX(-50%) translateY(0); }

  .show-now-card {
    /* pointer-events: none on the card too — user can click stuff behind the banner */
    pointer-events: none;
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 14px 18px;
    border-radius: 18px;
    background: rgba(24,30,41,0.96);
    border: 1px solid rgba(255,255,255,0.15);
    box-shadow: 0 14px 44px rgba(0,0,0,0.42);
    color: #eaf0fd;
    font-family: Inter, Arial, Helvetica, sans-serif;
    backdrop-filter: blur(16px);
    -webkit-backdrop-filter: blur(16px);
  }
  .show-now-card .show-now-image {
    display: none;
    width: 44px;
    height: 44px;
    object-fit: cover;
    border-radius: 12px;
    border: 1px solid rgba(255,255,255,0.15);
    flex: 0 0 44px;
  }
  .show-now-card .show-now-body {
    flex: 1;
    min-width: 0;
    text-align: left;
  }
  .show-now-card .show-now-title {
    font-size: 1rem;
    font-weight: 950;
    color: #eef3ff;
    line-height: 1.25;
    margin: 0 0 2px 0;
    letter-spacing: -0.02em;
    overflow-wrap: anywhere;
  }
  .show-now-card .show-now-message {
    font-size: 0.88rem;
    font-weight: 800;
    color: rgba(234,240,253,0.82);
    line-height: 1.35;
    margin: 0;
    overflow-wrap: anywhere;
  }

  /* Also keep the old non-blocking toast around for backwards compatibility
     (other code on the site may call window.showGlobalNotice). */
  .global-notice-wrap {
    position: fixed;
    left: 50%;
    top: calc(16px + env(safe-area-inset-top));
    z-index: 2147483000;
    transform: translateX(-50%) translateY(-16px);
    width: min(440px, calc(100vw - 28px));
    pointer-events: none;
    opacity: 0;
    transition: opacity .18s ease, transform .18s ease;
  }
  .global-notice-wrap.active { opacity: 1; transform: translateX(-50%) translateY(0); }
  .global-notice-card {
    pointer-events: auto;
    display: flex; align-items: center; gap: 12px;
    padding: 14px 16px; max-height: min(72vh, 460px); overflow: auto;
    border-radius: 20px;
    background: rgba(24,30,41,.96);
    border: 1px solid rgba(255,255,255,.15);
    box-shadow: 0 18px 50px rgba(0,0,0,.38);
    color: #eaf0fd;
    font-family: Inter, Arial, Helvetica, sans-serif;
    text-align: center;
    backdrop-filter: blur(16px);
    -webkit-backdrop-filter: blur(16px);
  }
  .global-notice-image { display: none; width: 58px; height: 58px; max-width: 30vw; object-fit: cover; border-radius: 14px; border: 1px solid rgba(255,255,255,.15); flex: 0 0 58px; }
  .global-notice-text { flex: 1; font-size: .98rem; font-weight: 900; line-height: 1.35; text-align: center; overflow-wrap: anywhere; }
  .global-notice-action { display: none; margin-left: 4px; border: none; border-radius: 12px; padding: 9px 11px; background: linear-gradient(110deg,#a2c4ff 20%,#6c91c2 80%); color: #111; font-weight: 950; cursor: pointer; font-family: inherit; }
</style>

<!-- Full-screen launch popup (blocks interaction until OK is pressed) — every_launch + once_on_launch -->
<div id="launchPopupBackdrop" role="dialog" aria-modal="true" aria-hidden="true">
  <div class="launch-popup-card">
    <h2 id="launchPopupTitle"></h2>
    <p id="launchPopupMessage"></p>
    <img id="launchPopupImage" alt="" style="display:none;">
    <br>
    <button type="button" class="launch-popup-btn" id="launchPopupBtn">Got it</button>
  </div>
</div>

<!-- Show Now banner (top of screen, non-blocking, 5s auto-dismiss, no button) -->
<div id="showNowBanner" role="status" aria-live="polite">
  <div class="show-now-card">
    <img id="showNowImage" class="show-now-image" alt="">
    <div class="show-now-body">
      <div id="showNowTitle" class="show-now-title"></div>
      <div id="showNowMessage" class="show-now-message"></div>
    </div>
  </div>
</div>

<!-- Legacy non-blocking toast (kept for backwards compatibility) -->
<div id="globalNotice" class="global-notice-wrap" role="status" aria-live="polite">
  <div class="global-notice-card">
    <img id="globalNoticeImage" class="global-notice-image" alt="">
    <div id="globalNoticeText" class="global-notice-text"></div>
    <button id="globalNoticeAction" class="global-notice-action" type="button">Open</button>
  </div>
</div>

<script>
(function () {
  'use strict';

  let noticeTimer = null;
  let showNowTimer = null;
  let currentPopupId = null;
  let currentPopupMode = null;
  let popupCheckInProgress = false;

  const SHOW_NOW_DURATION_MS = 5000; // 5 seconds

  function escapeHTML(text) {
    return String(text || '').replace(/[&<>"']/g, s => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[s]));
  }

  // ---------- Legacy toast (kept for backwards compatibility) ----------
  window.showGlobalNotice = function (message, options) {
    options = options || {};
    const wrap = document.getElementById('globalNotice');
    const text = document.getElementById('globalNoticeText');
    const image = document.getElementById('globalNoticeImage');
    const action = document.getElementById('globalNoticeAction');
    if (!wrap || !text || !image || !action) return;
    text.innerHTML = options.html ? String(message || '') : escapeHTML(message || '');
    if (options.image) { image.src = String(options.image); image.style.display = 'block'; }
    else { image.removeAttribute('src'); image.style.display = 'none'; }
    if (options.actionText && typeof options.onAction === 'function') {
      action.textContent = options.actionText;
      action.style.display = 'inline-block';
      action.onclick = options.onAction;
    } else {
      action.style.display = 'none';
      action.onclick = null;
    }
    wrap.classList.add('active');
    clearTimeout(noticeTimer);
    noticeTimer = setTimeout(() => wrap.classList.remove('active'), options.duration || 3000);
  };

  // ---------- Full-screen launch popup (every_launch + once_on_launch) ----------
  function showLaunchPopup(data) {
    const backdrop = document.getElementById('launchPopupBackdrop');
    const titleEl = document.getElementById('launchPopupTitle');
    const msgEl = document.getElementById('launchPopupMessage');
    const imgEl = document.getElementById('launchPopupImage');
    const btnEl = document.getElementById('launchPopupBtn');
    if (!backdrop || !titleEl || !msgEl || !btnEl) return;

    currentPopupId = data.id || null;
    currentPopupMode = data.mode || 'every_launch';

    const titleText = (data.title && String(data.title).trim()) || '';
    const msgText = (data.message && String(data.message).trim()) || '';
    titleEl.textContent = titleText;
    titleEl.style.display = titleText ? 'block' : 'none';
    msgEl.textContent = msgText;
    msgEl.style.display = msgText ? 'block' : 'none';

    if (data.image) { imgEl.src = String(data.image); imgEl.style.display = 'block'; }
    else { imgEl.removeAttribute('src'); imgEl.style.display = 'none'; }

    btnEl.textContent = (data.button_text && String(data.button_text).trim()) || 'Got it';

    document.body.style.overflow = 'hidden';
    backdrop.classList.add('active');
    backdrop.setAttribute('aria-hidden', 'false');
    setTimeout(() => { try { btnEl.focus(); } catch(e){} }, 50);
  }

  function closeLaunchPopup() {
    const backdrop = document.getElementById('launchPopupBackdrop');
    if (!backdrop) return;
    backdrop.classList.remove('active');
    backdrop.setAttribute('aria-hidden', 'true');
    document.body.style.overflow = '';

    if (currentPopupId) {
      const endpoint = (currentPopupMode === 'once_on_launch' || currentPopupMode === 'show_now')
        ? 'dismiss_once'
        : 'seen';
      try { fetch('/popup_api.php?action=' + endpoint + '&id=' + encodeURIComponent(currentPopupId)); } catch(e) {}

      // For once_on_launch: persist the dismissal in localStorage so the
      // popup NEVER shows again for this browser, even if the PHP session
      // changes (cookie expiry, session regeneration, etc.).
      if (currentPopupMode === 'once_on_launch') {
        try { localStorage.setItem('popup_dismissed_' + currentPopupId, String(Date.now())); } catch(e) {}
      }
    }
    currentPopupId = null;
    currentPopupMode = null;
  }

  // ---------- Show Now banner (top of screen, non-blocking, 5s auto-dismiss) ----------
  function showNowBanner(data) {
    const banner = document.getElementById('showNowBanner');
    const titleEl = document.getElementById('showNowTitle');
    const msgEl = document.getElementById('showNowMessage');
    const imgEl = document.getElementById('showNowImage');
    if (!banner || !titleEl || !msgEl) return;

    currentPopupId = data.id || null;
    currentPopupMode = 'show_now';

    const titleText = (data.title && String(data.title).trim()) || '';
    const msgText = (data.message && String(data.message).trim()) || '';
    titleEl.textContent = titleText;
    titleEl.style.display = titleText ? 'block' : 'none';
    msgEl.textContent = msgText;
    msgEl.style.display = msgText ? 'block' : 'none';

    if (data.image) { imgEl.src = String(data.image); imgEl.style.display = 'block'; }
    else { imgEl.removeAttribute('src'); imgEl.style.display = 'none'; }

    // Show the banner
    banner.classList.add('active');

    // Auto-dismiss after 5 seconds, then mark as dismissed so it doesn't reappear
    clearTimeout(showNowTimer);
    showNowTimer = setTimeout(function () {
      banner.classList.remove('active');
      if (currentPopupId) {
        try { fetch('/popup_api.php?action=dismiss_once&id=' + encodeURIComponent(currentPopupId)); } catch(e) {}
      }
      currentPopupId = null;
      currentPopupMode = null;
    }, SHOW_NOW_DURATION_MS);
  }

  // ---------- OK button handler (closes full-screen popup only) ----------
  // Per requirements: the modal must ONLY close when the user clicks the
  // dismiss button. It must NOT close on Escape, on Enter (unless the
  // dismiss button is focused — that's the browser default for buttons),
  // on Space (same), or on backdrop click.
  document.addEventListener('DOMContentLoaded', function() {
    const btn = document.getElementById('launchPopupBtn');
    if (btn) {
      btn.addEventListener('click', closeLaunchPopup);
    }

    // Escape key: explicitly DO NOTHING. The modal stays open.
    // (We swallow Escape so screen-readers / keyboard users can't accidentally
    // dismiss via the conventional ESC = close-dialog pattern.)
    document.addEventListener('keydown', function(e) {
      if (e.key !== 'Escape') return;
      const backdrop = document.getElementById('launchPopupBackdrop');
      if (!backdrop || !backdrop.classList.contains('active')) return;
      // Modal is showing + Escape pressed -> swallow it, do NOT close.
      e.preventDefault();
      e.stopPropagation();
    });

    // Backdrop click: explicitly DO NOTHING. The modal stays open.
    // (Only the dismiss button inside the card closes the modal.)
    const backdrop = document.getElementById('launchPopupBackdrop');
    if (backdrop) {
      backdrop.addEventListener('click', function(e) {
        if (e.target === backdrop) { e.preventDefault(); e.stopPropagation(); }
      });
    }
  });

  // ---------- Polling ----------
  // Track Every Launch + Once On Launch popup IDs we've already shown THIS page session.
  // This prevents the 30-second poll from re-displaying the same popup
  // over and over. When the page is refreshed, these Sets reset.
  //   - Every Launch: shows again on every refresh (backend has no per-user gate)
  //   - Once On Launch: shows again on refresh ONLY if the user hasn't seen it before
  //     (backend gates on seen[]; once dismissed, never shows again for that user)
  const shownEveryLaunchIdsThisSession = new Set();
  const shownOnceOnLaunchIdsThisSession = new Set();

  async function checkGlobalLaunchPopup(isInitial) {
    if (popupCheckInProgress) return;
    // Don't poll if a full-screen modal popup is already showing
    const backdrop = document.getElementById('launchPopupBackdrop');
    if (backdrop && backdrop.classList.contains('active')) return;

    popupCheckInProgress = true;
    try {
      const response = await fetch('/popup_api.php?action=check&cache=' + Date.now());
      if (!response.ok) return;
      const data = await response.json();
      if (!data || (!data.message && !data.image && !data.title)) return;
      // Don't replace an already-showing full-screen modal
      if (backdrop && backdrop.classList.contains('active')) return;
      // Don't replace an already-showing show_now banner (let it finish its 5s)
      const banner = document.getElementById('showNowBanner');
      if (banner && banner.classList.contains('active') && data.mode === 'show_now') return;

      // Every Launch: ONLY fires when the user initially enters the Home page.
      // We check that #homeView is the active view. This prevents the popup
      // from showing on Games, Apps, Messages, Proxy, or other views.
      if (data.mode === 'every_launch') {
        if (shownEveryLaunchIdsThisSession.has(data.id)) return;
        // Only display on the initial page-load check. Skip on 30s polls.
        if (!isInitial) return;
        // ONLY show when the Home view is active (user is on the homepage).
        const homeView = document.getElementById('homeView');
        if (!homeView || !homeView.classList.contains('active')) return;
        shownEveryLaunchIdsThisSession.add(data.id);
        showLaunchPopup(data);
        return;
      }

      // Once On Launch: shows exactly ONE time per user, ever.
      // Tracking is dual-layered:
      //   1. Server-side: popup_api.php gates on seen[$key] (per session account)
      //   2. Client-side: localStorage key 'popup_dismissed_<id>' (per browser)
      // The localStorage layer survives session cookie expiry, different devices
      // (same browser profile), and PHP session regeneration — ensuring the
      // popup truly shows only once per user, ever.
      if (data.mode === 'once_on_launch') {
        if (shownOnceOnLaunchIdsThisSession.has(data.id)) return;
        if (!isInitial) return;
        // Client-side persistence check: if this browser has already dismissed
        // this popup, never show it again.
        try {
          if (localStorage.getItem('popup_dismissed_' + data.id)) return;
        } catch(e) {}
        shownOnceOnLaunchIdsThisSession.add(data.id);
        showLaunchPopup(data);
        return;
      }

      // Show Now: top-of-screen banner (non-blocking, 5s auto-dismiss).
      // Show Now is allowed to fire on 30s polls because the backend gates it on
      // dismissed[] (per-user persistent), so a brand-new Show Now popup created
      // after the page loaded should still appear to the user via polling.
      if (data.mode === 'show_now') {
        showNowBanner(data);
      }
    } catch (error) {
      // Silent — popups are best-effort
    } finally {
      popupCheckInProgress = false;
    }
  }

  window.addEventListener('DOMContentLoaded', function () {
    // Initial check on page launch — passes isInitial=true so Every Launch + Once On Launch popups show.
    setTimeout(function () { checkGlobalLaunchPopup(true); }, 600);
    // 30-second polls — passes isInitial=false so Every Launch + Once On Launch popups are skipped
    // (they only show on the initial page-load check, not on polls).
    // Show Now popups still fire on polls (the backend gates them on dismissed[]).
    setInterval(function () { checkGlobalLaunchPopup(false); }, 30000);
  });
})();
</script>
