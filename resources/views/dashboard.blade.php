<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>Apparel Soft Track — Dashboard</title>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Sora:wght@500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/summernote/0.8.18/summernote-lite.min.css">
<link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}">
</head>
<body>
<div class="sidebar-backdrop" id="sidebarBackdrop" hidden></div>
<div class="app-shell">
  <aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
      <div class="brand-mark">
        <svg viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16v3H4z"/><path d="M6 7v13h12V7"/><path d="M9 11h6"/><path d="M9 15h6"/></svg>
      </div>
      <div>
        <p class="brand">Apparel Soft Track</p>
        <p class="tagline">Merchandising tracker</p>
      </div>
    </div>

    <nav class="side-nav">
      <p class="side-nav-label">Overview</p>
      <button type="button" class="side-nav-item active" data-nav="tracker">
        <span class="side-nav-label-group">
          <span class="side-nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v16a2 2 0 0 0 2 2h16"/><path d="m7 14 3-4 3 3 4-6"/></svg></span>Progress Tracker</span><span class="side-nav-count" id="navCountTracker">0</span>
      </button>
      <button type="button" class="side-nav-item" data-nav="all">
        <span class="side-nav-label-group">
          <span class="side-nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="5.5" height="16" rx="1.4"/><rect x="9.8" y="4" width="5.5" height="11" rx="1.4"/><rect x="16.6" y="4" width="4.4" height="14" rx="1.4"/></svg></span>Board (All)</span><span class="side-nav-count" id="navCountAll">0</span>
      </button>
      <button type="button" class="side-nav-item" data-nav="reports">
        <span class="side-nav-label-group">
          <span class="side-nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M7 20v-6M12 20V8M17 20v-9"/><path d="M3 21h18"/></svg></span>Reports</span>
      </button>

      @if (auth()->user()->isAdmin())
      <p class="side-nav-label">Administration</p>
      <button type="button" class="side-nav-item" data-nav="users">
        <span class="side-nav-label-group">
          <span class="side-nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg></span>Users &amp; Access</span><span class="side-nav-count" id="navCountUsers">0</span>
      </button>
      @endif

      <p class="side-nav-label">Master Data</p>
      <button type="button" class="side-nav-item" data-nav="buyers">
        <span class="side-nav-label-group">
          <span class="side-nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 20v-1.6a3.4 3.4 0 0 0-3.4-3.4H6.4A3.4 3.4 0 0 0 3 18.4V20"/><circle cx="9.5" cy="7.5" r="3.5"/><path d="M21 20v-1.6a3.4 3.4 0 0 0-2.6-3.3M15.5 4.2a3.4 3.4 0 0 1 0 6.6"/></svg></span>Buyers</span><span class="side-nav-count" id="navCountBuyers">0</span>
      </button>

      <p class="side-nav-label">Status</p>
      <button type="button" class="side-nav-item" data-nav="status" data-status="new">
        <span class="side-nav-label-group">
          <span class="side-nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 8v8M8 12h8"/></svg></span>New</span><span class="side-nav-count" id="navCountNew">0</span>
      </button>
      <button type="button" class="side-nav-item" data-nav="status" data-status="in_progress">
        <span class="side-nav-label-group">
          <span class="side-nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 12a8 8 0 1 1-2.3-5.6"/><path d="M20 4v4h-4"/></svg></span>In Progress</span><span class="side-nav-count" id="navCountRunning">0</span>
      </button>
      <button type="button" class="side-nav-item" data-nav="status" data-status="waiting">
        <span class="side-nav-label-group">
          <span class="side-nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3.2 2"/></svg></span>Waiting</span><span class="side-nav-count" id="navCountWaiting">0</span>
      </button>
      <button type="button" class="side-nav-item" data-nav="status" data-status="done">
        <span class="side-nav-label-group">
          <span class="side-nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="m8.5 12.3 2.4 2.4 4.6-5"/></svg></span>Done</span><span class="side-nav-count" id="navCountDone">0</span>
      </button>
      <button type="button" class="side-nav-item" data-nav="status" data-status="cancelled">
        <span class="side-nav-label-group">
          <span class="side-nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="m9 9 6 6M15 9l-6 6"/></svg></span>Cancelled</span><span class="side-nav-count" id="navCountCancelled">0</span>
      </button>
      <button type="button" class="side-nav-item" data-nav="overdue">
        <span class="side-nav-label-group">
          <span class="side-nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.3 4.3 2.6 17.4A1.9 1.9 0 0 0 4.3 20.3h15.4a1.9 1.9 0 0 0 1.7-2.9L13.7 4.3a1.9 1.9 0 0 0-3.4 0Z"/><path d="M12 9.5v4M12 17.2h.01"/></svg></span>Overdue</span><span class="side-nav-count danger" id="navCountOverdue">0</span>
      </button>
    </nav>

    <div class="sidebar-footer">
      <p class="sidebar-version">APPAREL SOFT TRACK <span>V1.0</span></p>
    </div>
  </aside>

  <main class="main">
    <header class="topbar">
      <button type="button" class="sidebar-toggle" id="sidebarToggle" aria-label="Open menu">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
      </button>
      <input type="text" id="searchInput" placeholder="Search by style, buyer, or title...">
      <span class="topbar-spacer"></span>
      <div class="export-menu">
        <button type="button" class="btn btn-ghost" id="exportMenuBtn">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v12"/><path d="m7 10 5 5 5-5"/><path d="M5 21h14"/></svg>
          Export CSV
        </button>
        <div class="export-list" id="exportMenuList" hidden>
          <p class="export-group">Your working sheet</p>
          <a class="export-item primary" href="{{ route('tasks.sheet') }}">
            <span class="export-icon">📗</span>
            <span class="export-text">
              <span class="export-title">Sample Tracking List</span>
              <span class="export-note">.xlsx — same columns, colours &amp; photos</span>
            </span>
          </a>

          <p class="export-group">Raw data</p>
          <a class="export-item" href="{{ route('tasks.export') }}">
            <span class="export-icon">📋</span>
            <span class="export-text">
              <span class="export-title">Styles — full data</span>
              <span class="export-note">Every field, one row per style (.csv)</span>
            </span>
          </a>
          <a class="export-item" href="{{ route('buyers.export') }}">
            <span class="export-icon">👥</span>
            <span class="export-text">
              <span class="export-title">Buyers list</span>
              <span class="export-note">Contacts and style counts (.csv)</span>
            </span>
          </a>
          <a class="export-item" href="{{ route('updates.export') }}">
            <span class="export-icon">💬</span>
            <span class="export-text">
              <span class="export-title">Updates &amp; corrections</span>
              <span class="export-note">Every comment and buyer email (.csv)</span>
            </span>
          </a>
        </div>
      </div>
      <button class="btn btn-primary" id="addTaskBtn">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
        New Task
      </button>
      <div class="user-chip">
        <span class="dot">{{ mb_substr(auth()->user()->name, 0, 1) }}</span>
        <span>{{ auth()->user()->name }}</span>
        <form method="POST" action="{{ route('logout') }}">
          @csrf
          <button type="submit" class="link">Log out</button>
        </form>
      </div>
    </header>

    <div class="page-head">
      <h1 class="page-title" id="pageTitle">Progress Tracker</h1>
      <p class="page-sub" id="pageSub">Every style and the stage it is sitting at right now</p>
    </div>

    <div class="main-inner">
      <div id="bannerHost"></div>


      <div id="listView">
      <section class="stats">
        <button type="button" class="stat-card total" data-jump="tracker">
          <div class="stat-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="m12 3 8.5 4.6v8.8L12 21l-8.5-4.6V7.6L12 3Z"/><path d="M3.8 7.7 12 12.2l8.2-4.5M12 12.2V21"/></svg></div>
          <div class="stat-body">
            <div class="stat-label">Total Tasks</div>
            <div class="stat-num" id="statTotal">0</div>
          </div>
          <div class="stat-chevron">›</div>
        </button>
        <button type="button" class="stat-card running" data-jump="in_progress">
          <div class="stat-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M20 12a8 8 0 1 1-2.3-5.6"/><path d="M20 4v4h-4"/></svg></div>
          <div class="stat-body">
            <div class="stat-label">In Progress</div>
            <div class="stat-num" id="statRunning">0</div>
          </div>
          <div class="stat-chevron">›</div>
        </button>
        <button type="button" class="stat-card waiting" data-jump="waiting">
          <div class="stat-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3.2 2"/></svg></div>
          <div class="stat-body">
            <div class="stat-label">Waiting</div>
            <div class="stat-num" id="statWaiting">0</div>
          </div>
          <div class="stat-chevron">›</div>
        </button>
        <button type="button" class="stat-card overdue" data-jump="overdue">
          <div class="stat-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M10.3 4.3 2.6 17.4A1.9 1.9 0 0 0 4.3 20.3h15.4a1.9 1.9 0 0 0 1.7-2.9L13.7 4.3a1.9 1.9 0 0 0-3.4 0Z"/><path d="M12 9.5v4M12 17.2h.01"/></svg></div>
          <div class="stat-body">
            <div class="stat-label">Overdue</div>
            <div class="stat-num" id="statOverdue">0</div>
          </div>
          <div class="stat-chevron">›</div>
        </button>
      </section>
        <section class="toolbar">
          <select id="periodFilter">
            <option value="all">All Time</option>
            <option value="week">This Week</option>
            <option value="month">This Month</option>
            <option value="year">This Year</option>
            <option value="last_month">Last Month</option>
            <option value="custom">Custom Range…</option>
          </select>
          <select id="periodBasis" title="Which date the period applies to">
            <option value="received_at">by Received Date</option>
            <option value="deadline">by Deadline</option>
            <option value="ship_date">by Ship Date</option>
            <option value="created_at">by Entry Date</option>
          </select>
          <span class="custom-range" id="customRange" hidden>
            <input type="date" id="periodFrom" title="From">
            <span class="range-sep">to</span>
            <input type="date" id="periodTo" title="To">
          </span>
          <select id="priorityFilter">
            <option value="all">All Priorities</option>
            <option value="low">Low</option>
            <option value="medium">Medium</option>
            <option value="high">High</option>
            <option value="urgent">Urgent</option>
          </select>
          <select id="buyerFilter">
            <option value="all">All Buyers</option>
          </select>
          <select id="seasonFilter">
            <option value="all">All Seasons</option>
          </select>
        </section>

        <section class="summary-strip" id="summaryStrip"></section>

        <section class="grid" id="taskGrid">
          <div class="empty-state" id="loadingState">Loading...</div>
        </section>

        <p class="footnote">To let a colleague use this, share this computer's local-network link and have them create their own account.</p>
      </div>

      <section class="buyers-view" id="buyersView" hidden>
        <div class="panel">
          <h2 class="panel-title" id="buyerFormTitle">Add a Buyer</h2>
          <form id="buyerForm">
            <div class="field-row">
              <div class="field">
                <label for="bName">Buyer Name *</label>
                <input type="text" id="bName" required placeholder="e.g. H&amp;M">
              </div>
              <div class="field">
                <label for="bBrand">Brand / Division</label>
                <input type="text" id="bBrand" placeholder="e.g. Divided">
              </div>
            </div>
            <div class="field-row">
              <div class="field">
                <label for="bContact">Contact Person</label>
                <input type="text" id="bContact" placeholder="e.g. Anna Lind">
              </div>
              <div class="field">
                <label for="bCountry">Country</label>
                <input type="text" id="bCountry" placeholder="e.g. Sweden">
              </div>
            </div>
            <div class="field-row">
              <div class="field">
                <label for="bEmail">Email</label>
                <input type="email" id="bEmail" placeholder="e.g. anna@buyer.com">
              </div>
              <div class="field">
                <label for="bPhone">Phone</label>
                <input type="text" id="bPhone" placeholder="e.g. +46 70 123 4567">
              </div>
            </div>
            <div class="field">
              <label for="bNotes">Notes</label>
              <textarea id="bNotes" placeholder="Payment terms, shipping mode, packing instructions, anything worth remembering..."></textarea>
            </div>
            <div class="form-actions">
              <button type="button" class="btn btn-ghost" id="buyerResetBtn" hidden>Cancel Edit</button>
              <button type="submit" class="btn btn-primary" id="buyerSaveBtn">Save Buyer</button>
            </div>
          </form>
        </div>

        <div class="panel" style="margin-top:18px;">
          <div class="section-title" style="margin-top:0;">Saved Buyers</div>
          <div class="table-wrap">
            <table class="data-table">
              <thead>
                <tr>
                  <th>Buyer</th>
                  <th>Contact</th>
                  <th>Country</th>
                  <th class="num">Styles</th>
                  <th class="right">Action</th>
                </tr>
              </thead>
              <tbody id="buyersTableBody"></tbody>
            </table>
          </div>
        </div>
      </section>

      @if (auth()->user()->isAdmin())
      <section class="users-view" id="usersView" hidden>
        <div class="panel">
          <div class="section-title" style="margin-top:0;">Waiting for Approval</div>
          <div class="table-wrap">
            <table class="data-table">
              <thead>
                <tr><th>Name</th><th>Email</th><th>Requested</th><th class="right">Action</th></tr>
              </thead>
              <tbody id="pendingUsersBody"></tbody>
            </table>
          </div>
        </div>

        <div class="panel" style="margin-top:18px;">
          <div class="section-title" style="margin-top:0;">Approved Users</div>
          <div class="table-wrap">
            <table class="data-table">
              <thead>
                <tr><th>Name</th><th>Email</th><th>Role</th><th>Approved</th><th class="right">Action</th></tr>
              </thead>
              <tbody id="approvedUsersBody"></tbody>
            </table>
          </div>
        </div>
      </section>
      @endif

      <section class="reports-view" id="reportsView" hidden>
        <div class="report-hero" id="reportHero"></div>

        <div class="panel">
          <h3 class="chart-title">Tech packs received per month</h3>
          <p class="chart-sub" id="chartIntakeSub">Last 12 months</p>
          <div class="chart-box" id="chartIntake"></div>
        </div>

        <div class="report-cols">
          <div class="panel">
            <h3 class="chart-title">Where the work is sitting</h3>
            <p class="chart-sub">Styles at each stage right now</p>
            <div class="chart-box" id="chartPipeline"></div>
          </div>
          <div class="panel">
            <h3 class="chart-title">Busiest buyers</h3>
            <p class="chart-sub">By number of styles</p>
            <div class="chart-box" id="chartBuyers"></div>
          </div>
        </div>

        <p class="footnote">Every figure here follows the filters you set on the tracker. Use Export CSV for the row-by-row numbers.</p>
      </section>

      <section class="form-view" id="formView" hidden>
        <form id="taskForm">
          <div class="form-topline">
            <button type="button" class="btn btn-ghost" id="taskCancelBtn">← Back</button>
            <div class="form-topline-actions">
              <button type="submit" class="btn btn-primary" id="taskSaveBtn">Save Task</button>
            </div>
          </div>

          <div class="form-grid">
          <div class="form-main">

          <div class="panel">
            <div class="section-title" style="margin-top:0;">Style <span class="col-ref">A · B · C</span></div>
            <div class="field">
              <label for="fTitle">Style *</label>
              <input type="text" id="fTitle" required placeholder="e.g. EYX0826014 BALLOON UTILITY CARGO">
            </div>
            <div class="field-row three">
              <div class="field">
                <label for="fSeason">Season</label>
                <input type="text" id="fSeason" list="seasonList" placeholder="e.g. SS27">
                <datalist id="seasonList">
                  <option value="SS27"><option value="AW27"><option value="SS28"><option value="AW28">
                </datalist>
              </div>
              <div class="field">
                <label for="fDept">Dept</label>
                <input type="text" id="fDept" list="deptCodeList" placeholder="e.g. OG">
                <datalist id="deptCodeList">
                  <option value="MP"><option value="OX"><option value="BG"><option value="OG">
                </datalist>
              </div>
              <div class="field">
                <label for="fBuyer">Buyer</label>
                <div class="combo" id="buyerCombo">
                  <input type="text" id="fBuyer" autocomplete="off" placeholder="Saved buyer">
                  <div class="combo-list" id="buyerComboList" hidden></div>
                </div>
              </div>
            </div>
          </div>

          <div class="panel">
            <div class="section-title" style="margin-top:0;">Rcvd Status <span class="col-ref">Column E</span></div>
            <p class="hint" style="margin:-6px 0 14px;">These four lines print inside one cell, exactly like your sheet.</p>
            <div class="field">
              <label for="fThread">Thread</label>
              <input type="text" id="fThread" placeholder="e.g. DTM 13-0907 TCX">
            </div>
            <div class="field">
              <label for="fSpec">Spec</label>
              <input type="text" id="fSpec" placeholder="e.g. H52069 with 24 CM Length">
            </div>
            <div class="field">
              <label for="fButtonRivet">Button and Rivet</label>
              <input type="text" id="fButtonRivet" placeholder="e.g. Shank: GA60 26 Ligne, Rivet: AKUM/RVND/30332">
            </div>
            <div class="field">
              <label for="fWashDetail">Wash</label>
              <input type="text" id="fWashDetail" placeholder="e.g. Desize + enzyme + softener">
            </div>
          </div>

          <div class="panel">
            <div class="section-title" style="margin-top:0;">Booking Status <span class="col-ref">Column P</span></div>
            <div class="field-row">
              <div class="field">
                <label for="fBookFabric">Fabric</label>
                <input type="text" id="fBookFabric" list="bookingList" placeholder="Booked / In House / N/A">
              </div>
              <div class="field">
                <label for="fBookBodyThread">Body Thread</label>
                <input type="text" id="fBookBodyThread" list="bookingList" placeholder="Booked">
              </div>
            </div>
            <div class="field-row">
              <div class="field">
                <label for="fBookEmbThread">EMB Thread</label>
                <input type="text" id="fBookEmbThread" list="bookingList" placeholder="Booked">
              </div>
              <div class="field">
                <label for="fBookMetalwork">Metalwork</label>
                <input type="text" id="fBookMetalwork" list="bookingList" placeholder="Booked">
              </div>
            </div>
            <div class="field">
              <label for="fBookLace">Lace</label>
              <input type="text" id="fBookLace" list="bookingList" placeholder="Booked / N/A">
            </div>
            <datalist id="bookingList">
              <option value="Booked"><option value="In House"><option value="N/A">
              <option value="Waiting for swatch"><option value="Supplier info pending"><option value="3rd party supplier">
            </datalist>
          </div>

          <div class="panel">
            <div class="section-title" style="margin-top:0;">Image &amp; Notes <span class="col-ref">Column D · Q</span></div>
            <div class="field">
              <label>Style Image</label>
              <p class="hint" style="margin:-2px 0 10px;">The first photo goes into the IMAGE column of the exported sheet.</p>
              <div class="img-previews" id="formImgPreviews"></div>
              <div class="img-uploader">
                <input type="file" id="formImageInput" accept="image/png,image/jpeg,image/webp,image/gif" multiple>
                <label for="formImageInput">📎 Add Photos</label>
                <div class="hint" id="formImgHint"></div>
              </div>
            </div>
            <div class="field">
              <label for="fRemarks">Remarks</label>
              <input type="text" id="fRemarks" placeholder="e.g. Option-1 / Drop / anything worth noting">
            </div>
            <div class="field">
              <label for="fDescription">Internal Notes</label>
              <textarea id="fDescription" placeholder="Anything extra — stays in the app, not in the sheet..."></textarea>
            </div>
          </div>

          </div>

          <aside class="form-side">

          <div class="panel">
            <div class="section-title" style="margin-top:0;">Row Colour</div>
            <div class="field">
              <label for="fRowState">Sheet row state</label>
              <select id="fRowState">
                <option value="active">🟩 Active — green</option>
                <option value="hold">🟧 Hold — amber</option>
                <option value="license_hold">🟨 Hold, licence issue — yellow</option>
                <option value="drop">🟥 Drop / blocked — red</option>
              </select>
              <p class="hint">Exactly how the row is filled in the exported workbook. Write the reason in Remarks.</p>
            </div>
          </div>

          <div class="panel">
            <div class="section-title" style="margin-top:0;">Fabric &amp; Sample <span class="col-ref">F · H</span></div>
            <div class="field">
              <label for="fFabArt">Fab Art.</label>
              <input type="text" id="fFabArt" placeholder="e.g. HAMEEM DENIM S3656-I">
            </div>
            <div class="field">
              <label for="fSampleType">Sample Type</label>
              <input type="text" id="fSampleType" list="sampleTypeList" placeholder="Development">
              <datalist id="sampleTypeList">
                <option value="Development"><option value="Proto"><option value="Fit"><option value="SMS">
                <option value="Size Set"><option value="PP"><option value="TOP"><option value="Shipment">
              </datalist>
            </div>
            <div class="field">
              <label for="fSampleQty">Sample Qty</label>
              <input type="number" id="fSampleQty" min="0" placeholder="e.g. 5">
            </div>
          </div>

          <div class="panel">
            <div class="section-title" style="margin-top:0;">Dates &amp; Progress <span class="col-ref">G · I — O</span></div>
            <div class="field">
              <label for="fReceivedAt">Request Rcvd date</label>
              <input type="date" id="fReceivedAt">
            </div>
            <div class="field">
              <label for="fTechpackHandover">Techpack handover date</label>
              <input type="date" id="fTechpackHandover">
            </div>
            <div class="field-row">
              <div class="field">
                <label for="fCuttingStatus">Cutting status</label>
                <select id="fCuttingStatus">
                  <option value="">—</option>
                  <option value="Done">Done</option>
                  <option value="Running">Running</option>
                  <option value="N/A">N/A</option>
                </select>
              </div>
              <div class="field">
                <label for="fSewingStatus">Sewing Status</label>
                <input type="text" id="fSewingStatus" list="sewingList" placeholder="Done / Running 10-Sep">
                <datalist id="sewingList">
                  <option value="Done"><option value="Running"><option value="N/A">
                </datalist>
              </div>
            </div>
            <div class="field">
              <label for="fWashSendDate">Wash send date</label>
              <input type="date" id="fWashSendDate">
            </div>
            <div class="field">
              <label for="fWashRcvdDate">Rcvd from Wash</label>
              <input type="date" id="fWashRcvdDate">
            </div>
            <div class="field">
              <label for="fSampleSubmitDate">Sample submit date</label>
              <input type="date" id="fSampleSubmitDate">
            </div>
            <div class="field">
              <label for="fPriceNote">Price</label>
              <input type="text" id="fPriceNote" placeholder="e.g. Done 17-Aug">
            </div>
          </div>

          <div class="panel">
            <div class="section-title" style="margin-top:0;">App Tracking</div>
            <p class="hint" style="margin:-6px 0 14px;">Drives the board, reports and reminders — not part of the sheet.</p>
            <div class="field-row">
              <div class="field">
                <label for="fStatus">Stage</label>
                <select id="fStatus">
                  <option value="new">New</option>
                  <option value="in_progress">In Progress</option>
                  <option value="waiting">Waiting</option>
                  <option value="done">Done</option>
                  <option value="cancelled">Cancelled</option>
                </select>
              </div>
              <div class="field">
                <label for="fPriority">Priority</label>
                <select id="fPriority">
                  <option value="low">Low</option>
                  <option value="medium" selected>Medium</option>
                  <option value="high">High</option>
                  <option value="urgent">Urgent</option>
                </select>
              </div>
            </div>
            <div class="field">
              <label for="fDeadline">Internal Deadline</label>
              <input type="date" id="fDeadline">
            </div>
            <div class="field">
              <label for="fDepartment">Currently With</label>
              <div class="combo" id="deptCombo">
                <input type="text" id="fDepartment" autocomplete="off" placeholder="e.g. Sample room">
                <div class="combo-list" id="deptComboList" hidden></div>
              </div>
            </div>
          </div>

          </aside>
          </div>
          <div class="form-actions sticky-actions">
            <button type="button" class="btn btn-ghost" id="taskCancelBtn2">Cancel</button>
            <button type="submit" class="btn btn-primary">Save Task</button>
          </div>
        </form>
      </section>

      <section class="detail-view" id="detailView" hidden>
        <div class="detail-topline">
          <button class="btn btn-ghost" id="detailBackBtn" type="button">← Back to list</button>
          <div class="detail-actions">
            <button class="btn btn-primary" id="detailNextStageBtn" type="button">➡️ Move to Next Stage</button>
            <button class="btn btn-success" id="detailFinishBtn" type="button">✅ Finish</button>
            <button class="btn btn-warning" id="detailCancelBtn" type="button">🚫 Cancel Task</button>
            <button class="btn btn-ghost" id="detailEditBtn" type="button">✏️ Edit</button>
            <button class="btn btn-danger" id="detailDeleteBtn" type="button">🗑️ Delete</button>
          </div>
        </div>

        <div class="detail-grid">
          <div class="panel">
            <h2 class="panel-title" id="detailTitle">—</h2>
            <div class="detail-meta" id="detailMeta"></div>
            <div class="section-title">Style Information</div>
            <div class="spec-grid" id="detailSpecs"></div>
            <div class="section-title">Details</div>
            <div class="detail-desc" id="detailDescription"></div>

            <div class="section-title">Photos</div>
            <div class="detail-gallery" id="detailImages"></div>
            <div class="img-uploader" id="detailUploader">
              <input type="file" id="detailImageInput" accept="image/png,image/jpeg,image/webp,image/gif" multiple>
              <label for="detailImageInput">📎 Add Photos</label>
              <div class="hint" id="detailUploadHint"></div>
            </div>
          </div>

          <div class="panel">
            <div class="section-title" style="margin-top:0;">Updates, Corrections &amp; Emails</div>
            <div id="commentsList"></div>
            <div class="comment-form">
              <div class="comment-type-row">
                <select id="commentType">
                  <option value="comment">💬 Comment</option>
                  <option value="correction">🛠️ Correction</option>
                  <option value="buyer_email">📧 Buyer Email</option>
                  <option value="reply">↩️ My Reply</option>
                </select>
              </div>
              <textarea id="commentInput" placeholder="Write the update, correction note, or email content..."></textarea>
              <div class="img-previews" id="commentImgPreviews"></div>
              <div class="comment-input-row">
                <div class="img-uploader small">
                  <input type="file" id="commentImageInput" accept="image/png,image/jpeg,image/webp,image/gif" multiple>
                  <label for="commentImageInput">📎 Attach</label>
                </div>
                <button class="btn btn-primary" id="commentSendBtn" type="button">Send</button>
              </div>
            </div>
          </div>
        </div>
      </section>
    </div>
  </main>
</div>
<!-- lightbox -->
<div class="modal-overlay" id="lightbox" hidden>
  <img class="lightbox-img" id="lightboxImg" src="" alt="">
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/summernote/0.8.18/summernote-lite.min.js"></script>
<script src="{{ asset('js/app.js') }}?v={{ filemtime(public_path('js/app.js')) }}"></script>
</body>
</html>
