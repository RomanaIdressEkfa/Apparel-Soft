<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>ThreadTrack — Dashboard</title>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Sora:wght@500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/summernote/0.8.18/summernote-lite.min.css">
<link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>
<div class="app-shell">
  <aside class="sidebar">
    <div class="sidebar-brand">
      <div class="brand-mark">
        <svg viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16v3H4z"/><path d="M6 7v13h12V7"/><path d="M9 11h6"/><path d="M9 15h6"/></svg>
      </div>
      <div>
        <p class="brand">ThreadTrack</p>
        <p class="tagline">Merchandising tracker</p>
      </div>
    </div>

    <nav class="side-nav">
      <p class="side-nav-label">Overview</p>
      <button type="button" class="side-nav-item active" data-nav="all">
        <span class="side-nav-label-group"><span class="side-nav-icon">📋</span>Board (All)</span><span class="side-nav-count" id="navCountAll">0</span>
      </button>

      <p class="side-nav-label">Status</p>
      <button type="button" class="side-nav-item" data-nav="status" data-status="new">
        <span class="side-nav-label-group"><span class="side-nav-icon">🆕</span>New</span><span class="side-nav-count" id="navCountNew">0</span>
      </button>
      <button type="button" class="side-nav-item" data-nav="status" data-status="in_progress">
        <span class="side-nav-label-group"><span class="side-nav-icon">🔄</span>In Progress</span><span class="side-nav-count" id="navCountRunning">0</span>
      </button>
      <button type="button" class="side-nav-item" data-nav="status" data-status="waiting">
        <span class="side-nav-label-group"><span class="side-nav-icon">⏳</span>Waiting</span><span class="side-nav-count" id="navCountWaiting">0</span>
      </button>
      <button type="button" class="side-nav-item" data-nav="status" data-status="done">
        <span class="side-nav-label-group"><span class="side-nav-icon">✅</span>Done</span><span class="side-nav-count" id="navCountDone">0</span>
      </button>
      <button type="button" class="side-nav-item" data-nav="overdue">
        <span class="side-nav-label-group"><span class="side-nav-icon">🔥</span>Overdue</span><span class="side-nav-count danger" id="navCountOverdue">0</span>
      </button>
    </nav>

    <div class="sidebar-footer">
      <div class="user-chip">
        <span class="dot">{{ mb_substr(auth()->user()->name, 0, 1) }}</span>
        <span>{{ auth()->user()->name }}</span>
        <form method="POST" action="{{ route('logout') }}">
          @csrf
          <button type="submit" class="link">Log out</button>
        </form>
      </div>
    </div>
  </aside>

  <main class="main">
    <header class="topbar">
      <input type="text" id="searchInput" placeholder="Search by style, buyer, or title...">
      <a class="btn btn-ghost" href="{{ route('tasks.export') }}">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v12"/><path d="m7 10 5 5 5-5"/><path d="M5 21h14"/></svg>
        Export CSV
      </a>
      <button class="btn btn-primary" id="addTaskBtn">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
        New Task
      </button>
    </header>

    <div id="bannerHost"></div>

    <section class="stats">
      <div class="stat-card total">
        <div>
          <div class="stat-label">Total Tasks</div>
          <div class="stat-num" id="statTotal">0</div>
        </div>
        <div class="stat-icon">📦</div>
      </div>
      <div class="stat-card running">
        <div>
          <div class="stat-label">In Progress</div>
          <div class="stat-num" id="statRunning">0</div>
        </div>
        <div class="stat-icon">🔄</div>
      </div>
      <div class="stat-card waiting">
        <div>
          <div class="stat-label">Waiting</div>
          <div class="stat-num" id="statWaiting">0</div>
        </div>
        <div class="stat-icon">⏳</div>
      </div>
      <div class="stat-card overdue">
        <div>
          <div class="stat-label">Overdue</div>
          <div class="stat-num" id="statOverdue">0</div>
        </div>
        <div class="stat-icon">🔥</div>
      </div>
    </section>

    <section class="toolbar">
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
    </section>

    <section class="grid" id="taskGrid">
      <div class="empty-state" id="loadingState">Loading...</div>
    </section>

    <p class="footnote">To let a colleague use this, share this computer's local-network link and have them create their own account.</p>
  </main>
