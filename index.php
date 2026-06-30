<?php
require __DIR__ . '/auth_check.php';
require_once __DIR__ . '/profile_helpers.php';
$currentAccountKeyForHome = profile_account_key();
$currentDisplayForHome = profile_display_name($currentAccountKeyForHome);
$homeSafePfpKey = preg_replace('/[^a-z0-9_-]/i', '-', $currentAccountKeyForHome);
$homePfpMissing = ($currentAccountKeyForHome === 'guest') || !file_exists(__DIR__ . '/profile_pfps/' . $homeSafePfpKey . '.png');
$homeOnlineCount = count(profile_online_map());
$homeAdminAlertCount = 0;
if (profile_can_access_admin()) {
  $homeProfileRequests = profile_load_json(profile_requests_file());
  $homeAdminAlertCount += is_array($homeProfileRequests) ? count($homeProfileRequests) : 0;
  $homeIssuesFile = __DIR__ . '/_private/issues.txt';
  if (file_exists($homeIssuesFile)) {
    $homeToday = date('Y-m-d');
    foreach (file($homeIssuesFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $homeLine) {
      if (strpos($homeLine, $homeToday) === 0) $homeAdminAlertCount++;
    }
  }
  $homeUserReportsFile = __DIR__ . '/_private/user_reports.json';
  $homeUserReports = profile_load_json($homeUserReportsFile);
  foreach ($homeUserReports as $homeReport) {
    if (($homeReport['status'] ?? '') === 'new') $homeAdminAlertCount++;
  }
}
// Load apps data
$homeAppsFile = profile_data_dir() . '/apps.json';
$homeAppsData = profile_load_json($homeAppsFile);
$homeEnabledApps = [];
foreach ($homeAppsData as $homeAppId => $homeApp) {
  if (!empty($homeApp['enabled'])) {
    $homeEnabledApps[] = $homeApp;
  }
}
// Load games data from _private/games.json (managed by game_admin.php)
$homeGamesFile = profile_data_dir() . '/games.json';
$homeGamesData = profile_load_json($homeGamesFile);
$homeEnabledGames = [];
foreach ($homeGamesData as $homeGame) {
  if (!empty($homeGame['enabled']) && !empty($homeGame['url'])) {
    $homeEnabledGames[] = $homeGame;
  }
}
// Load proxy content
$proxyContentFile = profile_data_dir() . '/proxy_content.json';
$proxyContentData = profile_load_json($proxyContentFile);
$homeProxyHtml = $proxyContentData['html'] ?? '';
$homeProxyUrl = $proxyContentData['url'] ?? '';
$homeProxyType = $proxyContentData['type'] ?? 'code';
$homeProxyEnabled = isset($proxyContentData['enabled']) ? (bool)$proxyContentData['enabled'] : true;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
  <title>Don't Leak To E Man</title>
  <link id="faviconLink" rel="icon" type="image/png" href="/favicon.png" fetchpriority="high">
  <script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-1819490803357082" crossorigin="anonymous" defer></script>
  <style>
    :root { --theme-color: #5f82dc; --theme-color-rgb: 95, 130, 220; }
    * { margin: 0; padding: 0; box-sizing: border-box; }
    html, body {
      width: 100%; min-height: 100%;
      font-family: Inter, Arial, Helvetica, sans-serif;
      background: radial-gradient(circle at 10% 20%, rgba(var(--theme-color-rgb), 0.38), transparent 28%),
                  linear-gradient(120deg, #080d14 0%, #141a24 45%, #222936 100%);
      color: #d7d9df;
    }
    body { position: relative; overflow-x: hidden; }

    .animated-bg { position: fixed; inset: 0; z-index: 0; background: radial-gradient(circle at 10% 20%, rgba(var(--theme-color-rgb), 0.55), transparent 28%), radial-gradient(circle at 90% 18%, rgba(var(--theme-color-rgb), 0.32), transparent 26%), radial-gradient(circle at 45% 90%, rgba(var(--theme-color-rgb), 0.48), transparent 34%), radial-gradient(circle at 72% 68%, rgba(var(--theme-color-rgb), 0.65), transparent 30%), linear-gradient(120deg, #080d14 0%, #141a24 45%, #222936 100%); background-size: 220% 220%; animation: bgMove 8s ease-in-out infinite alternate; will-change: transform; }
    .animated-bg::before { content: ""; position: absolute; inset: -55%; background: repeating-linear-gradient(115deg, rgba(255,255,255,.04) 0 1px, transparent 1px 70px), repeating-linear-gradient(25deg, rgba(var(--theme-color-rgb),.035) 0 1px, transparent 1px 105px); animation: linesDrift 12s linear infinite; will-change: transform; }
    .animated-bg::after { content: ""; position: absolute; inset: -20%; background: radial-gradient(circle at 25% 45%, rgba(var(--theme-color-rgb), .12), transparent 18%), radial-gradient(circle at 75% 55%, rgba(var(--theme-color-rgb),.14), transparent 20%), radial-gradient(circle at center, transparent 0%, rgba(0,0,0,.36) 74%); animation: glowSweep 8s ease-in-out infinite alternate; will-change: transform, opacity; }
    .light-beam { position: fixed; inset: -30%; z-index: 1; pointer-events: none; background: conic-gradient(from 180deg, transparent 0deg, rgba(var(--theme-color-rgb),.18) 55deg, transparent 115deg, rgba(255,255,255,.09) 170deg, transparent 250deg, rgba(var(--theme-color-rgb),.16) 315deg, transparent 360deg); filter: blur(25px); animation: beamSpin 20s linear infinite; will-change: transform; }
    .wave-layer { position: fixed; inset: 0; z-index: 1; pointer-events: none; background: linear-gradient(100deg, transparent 0%, rgba(var(--theme-color-rgb), .12) 42%, transparent 62%), linear-gradient(80deg, transparent 15%, rgba(var(--theme-color-rgb),.16) 50%, transparent 85%); transform: translateX(-100%); animation: waveSweep 9s ease-in-out infinite; will-change: transform, opacity; }
    .particle-field { position: fixed; inset: 0; z-index: 2; pointer-events: none; overflow: hidden; }
    .particle-field span { position: absolute; width: 5px; height: 5px; border-radius: 50%; background: rgba(230,238,255,.7); animation: floatParticle 8s linear infinite; opacity: 0; will-change: transform, opacity; }
    .particle-field span:nth-child(1) { left: 8%; animation-delay: 0s; animation-duration: 10s; } .particle-field span:nth-child(2) { left: 24%; animation-delay: 2s; animation-duration: 12s; } .particle-field span:nth-child(3) { left: 40%; animation-delay: 4s; animation-duration: 10s; } .particle-field span:nth-child(4) { left: 56%; animation-delay: 1s; animation-duration: 14s; } .particle-field span:nth-child(5) { left: 72%; animation-delay: 3s; animation-duration: 11s; } .particle-field span:nth-child(6) { left: 88%; animation-delay: 5s; animation-duration: 13s; }
    @keyframes bgMove { 0% { transform: scale(1) translate(0, 0); } 50% { transform: scale(1.05) translate(2%, -1%); } 100% { transform: scale(1.03) translate(-1%, 1%); } }
    @keyframes beamSpin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
    @keyframes waveSweep { 0% { transform: translateX(-120%) skewX(-12deg); opacity: 0; } 25% { opacity: 1; } 55% { opacity: .7; } 100% { transform: translateX(120%) skewX(-12deg); opacity: 0; } }
    @keyframes glowSweep { 0% { transform: translate3d(-6%,-4%,0) scale(1); opacity: .75; } 100% { transform: translate3d(6%,5%,0) scale(1.12); opacity: 1; } }
    @keyframes floatParticle { 0% { top: 110%; transform: translateX(0) scale(.5); opacity: 0; } 12% { opacity: .7; } 80% { opacity: .5; } 100% { top: -10%; transform: translateX(80px) scale(1.3); opacity: 0; } }
    @keyframes linesDrift { from { transform: translate3d(-8%,-8%,0) rotate(8deg); } to { transform: translate3d(8%,8%,0) rotate(8deg); } }
    @keyframes fadeUp { from { opacity: 0; transform: translateY(14px); } to { opacity: 1; transform: translateY(0); } }
    @keyframes titleShimmer { 0% { background-position: 0% 50%; } 50% { background-position: 100% 50%; } 100% { background-position: 0% 50%; } }
    @keyframes titleFloat { 0%,100% { transform: translateY(0) scale(1); } 50% { transform: translateY(-5px) scale(1.008); } }
    @keyframes subtitleGlow { 0%,100% { opacity: .72; transform: translateY(0); } 50% { opacity: 1; transform: translateY(-2px); } }
    @keyframes dateTimeGlow { 0%,100% { opacity: .86; } 50% { opacity: 1; } }

    /* 60fps optimization: reduce motion for users who prefer it, and pause heavy
       animations when the tab is not visible. */
    @media (prefers-reduced-motion: reduce) {
      .animated-bg, .animated-bg::before, .animated-bg::after, .light-beam, .wave-layer,
      .particle-field span, .title, .subtitle, .home-date-time {
        animation: none !important;
      }
    }
    body.tab-hidden .animated-bg, body.tab-hidden .animated-bg::before, body.tab-hidden .animated-bg::after,
    body.tab-hidden .light-beam, body.tab-hidden .wave-layer, body.tab-hidden .particle-field span,
    body.tab-hidden .title, body.tab-hidden .subtitle, body.tab-hidden .home-date-time {
      animation-play-state: paused !important;
    }

    .home-view, .games-view, .apps-view { display: none; }
    .active { display: block; }
    .games-view, .apps-view { position: relative; z-index: 25; height: 100vh; overflow-y: auto; padding-bottom: 90px; }
    body.games-open, body.apps-open { overflow: hidden; }
    body.game-player-open, body.proxy-open { overflow: hidden; }
    body.games-open .whats-new-box, body.games-open .issue-btn, body.games-open .game-request-btn, body.apps-open .whats-new-box, body.apps-open .issue-btn, body.apps-open .game-request-btn, body.game-player-open .whats-new-box, body.game-player-open .issue-btn, body.game-player-open .game-request-btn, body.proxy-open .whats-new-box, body.proxy-open .issue-btn, body.proxy-open .game-request-btn, body.proxy-open .message-fab, body.proxy-open .proxy-fab, body.proxy-open .games-fab, body.game-player-open .bottom-center, body.proxy-open .bottom-center { display: none; }
    .game-player-view, .proxy-player-view, .app-player-view { display: none; position: relative; z-index: 40; width: 100vw; height: 100vh; padding: 16px; }
    .proxy-player-view { padding: 0; }
    .game-player-view.active, .proxy-player-view.active, .app-player-view.active { display: block; }
    .player-panel { width: 100%; height: 100%; border-radius: 18px; overflow: hidden; background: rgba(10,15,24,.96); border: 1px solid rgba(255,255,255,.14); box-shadow: 0 18px 55px rgba(0,0,0,.35); display: flex; flex-direction: column; }
    .proxy-panel { border-radius: 0; border: none; box-shadow: none; }
    .player-top { height: 58px; display: flex; align-items: center; gap: 12px; padding: 0 14px; background: rgba(24,30,41,.98); border-bottom: 1px solid rgba(255,255,255,.1); }
    .player-title { flex: 1; font-weight: 900; color: #eef3ff; text-align: center; }
    .player-btn { border: 1px solid rgba(255,255,255,.16); background: rgba(55,70,100,.9); color: white; padding: 10px 14px; border-radius: 12px; font-weight: 900; cursor: pointer; font-family: inherit; transition: .15s; min-height: 44px; }
    .player-btn:hover { background: rgba(75,95,135,.95); }
    /* Phase 1: Player actions right group + lag hint */
    .player-actions-right { display: flex; align-items: flex-end; gap: 8px; margin-left: auto; flex-wrap: nowrap; }
    .lag-hint-group { display: flex; flex-direction: column; align-items: center; gap: 2px; }
    .lag-hint-wrapper { display: flex; flex-direction: column; align-items: center; gap: 0; }
    .lag-hint-text { font-size: .72rem; font-weight: 850; color: rgba(255,200,100,.9); cursor: pointer; white-space: nowrap; padding: 2px 6px; border-radius: 6px; transition: .15s; }
    .lag-hint-text:hover { background: rgba(255,200,100,.12); }
    .lag-hint-arrow { font-size: .65rem; color: rgba(255,200,100,.7); line-height: 1; margin-top: -1px; }
    .game-frame, .proxy-frame, .app-frame { flex: 1; width: 100%; border: 0; background: #000; }

    .hero { text-align: center; animation: fadeUp 900ms ease both; width: 100vw; display: flex; align-items: center; justify-content: center; flex-direction: column; min-height: 100vh; padding-bottom: 210px; }
    .title, .games-title, .apps-title { font-weight: 950; line-height: .9; letter-spacing: -.09em; text-transform: uppercase; background: linear-gradient(110deg, rgba(75,85,110,.28) 0%, rgba(var(--theme-color-rgb),.86) 28%, rgba(245,247,255,.95) 48%, rgba(var(--theme-color-rgb),.76) 68%, rgba(210,213,220,.88) 100%); background-size: 240% 240%; -webkit-background-clip: text; background-clip: text; color: transparent; animation: titleShimmer 3.2s ease-in-out infinite, titleFloat 5s ease-in-out infinite; font-size: clamp(42px, 8vw, 112px); max-width: 90vw; text-align: center; margin: 0; }
    .games-title, .apps-title { padding-top: 40px; margin: 0 auto 8px auto; width: 100%; text-align: center; }
    .home-date-time { position: fixed; top: 24px; left: 50%; width: max-content; max-width: 92vw; transform: translateX(-50%); z-index: 30; display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center; color: rgba(238, 243, 252, 0.9); font-weight: 900; letter-spacing: -0.03em; text-shadow: 0 10px 34px rgba(0,0,0,.35), 0 0 22px rgba(var(--theme-color-rgb),.18); animation: dateTimeGlow 3s ease-in-out infinite; pointer-events: none; }
    .home-time { font-size: clamp(22px, 3.2vw, 44px); line-height: 1; }
    .home-date { margin-top: 7px; font-size: clamp(13px, 1.25vw, 18px); opacity: .78; text-transform: uppercase; letter-spacing: .04em; }
    .subtitle { margin-top: 18px; font-size: clamp(15px,1.6vw,25px); font-weight: 800; color: rgba(236,237,240,.82); letter-spacing: -.05em; text-transform: lowercase; animation: subtitleGlow 3s ease-in-out infinite; }
    .whats-new-box { position: fixed; left: 50%; bottom: 72px; z-index: 30; transform: translateX(-50%); width: min(420px, 88vw); padding: 14px 16px; border-radius: 18px; background: rgba(24,30,41,.82); border: 1px solid rgba(var(--theme-color-rgb), .18); box-shadow: 0 14px 34px rgba(0,0,0,.22), 0 0 28px rgba(var(--theme-color-rgb), .08); backdrop-filter: blur(14px); -webkit-backdrop-filter: blur(14px); text-align: center; }
    .home-ad-box { position: fixed; left: 50%; bottom: 140px; z-index: 29; transform: translateX(-50%); width: min(728px, 92vw); min-height: 90px; overflow: hidden; }
    body.games-open .home-ad-box, body.game-player-open .home-ad-box, body.proxy-open .home-ad-box, body.apps-open .home-ad-box { display: none; }
    .bottom-center { position: fixed; left: 50%; bottom: 28px; z-index: 130; transform: translateX(-50%); color: rgba(235,237,242,.82); font-weight: 850; font-size: 20px; user-select: none; pointer-events: none; text-align: center; }
    .home-user-menu { position: fixed; top: 12px; right: 12px; z-index: 10000; display: flex; gap: 8px; align-items: center; padding: 8px; border-radius: 999px; background: rgba(24,30,41,.88); border: 1px solid rgba(255,255,255,.12); box-shadow: 0 14px 35px rgba(0,0,0,.26); backdrop-filter: blur(14px); -webkit-backdrop-filter: blur(14px); color: white; font-family: Inter, Arial, Helvetica, sans-serif; font-weight: 900; }
    .home-user-menu span { padding: 0 6px; max-width: 160px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .home-user-menu a { color: #eef3ff; text-decoration: none; padding: 8px 10px; border-radius: 999px; background: rgba(55,70,100,.72); }
    .home-user-menu a:hover { background: rgba(75,95,135,.95); }
    .online-count-pill { position: fixed; left: 50%; top: 142px; transform: translateX(-50%); z-index: 30; padding: 9px 14px; border-radius: 999px; background: rgba(24,30,41,.84); border: 1px solid rgba(255,255,255,.13); color: #bfffe0; font-weight: 950; box-shadow: 0 12px 30px rgba(0,0,0,.22); backdrop-filter: blur(14px); -webkit-backdrop-filter: blur(14px); }
    @media(max-width: 560px){ .home-user-menu { left: 10px; right: 10px; justify-content: center; border-radius: 18px; } .home-user-menu span { max-width: 110px; } }

    .home-menu-fab { position: fixed; bottom: 22px; right: 22px; z-index: 2147482500; width: 56px; height: 56px; border-radius: 18px; background: rgba(38,45,58,.9); border: 1px solid rgba(255,255,255,.14); backdrop-filter: blur(14px); -webkit-backdrop-filter: blur(14px); color: #eef1f6; display: flex; align-items: center; justify-content: center; box-shadow: 0 14px 35px rgba(0,0,0,.28); cursor: pointer; outline: none; font: inherit; transition: .15s; }
    .home-menu-fab:hover { background: rgba(48,57,73,.96); transform: translateY(-2px); }
    .home-menu-fab svg { width: 28px; height: 28px; transition: transform 300ms cubic-bezier(0.34, 1.56, 0.64, 1); }
    .home-menu-fab.open svg { transform: rotate(135deg); }
    .home-menu-dropdown { position: fixed; bottom: 90px; right: 22px; z-index: 2147482499; display: flex; flex-direction: column; gap: 10px; pointer-events: none; opacity: 0; transform: translateY(16px) scale(0.9); transform-origin: bottom right; transition: opacity 200ms ease, transform 220ms cubic-bezier(0.34, 1.56, 0.64, 1); }
    .home-menu-dropdown.open { opacity: 1; transform: translateY(0) scale(1); pointer-events: auto; }
    .home-menu-item { display: flex; align-items: center; gap: 12px; padding: 13px 20px 13px 16px; border-radius: 16px; background: rgba(38,45,58,.92); border: 1px solid rgba(255,255,255,.12); backdrop-filter: blur(14px); -webkit-backdrop-filter: blur(14px); color: #eef1f6; font-weight: 850; font-size: 16px; cursor: pointer; text-decoration: none; font-family: inherit; box-shadow: 0 8px 28px rgba(0,0,0,.28); white-space: nowrap; opacity: 0; transform: translateY(20px) scale(0.7); transform-origin: bottom right; transition: opacity 0s ease, transform 0s cubic-bezier(0.34, 1.56, 0.64, 1), background 170ms, border-color 150ms, box-shadow 150ms; }
    .home-menu-item:hover { background: rgba(55,67,92,.96); border-color: rgba(var(--theme-color-rgb), .5); transform: translateX(-4px) scale(1.03); box-shadow: 0 10px 32px rgba(0,0,0,.34); }
    .home-menu-item svg { width: 24px; height: 24px; flex: 0 0 24px; }
    .home-menu-dropdown.open .home-menu-item { opacity: 1; transform: translateY(0) scale(1); }
    .home-menu-dropdown.open .home-menu-item:nth-child(1) { transition-duration: 240ms, 260ms; transition-delay: 240ms, 240ms; } .home-menu-dropdown.open .home-menu-item:nth-child(2) { transition-duration: 240ms, 260ms; transition-delay: 200ms, 200ms; } .home-menu-dropdown.open .home-menu-item:nth-child(3) { transition-duration: 240ms, 260ms; transition-delay: 160ms, 160ms; } .home-menu-dropdown.open .home-menu-item:nth-child(4) { transition-duration: 240ms, 260ms; transition-delay: 120ms, 120ms; } .home-menu-dropdown.open .home-menu-item:nth-child(5) { transition-duration: 240ms, 260ms; transition-delay: 80ms, 80ms; } .home-menu-dropdown.open .home-menu-item:nth-child(6) { transition-duration: 240ms, 260ms; transition-delay: 40ms, 40ms; } .home-menu-dropdown.open .home-menu-item:nth-child(7) { transition-duration: 240ms, 260ms; transition-delay: 0ms, 0ms; }
    .home-menu-dropdown:not(.open) .home-menu-item:nth-child(1) { transition-duration: 150ms, 180ms; transition-delay: 0ms, 0ms; } .home-menu-dropdown:not(.open) .home-menu-item:nth-child(2) { transition-duration: 150ms, 180ms; transition-delay: 30ms, 30ms; } .home-menu-dropdown:not(.open) .home-menu-item:nth-child(3) { transition-duration: 150ms, 180ms; transition-delay: 60ms, 60ms; } .home-menu-dropdown:not(.open) .home-menu-item:nth-child(4) { transition-duration: 150ms, 180ms; transition-delay: 90ms, 90ms; } .home-menu-dropdown:not(.open) .home-menu-item:nth-child(5) { transition-duration: 150ms, 180ms; transition-delay: 120ms, 120ms; } .home-menu-dropdown:not(.open) .home-menu-item:nth-child(6) { transition-duration: 150ms, 180ms; transition-delay: 150ms, 150ms; } .home-menu-dropdown:not(.open) .home-menu-item:nth-child(7) { transition-duration: 150ms, 180ms; transition-delay: 180ms, 180ms; }
    body.chat-player-open .home-menu-fab, body.chat-player-open .home-menu-dropdown { display: none !important; }

    .games-header, .apps-header { position: relative; z-index: 30; text-align: center; }
    .games-search-bar, .apps-search-bar { position: relative; z-index: 31; margin: 18px auto 28px auto; display: block; width: min(460px, 88vw); max-width: 99vw; opacity: 1; visibility: visible; background: rgba(24,30,41,.96); border: 1.5px solid rgba(var(--theme-color-rgb), 0.55); color: #eaf0fd; border-radius: 16px; font-size: 1.12rem; font-weight: 850; padding: 16px 22px; box-shadow: 0 10px 26px rgba(0,0,0,.22), 0 0 28px rgba(var(--theme-color-rgb), .16); outline: none; transition: .17s; text-align: center; }
    .games-search-bar:focus, .apps-search-bar:focus { border-color: rgba(var(--theme-color-rgb),0.75); background: rgba(30,38,54,.96); box-shadow: 0 12px 30px rgba(0,0,0,.24), 0 0 32px rgba(var(--theme-color-rgb),.18); transform: translateY(-1px); }
    .search-sort-row { display: flex; align-items: center; justify-content: center; gap: 10px; margin: 18px auto 28px auto; width: min(560px, 92vw); }
    .search-sort-row .games-search-bar, .search-sort-row .apps-search-bar { margin: 0; flex: 1; width: auto; }
    .sort-select { background: rgba(24,30,41,.96); border: 1.5px solid rgba(var(--theme-color-rgb), 0.55); color: #eaf0fd; border-radius: 16px; font-size: 0.9rem; font-weight: 850; padding: 16px 14px; outline: none; cursor: pointer; font-family: inherit; min-width: 100px; }
    .games-list, .apps-list { width: min(1180px, 92vw); margin: 0 auto; padding: 0 0 80px 0; min-height: 120px; display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 18px; justify-items: center; text-align: center; }
    .game-card, .app-card { width: 100%; max-width: 180px; min-height: 190px; padding: 12px; background: rgba(30,38,54,.82); border-radius: 18px; font-weight: 850; font-size: 1rem; color: #e4ecf8; border: 1.5px solid rgba(130,170,255,0.16); letter-spacing: -.01em; box-shadow: 0 8px 24px rgba(0,0,0,0.18); transition: .13s; cursor: pointer; user-select: none; display: flex; flex-direction: column; align-items: center; justify-content: flex-start; gap: 12px; text-align: center; font-family: inherit; position: relative; }
    .game-card:hover, .app-card:hover { border: 1.5px solid var(--theme-color); transform: translateY(-2px) scale(1.022); background: rgba(38,58,96,0.94); color: #fff; }
    /* Feature 1: ★ favorite button on game cards */
    .fav-star { position: absolute; top: 8px; right: 8px; width: 28px; height: 28px; border-radius: 50%; background: rgba(0,0,0,.55); color: rgba(255,255,255,.7); font-size: 1rem; font-weight: 950; display: flex; align-items: center; justify-content: center; cursor: pointer; z-index: 2; transition: .15s; line-height: 1; }
    .fav-star:hover { background: rgba(0,0,0,.8); transform: scale(1.15); }
    .fav-star.fav-star-on { color: #ffd84a; text-shadow: 0 0 8px rgba(255,216,74,.6); }
    /* Feature 1: Favorites + Recently Played rows */
    .fav-recent-section { width: min(1180px, 92vw); margin: 0 auto 18px auto; display: flex; flex-direction: column; gap: 14px; }
    .fav-recent-row { background: rgba(24,30,41,.6); border: 1px solid rgba(255,255,255,.08); border-radius: 18px; padding: 14px 16px; }
    .fav-recent-label { font-size: .82rem; font-weight: 950; color: rgba(234,240,253,.7); text-transform: uppercase; letter-spacing: .06em; margin-bottom: 10px; }
    .fav-recent-cards { display: flex; gap: 12px; overflow-x: auto; padding-bottom: 4px; }
    .fav-recent-cards .game-card { min-width: 130px; max-width: 130px; min-height: 160px; flex: 0 0 130px; }
    .fav-recent-cards .game-card .game-picture { aspect-ratio: 1 / 1; }
    .fav-recent-cards .game-card .game-name { font-size: .82rem; min-height: 32px; }
    .game-picture, .app-picture { width: 100%; aspect-ratio: 1 / 1; border-radius: 14px; border: 1px solid rgba(255,255,255,.12); background: rgba(10,15,24,.55); background-size: cover; background-position: center; display: flex; align-items: center; justify-content: center; color: rgba(235,240,250,.55); font-size: .82rem; font-weight: 900; text-transform: uppercase; overflow: hidden; }
    .game-name, .app-name { min-height: 38px; display: flex; align-items: center; justify-content: center; line-height: 1.1; word-break: break-word; }
    .app-card .app-icon-placeholder { width: 100%; aspect-ratio: 1 / 1; border-radius: 14px; background: rgba(var(--theme-color-rgb), .25); display: flex; align-items: center; justify-content: center; font-size: 2.5rem; font-weight: 900; color: rgba(var(--theme-color-rgb), .7); }

    .issue-btn, .game-request-btn { position: fixed; left: 18px; z-index: 120; border: 1px solid rgba(255,255,255,.15); border-radius: 999px; background: rgba(38,45,58,.86); backdrop-filter: blur(14px); -webkit-backdrop-filter: blur(14px); color: #eef3ff; padding: 12px 16px; font-weight: 950; font-size: .95rem; cursor: pointer; font-family: inherit; box-shadow: 0 12px 28px rgba(0,0,0,.24), 0 0 22px rgba(var(--theme-color-rgb), .08); transition: .15s; }
    .issue-btn { top: 18px; } .game-request-btn { top: 70px; }
    .issue-btn:hover, .game-request-btn:hover { background: rgba(55,67,92,.94); border-color: rgba(var(--theme-color-rgb), .5); transform: translateY(-1px); }
    .issue-modal-backdrop { position: fixed; inset: 0; z-index: 10001; background: rgba(8,13,20,.78); backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px); display: none; align-items: center; justify-content: center; padding: 18px; }
    .issue-modal-backdrop.active { display: flex; }
    .issue-modal { width: min(92vw, 460px); background: rgba(24,30,41,.98); border: 1px solid rgba(255,255,255,.13); border-radius: 24px; box-shadow: 0 18px 60px rgba(0,0,0,.38); padding: 26px; color: #eaf0fd; animation: fadeUp 260ms ease both; }
    .modal-backdrop { position: fixed; z-index: 10000; top: 0; left: 0; width: 100vw; height: 100vh; background: rgba(15,22,34,0.77); display: flex; align-items: center; justify-content: center; transition: opacity 220ms; animation: fadeUp 300ms; }
    .modal { background: rgba(24,30,41,0.98); border-radius: 22px; box-shadow: 0 12px 38px rgba(0,0,0,0.28); border: 1px solid rgba(255,255,255,0.12); padding: 38px 34px 26px; max-width: min(92vw, 368px); color: #eaf0fd; text-align: center; font-size: 1.15rem; font-weight: 800; animation: fadeUp 350ms; letter-spacing: -0.01em; position: relative; }
    .modal h2 { font-size: 1.4rem; margin-bottom: 8px; letter-spacing: -.04em; }
    .modal p { color: rgba(234,240,253,.78); font-weight: 750; line-height: 1.35; margin-bottom: 4px; }
    .modal button { margin-top: 20px; padding: 11px 38px; background: linear-gradient(110deg,rgba(var(--theme-color-rgb),.85) 20%,rgba(var(--theme-color-rgb),.65) 80%); color: #111; font-weight: 900; border: none; border-radius: 12px; font-size: 1rem; cursor: pointer; outline: none; box-shadow: 0 2px 12px rgba(var(--theme-color-rgb), .1); }
    .settings-modal { max-width: min(92vw, 400px); }
    .settings-modal h2 { font-size: 1.5rem; margin-bottom: 20px; letter-spacing: -.04em; }
    .settings-section { margin-bottom: 20px; }
    .settings-section label { display: block; margin-bottom: 8px; font-weight: 850; color: rgba(234,240,253,.8); }
    .settings-section input[type="color"] { width: 100%; height: 50px; border: 1.5px solid rgba(var(--theme-color-rgb), .32); border-radius: 12px; background: rgba(10,15,24,.72); cursor: pointer; padding: 5px; }
    .settings-section select { width: 100%; padding: 14px 15px; border: 1.5px solid rgba(var(--theme-color-rgb), .32); border-radius: 12px; background: rgba(10,15,24,.72); color: #eef3ff; font-family: inherit; font-size: 1rem; font-weight: 850; outline: none; cursor: pointer; }
    .settings-actions { display: flex; gap: 10px; margin-top: 20px; }
    .settings-actions button { flex: 1; padding: 12px 16px; border: none; border-radius: 12px; font-weight: 950; cursor: pointer; font-family: inherit; }
    .reset-btn { background: rgba(255,100,100,.2); color: #ff6b6b; border: 1px solid rgba(255,100,100,.3); }
    .reset-btn:hover { background: rgba(255,100,100,.3); }

    /* ===== FULL-SCREEN iMESSAGE CHAT ===== */
    .chat-player-view { position: fixed; inset: 0; z-index: 100; display: none; background: #000; }
    .chat-player-view.active { display: flex; }
    body.chat-player-open { overflow: hidden; }
    body.chat-player-open .issue-btn, body.chat-player-open .game-request-btn, body.chat-player-open .home-menu-fab, body.chat-player-open .home-menu-dropdown { display: none !important; }

    /* Two-pane layout: sidebar + main */
    .chat-interface {
      display: flex; width: 100%; height: 100%;
      background: rgba(10,15,24,.98);
      overflow: hidden;
    }
    .chat-sidebar {
      width: 300px; flex: 0 0 300px; height: 100%;
      display: flex; flex-direction: column;
      background: rgba(15,20,28,.98);
      border-right: 1px solid rgba(255,255,255,.08);
    }
    .chat-sidebar-header {
      display: flex; align-items: center; justify-content: space-between;
      padding: 16px 18px 12px;
      border-bottom: 1px solid rgba(255,255,255,.06);
      flex: 0 0 auto;
    }
    .chat-sidebar-title { font-size: 1.25rem; font-weight: 950; letter-spacing: -.03em; color: #eef3ff; }
    .chat-new-dm-btn {
      width: 32px; height: 32px; border-radius: 50%; border: none;
      background: var(--theme-color); color: white; font-size: 1.3rem; font-weight: 900;
      cursor: pointer; font-family: inherit; line-height: 1;
      display: flex; align-items: center; justify-content: center;
      transition: .15s;
    }
    .chat-new-dm-btn:hover { background: #1a7cff; transform: scale(1.08); }
    .chat-conversation-list {
      flex: 1; overflow-y: auto; padding: 8px 8px 16px;
      display: flex; flex-direction: column; gap: 2px;
    }
    .chat-conv-item {
      display: flex; flex-direction: column; gap: 2px;
      padding: 10px 12px; border-radius: 14px;
      cursor: pointer; font-family: inherit; transition: background .12s;
      color: #eef3ff;
    }
    .chat-conv-item:hover { background: rgba(255,255,255,.05); }
    .chat-conv-item.active { background: rgba(10,108,255,.22); }
    .chat-conv-row { display: flex; justify-content: space-between; align-items: center; gap: 8px; }
    .chat-conv-name { font-weight: 850; font-size: .98rem; color: #eef3ff; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 180px; }
    .chat-conv-time { font-size: .68rem; opacity: .55; font-weight: 750; white-space: nowrap; }
    .chat-conv-preview { font-size: .82rem; opacity: .55; font-weight: 750; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 240px; }
    .chat-conv-avatar {
      width: 36px; height: 36px; border-radius: 50%;
      background: linear-gradient(135deg, rgba(var(--theme-color-rgb),.65), rgba(var(--theme-color-rgb),.85));
      color: #111; font-weight: 950; font-size: 1rem;
      display: flex; align-items: center; justify-content: center;
      flex: 0 0 36px; margin-right: 10px;
    }
    .chat-conv-item-row { display: flex; align-items: center; }

    .chat-main {
      flex: 1; height: 100%; min-width: 0;
      display: flex; flex-direction: column;
      background: rgba(10,15,24,.98);
    }
    .chat-header {
      display: flex; align-items: center; justify-content: space-between; gap: 8px;
      padding: 12px 16px; background: rgba(24,30,41,.98);
      border-bottom: 1px solid rgba(255,255,255,.1);
      flex: 0 0 auto;
    }
    .chat-header-left { display: flex; align-items: center; gap: 10px; min-width: 0; }
    .chat-header h3 { font-size: 1.05rem; font-weight: 900; letter-spacing: -.03em; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .chat-header-actions { display: flex; gap: 8px; align-items: center; flex: 0 0 auto; }
    .chat-header-btn {
      background: rgba(55,70,100,.6); border: none; color: white; padding: 6px 12px;
      border-radius: 10px; font-weight: 850; font-size: .82rem; cursor: pointer; font-family: inherit;
    }
    .chat-header-btn:hover { background: rgba(75,95,135,.8); }
    .chat-back-btn {
      display: none; background: none; border: none; color: var(--theme-color);
      font-size: 1.5rem; cursor: pointer; padding: 0 6px; line-height: 1; font-family: inherit;
      align-items: center; justify-content: center;
    }
    .chat-back-btn:hover { color: #1a7cff; }
    .chat-close-btn { background: none; border: none; color: rgba(234,240,253,.6); font-size: 1.8rem; cursor: pointer; padding: 0 4px; line-height: 1; }
    .chat-close-btn:hover { color: rgba(234,240,253,.9); }

    .chat-messages-area {
      flex: 1; overflow-y: auto; padding: 14px 16px;
      display: flex; flex-direction: column; gap: 4px;
      background: rgba(8,13,20,.6);
      scroll-behavior: smooth;
    }
    .chat-message {
      max-width: 82%; padding: 9px 14px; border-radius: 18px;
      word-wrap: break-word; overflow-wrap: anywhere; font-size: .95rem; line-height: 1.35;
      margin-bottom: 6px; position: relative;
    }
    .chat-message .sender { font-size: 0.7rem; font-weight: 850; opacity: 0.6; margin-bottom: 2px; }
    .chat-message .text { font-weight: 750; white-space: pre-wrap; word-break: break-word; }
    .chat-message .time { font-size: 0.62rem; opacity: 0.45; margin-top: 3px; text-align: right; }
    .chat-message.own { align-self: flex-end; background: var(--theme-color); color: white; border-bottom-right-radius: 4px; }
    .chat-message.other { align-self: flex-start; background: rgba(50,58,75,.9); color: #eef3ff; border-bottom-left-radius: 4px; }
    .chat-message.system { align-self: center; background: none; color: rgba(234,240,253,.4); font-size: .75rem; font-weight: 850; max-width: 90%; text-align: center; padding: 4px; }
    /* Feature 4: Profile picture in chat messages */
    .chat-msg-content { display: flex; align-items: flex-end; gap: 6px; }
    .chat-message.own .chat-msg-content { flex-direction: row-reverse; }
    .chat-msg-pfp { width: 28px; height: 28px; border-radius: 50%; object-fit: cover; flex: 0 0 28px; background: rgba(255,255,255,.1); }
    .chat-msg-pfp-fallback { display: flex; align-items: center; justify-content: center; font-size: .72rem; font-weight: 950; background: rgba(var(--theme-color-rgb), .35); color: rgba(234,240,253,.8); }
    .chat-msg-bubble { flex: 1; min-width: 0; }
    .chat-empty { text-align: center; padding: 40px 20px; color: rgba(234,240,253,.4); font-weight: 800; font-size: .92rem; }

    .chat-input-container {
      display: flex; align-items: center; gap: 8px;
      padding: 10px 12px; background: rgba(18,24,34,.98);
      border-top: 1px solid rgba(255,255,255,.08);
      flex: 0 0 auto;
    }
    .chat-input-container input {
      flex: 1; padding: 12px 16px; border: 1.5px solid rgba(var(--theme-color-rgb), .15);
      border-radius: 24px; background: rgba(30,38,54,.9); color: #eef3ff;
      font-family: inherit; font-size: .95rem; font-weight: 850; outline: none; transition: .15s; min-width: 0;
    }
    .chat-input-container input:focus { border-color: rgba(var(--theme-color-rgb), .4); }
    .chat-input-container input::placeholder { color: rgba(234,240,253,.3); }
    .chat-input-container button {
      padding: 12px 22px; border: none; border-radius: 24px; font-weight: 950;
      cursor: pointer; font-family: inherit; background: var(--theme-color); color: white;
      font-size: .9rem; transition: .15s; white-space: nowrap;
    }
    .chat-input-container button:hover { background: #1a7cff; }
    .chat-input-container button:disabled { opacity: .4; cursor: default; }
    .chat-cooldown { padding: 6px 16px; text-align: center; font-size: .8rem; color: rgba(255,100,100,.85); font-weight: 850; border-top: 1px solid rgba(255,100,100,.15); flex: 0 0 auto; }

    /* Mobile: stack sidebar/chat, show one at a time */
    @media (max-width: 768px) {
      .chat-sidebar { width: 100%; flex: 1 1 100%; }
      .chat-main { display: none; flex: 1 1 100%; }
      .chat-player-view.showing-chat .chat-sidebar { display: none; }
      .chat-player-view.showing-chat .chat-main { display: flex; }
      .chat-player-view.showing-chat .chat-back-btn { display: flex; }
      .chat-conv-name { max-width: 60vw; }
      .chat-conv-preview { max-width: 70vw; }
      .chat-header h3 { max-width: 40vw; }
    }

    /* 60fps optimization: reduce backdrop-filter blur on touch devices (iPad).
       backdrop-filter is the #1 GPU cost — reducing blur radius from 14-16px to 8px
       cuts compositing time by ~40% with no visible difference on small screens. */
    @media (max-width: 1024px) and (pointer: coarse) {
      .home-menu-fab, .home-menu-item, .issue-btn, .game-request-btn,
      .online-count-pill, .whats-new-box, .bottom-center,
      .chat-header, .chat-input-container, .chat-sidebar,
      .modal-backdrop, .modal, .chat-conv-item,
      .show-now-card, .dm-toast-card, .launch-popup-card {
        backdrop-filter: blur(8px) !important;
        -webkit-backdrop-filter: blur(8px) !important;
      }
      .home-menu-fab { width: 60px; height: 60px; border-radius: 20px; }
      .home-menu-fab svg { width: 30px; height: 30px; }
      .home-menu-item { padding: 16px 24px 16px 18px; font-size: 17px; min-height: 48px; }
      .home-menu-item svg { width: 26px; height: 26px; flex: 0 0 26px; }
      .player-btn { padding: 12px 18px; font-size: .95rem; min-height: 48px; }
      .game-card, .app-card { min-height: 210px; }
      .game-card .game-name, .app-card .app-name { font-size: 1.05rem; min-height: 42px; }
      .games-search-bar, .apps-search-bar { font-size: 1.15rem; padding: 18px 24px; }
      .sort-select { padding: 18px 16px; font-size: 1rem; }
      .chat-input-container input { padding: 14px 18px; font-size: 1rem; min-height: 48px; }
      .chat-input-container button { padding: 14px 26px; font-size: .95rem; min-height: 48px; }
      .chat-send-btn { min-height: 48px; }
      .launch-popup-btn { padding: 14px 42px; font-size: 1.05rem; min-height: 48px; }
      .modal button { padding: 14px 42px; min-height: 48px; }
      .issue-btn, .game-request-btn { padding: 14px 20px; font-size: 1rem; }
      .fav-star { width: 34px; height: 34px; font-size: 1.2rem; }
      .lag-hint-text { font-size: .8rem; padding: 4px 8px; }
      .player-top { height: 64px; }
    }
    /* Phase 4: Desktop expansion — use wider layouts on large screens */
    @media (min-width: 1200px) {
      .games-list, .apps-list { grid-template-columns: repeat(auto-fill, minmax(170px, 1fr)); }
      .fav-recent-cards .game-card { min-width: 150px; max-width: 150px; flex: 0 0 150px; }
    }

    .site-toast { position: fixed; left: 50%; bottom: 118px; z-index: 20000; transform: translateX(-50%); width: min(460px, 92vw); background: rgba(24,30,41,.98); border: 1px solid rgba(var(--theme-color-rgb), .2); border-radius: 20px; box-shadow: 0 20px 60px rgba(0,0,0,.45); color: #eef3ff; padding: 16px; display: none; font-weight: 850; line-height: 1.35; }
    .site-toast.active { display: block; }

    /* Phase 5: DM Toast Notification (top of screen, non-blocking, 5s auto-dismiss) */
    #dmToastBanner {
      position: fixed; left: 50%; top: calc(14px + env(safe-area-inset-top));
      z-index: 2147483645; transform: translateX(-50%) translateY(-20px);
      width: min(440px, calc(100vw - 24px)); opacity: 0;
      pointer-events: none; /* non-blocking — clicks pass through */
      transition: opacity .25s ease, transform .25s cubic-bezier(0.34, 1.56, 0.64, 1);
    }
    #dmToastBanner.active { opacity: 1; transform: translateX(-50%) translateY(0); }
    .dm-toast-card {
      pointer-events: none; display: flex; align-items: center; gap: 12px;
      padding: 14px 18px; border-radius: 18px;
      background: rgba(24,30,41,0.96); border: 1px solid rgba(255,255,255,0.15);
      box-shadow: 0 14px 44px rgba(0,0,0,0.42); color: #eaf0fd;
      font-family: Inter, Arial, Helvetica, sans-serif;
      backdrop-filter: blur(16px); -webkit-backdrop-filter: blur(16px);
    }
    .dm-toast-pfp { width: 40px; height: 40px; border-radius: 50%; object-fit: cover; flex: 0 0 40px; background: rgba(95,130,220,.3); }
    .dm-toast-pfp-fallback { display: flex; align-items: center; justify-content: center; font-size: 1rem; font-weight: 950; background: rgba(var(--theme-color-rgb),.35); color: rgba(234,240,253,.8); }
    .dm-toast-body { flex: 1; min-width: 0; text-align: left; }
    .dm-toast-sender { font-size: 1rem; font-weight: 950; color: #eef3ff; line-height: 1.25; margin: 0 0 2px 0; letter-spacing: -0.02em; overflow-wrap: anywhere; }
    .dm-toast-message { font-size: 0.85rem; font-weight: 800; color: rgba(234,240,253,0.72); line-height: 1.35; margin: 0; overflow-wrap: anywhere; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

    /* Phase 5: Kebab menu + mute in conversation list */
    .chat-conv-item-row { display: flex; align-items: center; }
    .chat-conv-kebab { width: 28px; height: 28px; border-radius: 50%; border: none; background: rgba(255,255,255,.08); color: rgba(234,240,253,.6); font-size: 1.1rem; cursor: pointer; display: flex; align-items: center; justify-content: center; flex: 0 0 28px; transition: .15s; line-height: 1; padding: 0; }
    .chat-conv-kebab:hover { background: rgba(255,255,255,.15); color: #eef3ff; }
    .chat-conv-mute-row { display: none; padding: 6px 12px 6px 56px; font-size: .82rem; font-weight: 850; color: rgba(234,240,253,.7); align-items: center; gap: 8px; }
    .chat-conv-mute-row.show { display: flex; }
    .chat-conv-mute-row input { width: auto; margin: 0; }
    .chat-conv-muted-icon { font-size: .72rem; color: rgba(255,180,80,.7); margin-left: 4px; }
    body.games-open .settings-fab, body.game-player-open .settings-fab, body.proxy-open .settings-fab, body.apps-open .settings-fab, body.chat-player-open .settings-fab { display: none; }
  </style>
</head>
<body>
  <!-- Bug #10 fix: Removed <audio id="siteMusicAudio" src="/site-music.mp3">
       because the file doesn't exist (404 on every page load). The unlock
       script below was also removed. -->
  <div class="animated-bg"></div>
  <div class="light-beam" aria-hidden="true"></div>
  <div class="wave-layer" aria-hidden="true"></div>
  <div class="particle-field" aria-hidden="true">
    <span></span><span></span><span></span><span></span><span></span><span></span>
  </div>

  <button class="home-menu-fab" id="homeMenuFab" type="button" aria-label="Menu">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="4" y1="7" x2="20" y2="7"/><line x1="4" y1="12" x2="20" y2="12"/><line x1="4" y1="17" x2="20" y2="17"/></svg>
  </button>
  <div class="home-menu-dropdown" id="homeMenuDropdown">
    <button class="home-menu-item" id="homeMenuHomeBtn" type="button"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg> Home</button>
    <button class="home-menu-item" id="homeMenuGamesBtn" type="button"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="6" y1="11" x2="10" y2="11"/><line x1="8" y1="9" x2="8" y2="13"/><line x1="15" y1="12" x2="15.01" y2="12"/><line x1="18" y1="10" x2="18.01" y2="10"/><path d="M17.32 5H6.68a4 4 0 0 0-3.978 3.59c-.006.052-.01.101-.017.152C2.604 9.416 2 14.456 2 16a3 3 0 0 0 3 3c1 0 1.5-.5 2-1l1.414-1.414A2 2 0 0 1 9.828 16h4.344a2 2 0 0 1 1.414.586L17 18c.5.5 1 1 2 1a3 3 0 0 0 3-3c0-1.545-.604-6.584-.685-7.258C21.31 6.587 19.762 5 17.32 5z"/></svg> Games</button>
    <button class="home-menu-item" id="homeMenuAppsBtn" type="button"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="3" width="7" height="7" rx="2"/><rect x="14" y="3" width="7" height="7" rx="2"/><rect x="3" y="14" width="7" height="7" rx="2"/><rect x="14" y="14" width="7" height="7" rx="2"/></svg> Apps</button>
    <button class="home-menu-item" id="homeMenuMessageBtn" type="button"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg> Messages</button>
    <button class="home-menu-item" id="homeMenuProxyBtn" type="button"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg> Proxy</button>
    <button class="home-menu-item" id="homeMenuSettingsBtn" type="button"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg> Settings</button>
    <a class="home-menu-item" href="/admin_login.php"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg> Admin</a>
  </div>

  <main class="home-view active" id="homeView">
    <div class="home-date-time" id="homeDateTime"><div class="home-time" id="homeTime">--:--</div><div class="home-date" id="homeDate">Loading date...</div></div>
    <div class="online-count-pill"><span id="onlineCount"><?= (int)$homeOnlineCount ?></span> online now</div>
    <section class="hero"><h1 class="title">Don't Leak To E Man</h1><p class="subtitle">made by baldy</p></section>
  </main>

  <main class="games-view" id="gamesView" aria-hidden="true">
    <div class="games-header"><h1 class="games-title">Games</h1><div class="search-sort-row"><input id="searchBar" class="games-search-bar" type="text" placeholder="Search games..."><select id="gamesSortSelect" class="sort-select"><option value="az">A–Z</option><option value="most-used">Most Used</option></select></div></div>
    <!-- Feature 1: Favorites + Recently Played rows -->
    <div class="fav-recent-section" id="favRecentSection">
      <div class="fav-recent-row">
        <div class="fav-recent-label">★ Favorites</div>
        <div class="fav-recent-cards" id="favoritesRow"></div>
      </div>
      <div class="fav-recent-row">
        <div class="fav-recent-label">🕘 Recently Played</div>
        <div class="fav-recent-cards" id="recentRow"></div>
      </div>
    </div>
    <div class="games-list" id="gamesList"></div>
  </main>

  <main class="game-player-view" id="gamePlayerView" aria-hidden="true">
    <div class="player-panel">
      <div class="player-top">
        <button class="player-btn" id="backToGamesBtn" type="button">← Back</button>
        <div class="player-title" id="playerTitle">Game</div>
        <div class="player-actions-right">
          <div class="lag-hint-group">
            <div class="lag-hint-wrapper">
              <span id="lagHintText" class="lag-hint-text">click here if lagging</span>
              <span class="lag-hint-arrow">↓</span>
            </div>
            <button class="player-btn" id="openNewTabBtn" type="button">Open new tab</button>
          </div>
          <button class="player-btn" id="downloadGameBtn" type="button">Download</button>
          <button class="player-btn" id="fullscreenBtn" type="button">Fullscreen</button>
        </div>
      </div>
      <iframe id="gameFrame" class="game-frame" title="Game player" allowfullscreen allow="fullscreen; gamepad; autoplay"></iframe>
    </div>
  </main>

  <main class="apps-view" id="appsView" aria-hidden="true">
    <div class="apps-header"><h1 class="apps-title">Apps</h1><div class="search-sort-row"><input id="appsSearchBar" class="apps-search-bar" type="text" placeholder="Search apps..."><select id="appsSortSelect" class="sort-select"><option value="az">A–Z</option><option value="most-used">Most Used</option></select></div></div>
    <div class="apps-list" id="appsList"></div>
  </main>

  <main class="app-player-view" id="appPlayerView" aria-hidden="true">
    <div class="player-panel"><div class="player-top"><button class="player-btn" id="backToAppsBtn" type="button">← Back</button><div class="player-title" id="appPlayerTitle">App</div><button class="player-btn" id="openAppNewTabBtn" type="button">Open new tab</button></div><iframe id="appFrame" class="app-frame" title="App player" allowfullscreen></iframe></div>
  </main>

  <main class="proxy-player-view" id="proxyPlayerView" aria-hidden="true">
    <div class="player-panel proxy-panel"><div class="player-top"><button class="player-btn" id="proxyHomeBtn" type="button">← Home</button><div class="player-title">Proxy</div><button class="player-btn" id="proxyOpenNewTabBtn" type="button">Open new tab</button></div><iframe id="proxyFrame" class="proxy-frame" title="Proxy" allowfullscreen></iframe></div>
  </main>

  <div class="whats-new-box">
    <h2 style="margin:0 0 6px 0;font-size:1rem;font-weight:950;letter-spacing:-.02em;color:#eef3ff;">What's new in V2.0</h2>
    <p style="margin:0;font-size:.85rem;font-weight:750;line-height:1.4;color:rgba(234,240,253,.78);">Placeholder text for V2.0 updates. Edit this text later.</p>
  </div>

  <footer class="bottom-center"><span>V2.0</span></footer>

  <div class="home-ad-box"><ins class="adsbygoogle" style="display:block" data-ad-client="ca-pub-1819490803357082" data-ad-slot="5434884995" data-ad-format="auto" data-full-width-responsive="true"></ins><script>(adsbygoogle = window.adsbygoogle || []).push({});</script></div>

  <button class="issue-btn" id="issueBtn" type="button">Found a issue?</button>
  <button class="game-request-btn" id="gameRequestBtn" type="button">Want a game?</button>

  <div class="issue-modal-backdrop" id="issueModal">
    <div class="issue-modal">
      <h2 id="issueModalTitle">Report an issue</h2>
      <p id="issueModalText">Tell me what broke, what game it happened on, and what you clicked.</p>
      <textarea class="issue-textarea" id="issueText" placeholder="Type the issue here..."></textarea>
      <div class="issue-actions"><button class="issue-action-btn issue-cancel-btn" id="issueCancelBtn" type="button">Cancel</button><button class="issue-action-btn issue-submit-btn" id="issueSubmitBtn" type="button">Submit issue</button></div>
      <div class="issue-status" id="issueStatus"></div>
    </div>
  </div>

  <div id="settingsModal" class="modal-backdrop" style="display: none;">
    <div class="modal settings-modal">
      <h2>Settings <span style="font-size:0.7rem;opacity:0.6;font-weight:700;">(Press S to open)</span></h2>
      <div class="settings-section"><label>Theme Color</label><input type="color" id="themeColorPicker" value="#5f82dc"></div>
      <div class="settings-section">
        <label>Tab Title</label>
        <select id="tabTitleSelector">
          <option value="Don't Leak To E Man" data-favicon="favicon.png">Don't Leak To E Man</option>
          <option value="Microsoft Teams" data-favicon="microsoft-teams.png">Microsoft Teams</option>
          <option value="Formative" data-favicon="formative.png">Formative</option>
          <option value="Canva" data-favicon="canva.png">Canva</option>
          <option value="Sparx Maths" data-favicon="sparx.png">Sparx Maths</option>
          <option value="MathsWatch" data-favicon="mathswatch.png">MathsWatch</option>
          <option value="Google Classroom" data-favicon="google-classroom.png">Google Classroom</option>
          <option value="YouTube" data-favicon="youtube.png">YouTube</option>
          <option value="Google Docs" data-favicon="docs.png">Google Docs</option>
          <option value="Gmail" data-favicon="gmail.png">Gmail</option>
        </select>
      </div>
      <!-- Feature 4: Profile Picture upload (goes to moderation queue) -->
      <div class="settings-section">
        <label>Profile Picture</label>
        <div style="display:flex;align-items:center;gap:12px;margin-top:8px;flex-wrap:wrap;">
          <img id="settingsPfpPreview" src="/favicon.png" alt="Your profile picture preview" style="width:50px;height:50px;border-radius:50%;object-fit:cover;border:1px solid rgba(255,255,255,.15);">
          <input type="file" id="settingsPfpInput" accept="image/png,image/jpeg,image/webp,image/gif" style="flex:1;min-width:150px;padding:8px;border:1.5px solid rgba(var(--theme-color-rgb), .32);border-radius:10px;background:rgba(10,15,24,.72);color:#eef3ff;font-family:inherit;font-weight:850;">
          <button type="button" id="settingsPfpUploadBtn" style="padding:10px 16px;border:none;border-radius:10px;font-weight:950;cursor:pointer;font-family:inherit;background:linear-gradient(110deg,rgba(var(--theme-color-rgb),.85) 20%,rgba(var(--theme-color-rgb),.65) 80%);color:#111;">Upload</button>
        </div>
        <p id="pfpUploadStatus" style="font-size:.78rem;font-weight:800;margin-top:6px;opacity:.7;"></p>
      </div>
      <!-- Shortcuts moved to dedicated modal — replaced with a button -->
      <div class="settings-section">
        <label>Keyboard Shortcuts</label>
        <button type="button" id="editShortcutsBtn" style="width:100%;margin-top:8px;padding:12px 16px;border:1.5px solid rgba(var(--theme-color-rgb), .32);border-radius:12px;background:rgba(10,15,24,.72);color:#eef3ff;font-family:inherit;font-weight:850;font-size:.95rem;cursor:pointer;transition:.15s;">⌨️ Edit Shortcuts</button>
      </div>
      <div class="settings-actions"><button id="settingsResetBtn" type="button" class="reset-btn">Reset to Default</button><button id="settingsCloseBtn" type="button">Save & Close</button></div>
    </div>
  </div>

  <!-- Dedicated Shortcuts modal — same styling as settings modal -->
  <div id="shortcutsModal" class="modal-backdrop" style="display: none;">
    <div class="modal settings-modal">
      <h2>Keyboard Shortcuts</h2>
      <div class="settings-section">
        <div id="shortcutsEditor" style="display:flex;flex-direction:column;gap:8px;margin-top:8px;"></div>
        <p class="hint" style="opacity:.6;font-size:.78rem;font-weight:800;margin-top:6px;">Click a key box and press a key to rebind. Shortcuts are ignored while typing in inputs/textareas.</p>
      </div>
      <div class="settings-actions">
        <button id="shortcutsResetBtn" type="button" class="reset-btn">Reset Shortcuts</button>
        <button id="shortcutsBackBtn" type="button">Back to Settings</button>
      </div>
    </div>
  </div>

  <div id="chatNameModal" class="modal-backdrop" style="display: none;">
    <div class="modal chat-name-modal">
      <h2>Enter Your Name</h2>
      <input type="text" id="chatNameInput" placeholder="Your name..." maxlength="20" autocomplete="off" style="width:100%;padding:14px 15px;border:1.5px solid rgba(var(--theme-color-rgb), .32);border-radius:12px;background:rgba(10,15,24,.72);color:#eef3ff;font-family:inherit;font-size:1rem;font-weight:850;outline:none;margin-bottom:20px;text-align:center;">
      <button id="chatNameSubmitBtn" type="button" style="width:100%;padding:12px;border:none;border-radius:12px;font-weight:950;cursor:pointer;font-family:inherit;background:linear-gradient(110deg,rgba(var(--theme-color-rgb),.85) 20%,rgba(var(--theme-color-rgb),.65) 80%);color:#111;">Join Chat</button>
    </div>
  </div>

  <div id="chatRulesOverlay" class="rules-overlay" style="display: none;">
    <div class="rules-card">
      <h2>Message Rules</h2>
      <p>You must read this before using the chat.</p>
      <div class="rules-list">
        <div class="rule-item"><span class="rule-num">1</span>I can see who sent messages, what time and date, and what was said.</div>
        <div class="rule-item"><span class="rule-num">2</span>Any slurs or anything I deem as "too far" will result in a 3 DAY BAN.</div>
        <div class="rule-item"><span class="rule-num">3</span>Anything crazy or insane I WILL.</div>
        <div class="rule-item"><span class="rule-num">4</span>Don't be a jack ass.</div>
      </div>
      <p>Type this exactly:</p>
      <div class="agreement-copy">I have read, noticed, understood, and agreed to all of these rules. I accept that anything I say is my own responsibility and NOT the responsibility of dontleaktoeman.co.uk.</div>
      <textarea id="chatRulesInput" class="rules-input" placeholder="Type the exact sentence here..." required></textarea>
      <button id="chatRulesSubmitBtn" class="rules-submit" type="button">I agree</button>
    </div>
  </div>

  <!-- ===== FULL-SCREEN iMESSAGE CHAT ===== -->
  <main class="chat-player-view" id="chatPlayerView" aria-hidden="true">
    <div class="chat-interface">
      <!-- LEFT: conversation list sidebar -->
      <aside class="chat-sidebar" id="chatSidebar">
        <div class="chat-sidebar-header">
          <div class="chat-sidebar-title">Messages</div>
          <button class="chat-new-dm-btn" id="chatNewDmBtn" type="button" title="New message" aria-label="New message">+</button>
        </div>
        <div class="chat-conversation-list" id="chatConvList"></div>
      </aside>

      <!-- RIGHT: chat area -->
      <section class="chat-main" id="chatMain">
        <div class="chat-header">
          <div class="chat-header-left">
            <button class="chat-back-btn" id="chatBackToListBtn" type="button" aria-label="Back to conversations">‹</button>
            <h2 id="chatHeaderTitle" style="font-size:1.05rem;font-weight:900;letter-spacing:-.03em;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">💬 Live Chat</h2>
          </div>
          <div class="chat-header-actions">
            <button class="chat-header-btn" id="chatChangeNameBtn">Change Name</button>
            <button class="chat-close-btn" id="chatBackBtn" aria-label="Close">×</button>
          </div>
        </div>
        <div class="chat-messages-area" id="chatMessages"></div>
        <div class="chat-cooldown" id="chatCooldown" style="display: none;">Slow down! Wait <span id="cooldownSeconds">5</span>s</div>
        <div class="chat-input-container">
          <input type="text" id="chatMessageInput" placeholder="iMessage" maxlength="500" autocomplete="off">
          <button id="chatSendBtn" type="button">Send</button>
        </div>
      </section>
    </div>
  </main>

  <!-- New DM modal: ask for recipient name -->
  <div id="chatNewDmModal" class="modal-backdrop" style="display: none;">
    <div class="modal chat-name-modal">
      <h2>New Message</h2>
      <input type="text" id="chatNewDmInput" placeholder="Recipient's name..." maxlength="20" autocomplete="off" style="width:100%;padding:14px 15px;border:1.5px solid rgba(var(--theme-color-rgb), .32);border-radius:12px;background:rgba(10,15,24,.72);color:#eef3ff;font-family:inherit;font-size:1rem;font-weight:850;outline:none;margin-bottom:20px;text-align:center;">
      <button id="chatNewDmSubmitBtn" type="button" style="width:100%;padding:12px;border:none;border-radius:12px;font-weight:950;cursor:pointer;font-family:inherit;background:linear-gradient(110deg,rgba(var(--theme-color-rgb),.85) 20%,rgba(var(--theme-color-rgb),.65) 80%);color:#111;">Start Conversation</button>
    </div>
  </div>

  <div id="noLoginPopup" class="modal-backdrop" style="display: none;">
    <div class="modal"><h2>I heard you</h2><p>Ok no more log-ins</p><button id="noLoginPopupCloseBtn" type="button">Got it</button></div>
  </div>

  <div class="site-toast" id="adminBroadcastToast"></div>
  <div id="adminBroadcastModal" class="modal-backdrop" style="display: none;"><div class="modal"><div id="adminBroadcastText"></div></div></div>

  <!-- Phase 5: DM Toast Notification banner (non-blocking, 5s auto-dismiss) -->
  <div id="dmToastBanner" role="status" aria-live="polite">
    <div class="dm-toast-card">
      <div id="dmToastPfp" class="dm-toast-pfp dm-toast-pfp-fallback">?</div>
      <div class="dm-toast-body">
        <div id="dmToastSender" class="dm-toast-sender"></div>
        <div id="dmToastMessage" class="dm-toast-message"></div>
      </div>
    </div>
  </div>

<style>
  .rules-overlay { position: fixed; inset: 0; z-index: 99999; display: flex; align-items: center; justify-content: center; padding: 18px; background: rgba(4,8,14,.88); backdrop-filter: blur(14px); -webkit-backdrop-filter: blur(14px); }
  .rules-card { width: min(680px, 96vw); max-height: 92dvh; overflow-y: auto; border-radius: 28px; background: rgba(24,30,41,.98); border: 1px solid rgba(255,255,255,.14); box-shadow: 0 24px 85px rgba(0,0,0,.55); padding: 28px; color: #eaf0fd; }
  .rules-card h2 { font-size: clamp(1.8rem, 6vw, 3.2rem); line-height: .95; letter-spacing: -.08em; text-transform: uppercase; margin-bottom: 14px; background: linear-gradient(110deg, rgba(var(--theme-color-rgb), .9), rgba(245,247,255,.98), rgba(var(--theme-color-rgb), .86)); -webkit-background-clip: text; background-clip: text; color: transparent; }
  .rules-card p { color: rgba(234,240,253,.72); font-weight: 800; line-height: 1.4; margin-bottom: 16px; }
  .rules-list { display: flex; flex-direction: column; gap: 12px; margin: 18px 0; }
  .rule-item { padding: 14px; border-radius: 18px; background: rgba(10,15,24,.58); border: 1px solid rgba(255,255,255,.1); font-weight: 850; line-height: 1.42; color: rgba(238,243,252,.92); }
  .rule-num { display: inline-flex; align-items: center; justify-content: center; width: 26px; height: 26px; margin-right: 7px; border-radius: 50%; background: var(--theme-color); color: white; font-weight: 950; font-size: .9rem; }
  .agreement-copy { padding: 12px; border-radius: 16px; background: rgba(47,140,255,.12); border: 1px solid rgba(90,165,255,.24); color: rgba(238,243,252,.96); font-weight: 900; line-height: 1.35; margin: 14px 0; }
  .rules-input { width: 100%; min-height: 80px; resize: vertical; border: 1.5px solid rgba(var(--theme-color-rgb), .32); border-radius: 18px; background: rgba(10,15,24,.72); color: #eef3ff; padding: 14px 15px; font-family: inherit; font-size: .98rem; font-weight: 800; outline: none; }
  .rules-submit { width: 100%; margin-top: 12px; border: none; border-radius: 16px; padding: 14px 18px; font-weight: 950; cursor: pointer; font-family: inherit; font-size: 1rem; background: linear-gradient(110deg,rgba(var(--theme-color-rgb),.85) 20%,rgba(var(--theme-color-rgb),.65) 80%); color: #111; }
  .issue-textarea { width: 100%; min-height: 150px; resize: vertical; border: 1.5px solid rgba(var(--theme-color-rgb), .32); border-radius: 16px; background: rgba(10,15,24,.72); color: #eef3ff; padding: 14px 15px; font-family: inherit; font-size: 1rem; font-weight: 750; outline: none; }
  .issue-actions { display: flex; gap: 10px; justify-content: flex-end; margin-top: 14px; flex-wrap: wrap; }
  .issue-action-btn { border: 1px solid rgba(255,255,255,.15); border-radius: 13px; padding: 11px 16px; font-weight: 950; cursor: pointer; font-family: inherit; }
  .issue-cancel-btn { background: rgba(55,65,85,.78); color: #eaf0fd; }
  .issue-submit-btn { background: linear-gradient(110deg,rgba(var(--theme-color-rgb),.85) 20%,rgba(var(--theme-color-rgb),.65) 80%); color: #111; }
  .issue-status { margin-top: 12px; min-height: 20px; font-size: .9rem; font-weight: 850; color: rgba(234,240,253,.78); }
  .chat-name-modal { max-width: min(92vw, 400px); }
  .chat-name-modal h2 { font-size: 1.5rem; margin-bottom: 20px; letter-spacing: -.04em; }
</style>

<script>
(function() {
  'use strict';

  // ===== DOM REFS =====
  const $ = id => document.getElementById(id);
  const homeView = $('homeView'), gamesView = $('gamesView'), appsView = $('appsView');
  const gamePlayerView = $('gamePlayerView'), appPlayerView = $('appPlayerView'), proxyPlayerView = $('proxyPlayerView');
  const searchBar = $('searchBar'), appsSearchBar = $('appsSearchBar');
  const gamesList = $('gamesList'), appsList = $('appsList');
  const homeTime = $('homeTime'), homeDate = $('homeDate');
  const gameFrame = $('gameFrame'), appFrame = $('appFrame'), proxyFrame = $('proxyFrame');
  const playerTitle = $('playerTitle'), appPlayerTitle = $('appPlayerTitle');
  const backToGamesBtn = $('backToGamesBtn'), backToAppsBtn = $('backToAppsBtn');
  const openNewTabBtn = $('openNewTabBtn'), openAppNewTabBtn = $('openAppNewTabBtn');
  const downloadGameBtn = $('downloadGameBtn');
  const lagHintText = $('lagHintText');
  const fullscreenBtn = $('fullscreenBtn');
  const proxyHomeBtn = $('proxyHomeBtn'), proxyOpenNewTabBtn = $('proxyOpenNewTabBtn');
  const issueBtn = $('issueBtn'), gameRequestBtn = $('gameRequestBtn');
  const issueModal = $('issueModal'), issueModalTitle = $('issueModalTitle'), issueModalText = $('issueModalText');
  const issueText = $('issueText'), issueCancelBtn = $('issueCancelBtn'), issueSubmitBtn = $('issueSubmitBtn'), issueStatus = $('issueStatus');
  const settingsModal = $('settingsModal'), settingsCloseBtn = $('settingsCloseBtn');
  const themeColorPicker = $('themeColorPicker'), tabTitleSelector = $('tabTitleSelector'), settingsResetBtn = $('settingsResetBtn');
  const shortcutsModal = $('shortcutsModal'), editShortcutsBtn = $('editShortcutsBtn'), shortcutsBackBtn = $('shortcutsBackBtn'), shortcutsResetBtn = $('shortcutsResetBtn');
  const chatNameModal = $('chatNameModal'), chatNameInput = $('chatNameInput'), chatNameSubmitBtn = $('chatNameSubmitBtn');
  const chatRulesOverlay = $('chatRulesOverlay'), chatRulesInput = $('chatRulesInput'), chatRulesSubmitBtn = $('chatRulesSubmitBtn');
  const chatPlayerView = $('chatPlayerView'), chatBackBtn = $('chatBackBtn'), chatChangeNameBtn = $('chatChangeNameBtn');
  const chatMessages = $('chatMessages'), chatMessageInput = $('chatMessageInput'), chatSendBtn = $('chatSendBtn');
  const chatCooldown = $('chatCooldown'), cooldownSeconds = $('cooldownSeconds');
  const chatSidebar = $('chatSidebar'), chatConvList = $('chatConvList'), chatNewDmBtn = $('chatNewDmBtn');
  const chatNewDmModal = $('chatNewDmModal'), chatNewDmInput = $('chatNewDmInput'), chatNewDmSubmitBtn = $('chatNewDmSubmitBtn');
  const chatBackToListBtn = $('chatBackToListBtn'), chatHeaderTitle = $('chatHeaderTitle'), chatMain = $('chatMain');
  const noLoginPopup = $('noLoginPopup'), noLoginPopupCloseBtn = $('noLoginPopupCloseBtn');
  const gamesSortSelect = $('gamesSortSelect'), appsSortSelect = $('appsSortSelect');
  const faviconLink = $('faviconLink');
  const homeMenuFab = $('homeMenuFab'), homeMenuDropdown = $('homeMenuDropdown');
  const homeMenuSettingsBtn = $('homeMenuSettingsBtn'), homeMenuGamesBtn = $('homeMenuGamesBtn');
  const homeMenuAppsBtn = $('homeMenuAppsBtn'), homeMenuMessageBtn = $('homeMenuMessageBtn'), homeMenuProxyBtn = $('homeMenuProxyBtn');
  const homeMenuHomeBtn = $('homeMenuHomeBtn');

  let showingGames = false, showingApps = false, currentGameUrl = '', currentAppUrl = '', currentAppType = '', reportType = 'Issue';
  const proxyLink = 'https://dontleaktoeman.global.ssl.fastly.net/';
  const currentAccountKey = <?= json_encode($currentAccountKeyForHome) ?>;
  const homeProxyHtml = <?= json_encode($homeProxyHtml) ?>;
  const homeProxyUrl = <?= json_encode($homeProxyUrl) ?>;
  const homeProxyType = <?= json_encode($homeProxyType) ?>;
  const homeProxyEnabled = <?= json_encode($homeProxyEnabled) ?>;

  // ===== USAGE TRACKING =====
  async function incrementUsage(type, name) { try { await fetch('/track_usage.php', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: 'type=' + encodeURIComponent(type) + '&name=' + encodeURIComponent(name) }); } catch(e) {} }
  function getUsageData() { try { return JSON.parse(localStorage.getItem('usageData') || '{}'); } catch(e) { return {}; } }
  function incrementLocalUsage(type, name) { const d = getUsageData(); d[type + ':' + name] = (d[type + ':' + name] || 0) + 1; localStorage.setItem('usageData', JSON.stringify(d)); }
  function getLocalUsageCount(type, name) { return getUsageData()[type + ':' + name] || 0; }

  function escapeHTML(t) { return String(t).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c])); }

  // ===== GAMES DATA =====
  // Games are loaded from _private/games.json (managed by game_admin.php).
  // The previous version tried to parse a static JS array out of this same
  // file via regex — but no such array existed, so allGames was always [].
  const allGames = <?= json_encode($homeEnabledGames) ?>;
  const allApps = <?= json_encode($homeEnabledApps) ?>;

  function getDefaultCoverPath(u) { return u.replace(/index\.html$/i, 'cover.png'); }

  // ===== ISSUE MODAL =====
  function openIssueModal(type) {
    reportType = type || 'Issue'; issueStatus.textContent = '';
    if (type === 'Game request') { issueModalTitle.textContent = 'Request a game'; issueModalText.textContent = 'Type the game you want added and any link/info if you have it.'; issueText.placeholder = 'Type the game name here...'; issueSubmitBtn.textContent = 'Submit game'; }
    else { issueModalTitle.textContent = 'Report an issue'; issueModalText.textContent = 'Tell me what broke, what game it happened on, and what you clicked.'; issueText.placeholder = 'Type the issue here...'; issueSubmitBtn.textContent = 'Submit issue'; }
    issueModal.classList.add('active'); setTimeout(() => issueText.focus(), 80);
  }
  function closeIssueModal() { issueModal.classList.remove('active'); issueText.value = ''; issueStatus.textContent = ''; }
  async function submitIssue() {
    const issue = issueText.value.trim(); if (!issue) { issueStatus.textContent = 'Type the issue first.'; return; }
    issueSubmitBtn.disabled = true; issueStatus.textContent = 'Saving issue...';
    try {
      const r = await fetch('/report_issue.php', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: new URLSearchParams({ issue: '[' + reportType + '] ' + issue }) });
      if (!r.ok) { if (r.status === 403) { window.location.href = '/banned.php'; return; } throw new Error(await r.text()); }
      issueStatus.textContent = reportType === 'Game request' ? 'Game request saved. Thank you.' : 'Issue saved. Thank you.';
      setTimeout(closeIssueModal, 900);
    } catch(e) { issueStatus.textContent = e.message || 'Could not save.'; } finally { issueSubmitBtn.disabled = false; }
  }

  // ===== GAME/APP FUNCTIONS =====
  function openGame(game) {
    currentGameUrl = game.url; playerTitle.textContent = game.name; gameFrame.src = game.url;
    document.body.classList.remove('games-open'); document.body.classList.add('game-player-open');
    homeView.classList.remove('active'); gamesView.classList.remove('active'); appsView.classList.remove('active'); gamePlayerView.classList.add('active');
    gamesView.setAttribute('aria-hidden', 'true'); gamePlayerView.setAttribute('aria-hidden', 'false');
    incrementLocalUsage('game', game.name); incrementUsage('game', game.name);
    // Feature 1: Record in recently played
    addToRecentlyPlayed(game);
  }

  // ===== Feature 1: Favorites + Recently Played =====
  function getFavorites() { try { return JSON.parse(localStorage.getItem('gameFavorites') || '[]'); } catch(e) { return []; } }
  function isFavorite(gameName) { return getFavorites().includes(gameName); }
  function toggleFavorite(gameName) {
    let favs = getFavorites();
    if (favs.includes(gameName)) { favs = favs.filter(n => n !== gameName); }
    else { favs.push(gameName); }
    localStorage.setItem('gameFavorites', JSON.stringify(favs));
    return favs.includes(gameName);
  }
  function getRecentlyPlayed() { try { return JSON.parse(localStorage.getItem('recentlyPlayed') || '[]'); } catch(e) { return []; } }
  function addToRecentlyPlayed(game) {
    let recent = getRecentlyPlayed().filter(g => g.name !== game.name);
    recent.unshift({ name: game.name, url: game.url, image: game.image || '' });
    recent = recent.slice(0, 8); // keep last 8
    localStorage.setItem('recentlyPlayed', JSON.stringify(recent));
  }
  function renderGameCardRow(container, games, emptyMsg) {
    if (!container) return;
    container.innerHTML = '';
    if (!games.length) {
      container.innerHTML = '<div style="opacity:.55;font-weight:800;font-size:.9rem;padding:8px 4px;">' + escapeHTML(emptyMsg) + '</div>';
      return;
    }
    games.forEach(g => {
      const gameData = allGames.find(ag => ag.name === g.name) || g;
      const b = document.createElement('button'); b.className = 'game-card'; b.type = 'button';
      const p = document.createElement('div'); p.className = 'game-picture';
      p.style.backgroundImage = "url(" + (gameData.image || getDefaultCoverPath(gameData.url || g.url)) + ")";
      const n = document.createElement('div'); n.className = 'game-name'; n.textContent = g.name;
      b.appendChild(p); b.appendChild(n);
      b.addEventListener('click', () => openGame(gameData));
      container.appendChild(b);
    });
  }
  function refreshFavoritesAndRecent() {
    const favContainer = document.getElementById('favoritesRow');
    const recentContainer = document.getElementById('recentRow');
    if (favContainer) {
      const favNames = getFavorites();
      const favGames = favNames.map(n => allGames.find(g => g.name === n)).filter(g => g);
      renderGameCardRow(favContainer, favGames, 'No favorites yet. Click the ★ on a game to add it.');
    }
    if (recentContainer) {
      renderGameCardRow(recentContainer, getRecentlyPlayed(), 'No recently played games yet.');
    }
  }
  function openApp(app) {
    currentAppUrl = app.content; currentAppType = app.type; appPlayerTitle.textContent = app.name;
    if (app.type === 'url') appFrame.src = app.content; else appFrame.srcdoc = app.content;
    document.body.classList.remove('apps-open'); document.body.classList.add('game-player-open');
    homeView.classList.remove('active'); gamesView.classList.remove('active'); appsView.classList.remove('active'); appPlayerView.classList.add('active');
    appsView.setAttribute('aria-hidden', 'true'); appPlayerView.setAttribute('aria-hidden', 'false');
    incrementLocalUsage('app', app.name); incrementUsage('app', app.name);
  }

  function renderGames(list) {
    if (!gamesList) return; gamesList.innerHTML = '';
    if (!list.length) { gamesList.innerHTML = '<div style="opacity:0.8;font-size:1.1em;padding:30px 5px;grid-column:1/-1;">No games found.<br><button type="button" id="requestMissingGameBtn" style="margin-top:14px;border:1px solid rgba(255,255,255,.15);border-radius:999px;background:rgba(38,45,58,.9);color:#eef3ff;padding:12px 16px;font-weight:950;font-family:inherit;">Request a game</button></div>'; setTimeout(() => { const b = document.getElementById('requestMissingGameBtn'); if (b) b.addEventListener('click', () => openIssueModal('Game request')); }, 0); return; }
    list.forEach(g => {
      const b = document.createElement('button'); b.className = 'game-card'; b.type = 'button';
      const p = document.createElement('div'); p.className = 'game-picture'; p.style.backgroundImage = "url(" + (g.image || getDefaultCoverPath(g.url)) + ")";
      const n = document.createElement('div'); n.className = 'game-name'; n.textContent = g.name;
      // Feature 1: ★ favorite toggle button (top-right of card)
      const fav = document.createElement('div');
      fav.className = 'fav-star' + (isFavorite(g.name) ? ' fav-star-on' : '');
      fav.textContent = isFavorite(g.name) ? '★' : '☆';
      fav.title = isFavorite(g.name) ? 'Remove from favorites' : 'Add to favorites';
      fav.addEventListener('click', function(e) {
        e.stopPropagation();
        const nowFav = toggleFavorite(g.name);
        fav.textContent = nowFav ? '★' : '☆';
        fav.classList.toggle('fav-star-on', nowFav);
        refreshFavoritesAndRecent();
      });
      b.appendChild(p); b.appendChild(n); b.appendChild(fav);
      b.addEventListener('click', () => openGame(g));
      gamesList.appendChild(b);
    });
    // Refresh favorites + recent rows after rendering
    refreshFavoritesAndRecent();
  }
  function renderApps(list) {
    if (!appsList) return; appsList.innerHTML = '';
    if (!list.length) { appsList.innerHTML = '<div style="opacity:0.8;font-size:1.1em;padding:30px 5px;grid-column:1/-1;">No apps found.</div>'; return; }
    list.forEach(a => {
      const b = document.createElement('button'); b.className = 'app-card'; b.type = 'button';
      const p = document.createElement('div'); p.className = 'app-picture';
      if (a.image) p.style.backgroundImage = "url(" + a.image + ")";
      else p.innerHTML = '<div class="app-icon-placeholder">' + escapeHTML((a.name || 'A').charAt(0).toUpperCase()) + '</div>';
      const n = document.createElement('div'); n.className = 'app-name'; n.textContent = a.name;
      b.appendChild(p); b.appendChild(n); b.addEventListener('click', () => openApp(a)); appsList.appendChild(b);
    });
  }

  function getSortedGames(sb) { let l = [...allGames]; if (sb === 'most-used') l.sort((a,b) => getLocalUsageCount('game', b.name) - getLocalUsageCount('game', a.name)); else l.sort((a,b) => a.name.localeCompare(b.name, 'en', { sensitivity: 'base' })); return l; }
  function getSortedApps(sb) { let l = [...allApps]; if (sb === 'most-used') l.sort((a,b) => getLocalUsageCount('app', b.name) - getLocalUsageCount('app', a.name)); else l.sort((a,b) => (a.name||'').localeCompare(b.name||'', 'en', { sensitivity: 'base' })); return l; }
  function filterAndRenderGames() { const t = (searchBar ? searchBar.value : '').toLowerCase(); let l = getSortedGames(gamesSortSelect ? gamesSortSelect.value : 'az'); if (t) l = l.filter(g => g.name.toLowerCase().includes(t)); renderGames(l); }
  function filterAndRenderApps() { const t = (appsSearchBar ? appsSearchBar.value : '').toLowerCase(); let l = getSortedApps(appsSortSelect ? appsSortSelect.value : 'az'); if (t) l = l.filter(a => (a.name||'').toLowerCase().includes(t)); renderApps(l); }

  function showView(games) {
    showingGames = games; if (games) showingApps = false;
    gameFrame.src = 'about:blank'; appFrame.src = 'about:blank'; appFrame.srcdoc = ''; proxyFrame.srcdoc = '';
    gamePlayerView.classList.remove('active'); appPlayerView.classList.remove('active'); proxyPlayerView.classList.remove('active');
    document.body.classList.remove('game-player-open', 'proxy-open');
    if (games) { document.body.classList.add('games-open'); document.body.classList.remove('apps-open'); homeView.classList.remove('active'); gamesView.classList.add('active'); appsView.classList.remove('active'); gamesView.setAttribute('aria-hidden', 'false'); appsView.setAttribute('aria-hidden', 'true'); setTimeout(() => { searchBar && searchBar.focus(); }, 150); }
    else { document.body.classList.remove('games-open', 'apps-open'); homeView.classList.add('active'); gamesView.classList.remove('active'); appsView.classList.remove('active'); gamesView.setAttribute('aria-hidden', 'true'); appsView.setAttribute('aria-hidden', 'true'); }
  }
  function showAppsView() {
    showingGames = false; showingApps = true;
    gameFrame.src = 'about:blank'; appFrame.src = 'about:blank'; appFrame.srcdoc = ''; proxyFrame.srcdoc = '';
    gamePlayerView.classList.remove('active'); appPlayerView.classList.remove('active'); proxyPlayerView.classList.remove('active');
    document.body.classList.remove('game-player-open', 'proxy-open');
    document.body.classList.add('apps-open'); document.body.classList.remove('games-open');
    homeView.classList.remove('active'); gamesView.classList.remove('active'); appsView.classList.add('active');
    gamesView.setAttribute('aria-hidden', 'true'); appsView.setAttribute('aria-hidden', 'false');
    setTimeout(() => { appsSearchBar && appsSearchBar.focus(); }, 150);
  }
  function showProxyView() {
    gameFrame.src = 'about:blank'; appFrame.src = 'about:blank'; appFrame.srcdoc = ''; proxyFrame.srcdoc = '';
    gamePlayerView.classList.remove('active'); appPlayerView.classList.remove('active'); proxyPlayerView.classList.remove('active');
    document.body.classList.remove('game-player-open', 'proxy-open', 'games-open', 'apps-open');

    if (!homeProxyEnabled) {
      // Proxy page is disabled by admin — show a clean disabled message inline.
      const disabledHtml = '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Proxy Disabled</title><style>body{font-family:Inter,Arial,sans-serif;background:#0e1320;color:#eef3ff;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;text-align:center;padding:20px}h1{font-size:2rem;margin:0 0 10px}p{opacity:.7;margin:0}</style></head><body><div><h1>Proxy is currently disabled</h1><p>Please check back later.</p></div></body></html>';
      proxyFrame.srcdoc = disabledHtml;
    } else if (homeProxyType === 'url' && homeProxyUrl) {
      // Phase 3: URL mode — load the saved URL
      proxyFrame.src = homeProxyUrl;
    } else if (homeProxyHtml) {
      // Code mode — admin-saved HTML
      proxyFrame.srcdoc = homeProxyHtml;
    } else {
      // Fall back to the default proxy URL
      proxyFrame.src = proxyLink;
    }

    document.body.classList.add('proxy-open');
    homeView.classList.remove('active'); gamesView.classList.remove('active'); appsView.classList.remove('active');
    proxyPlayerView.classList.add('active');
    gamesView.setAttribute('aria-hidden', 'true'); appsView.setAttribute('aria-hidden', 'true'); proxyPlayerView.setAttribute('aria-hidden', 'false');
  }
  function forceHomeView() {
    showingGames = false; showingApps = false;
    gameFrame.src = 'about:blank'; appFrame.src = 'about:blank'; appFrame.srcdoc = ''; proxyFrame.srcdoc = '';
    document.body.classList.remove('game-player-open', 'proxy-open', 'games-open', 'apps-open');
    gamePlayerView.classList.remove('active'); appPlayerView.classList.remove('active'); proxyPlayerView.classList.remove('active');
    gamesView.classList.remove('active'); appsView.classList.remove('active'); homeView.classList.add('active');
    window.scrollTo(0, 0);
  }

  // ===== EVENT LISTENERS =====
  issueBtn.addEventListener('click', () => openIssueModal('Issue'));
  gameRequestBtn.addEventListener('click', () => openIssueModal('Game request'));
  issueCancelBtn.addEventListener('click', closeIssueModal);
  issueSubmitBtn.addEventListener('click', submitIssue);
  issueModal.addEventListener('click', e => { if (e.target === issueModal) closeIssueModal(); });
  backToGamesBtn.addEventListener('click', () => showView(true));
  backToAppsBtn.addEventListener('click', showAppsView);
  openNewTabBtn.addEventListener('click', () => { if (currentGameUrl) window.open(currentGameUrl, '_blank'); });
  if (downloadGameBtn) downloadGameBtn.addEventListener('click', () => {
    if (!currentGameUrl) return;
    // Trigger a download of the game's index.html. Game files live under
    // /learn/<slug>/index.html (same-origin), so a programmatic <a download>
    // click is the simplest secure approach — no PHP proxy needed.
    const a = document.createElement('a');
    a.href = currentGameUrl;
    // Derive a friendly filename from the URL slug.
    // e.g. /learn/1v1lol/index.html -> 1v1lol.html
    const parts = currentGameUrl.split('/').filter(Boolean); // ['learn', '1v1lol', 'index.html']
    const slug = parts.length >= 2 ? parts[1] : 'game';
    a.download = slug + '.html';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
  });
  // "click here if lagging" text — opens the game in a new tab (same as Open new tab button)
  if (lagHintText) lagHintText.addEventListener('click', () => { if (currentGameUrl) window.open(currentGameUrl, '_blank'); });
  // Feature 5: Fullscreen button — toggles the game iframe to fullscreen
  if (fullscreenBtn) fullscreenBtn.addEventListener('click', () => {
    const frame = gameFrame;
    if (!frame) return;
    if (document.fullscreenElement) {
      document.exitFullscreen();
    } else if (frame.requestFullscreen) {
      frame.requestFullscreen().catch(() => {});
    } else if (frame.webkitRequestFullscreen) {
      frame.webkitRequestFullscreen();
    }
  });
  // Update button label when fullscreen state changes
  document.addEventListener('fullscreenchange', () => {
    if (fullscreenBtn) fullscreenBtn.textContent = document.fullscreenElement ? 'Exit Fullscreen' : 'Fullscreen';
  });
  openAppNewTabBtn.addEventListener('click', () => { if (currentAppUrl) { if (currentAppType === 'url') window.open(currentAppUrl, '_blank'); else { const w = window.open('', '_blank'); if (w) { w.document.write(currentAppUrl); w.document.close(); } } } });
  proxyHomeBtn.addEventListener('click', forceHomeView);
  proxyOpenNewTabBtn.addEventListener('click', () => { if (homeProxyHtml) { const w = window.open('', '_blank'); if (w) { w.document.write(homeProxyHtml); w.document.close(); } } else { window.open(proxyLink, '_blank'); } });

  // ===== LAUNCH POPUPS =====
  // NOTE: Popup display is handled entirely by /site_popups.php (included at the
  // bottom of this page). It renders the full-screen modal for every_launch and
  // once_on_launch, and the top-of-screen banner for show_now. Do NOT add any
  // competing popup handler here — it would create duplicate popups and race
  // conditions with the site_popups.php polling loop.

  // ===== SETTINGS =====
  function loadSettings() {
    const savedTheme = localStorage.getItem('themeColor'), savedTitle = localStorage.getItem('tabTitle'), savedFavicon = localStorage.getItem('favicon');
    if (savedTheme) { themeColorPicker.value = savedTheme; applyThemeColor(savedTheme); }
    if (savedTitle) { tabTitleSelector.value = savedTitle; document.title = savedTitle; }
    if (savedFavicon) faviconLink.href = savedFavicon; else applyFaviconForPreset(tabTitleSelector.value);
  }
  // Bug #14 fix: Replaced fragile querySelector (which only escaped single quotes
  // and could throw on values containing `"]`) with a plain loop over options.
  function applyFaviconForPreset(v) {
    if (!tabTitleSelector || !tabTitleSelector.options) return;
    for (let i = 0; i < tabTitleSelector.options.length; i++) {
      if (tabTitleSelector.options[i].value === v) {
        const f = tabTitleSelector.options[i].getAttribute('data-favicon') || 'favicon.png';
        faviconLink.href = '/assets/favicons/' + f;
        localStorage.setItem('favicon', faviconLink.href);
        return;
      }
    }
  }
  function hexToRgb(h) { const r = /^#?([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})$/i.exec(h); return r ? { r: parseInt(r[1],16), g: parseInt(r[2],16), b: parseInt(r[3],16) } : null; }
  function applyThemeColor(c) { const rgb = hexToRgb(c); if (rgb) { document.documentElement.style.setProperty('--theme-color', c); document.documentElement.style.setProperty('--theme-color-rgb', rgb.r + ', ' + rgb.g + ', ' + rgb.b); } }
  settingsCloseBtn.addEventListener('click', () => { const c = themeColorPicker.value, t = tabTitleSelector.value; localStorage.setItem('themeColor', c); localStorage.setItem('tabTitle', t); applyThemeColor(c); document.title = t; applyFaviconForPreset(t); settingsModal.style.display = 'none'; });
  settingsResetBtn.addEventListener('click', () => { localStorage.removeItem('themeColor'); localStorage.removeItem('tabTitle'); localStorage.removeItem('favicon'); themeColorPicker.value = '#5f82dc'; tabTitleSelector.value = "Don't Leak To E Man"; applyThemeColor('#5f82dc'); document.title = "Don't Leak To E Man"; faviconLink.href = '/favicon.png'; });

  // ===== Shortcuts modal navigation =====
  if (editShortcutsBtn) editShortcutsBtn.addEventListener('click', () => {
    settingsModal.style.display = 'none';
    shortcutsModal.style.display = 'flex';
    renderShortcutsEditor();
  });
  if (shortcutsBackBtn) shortcutsBackBtn.addEventListener('click', () => {
    shortcutsModal.style.display = 'none';
    settingsModal.style.display = 'flex';
  });
  if (shortcutsResetBtn) shortcutsResetBtn.addEventListener('click', () => {
    localStorage.removeItem('keyboardShortcuts');
    renderShortcutsEditor();
  });
  // Click outside shortcuts modal closes it (back to nothing)
  if (shortcutsModal) shortcutsModal.addEventListener('click', e => {
    if (e.target === shortcutsModal) shortcutsModal.style.display = 'none';
  });

  // ===== Feature 2: Keyboard Shortcuts (view + edit in Settings) =====
  const DEFAULT_SHORTCUTS = {
    home:     { key: 'h', label: 'Home',     action: () => forceHomeView() },
    games:    { key: 'g', label: 'Games',    action: () => showView(true) },
    apps:     { key: 'a', label: 'Apps',     action: () => showAppsView() },
    messages: { key: 'm', label: 'Messages', action: () => openChatNameModal() },
    proxy:    { key: 'p', label: 'Proxy',    action: () => showProxyView() },
    settings: { key: 's', label: 'Settings', action: () => { settingsModal.style.display = 'flex'; } },
    help:     { key: '?', label: 'Show shortcuts', action: null } // handled separately — opens shortcutsModal
  };

  function getShortcuts() {
    try {
      const saved = JSON.parse(localStorage.getItem('keyboardShortcuts') || '{}');
      const result = {};
      for (const id in DEFAULT_SHORTCUTS) {
        result[id] = { ...DEFAULT_SHORTCUTS[id], key: saved[id] || DEFAULT_SHORTCUTS[id].key };
      }
      return result;
    } catch(e) { return { ...DEFAULT_SHORTCUTS }; }
  }

  function renderShortcutsEditor() {
    const editor = document.getElementById('shortcutsEditor');
    if (!editor) return;
    const shortcuts = getShortcuts();
    editor.innerHTML = '';
    for (const id in shortcuts) {
      const s = shortcuts[id];
      const row = document.createElement('div');
      row.style.cssText = 'display:flex;align-items:center;gap:10px;padding:6px 8px;border-radius:10px;background:rgba(10,15,24,.5);border:1px solid rgba(255,255,255,.08);';
      const label = document.createElement('div');
      label.style.cssText = 'flex:1;font-weight:850;font-size:.92rem;';
      label.textContent = s.label;
      const keyBox = document.createElement('button');
      keyBox.type = 'button';
      keyBox.style.cssText = 'min-width:50px;padding:8px 12px;border:1.5px solid rgba(var(--theme-color-rgb), .32);border-radius:10px;background:rgba(10,15,24,.72);color:#eef3ff;font-family:inherit;font-weight:950;text-transform:uppercase;cursor:pointer;';
      keyBox.textContent = s.key;
      keyBox.title = 'Click then press a key to rebind';
      keyBox.addEventListener('click', () => {
        keyBox.textContent = '...';
        keyBox.style.background = 'rgba(var(--theme-color-rgb), .2)';
        const handler = (e) => {
          e.preventDefault();
          e.stopPropagation();
          if (e.key === 'Escape') {
            keyBox.textContent = s.key;
            keyBox.style.background = '';
            document.removeEventListener('keydown', handler, true);
            return;
          }
          const newKey = e.key.length === 1 ? e.key.toLowerCase() : e.key;
          // Save
          const saved = JSON.parse(localStorage.getItem('keyboardShortcuts') || '{}');
          saved[id] = newKey;
          localStorage.setItem('keyboardShortcuts', JSON.stringify(saved));
          keyBox.textContent = newKey;
          keyBox.style.background = '';
          document.removeEventListener('keydown', handler, true);
        };
        document.addEventListener('keydown', handler, true);
      });
      row.appendChild(label);
      row.appendChild(keyBox);
      editor.appendChild(row);
    }
  }

  // Global keyboard shortcut handler
  document.addEventListener('keydown', function(e) {
    // Ignore if user is typing in an input/textarea/select
    const tag = (e.target.tagName || '').toLowerCase();
    if (tag === 'input' || tag === 'textarea' || tag === 'select') return;
    // Ignore if a modal is open (settings OR shortcuts)
    if (settingsModal && settingsModal.style.display !== 'none') return;
    if (shortcutsModal && shortcutsModal.style.display !== 'none') return;
    if (issueModal && issueModal.classList.contains('active')) return;
    if (chatPlayerView && chatPlayerView.classList.contains('active')) return;

    const key = e.key.length === 1 ? e.key.toLowerCase() : e.key;
    const shortcuts = getShortcuts();
    for (const id in shortcuts) {
      if (shortcuts[id].key === key) {
        e.preventDefault();
        if (id === 'help') {
          // ? opens the dedicated shortcuts modal directly
          shortcutsModal.style.display = 'flex';
          renderShortcutsEditor();
        } else if (shortcuts[id].action) {
          shortcuts[id].action();
        }
        return;
      }
    }
  });

  // Load pfp status when settings modal opens (shortcuts editor is in its own modal now)
  if (homeMenuSettingsBtn) {
    homeMenuSettingsBtn.addEventListener('click', () => { loadPfpStatus(); });
  }

  // ===== Feature 4: Profile Picture upload + status =====
  async function loadPfpStatus() {
    try {
      const r = await fetch('/pfp_upload.php?action=status&cache=' + Date.now());
      if (!r.ok) return;
      const d = await r.json();
      const preview = document.getElementById('settingsPfpPreview');
      const statusEl = document.getElementById('pfpUploadStatus');
      if (d.current_pfp && preview) preview.src = d.current_pfp;
      if (statusEl) {
        if (d.pending) {
          statusEl.textContent = 'Pending: awaiting admin approval (uploaded ' + (d.pending.requested_at_text || '') + ')';
          statusEl.style.color = '#ffd98a';
        } else {
          statusEl.textContent = 'No pending requests. Your current picture is shown.';
          statusEl.style.color = 'rgba(234,240,253,.5)';
        }
      }
    } catch(e) {}
  }
  const pfpUploadBtn = document.getElementById('settingsPfpUploadBtn');
  const pfpInput = document.getElementById('settingsPfpInput');
  if (pfpUploadBtn && pfpInput) {
    pfpUploadBtn.addEventListener('click', async () => {
      const file = pfpInput.files && pfpInput.files[0];
      if (!file) { alert('Please select an image file first.'); return; }
      const statusEl = document.getElementById('pfpUploadStatus');
      if (statusEl) { statusEl.textContent = 'Uploading...'; statusEl.style.color = 'rgba(var(--theme-color-rgb),.85)'; }
      pfpUploadBtn.disabled = true;
      try {
        const fd = new FormData();
        fd.append('pfp', file);
        const r = await fetch('/pfp_upload.php?action=upload', { method: 'POST', body: fd });
        const d = await r.json();
        if (d.ok) {
          if (statusEl) { statusEl.textContent = d.message || 'Uploaded. Awaiting admin approval.'; statusEl.style.color = '#bfffe0'; }
          pfpInput.value = '';
          loadPfpStatus();
        } else {
          if (statusEl) { statusEl.textContent = d.error || 'Upload failed.'; statusEl.style.color = '#ffa3a3'; }
        }
      } catch(e) {
        if (statusEl) { statusEl.textContent = 'Network error.'; statusEl.style.color = '#ffa3a3'; }
      } finally {
        pfpUploadBtn.disabled = false;
      }
    });
  }
  settingsModal.addEventListener('click', e => { if (e.target === settingsModal) settingsModal.style.display = 'none'; });
  loadSettings();

  // ===== HOME MENU =====
  let homeMenuOpen = false;
  function toggleHomeMenu(e) { if (e) { e.preventDefault(); e.stopPropagation(); } homeMenuOpen = !homeMenuOpen; homeMenuFab.classList.toggle('open', homeMenuOpen); homeMenuDropdown.classList.toggle('open', homeMenuOpen); }
  function closeHomeMenu() { homeMenuOpen = false; homeMenuFab.classList.remove('open'); homeMenuDropdown.classList.remove('open'); }
  homeMenuFab.addEventListener('click', toggleHomeMenu);
  homeMenuSettingsBtn.addEventListener('click', function(e) { e.preventDefault(); e.stopPropagation(); closeHomeMenu(); settingsModal.style.display = 'flex'; });
  homeMenuGamesBtn.addEventListener('click', function(e) { e.preventDefault(); e.stopPropagation(); closeHomeMenu(); showView(true); });
  homeMenuAppsBtn.addEventListener('click', function(e) { e.preventDefault(); e.stopPropagation(); closeHomeMenu(); showAppsView(); });
  homeMenuMessageBtn.addEventListener('click', function(e) { e.preventDefault(); e.stopPropagation(); closeHomeMenu(); openChatNameModal(); });
  homeMenuProxyBtn.addEventListener('click', function(e) { e.preventDefault(); e.stopPropagation(); closeHomeMenu(); showProxyView(); });
  if (homeMenuHomeBtn) homeMenuHomeBtn.addEventListener('click', function(e) { e.preventDefault(); e.stopPropagation(); closeHomeMenu(); forceHomeView(); });
  document.addEventListener('click', function(e) { if (homeMenuOpen && !homeMenuFab.contains(e.target) && !homeMenuDropdown.contains(e.target)) closeHomeMenu(); });
  document.addEventListener('keydown', function(e) { if (homeMenuOpen && (e.key === 'Escape' || e.which === 27)) closeHomeMenu(); });

  // ===== iMESSAGE CHAT SYSTEM =====
  let chatName = localStorage.getItem('chatName') || '';
  let chatPollingInterval = null, convPollingInterval = null, cooldownInterval = null;
  // Currently-open conversation: "group" or "dm:<a>__<b>"
  let currentConversation = localStorage.getItem('chatCurrentConv') || 'group';
  // Recipient display name for DMs (used in header). Empty for group.
  let currentConvLabel = 'Group Chat';
  // Track which conversation was last rendered so we don't re-render identical data
  let lastRenderedSignature = '';

  // Use relative path so chat works whether the project lives at domain root or in a subfolder.
  const MESSAGE_API = (function() {
    // Resolve from the current script's location (index.php) → message_api.php
    // Falls back to root-relative '/message_api.php' if for some reason detection fails.
    const here = (window.location.pathname || '').replace(/[^/]*$/, '');
    return (here ? here : '/') + 'message_api.php';
  })();

  function openChatNameModal() {
    if (chatName) { openChatInterface(); }
    else { chatNameModal.style.display = 'flex'; chatNameInput.focus(); }
  }

  function openChatInterface() {
    chatNameModal.style.display = 'none';
    chatPlayerView.classList.add('active');
    document.body.classList.add('chat-player-open');
    // On mobile, default to showing the conversation list (not a chat) when opening
    chatPlayerView.classList.remove('showing-chat');
    // On desktop, show the chat area for the current conversation
    if (window.innerWidth > 768) showChatPane();
    // Bug #11 fix: Set the chat header to reflect the current conversation
    // (was stuck on "Live Chat" until the user clicked a conversation).
    if (chatHeaderTitle) {
      if (currentConversation === 'group') {
        chatHeaderTitle.textContent = '💬 Group Chat';
      } else if (currentConversation.indexOf('dm:') === 0) {
        const parts = currentConversation.slice(3).split('__');
        const me = (chatName || '').toLowerCase();
        const other = (parts.length === 2 && parts[0] === me) ? parts[1] : (parts[1] || '');
        chatHeaderTitle.textContent = '💬 ' + other;
      } else {
        chatHeaderTitle.textContent = '💬 Group Chat';
      }
    }
    setTimeout(() => { if (chatMessageInput) chatMessageInput.focus(); }, 100);
    loadChatConversations();
    loadChatMessages();
    startChatPolling();
  }

  function closeChatInterface() {
    chatPlayerView.classList.remove('active');
    chatPlayerView.classList.remove('showing-chat');
    document.body.classList.remove('chat-player-open');
    stopChatPolling();
    // Bug #17 fix: Clear the cooldown interval so it doesn't keep ticking
    // in the background after the chat is closed (small resource leak).
    if (cooldownInterval) { clearInterval(cooldownInterval); cooldownInterval = null; }
    if (chatCooldown) chatCooldown.style.display = 'none';
    if (chatMessageInput) chatMessageInput.disabled = false;
    if (chatSendBtn) chatSendBtn.disabled = false;
  }

  // ===== CONVERSATION LIST =====
  async function loadChatConversations() {
    if (!chatName) return;
    try {
      const res = await fetch(MESSAGE_API + '?action=conversations&name=' + encodeURIComponent(chatName) + '&cache=' + Date.now());
      if (!res.ok) return;
      const data = await res.json();
      if (data && data.conversations) {
        // Phase 5: Check for new DM messages before rendering
        checkForNewDms(data.conversations);
        renderConversationList(data.conversations);
      }
    } catch(e) { console.error('Chat conv load error:', e); }
  }

  // ===== Phase 5: DM Toast Notifications + Muting =====
  let dmToastsInitialized = false;
  let dmToastTimer = null;

  function getMutedUsers() { try { return JSON.parse(localStorage.getItem('mutedUsers') || '[]'); } catch(e) { return []; } }
  function isMuted(userKey) { return getMutedUsers().includes(userKey); }
  function toggleMute(userKey) {
    let muted = getMutedUsers();
    if (muted.includes(userKey)) { muted = muted.filter(u => u !== userKey); }
    else { muted.push(userKey); }
    localStorage.setItem('mutedUsers', JSON.stringify(muted));
    return muted.includes(userKey);
  }

  function checkForNewDms(convs) {
    convs.forEach(c => {
      if (c.key === 'group') return;        // only DMs
      if (c.key === currentConversation) return; // skip currently-open conversation
      if (!c.last_message) return;           // no message yet

      const sig = (c.last_time || '') + '|' + (c.last_message || '');
      const storageKey = 'dm_sig_' + c.key;
      const oldSig = localStorage.getItem(storageKey);

      // Only trigger toast AFTER initial load (don't fire on first poll)
      if (dmToastsInitialized && oldSig && oldSig !== sig) {
        // Check if sender is muted
        if (!isMuted(c.label)) {
          showDmToast(c.label, c.last_message, c.pfp);
        }
      }

      // Update stored sig
      localStorage.setItem(storageKey, sig);
    });
    dmToastsInitialized = true;
  }

  function showDmToast(senderName, message, pfpUrl) {
    const banner = document.getElementById('dmToastBanner');
    const senderEl = document.getElementById('dmToastSender');
    const msgEl = document.getElementById('dmToastMessage');
    const pfpEl = document.getElementById('dmToastPfp');
    if (!banner || !senderEl || !msgEl || !pfpEl) return;

    senderEl.textContent = senderName || 'Unknown';
    msgEl.textContent = message.length > 80 ? message.slice(0, 80) + '…' : message;

    // Set pfp: use real pfp if available, otherwise fallback to initial letter
    if (pfpUrl && pfpUrl.indexOf('/favicon.png') === -1) {
      pfpEl.className = 'dm-toast-pfp';
      pfpEl.innerHTML = '<img src="' + escapeHTML(pfpUrl) + '" style="width:40px;height:40px;border-radius:50%;object-fit:cover;" alt="">';
    } else {
      pfpEl.className = 'dm-toast-pfp dm-toast-pfp-fallback';
      pfpEl.textContent = (senderName || '?').charAt(0).toUpperCase();
    }

    banner.classList.add('active');
    clearTimeout(dmToastTimer);
    dmToastTimer = setTimeout(function() {
      banner.classList.remove('active');
    }, 5000); // 5 seconds, same as Show Now
  }

  function renderConversationList(convs) {
    if (!chatConvList) return;
    chatConvList.innerHTML = '';
    if (!convs || convs.length === 0) {
      const empty = document.createElement('div');
      empty.className = 'chat-empty';
      empty.textContent = 'No conversations yet.';
      chatConvList.appendChild(empty);
      return;
    }
    convs.forEach(c => {
      const isGroup = c.key === 'group';
      const isDm = !isGroup;
      const muted = isDm && isMuted(c.label);
      const item = document.createElement('div');
      item.className = 'chat-conv-item' + (c.key === currentConversation ? ' active' : '');
      item.dataset.convKey = c.key;
      item.dataset.convLabel = c.label || 'Group Chat';

      const initial = (c.label || 'G').charAt(0).toUpperCase();
      const preview = c.last_message ? (c.last_message.length > 40 ? c.last_message.slice(0, 40) + '…' : c.last_message) : 'No messages yet';
      const time = c.last_time || '';

      item.innerHTML =
        '<div class="chat-conv-item-row">' +
          '<div class="chat-conv-avatar">' + escapeHTML(initial) + '</div>' +
          '<div style="flex:1;min-width:0;">' +
            '<div class="chat-conv-row">' +
              '<span class="chat-conv-name">' + escapeHTML(c.label || 'Group Chat') + (muted ? '<span class="chat-conv-muted-icon">🔇</span>' : '') + '</span>' +
              '<span class="chat-conv-time">' + escapeHTML(time) + '</span>' +
            '</div>' +
            '<div class="chat-conv-preview">' + escapeHTML(preview) + '</div>' +
          '</div>' +
          (isDm ? '<button class="chat-conv-kebab" type="button" aria-label="Options">⋮</button>' : '') +
        '</div>' +
        (isDm ? '<div class="chat-conv-mute-row" id="muteRow_' + escapeHTML(c.label) + '">' +
          '<input type="checkbox" id="muteChk_' + escapeHTML(c.label) + '" ' + (muted ? 'checked' : '') + '>' +
          '<label for="muteChk_' + escapeHTML(c.label) + '">Mute notifications from ' + escapeHTML(c.label) + '</label>' +
        '</div>' : '');

      // Click on the item (not the kebab) selects the conversation
      item.addEventListener('click', function(e) {
        if (e.target.classList.contains('chat-conv-kebab')) return;
        if (e.target.closest('.chat-conv-mute-row')) return;
        selectConversation(c.key, c.label || 'Group Chat');
      });

      // Kebab menu toggle for DMs
      if (isDm) {
        const kebab = item.querySelector('.chat-conv-kebab');
        if (kebab) {
          kebab.addEventListener('click', function(e) {
            e.stopPropagation();
            const muteRow = item.querySelector('.chat-conv-mute-row');
            if (muteRow) muteRow.classList.toggle('show');
          });
        }
        // Mute checkbox handler
        const muteChk = item.querySelector('input[type="checkbox"]');
        if (muteChk) {
          muteChk.addEventListener('change', function(e) {
            e.stopPropagation();
            const nowMuted = toggleMute(c.label);
            // Update the name display
            const nameEl = item.querySelector('.chat-conv-name');
            if (nameEl) {
              nameEl.innerHTML = escapeHTML(c.label) + (nowMuted ? '<span class="chat-conv-muted-icon">🔇</span>' : '');
            }
          });
        }
      }

      chatConvList.appendChild(item);
    });
  }

  function selectConversation(key, label) {
    currentConversation = key;
    currentConvLabel = label || 'Group Chat';
    localStorage.setItem('chatCurrentConv', currentConversation);
    // Update active states
    Array.prototype.forEach.call(chatConvList.children, function(el) {
      if (el.classList) el.classList.toggle('active', el.dataset.convKey === currentConversation);
    });
    // Update header
    if (chatHeaderTitle) {
      chatHeaderTitle.textContent = key === 'group' ? '💬 Group Chat' : '💬 ' + label;
    }
    // Reset render signature so messages always re-render after switching
    lastRenderedSignature = '';
    showChatPane();
    loadChatMessages();
  }

  function showChatPane() {
    chatPlayerView.classList.add('showing-chat');
    setTimeout(function() { if (chatMessageInput) chatMessageInput.focus(); }, 50);
  }

  function showConvListPane() {
    chatPlayerView.classList.remove('showing-chat');
  }

  // ===== LOAD MESSAGES (current conversation) =====
  async function loadChatMessages() {
    if (!chatName) return;
    try {
      const url = MESSAGE_API + '?action=get&conversation=' + encodeURIComponent(currentConversation) +
                  '&name=' + encodeURIComponent(chatName) + '&cache=' + Date.now();
      const res = await fetch(url);
      if (!res.ok) return;
      const data = await res.json();
      if (data && data.messages) renderChatMessages(data.messages);
    } catch(e) { console.error('Chat load error:', e); }
  }

  function renderChatMessages(messages) {
    if (!chatMessages) return;
    const wasAtBottom = chatMessages.scrollTop >= chatMessages.scrollHeight - chatMessages.clientHeight - 60;
    // Signature so we skip re-rendering identical content (reduces flicker on poll)
    const sig = (currentConversation) + '|' + (messages ? messages.map(function(m){return (m.ts||0)+':'+(m.sender||'')+':'+(m.message||'');}).join('§') : '');
    if (sig === lastRenderedSignature) {
      // Still maintain auto-scroll if user was at bottom
      if (wasAtBottom) chatMessages.scrollTop = chatMessages.scrollHeight;
      return;
    }
    lastRenderedSignature = sig;

    chatMessages.innerHTML = '';
    if (!messages || messages.length === 0) {
      const empty = document.createElement('div');
      empty.className = 'chat-empty';
      empty.textContent = currentConversation === 'group'
        ? 'No messages yet. Say hi to everyone!'
        : 'No messages yet. Start the conversation!';
      chatMessages.appendChild(empty);
      return;
    }
    const myNameLower = (chatName || '').toLowerCase();
    messages.forEach(function(msg) {
      // Use sender (lowercased) for own/other detection — robust to display-name casing
      const isOwn = (msg.sender && msg.sender === myNameLower) || (msg.name === chatName);
      const div = document.createElement('div');
      div.className = 'chat-message ' + (isOwn ? 'own' : 'other');
      // Feature 4: show sender's profile picture (if available and not default)
      const pfpUrl = msg.pfp || '';
      const hasRealPfp = pfpUrl && pfpUrl.indexOf('/favicon.png') === -1;
      // In group chat, show sender name on incoming messages. In DMs, names are redundant.
      const showSender = !isOwn && currentConversation === 'group';
      const pfpHtml = hasRealPfp
        ? '<img class="chat-msg-pfp" src="' + escapeHTML(pfpUrl) + '" alt="">'
        : '<div class="chat-msg-pfp chat-msg-pfp-fallback">' + escapeHTML((msg.name || '?').charAt(0).toUpperCase()) + '</div>';
      div.innerHTML =
        (showSender ? '<div class="sender">' + escapeHTML(msg.name) + '</div>' : '') +
        '<div class="chat-msg-content">' + pfpHtml +
          '<div class="chat-msg-bubble">' +
            '<div class="text">' + escapeHTML(msg.message) + '</div>' +
            '<div class="time">' + escapeHTML(msg.time || '') + '</div>' +
          '</div>' +
        '</div>';
      chatMessages.appendChild(div);
    });
    // Auto-scroll to newest message
    if (wasAtBottom || messages.length > 0) chatMessages.scrollTop = chatMessages.scrollHeight;
  }

  // ===== SEND MESSAGE =====
  let isSending = false;
  async function sendChatMessage() {
    if (isSending) return;
    if (!chatMessageInput) return;
    const message = chatMessageInput.value.trim();
    if (!message || !chatName) return;

    // Determine recipient based on current conversation
    let to = 'group';
    if (currentConversation !== 'group' && currentConversation.indexOf('dm:') === 0) {
      // dm:<a>__<b> — pick the part that isn't me
      const parts = currentConversation.slice(3).split('__');
      const me = (chatName || '').toLowerCase();
      if (parts.length === 2) {
        to = (parts[0] === me) ? parts[1] : parts[0];
      }
    }

    isSending = true;
    if (chatSendBtn) chatSendBtn.disabled = true;
    try {
      const res = await fetch(MESSAGE_API + '?action=send', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'name=' + encodeURIComponent(chatName) +
              '&message=' + encodeURIComponent(message) +
              '&to=' + encodeURIComponent(to)
      });
      const data = await res.json();
      if (data && data.success) {
        chatMessageInput.value = '';
        // Refresh messages immediately so the sender sees their own message
        lastRenderedSignature = '';
        await loadChatMessages();
        await loadChatConversations();
        startCooldown();
      } else if (data && data.error) {
        console.error('Chat send error:', data.error);
      }
    } catch(e) { console.error('Failed to send:', e); }
    finally {
      isSending = false;
      if (chatSendBtn && (!chatCooldown.style.display || chatCooldown.style.display === 'none')) chatSendBtn.disabled = false;
    }
  }

  // ===== POLLING =====
  // 5-second auto-refresh per spec
  function startChatPolling() {
    if (chatPollingInterval) clearInterval(chatPollingInterval);
    if (convPollingInterval) clearInterval(convPollingInterval);
    chatPollingInterval = setInterval(loadChatMessages, 5000);
    convPollingInterval = setInterval(loadChatConversations, 5000);
  }
  function stopChatPolling() {
    if (chatPollingInterval) { clearInterval(chatPollingInterval); chatPollingInterval = null; }
    if (convPollingInterval) { clearInterval(convPollingInterval); convPollingInterval = null; }
  }

  function startCooldown() {
    let s = 5; chatCooldown.style.display = 'block'; cooldownSeconds.textContent = s;
    if (chatMessageInput) chatMessageInput.disabled = true;
    if (chatSendBtn) chatSendBtn.disabled = true;
    if (cooldownInterval) clearInterval(cooldownInterval);
    cooldownInterval = setInterval(function() {
      s--; cooldownSeconds.textContent = s;
      if (s <= 0) {
        clearInterval(cooldownInterval);
        chatCooldown.style.display = 'none';
        if (chatMessageInput) chatMessageInput.disabled = false;
        if (chatSendBtn) chatSendBtn.disabled = false;
        if (chatMessageInput) chatMessageInput.focus();
      }
    }, 1000);
  }

  // ===== NAME / RULES MODALS =====
  if (chatNameSubmitBtn) {
    chatNameSubmitBtn.addEventListener('click', function() {
      const name = chatNameInput.value.trim();
      if (!name) return;
      chatNameModal.style.display = 'none';
      chatRulesOverlay.style.display = 'flex';
      chatRulesInput.value = '';
      chatRulesInput.focus();
    });
  }
  if (chatRulesSubmitBtn) {
    chatRulesSubmitBtn.addEventListener('click', function() {
      const required = 'I have read, noticed, understood, and agreed to all of these rules. I accept that anything I say is my own responsibility and NOT the responsibility of dontleaktoeman.co.uk.';
      if (chatRulesInput.value.trim() !== required) { alert('You must type the agreement text exactly.'); return; }
      const name = chatNameInput.value.trim();
      if (!name) { chatRulesOverlay.style.display = 'none'; chatNameModal.style.display = 'flex'; return; }
      chatName = name; localStorage.setItem('chatName', name);
      chatRulesOverlay.style.display = 'none';
      openChatInterface();
    });
  }
  if (chatNameInput) {
    chatNameInput.addEventListener('keydown', function(e) {
      if (e.key === 'Enter') { e.preventDefault(); if (chatNameSubmitBtn) chatNameSubmitBtn.click(); }
    });
  }

  // ===== CHAT INTERFACE CONTROLS =====
  if (chatBackBtn) chatBackBtn.addEventListener('click', closeChatInterface);
  if (chatBackToListBtn) chatBackToListBtn.addEventListener('click', showConvListPane);
  if (chatChangeNameBtn) {
    chatChangeNameBtn.addEventListener('click', function() {
      chatName = ''; localStorage.removeItem('chatName');
      chatPlayerView.classList.remove('active');
      chatPlayerView.classList.remove('showing-chat');
      document.body.classList.remove('chat-player-open');
      stopChatPolling();
      chatNameModal.style.display = 'flex'; chatNameInput.value = ''; chatNameInput.focus();
    });
  }

  // ===== NEW DM MODAL =====
  if (chatNewDmBtn) {
    chatNewDmBtn.addEventListener('click', function() {
      if (!chatName) return;
      if (chatNewDmModal) {
        chatNewDmModal.style.display = 'flex';
        chatNewDmInput.value = '';
        setTimeout(function() { chatNewDmInput.focus(); }, 50);
      }
    });
  }
  if (chatNewDmSubmitBtn) {
    chatNewDmSubmitBtn.addEventListener('click', function() {
      const recipient = (chatNewDmInput.value || '').trim();
      if (!recipient) { alert('Please enter a recipient name.'); return; }
      if (recipient.toLowerCase() === (chatName || '').toLowerCase()) {
        alert('You cannot start a conversation with yourself. Use Group Chat instead.');
        return;
      }
      // Build conversation key the same way the backend does: dm:<a>__<b> sorted
      const a = (chatName || '').toLowerCase();
      const b = recipient.toLowerCase();
      const parts = [a, b].sort();
      const convKey = 'dm:' + parts.join('__');
      if (chatNewDmModal) chatNewDmModal.style.display = 'none';
      selectConversation(convKey, recipient);
    });
  }
  if (chatNewDmInput) {
    chatNewDmInput.addEventListener('keydown', function(e) {
      if (e.key === 'Enter') { e.preventDefault(); if (chatNewDmSubmitBtn) chatNewDmSubmitBtn.click(); }
    });
  }
  if (chatNewDmModal) {
    chatNewDmModal.addEventListener('click', function(e) {
      if (e.target === chatNewDmModal) chatNewDmModal.style.display = 'none';
    });
  }

  // ===== CRITICAL: Send button click handler =====
  if (chatSendBtn) {
    chatSendBtn.addEventListener('click', function(e) {
      e.preventDefault();
      sendChatMessage();
    });
  }

  // ===== CRITICAL: Enter key handler (keydown for reliability) =====
  if (chatMessageInput) {
    chatMessageInput.addEventListener('keydown', function(e) {
      // Enter sends. Shift+Enter inserts newline (but input is single-line so just allow default).
      if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        e.stopPropagation();
        sendChatMessage();
      }
    });
    // Also handle 'keypress' for older browsers / some IMEs
    chatMessageInput.addEventListener('keypress', function(e) {
      if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        e.stopPropagation();
        sendChatMessage();
      }
    });
  }

  // ===== NO-LOGIN POPUP =====
  // Bug #13 fix: Only show once per browser (localStorage gate). Previously
  // this showed on every single homepage load, which was annoying.
  setTimeout(() => {
    if (!noLoginPopup) return;
    try {
      if (localStorage.getItem('noLoginPopupSeen')) return; // already seen — skip
    } catch(e) {}
    noLoginPopup.style.display = 'flex';
  }, 500);
  if (noLoginPopupCloseBtn) noLoginPopupCloseBtn.addEventListener('click', () => {
    noLoginPopup.style.display = 'none';
    try { localStorage.setItem('noLoginPopupSeen', '1'); } catch(e) {}
  });
  if (noLoginPopup) noLoginPopup.addEventListener('click', e => {
    if (e.target === noLoginPopup) {
      noLoginPopup.style.display = 'none';
      try { localStorage.setItem('noLoginPopupSeen', '1'); } catch(e) {}
    }
  });

  // ===== ESCAPE KEY =====
  document.addEventListener('keydown', e => {
    if (shortcutsModal && shortcutsModal.style.display !== 'none' && (e.key === 'Escape' || e.which === 27)) shortcutsModal.style.display = 'none';
    else if (settingsModal.style.display !== 'none' && (e.key === 'Escape' || e.which === 27)) settingsModal.style.display = 'none';
    else if (issueModal.classList.contains('active') && (e.key === 'Escape' || e.which === 27)) closeIssueModal();
    else if (proxyPlayerView.classList.contains('active') && (e.key === 'Escape' || e.which === 27)) forceHomeView();
    else if (gamePlayerView.classList.contains('active') && (e.key === 'Escape' || e.which === 27)) showView(true);
    else if (appPlayerView.classList.contains('active') && (e.key === 'Escape' || e.which === 27)) showAppsView();
    else if (showingGames && (e.key === 'Escape' || e.which === 27)) showView(false);
    else if (showingApps && (e.key === 'Escape' || e.which === 27)) forceHomeView();
  });

  // ===== SEARCH/SORT =====
  if (searchBar) searchBar.addEventListener('input', filterAndRenderGames);
  if (appsSearchBar) appsSearchBar.addEventListener('input', filterAndRenderApps);
  if (gamesSortSelect) gamesSortSelect.addEventListener('change', filterAndRenderGames);
  if (appsSortSelect) appsSortSelect.addEventListener('change', filterAndRenderApps);

  // ===== DATE/TIME =====
  function updateHomeDateTime() {
    const n = new Date();
    if (homeTime) homeTime.textContent = n.toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' });
    if (homeDate) homeDate.textContent = n.toLocaleDateString('en-GB', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
  }
  updateHomeDateTime(); setInterval(updateHomeDateTime, 1000);

  async function updateOnlineCount() {
    const el = document.getElementById('onlineCount'); if (!el) return;
    try { const r = await fetch('/online_count.php?cache=' + Date.now()); if (!r.ok) return; const d = await r.json(); if (typeof d.count !== 'undefined') el.textContent = d.count; } catch(e) {}
  }
  updateOnlineCount(); setInterval(updateOnlineCount, 15000);

  // Bug #3 fix: Heartbeat — sends a lightweight ping every 60s so the user's
  // last_seen timestamp stays fresh while they're actively on the page.
  // Without this, a user who stays on the homepage >120s is marked offline
  // (profile_online_map filters by last_seen >= now - 120).
  async function sendHeartbeat() {
    try { await fetch('/heartbeat.php?cache=' + Date.now()); } catch(e) {}
  }
  setInterval(sendHeartbeat, 60000);

  // ===== INIT =====
  renderGames(allGames); filterAndRenderApps();
  // Launch popups are handled by /site_popups.php (included at end of page).
  // No competing checkLaunchPopups call here — see comment above.

  // Phase 2: bfcache support — restore intervals when page is restored from
  // back/forward cache. Without this, polling stops after bfcache navigation.
  window.addEventListener('pageshow', function(event) {
    if (event.persisted) {
      // Page was restored from bfcache — re-initialize polling
      updateOnlineCount();
      if (chatPlayerView && chatPlayerView.classList.contains('active')) {
        loadChatMessages();
        startChatPolling();
      }
    }
  });

  // 60fps optimization: Pause heavy CSS animations when tab is not visible
  // (saves GPU cycles and battery on iPad/mobile)
  document.addEventListener('visibilitychange', function() {
    if (document.hidden) {
      document.body.classList.add('tab-hidden');
    } else {
      document.body.classList.remove('tab-hidden');
    }
  });

  console.log('Lunaach loaded. Chat system initialized.');
})();
</script>

<!-- Bug #10 fix: Removed the site-music.mp3 unlock script (audio element was removed above). -->
<script>
(function() {
  function showProfilePicturePrompt() {
    const sp = <?= $homePfpMissing ? 'true' : 'false' ?>;
    const m = document.getElementById('profilePicturePromptModal'); const s = document.getElementById('profilePictureSettingsBtn'); const l = document.getElementById('profilePictureLaterBtn');
    if (!sp || !m || !s || !l) return; setTimeout(() => { m.style.display = 'flex'; }, 1200);
    s.addEventListener('click', () => location.href = '/profile_settings.php');
    l.addEventListener('click', () => m.style.display = 'none');
  }
  function showDailySupport() {
    const m = document.getElementById('dailySupportModal'); const b = document.getElementById('dailySupportCloseBtn');
    if (!m || !b) return;
    const t = new Date().toISOString().slice(0, 10);
    if (localStorage.getItem('support_popup_seen_day_v05_4_' + currentAccountKey) !== t) { setTimeout(() => { m.style.display = 'flex'; }, 900); }
    b.addEventListener('click', () => { localStorage.setItem('support_popup_seen_day_v05_4_' + currentAccountKey, t); m.style.display = 'none'; });
  }
  // Bug fix: removed dead checkGameMessages() polling — /message-board.php never
  // existed, so this fired a 404 every 5 seconds forever. The chat system has
  // its own polling in the chat interface; no global message notification is needed.
  window.addEventListener('DOMContentLoaded', function() { showDailySupport(); showProfilePicturePrompt(); });
})();
</script>
<?php include __DIR__ . '/site_popups.php'; ?>
</body>
</html>