<?php session_start(); if (($_SESSION['role'] ?? '') !== 'admin') { header('Location: ../index.html'); exit; } ?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta
      name="viewport"
      content="width=device-width, initial-scale=1, viewport-fit=cover"
    />
    <meta name="theme-color" content="#527d68" />
    <meta name="robots" content="noindex" />
    <title>I'll BeCare | Admin Dashboard</title>
    <link rel="stylesheet" href="../Assets/bootstrap-5.3.8-dist/css/bootstrap.min.css" />
    <link rel="stylesheet" href="../Assets/admin_dashboard.css" />
  </head>
  <body>
    <svg
      width="0"
      height="0"
      style="position: absolute"
      aria-hidden="true"
      focusable="false"
    >
      <symbol id="i-home" viewBox="0 0 24 24">
        <path d="M3 11l9-8 9 8v10a1 1 0 0 1-1 1h-5v-7H9v7H4a1 1 0 0 1-1-1z" />
      </symbol>
      <symbol id="i-users" viewBox="0 0 24 24">
        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
        <circle cx="9" cy="7" r="4" />
        <path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75" />
      </symbol>
      <symbol id="i-shield" viewBox="0 0 24 24">
        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" />
        <path d="M9 12l2 2 4-4" />
      </symbol>
      <symbol id="i-cal" viewBox="0 0 24 24">
        <rect x="3" y="4" width="18" height="18" rx="2" />
        <path d="M16 2v4M8 2v4M3 10h18" />
      </symbol>
      <symbol id="i-grid" viewBox="0 0 24 24">
        <rect x="3" y="3" width="7" height="7" />
        <rect x="14" y="3" width="7" height="7" />
        <rect x="14" y="14" width="7" height="7" />
        <rect x="3" y="14" width="7" height="7" />
      </symbol>
      <symbol id="i-flag" viewBox="0 0 24 24">
        <path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z" />
        <path d="M4 22v-7" />
      </symbol>
      <symbol id="i-star" viewBox="0 0 24 24">
        <path
          d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01z"
        />
      </symbol>
      <symbol id="i-act" viewBox="0 0 24 24">
        <path d="M22 12h-4l-3 9L9 3l-3 9H2" />
      </symbol>
      <symbol id="i-set" viewBox="0 0 24 24">
        <path
          d="M4 21v-7M4 10V3M12 21v-9M12 8V3M20 21v-5M20 12V3M1 14h6M9 8h6M17 16h6"
        />
      </symbol>
      <symbol id="i-out" viewBox="0 0 24 24">
        <path
          d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"
        />
      </symbol>
      <symbol id="i-bell" viewBox="0 0 24 24">
        <path
          d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9M13.73 21a2 2 0 0 1-3.46 0"
        />
      </symbol>
      <symbol id="i-menu" viewBox="0 0 24 24">
        <path d="M3 12h18M3 6h18M3 18h18" />
      </symbol>
    </svg>

    <div class="app">
      <aside class="sidebar" id="sidebar" aria-label="Main navigation">
        <div class="brand">
          <div class="mark">iB</div>
          <div><b>I'll BeCare</b><small>ADMINISTRATION</small></div>
        </div>

        <nav>
          <button class="nav active" data-section="dashboard">
            <svg class="ic"><use href="#i-home" /></svg><span>Dashboard</span>
          </button>
          <button class="nav" data-section="users">
            <svg class="ic"><use href="#i-users" /></svg
            ><span>User Management</span>
          </button>
          <button class="nav" data-section="providers">
            <svg class="ic"><use href="#i-shield" /></svg
            ><span>Provider Verification</span
            ><em id="bdgProv" class="hidden">0</em>
          </button>
          <button class="nav" data-section="bookings">
            <svg class="ic"><use href="#i-cal" /></svg><span>Bookings</span>
          </button>
          <button class="nav" data-section="services">
            <svg class="ic"><use href="#i-grid" /></svg><span>Services</span>
          </button>
          <button class="nav" data-section="reports">
            <svg class="ic"><use href="#i-flag" /></svg
            ><span>Reports &amp; Complaints</span
            ><em id="bdgRep" class="red hidden">0</em>
          </button>
          <button class="nav" data-section="reviews">
            <svg class="ic"><use href="#i-star" /></svg><span>Reviews</span>
          </button>
          <button class="nav" data-section="activity">
            <svg class="ic"><use href="#i-act" /></svg
            ><span>Activity Logs</span>
          </button>
        </nav>

        <div class="side-bottom">
          <button class="nav" data-section="settings">
            <svg class="ic"><use href="#i-set" /></svg><span>Settings</span>
          </button>
          <button class="logout" id="logout" type="button">
            <svg class="ic"><use href="#i-out" /></svg><span>Log out</span>
          </button>
        </div>
      </aside>
      <div class="backdrop" id="backdrop"></div>

      <main class="main">
        <header class="topbar">
          <button
            class="menu"
            id="menu"
            type="button"
            aria-label="Open menu"
            aria-expanded="false"
          >
            <svg class="ic"><use href="#i-menu" /></svg>
          </button>
          <div class="ttl">
            <small class="eyebrow">ADMINISTRATION</small>
            <h1 id="title">Dashboard</h1>
          </div>
          <div class="top-actions">
            <button
              class="bell"
              id="bell"
              type="button"
              aria-label="Notifications"
            >
              <svg class="ic"><use href="#i-bell" /></svg
              ><i id="bellDot" class="hidden"></i>
            </button>
            <div class="profile">
              <span id="initials">SA</span>
              <div>
                <b id="adminName">Administrator</b><small>Administrator</small>
              </div>
            </div>
          </div>
        </header>

        <!-- DASHBOARD -->
        <section class="content container-fluid" id="home">
          <div class="welcome">
            <div>
              <small class="eyebrow">OVERVIEW</small>
              <h2 id="greet">Good day, Administrator.</h2>
              <p>Monitor I'll BeCare and keep the service safe and reliable.</p>
            </div>
            <time id="date"></time>
          </div>

          <div class="stats row g-3">
            <article class="col-12 col-sm-6 col-xxl-3">
              <span class="stat-icon green"
                ><svg class="ic"><use href="#i-users" /></svg
              ></span>
              <div>
                <small>Total Users</small><strong id="sUsers">–</strong
                ><label id="sUsersSub">&nbsp;</label>
              </div>
            </article>
            <article class="col-12 col-sm-6 col-xxl-3">
              <span class="stat-icon gold"
                ><svg class="ic"><use href="#i-shield" /></svg
              ></span>
              <div>
                <small>Providers</small><strong id="sProv">–</strong
                ><label id="sProvSub">&nbsp;</label>
              </div>
            </article>
            <article class="col-12 col-sm-6 col-xxl-3">
              <span class="stat-icon blue"
                ><svg class="ic"><use href="#i-cal" /></svg
              ></span>
              <div>
                <small>Total Bookings</small><strong id="sBook">–</strong
                ><label id="sBookSub">&nbsp;</label>
              </div>
            </article>
            <article class="col-12 col-sm-6 col-xxl-3">
              <span class="stat-icon red"
                ><svg class="ic"><use href="#i-flag" /></svg
              ></span>
              <div>
                <small>Open Reports</small><strong id="sRep">–</strong
                ><label id="sRepSub">&nbsp;</label>
              </div>
            </article>
          </div>

          <div class="grid row g-3">
            <section class="panel col-12 col-xl-7">
              <header>
                <div>
                  <small class="eyebrow">ACTION CENTER</small>
                  <h3>Needs your attention</h3>
                </div>
              </header>
              <div class="attention" id="attention"></div>
            </section>
            <section class="panel col-12 col-xl-5">
              <header>
                <div>
                  <small class="eyebrow">BOOKINGS</small>
                  <h3>Booking overview</h3>
                </div>
                <button class="link" data-go="bookings" type="button">
                  View all
                </button>
              </header>
              <div class="bars" id="bars"></div>
            </section>
          </div>

          <div class="grid row g-3">
            <section class="panel col-12 col-xl-7">
              <header>
                <div>
                  <small class="eyebrow">RECENT</small>
                  <h3>Recent activity</h3>
                </div>
                <button class="link" data-go="activity" type="button">
                  Activity logs
                </button>
              </header>
              <div class="activity" id="recent"></div>
            </section>
            <section class="panel col-12 col-xl-5">
              <header>
                <div>
                  <small class="eyebrow">QUICK ACTIONS</small>
                  <h3>Manage system</h3>
                </div>
              </header>
              <div class="quick">
                <button data-go="providers" type="button">
                  <svg class="ic"><use href="#i-shield" /></svg
                  ><span>Review providers</span>
                </button>
                <button data-go="users" type="button">
                  <svg class="ic"><use href="#i-users" /></svg
                  ><span>Manage users</span>
                </button>
                <button data-go="services" type="button">
                  <svg class="ic"><use href="#i-grid" /></svg
                  ><span>Manage services</span>
                </button>
                <button data-go="reports" type="button">
                  <svg class="ic"><use href="#i-flag" /></svg
                  ><span>Review reports</span>
                </button>
              </div>
            </section>
          </div>
        </section>

        <!-- ALL OTHER MODULES RENDER HERE -->
        <section
          class="content container-fluid hidden"
          id="module"
          aria-live="polite"
        ></section>
      </main>

      <div id="modalRoot"></div>
      <div class="toast" id="toast" role="status" aria-live="polite"></div>
    </div>

    <script src="../admin/admin_dashboard.js" defer></script>
  </body>
</html>