</div>

<!-- add/edit task modal -->
<div class="modal-overlay" id="taskModal" hidden>
  <div class="modal wide">
    <div class="modal-head">
      <h3 class="modal-title" id="taskModalTitle">Add New Task</h3>
      <button class="icon-btn" id="taskModalCloseBtn" type="button">✕</button>
    </div>
    <form id="taskForm">
      <div class="field">
        <label for="fTitle">Title / Style Description *</label>
        <input type="text" id="fTitle" required placeholder="e.g. AW27 Girls Denim Cargo — Tech Pack Development">
      </div>
      <div class="field-row">
        <div class="field">
          <label for="fBuyer">Buyer</label>
          <input type="text" id="fBuyer" placeholder="e.g. H&amp;M">
        </div>
        <div class="field">
          <label for="fStyle">Style No.</label>
          <input type="text" id="fStyle" placeholder="e.g. ABC-123">
        </div>
      </div>
      <div class="field">
        <label for="fDepartment">Currently With Department</label>
        <div class="combo" id="deptCombo">
          <input type="text" id="fDepartment" autocomplete="off" placeholder="e.g. Pattern / Sample / Washing">
          <div class="combo-list" id="deptComboList" hidden></div>
        </div>
      </div>
      <div class="field-row">
        <div class="field">
          <label for="fSampleQty">Samples Required</label>
          <input type="number" id="fSampleQty" min="0" placeholder="e.g. 5">
        </div>
        <div class="field">
          <label for="fDeadline">Deadline</label>
          <input type="date" id="fDeadline">
        </div>
      </div>
      <div class="field-row">
        <div class="field">
          <label for="fStatus">Status</label>
          <select id="fStatus">
            <option value="new">New</option>
            <option value="in_progress">In Progress</option>
            <option value="waiting">Waiting</option>
            <option value="done">Done</option>
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
        <label for="fDescription">Details</label>
        <textarea id="fDescription" placeholder="Fabric, wash, comments, or any other details..."></textarea>
      </div>
      <div class="field">
        <label>Photos</label>
        <div class="img-previews" id="formImgPreviews"></div>
        <div class="img-uploader">
          <input type="file" id="formImageInput" accept="image/png,image/jpeg,image/webp,image/gif" multiple>
          <label for="formImageInput">📎 Add Photos</label>
          <div class="hint" id="formImgHint"></div>
        </div>
      </div>
      <div class="form-actions">
        <button type="button" class="btn btn-ghost" id="taskCancelBtn">Cancel</button>
        <button type="submit" class="btn btn-primary" id="taskSaveBtn">Save</button>
      </div>
    </form>
  </div>
</div>

<!-- detail modal -->
<div class="modal-overlay" id="detailModal" hidden>
  <div class="modal wide">
    <div class="modal-head">
      <h3 class="modal-title" id="detailTitle">—</h3>
      <button class="icon-btn" id="detailCloseBtn" type="button">✕</button>
    </div>
    <div class="detail-meta" id="detailMeta"></div>
    <div class="detail-desc" id="detailDescription"></div>

    <div class="section-title">Photos</div>
    <div class="detail-gallery" id="detailImages"></div>
    <div class="img-uploader" id="detailUploader">
      <input type="file" id="detailImageInput" accept="image/png,image/jpeg,image/webp,image/gif" multiple>
      <label for="detailImageInput">📎 Add Photos</label>
      <div class="hint" id="detailUploadHint"></div>
    </div>

    <div class="detail-actions">
      <button class="btn btn-primary" id="detailNextStageBtn" type="button">➡️ Move to Next Stage</button>
      <button class="btn btn-ghost" id="detailEditBtn" type="button">✏️ Edit</button>
      <button class="btn btn-danger" id="detailDeleteBtn" type="button">🗑️ Delete</button>
    </div>

    <div class="section-title">Updates, Corrections &amp; Emails</div>
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

<!-- lightbox -->
<div class="modal-overlay" id="lightbox" hidden>
  <img class="lightbox-img" id="lightboxImg" src="" alt="">
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/summernote/0.8.18/summernote-lite.min.js"></script>
<script src="{{ asset('js/app.js') }}"></script>
</body>
</html>
