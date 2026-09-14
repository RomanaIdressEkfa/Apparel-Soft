(function () {
  "use strict";

  var CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
  var tasks = [];
  var editingTaskId = null;
  var activeTaskId = null;
  var pollTimer = null;
  var activeStatus = 'all';
  var activeOverdueOnly = false;
  var activeView = 'tracker';

  var $ = function (id) { return document.getElementById(id); };

  /** Only surface errors thrown by this app — browser extensions share the same window. */
  function isOwnScript(filename) {
    return typeof filename === 'string' && filename.indexOf('/js/app.js') !== -1;
  }

  window.addEventListener('error', function (e) {
    if (!isOwnScript(e.filename)) return;
    showBanner('Script error: ' + (e.message || 'unknown error') + ' [line ' + e.lineno + ']');
  });
  window.addEventListener('unhandledrejection', function (e) {
    var reason = e.reason;
    if (!reason || !reason.stack || reason.stack.indexOf('/js/app.js') === -1) return;
    showBanner('Unhandled error: ' + (reason.message || reason));
  });

  // ---------- rich text (summernote) ----------
  var RICH_TEXT_OPTS = {
    toolbar: [
      ['style', ['bold', 'italic', 'underline', 'clear']],
      ['para', ['ul', 'ol']],
      ['insert', ['link']],
    ],
    disableDragAndDrop: true,
  };
  function isHtmlEmpty(html) {
    if (!html) return true;
    return html.replace(/<[^>]*>/g, '').replace(/&nbsp;/g, ' ').trim().length === 0;
  }
  /** Reads a Summernote editor, normalising its "empty" markup to an empty string. */
  function getRichText(selector) {
    var html = jQuery(selector).summernote('code');
    return isHtmlEmpty(html) ? '' : html;
  }
  function setRichText(selector, html) { jQuery(selector).summernote('code', html || ''); }

  function getDescriptionHtml() { return getRichText('#fDescription'); }
  function setDescriptionHtml(html) { setRichText('#fDescription', html); }
  function getCommentHtml() { return getRichText('#commentInput'); }
  function setCommentHtml(html) { setRichText('#commentInput', html); }
  function getBuyerNotesHtml() { return getRichText('#bNotes'); }
  function setBuyerNotesHtml(html) { setRichText('#bNotes', html); }

  jQuery('#fDescription').summernote(Object.assign({ height: 140, placeholder: 'Fabric, wash, comments, or any other details...' }, RICH_TEXT_OPTS));
  jQuery('#commentInput').summernote(Object.assign({ height: 90, placeholder: 'Write the update, correction note, or email content...' }, RICH_TEXT_OPTS));
  jQuery('#bNotes').summernote(Object.assign({ height: 110, placeholder: 'Payment terms, shipping mode, packing instructions...' }, RICH_TEXT_OPTS));
  jQuery('#commentInput').on('summernote.keydown', function (we, e) {
    if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) { e.preventDefault(); $('commentSendBtn').click(); }
  });

  function api(url, options) {
    options = options || {};
    var headers = Object.assign(
      { 'X-CSRF-TOKEN': CSRF_TOKEN, 'Accept': 'application/json' },
      options.headers || {}
    );
    if (options.json !== undefined) {
      headers['Content-Type'] = 'application/json';
      options.body = JSON.stringify(options.json);
      delete options.json;
    }
    options.headers = headers;
    return fetch(url, options).then(function (res) {
      if (res.status === 401) {
        window.location.href = '/login';
        return Promise.reject({ code: 'unauthorized' });
      }
      return res.json().then(function (body) {
        if (!res.ok) { return Promise.reject(body); }
        return body;
      });
    });
  }

  function fmtDate(iso) {
    if (!iso) return '';
    try {
      var d = new Date(iso.length <= 10 ? iso + 'T00:00:00' : iso);
      return new Intl.DateTimeFormat('en-GB', { day: 'numeric', month: 'short', year: 'numeric' }).format(d);
    } catch (e) { return iso; }
  }

  function todayISO() {
    var d = new Date();
    return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
  }

  function isOverdue(t) {
    return t.deadline && t.status !== 'done' && t.deadline < todayISO();
  }

  var STATUS_LABEL = { new: 'New', in_progress: 'In Progress', waiting: 'Waiting', done: 'Done', cancelled: 'Cancelled' };
  var PRIORITY_LABEL = { low: 'Low', medium: 'Medium', high: 'High', urgent: 'Urgent' };
  var PRIORITY_COLOR = { low: '', medium: '', high: 'var(--warn)', urgent: 'var(--danger)' };

  var bannerTimer = null;
  function showBanner(msg) {
    clearTimeout(bannerTimer);
    $('bannerHost').innerHTML = '<div class="banner">' + escapeHtml(msg) + '</div>';
    bannerTimer = setTimeout(clearBanner, 6000);
  }
  function showSuccess(msg) {
    clearTimeout(bannerTimer);
    $('bannerHost').innerHTML = '<div class="banner success">' + escapeHtml(msg) + '</div>';
    bannerTimer = setTimeout(clearBanner, 2200);
  }
  function clearBanner() { $('bannerHost').innerHTML = ''; }

  function escapeHtml(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }

  function errorMessage(err) {
    if (err && err.errors) {
      var first = Object.values(err.errors)[0];
      if (Array.isArray(first)) return first[0];
    }
    if (err && err.message) return err.message;
    return 'Something went wrong. Please try again.';
  }

  // ---------- load + render ----------
  function loadTasks() {
    return api('/api/tasks').then(function (data) {
      tasks = data.tasks || [];
      clearBanner();
      computeStats();
      populateBuyerFilter();
      renderGrid();
    }).catch(function (err) {
      showBanner('Could not load data: ' + errorMessage(err));
    });
  }

  function computeStats() {
    var total = tasks.length, newCount = 0, running = 0, waiting = 0, done = 0, cancelled = 0, overdue = 0;
    tasks.forEach(function (t) {
      if (t.status === 'new') newCount++;
      if (t.status === 'in_progress') running++;
      if (t.status === 'waiting') waiting++;
      if (t.status === 'done') done++;
      if (t.status === 'cancelled') cancelled++;
      if (isOverdue(t)) overdue++;
    });
    $('statTotal').textContent = total;
    $('statRunning').textContent = running;
    $('statWaiting').textContent = waiting;
    $('statOverdue').textContent = overdue;

    $('navCountTracker').textContent = total;
    $('navCountAll').textContent = total;
    $('navCountNew').textContent = newCount;
    $('navCountRunning').textContent = running;
    $('navCountWaiting').textContent = waiting;
    $('navCountDone').textContent = done;
    $('navCountCancelled').textContent = cancelled;
    $('navCountOverdue').textContent = overdue;
  }

  function populateChoiceFilter(selectId, field, allLabel) {
    var sel = $(selectId);
    var current = sel.value;
    var values = Array.from(new Set(tasks.map(function (t) { return (t[field] || '').trim(); }).filter(Boolean))).sort();
    sel.innerHTML = '<option value="all">' + allLabel + '</option>' + values.map(function (v) {
      return '<option value="' + escapeHtml(v) + '">' + escapeHtml(v) + '</option>';
    }).join('');
    if (values.indexOf(current) >= 0) sel.value = current;
  }

  function populateBuyerFilter() {
    var sel = $('buyerFilter');
    var current = sel.value;
    var fromTasks = tasks.map(function (t) { return (t.buyer || '').trim(); }).filter(Boolean);
    var fromMaster = buyers.map(function (b) { return b.name; });
    var names = Array.from(new Set(fromMaster.concat(fromTasks))).sort();
    sel.innerHTML = '<option value="all">All Buyers</option>' + names.map(function (n) {
      return '<option value="' + escapeHtml(n) + '">' + escapeHtml(n) + '</option>';
    }).join('');
    if (names.indexOf(current) >= 0) sel.value = current;

    populateChoiceFilter('seasonFilter', 'season', 'All Seasons');
  }

  /** Inclusive [from,to] date strings for the selected reporting period. */
  function currentPeriodRange() {
    var mode = $('periodFilter').value;
    if (mode === 'all') return null;
    if (mode === 'custom') {
      var from = $('periodFrom').value;
      var to = $('periodTo').value;
      if (!from && !to) return null;
      return { from: from || '0000-01-01', to: to || '9999-12-31' };
    }

    var now = new Date();
    var y = now.getFullYear();
    var iso = function (d) {
      return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
    };

    if (mode === 'week') {
      var day = now.getDay();
      var monday = new Date(now);
      monday.setDate(now.getDate() - (day === 0 ? 6 : day - 1));
      var sunday = new Date(monday);
      sunday.setDate(monday.getDate() + 6);
      return { from: iso(monday), to: iso(sunday) };
    }
    if (mode === 'month') {
      return { from: iso(new Date(y, now.getMonth(), 1)), to: iso(new Date(y, now.getMonth() + 1, 0)) };
    }
    if (mode === 'last_month') {
      return { from: iso(new Date(y, now.getMonth() - 1, 1)), to: iso(new Date(y, now.getMonth(), 0)) };
    }
    if (mode === 'year') {
      return { from: y + '-01-01', to: y + '-12-31' };
    }
    return null;
  }

  function taskDateFor(t, basis) {
    if (basis === 'created_at') return (t.created_at || '').slice(0, 10);
    return t[basis] || '';
  }

  function applySoftFilters(list) {
    var q = $('searchInput').value.trim().toLowerCase();
    var pr = $('priorityFilter').value;
    var by = $('buyerFilter').value;
    var season = $('seasonFilter').value;
    var range = currentPeriodRange();
    var basis = $('periodBasis').value;

    return list.filter(function (t) {
      if (pr !== 'all' && t.priority !== pr) return false;
      if (by !== 'all' && (t.buyer || '') !== by) return false;
      if (season !== 'all' && (t.season || '') !== season) return false;
      if (range) {
        var d = taskDateFor(t, basis);
        if (!d || d < range.from || d > range.to) return false;
      }
      if (q) {
        var hay = [t.title, t.description, t.buyer, t.style, t.season, t.po_number, t.department, t.fabric, t.color].join(' ').toLowerCase();
        if (hay.indexOf(q) === -1) return false;
      }
      return true;
    });
  }

  var KANBAN_COLUMNS = [
    { status: 'new', label: 'New' },
    { status: 'in_progress', label: 'In Progress' },
    { status: 'waiting', label: 'Waiting' },
    { status: 'done', label: 'Done' },
    { status: 'cancelled', label: 'Cancelled' },
  ];

  var STAGE_FLOW = ['new', 'in_progress', 'waiting', 'done'];

  function emptyStateHtml() {
    var headline = 'No tasks found.';
    var hint = 'Try clearing the filters above.';

    if (tasks.length === 0) {
      headline = 'Nothing here yet.';
      hint = 'Click "New Task" to add your first style.';
    } else if (activeView === 'list' && activeOverdueOnly) {
      headline = 'Nothing is overdue.';
      hint = 'Every style with a deadline is still on time.';
    } else if (activeView === 'list') {
      headline = 'Nothing is at the ' + STATUS_LABEL[activeStatus] + ' stage right now.';
      hint = 'Styles will show up here as soon as they reach this stage.';
    }

    return '<div class="empty-state"><div>' + headline + '</div>' +
      '<div class="hint" style="margin-top:6px;">' + hint + '</div></div>';
  }

  function renderSummary(list) {
    var samples = 0, submitted = 0, held = 0;
    list.forEach(function (t) {
      if (t.sample_qty) samples += t.sample_qty;
      if (t.sample_submit_date) submitted++;
      if (t.row_state && t.row_state !== 'active') held++;
    });

    var range = currentPeriodRange();
    var periodText = range
      ? fmtDate(range.from) + ' – ' + fmtDate(range.to)
      : 'All time';

    function item(label, value, cls) {
      return '<div class="summary-item"><span class="summary-label">' + label + '</span>' +
        '<span class="summary-value' + (cls ? ' ' + cls : '') + '">' + value + '</span></div>';
    }

    $('summaryStrip').innerHTML =
      item('Period', periodText) +
      item('Styles shown', list.length) +
      item('Samples', samples.toLocaleString('en-US') + ' pcs') +
      item('Submitted', submitted) +
      item('Hold / Drop', held, held ? 'warn' : '');
  }

  /** Everything the current view is showing, after every active filter. */
  function visibleTasks() {
    var soft = applySoftFilters(tasks);
    if (activeView === 'tracker' || (activeStatus === 'all' && !activeOverdueOnly)) {
      return soft;
    }
    return soft.filter(function (t) {
      if (activeOverdueOnly) return isOverdue(t);
      return t.status === activeStatus;
    });
  }

  function renderGrid() {
    var grid = $('taskGrid');
    var shown = visibleTasks();
    renderSummary(shown);

    if (activeView === 'tracker') {
      renderTracker(grid, shown);
      return;
    }
    if (activeStatus === 'all' && !activeOverdueOnly) {
      renderKanban(grid, shown);
      return;
    }
    renderTracker(grid, shown);
  }

  // ---------- progress tracker view ----------
  function trackerStepsHtml(t) {
    if (t.status === 'cancelled') {
      return '<div class="track-steps cancelled">' +
        STAGE_FLOW.map(function (s, i) {
          return (i ? '<span class="track-arrow">→</span>' : '') +
            '<span class="track-step"><span class="track-dot"></span><span class="track-name">' + STATUS_LABEL[s] + '</span></span>';
        }).join('') +
        '<span class="track-arrow">→</span>' +
        '<span class="track-step current cancelled-step"><span class="track-dot">✕</span><span class="track-name">Cancelled</span></span>' +
      '</div>';
    }

    var currentIdx = STAGE_FLOW.indexOf(t.status);
    return '<div class="track-steps">' + STAGE_FLOW.map(function (s, i) {
      var state = i < currentIdx ? 'done' : (i === currentIdx ? 'current' : 'todo');
      var mark = i < currentIdx ? '✓' : (i === currentIdx ? '●' : '');
      return (i ? '<span class="track-arrow' + (i <= currentIdx ? ' filled' : '') + '">→</span>' : '') +
        '<span class="track-step ' + state + '">' +
          '<span class="track-dot">' + mark + '</span>' +
          '<span class="track-name">' + STATUS_LABEL[s] + '</span>' +
        '</span>';
    }).join('') + '</div>';
  }

  function trackerRowHtml(t) {
    var overdue = isOverdue(t);
    var sub = [t.buyer, t.style].filter(Boolean).join(' • ');
    var nextStatus = STAGE_FLOW[STAGE_FLOW.indexOf(t.status) + 1];
    var isClosed = t.status === 'done' || t.status === 'cancelled';

    var actions = '';
    if (nextStatus) {
      actions += '<button type="button" class="track-btn primary" data-act="next" data-id="' + t.id + '">▶ ' + STATUS_LABEL[nextStatus] + '</button>';
    }
    if (!isClosed) {
      actions += '<button type="button" class="track-btn success" data-act="finish" data-id="' + t.id + '">✅ Finish</button>';
      actions += '<button type="button" class="track-btn warn" data-act="cancel" data-id="' + t.id + '">🚫 Cancel</button>';
    } else {
      actions += '<button type="button" class="track-btn subtle" data-act="reopen" data-id="' + t.id + '" ' +
        'title="Move this ' + STATUS_LABEL[t.status] + ' style back to In Progress">↩ Reopen</button>';
    }

    var deadlineHtml = t.deadline
      ? '<span class="track-deadline' + (overdue ? ' overdue' : '') + '">📅 ' + fmtDate(t.deadline) + (overdue ? ' · overdue' : '') + '</span>'
      : '<span class="track-deadline muted">📅 No deadline</span>';

    return '<div class="track-row' + (overdue ? ' is-overdue' : '') + (t.status === 'cancelled' ? ' is-cancelled' : '') + '" data-id="' + t.id + '">' +
      '<div class="track-main">' +
        '<div class="track-head">' +
          '<span class="track-id">#' + t.id + '</span>' +
          '<span class="track-title">' + escapeHtml(t.title || '(Untitled)') + '</span>' +
          '<span class="pill pill-' + t.status + '">' + STATUS_LABEL[t.status] + '</span>' +
        '</div>' +
        '<div class="track-sub">' +
          (sub ? '<span>' + escapeHtml(sub) + '</span>' : '') +
          (t.department ? '<span class="dept-chip">' + escapeHtml(t.department) + '</span>' : '') +
          (t.sample_qty != null ? '<span class="dept-chip">🧵 ' + t.sample_qty + ' samples</span>' : '') +
          deadlineHtml +
        '</div>' +
      '</div>' +
      '<div class="track-progress">' + trackerStepsHtml(t) + '</div>' +
      '<div class="track-actions">' + actions + '</div>' +
    '</div>';
  }

  function renderTracker(grid, list) {
    grid.className = 'tracker';
    if (list.length === 0) {
      grid.innerHTML = emptyStateHtml();
      return;
    }

    var order = { new: 0, in_progress: 1, waiting: 2, done: 3, cancelled: 4 };
    var sorted = list.slice().sort(function (a, b) {
      if (order[a.status] !== order[b.status]) return order[a.status] - order[b.status];
      if (!a.deadline) return 1;
      if (!b.deadline) return -1;
      return a.deadline < b.deadline ? -1 : 1;
    });

    grid.innerHTML = sorted.map(trackerRowHtml).join('');
    wireTrackerRows(grid);
  }

  function wireTrackerRows(grid) {
    Array.prototype.forEach.call(grid.querySelectorAll('.track-row'), function (row) {
      row.addEventListener('click', function (e) {
        if (e.target.closest('.track-btn')) return;
        openDetail(parseInt(row.getAttribute('data-id'), 10));
      });
      row.setAttribute('draggable', 'true');
      row.addEventListener('dragstart', function (e) {
        e.dataTransfer.setData('text/plain', row.getAttribute('data-id'));
        e.dataTransfer.effectAllowed = 'move';
        row.classList.add('dragging');
      });
      row.addEventListener('dragend', function () { row.classList.remove('dragging'); });
    });

    Array.prototype.forEach.call(grid.querySelectorAll('.track-btn'), function (btn) {
      btn.addEventListener('click', function (e) {
        e.stopPropagation();
        var id = parseInt(btn.getAttribute('data-id'), 10);
        var act = btn.getAttribute('data-act');
        var t = tasks.find(function (x) { return x.id === id; });
        if (!t) return;

        var target;
        if (act === 'next') {
          target = STAGE_FLOW[STAGE_FLOW.indexOf(t.status) + 1];
        } else if (act === 'finish') {
          target = 'done';
        } else if (act === 'cancel') {
          if (!confirm('Cancel this task? You can reopen it later.')) return;
          target = 'cancelled';
        } else if (act === 'reopen') {
          if (!confirm('Reopen "' + (t.title || 'this task') + '"?\n\nIt is currently ' + STATUS_LABEL[t.status] +
            ' and will move back to In Progress.')) return;
          target = 'in_progress';
        }
        if (!target) return;

        btn.disabled = true;
        changeStatus(id, target).catch(function () { btn.disabled = false; });
      });
    });
  }

  function changeStatus(id, status) {
    return api('/api/tasks/' + id + '/status', { method: 'PATCH', json: { status: status } })
      .then(function () {
        showSuccess('Moved to ' + STATUS_LABEL[status] + '.');
        return loadTasks();
      })
      .catch(function (err) {
        showBanner('Could not update status: ' + errorMessage(err));
        return Promise.reject(err);
      });
  }

  function wireCardClicks(host) {
    Array.prototype.forEach.call(host.querySelectorAll('.card'), function (el) {
      el.addEventListener('click', function () { openDetail(parseInt(el.getAttribute('data-id'), 10)); });
      el.setAttribute('draggable', 'true');
      el.addEventListener('dragstart', function (e) {
        e.dataTransfer.setData('text/plain', el.getAttribute('data-id'));
        e.dataTransfer.effectAllowed = 'move';
        el.classList.add('dragging');
      });
      el.addEventListener('dragend', function () { el.classList.remove('dragging'); });
    });
  }

  /** Board view: status groups stacked vertically, each listing its styles as full-width rows. */
  function renderKanban(grid, list) {
    grid.className = 'stage-board';
    grid.innerHTML = KANBAN_COLUMNS.map(function (col) {
      var colTasks = list.filter(function (t) { return t.status === col.status; });
      var rowsHtml = colTasks.length
        ? colTasks.map(trackerRowHtml).join('')
        : '<div class="stage-empty">No styles at this stage</div>';
      return '<section class="stage-group ' + col.status + '" data-status="' + col.status + '">' +
        '<header class="stage-head">' +
          '<span class="stage-dot"></span>' +
          '<h3 class="stage-title">' + col.label + '</h3>' +
          '<span class="stage-count">' + colTasks.length + '</span>' +
        '</header>' +
        '<div class="stage-rows">' + rowsHtml + '</div>' +
      '</section>';
    }).join('');

    wireTrackerRows(grid);

    Array.prototype.forEach.call(grid.querySelectorAll('.stage-group'), function (group) {
      group.addEventListener('dragover', function (e) {
        e.preventDefault();
        e.dataTransfer.dropEffect = 'move';
        group.classList.add('drag-over');
      });
      group.addEventListener('dragleave', function () { group.classList.remove('drag-over'); });
      group.addEventListener('drop', function (e) {
        e.preventDefault();
        group.classList.remove('drag-over');
        var id = parseInt(e.dataTransfer.getData('text/plain'), 10);
        var newStatus = group.getAttribute('data-status');
        var t = tasks.find(function (x) { return x.id === id; });
        if (!t || t.status === newStatus) return;
        changeStatus(id, newStatus);
      });
    });
  }

  function cardHtml(t) {
    var thumb = (t.images && t.images.length) ?
      '<img class="thumb" src="' + t.images[0].url + '" alt="">' :
      '<div class="thumb-placeholder"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 4h16v3H4z"/><path d="M6 7v13h12V7"/></svg></div>';
    var pdot = PRIORITY_COLOR[t.priority] ? '<span class="priority-dot" style="background:' + PRIORITY_COLOR[t.priority] + '" title="Priority: ' + PRIORITY_LABEL[t.priority] + '"></span>' : '';
    var sub = [t.buyer, t.style].filter(Boolean).join(' • ') || '—';
    var overdue = isOverdue(t);
    var deadlineLine = t.deadline ?
      '<span class="meta-line' + (overdue ? ' overdue' : '') + '">📅 ' + fmtDate(t.deadline) + (overdue ? ' (overdue)' : '') + '</span>' :
      '<span class="meta-line">📅 No deadline</span>';
    var deptChip = t.department ? '<span class="dept-chip">' + escapeHtml(t.department) + '</span>' : '';
    var sampleChip = t.sample_qty != null ? '<span class="dept-chip">🧵 ' + t.sample_qty + '</span>' : '';
    var commentChip = t.comment_count ? '<span class="comment-count">💬 ' + t.comment_count + '</span>' : '';
    return (
      '<div class="card" data-id="' + t.id + '">' +
        '<div class="card-top">' + thumb +
          '<div class="card-title-block">' +
            '<p class="card-title">' + escapeHtml(t.title || '(Untitled)') + '</p>' +
            '<p class="card-sub">' + escapeHtml(sub) + '</p>' +
          '</div>' +
          pdot +
        '</div>' +
        '<div class="card-bottom">' +
          '<span class="pill pill-' + t.status + '">' + STATUS_LABEL[t.status] + '</span>' +
          deptChip + sampleChip +
        '</div>' +
        '<div class="card-bottom">' + deadlineLine + commentChip + '</div>' +
      '</div>'
    );
  }

  ['searchInput', 'priorityFilter', 'buyerFilter', 'seasonFilter', 'periodBasis', 'periodFrom', 'periodTo'].forEach(function (id) {
    $(id).addEventListener('input', renderGrid);
    $(id).addEventListener('change', renderGrid);
  });

  $('periodFilter').addEventListener('change', function () {
    $('customRange').hidden = $('periodFilter').value !== 'custom';
    renderGrid();
  });

  var navButtons = Array.prototype.slice.call(document.querySelectorAll('.side-nav-item'));
  navButtons.forEach(function (btn) {
    btn.addEventListener('click', function () {
      navButtons.forEach(function (b) { b.classList.remove('active'); });
      btn.classList.add('active');
      setSidebar(false);
      var kind = btn.getAttribute('data-nav');
      activeTaskId = null;
      $('formView').hidden = true;
      $('reportsView').hidden = true;
      if (IS_ADMIN) $('usersView').hidden = true;

      if (kind === 'reports') {
        activeView = 'reports';
        $('detailView').hidden = true;
        $('listView').hidden = true;
        $('buyersView').hidden = true;
        $('reportsView').hidden = false;
        renderReports();
        updatePageHead();
        return;
      }
      if (kind === 'users') {
        activeView = 'users';
        $('detailView').hidden = true;
        $('listView').hidden = true;
        $('buyersView').hidden = true;
        $('usersView').hidden = false;
        loadUsers();
        updatePageHead();
        return;
      }
      if (kind === 'buyers') {
        activeView = 'buyers';
        $('detailView').hidden = true;
        $('listView').hidden = true;
        $('buyersView').hidden = false;
        resetBuyerForm();
        loadBuyers();
        updatePageHead();
        return;
      }
      $('buyersView').hidden = true;
      $('detailView').hidden = true;
      $('listView').hidden = false;
      if (kind === 'status') {
        activeView = 'list';
        activeStatus = btn.getAttribute('data-status');
        activeOverdueOnly = false;
      } else if (kind === 'overdue') {
        activeView = 'list';
        activeStatus = 'all';
        activeOverdueOnly = true;
      } else if (kind === 'tracker') {
        activeView = 'tracker';
        activeStatus = 'all';
        activeOverdueOnly = false;
      } else {
        activeView = 'board';
        activeStatus = 'all';
        activeOverdueOnly = false;
      }
      updatePageHead();
      renderGrid();
    });
  });

  var PAGE_HEADINGS = {
    tracker: ['Progress Tracker', 'Every style and the stage it is sitting at right now'],
    board: ['Task Board', 'Drag a card between columns to move it to the next stage'],
    overdue: ['Overdue Tasks', 'Past their deadline and not finished yet'],
    buyers: ['Buyers', 'Save your buyers once, then pick them while creating a style'],
    users: ['Users & Access', 'Nobody can see this data until you approve their account'],
    reports: ['Reports', 'How the work has moved — follows the filters you set on the tracker'],
  };

  function updatePageHead() {
    var head;
    if (activeView === 'reports') {
      head = PAGE_HEADINGS.reports;
    } else if (activeView === 'users') {
      head = PAGE_HEADINGS.users;
    } else if (activeView === 'buyers') {
      head = PAGE_HEADINGS.buyers;
    } else if (activeView === 'tracker') {
      head = PAGE_HEADINGS.tracker;
    } else if (activeView === 'board') {
      head = PAGE_HEADINGS.board;
    } else if (activeOverdueOnly) {
      head = PAGE_HEADINGS.overdue;
    } else {
      head = [STATUS_LABEL[activeStatus] + ' Tasks', 'Everything currently at the ' + STATUS_LABEL[activeStatus] + ' stage'];
    }
    $('pageTitle').textContent = head[0];
    $('pageSub').textContent = head[1];
  }

  function activateNav(selector) {
    var target = document.querySelector(selector);
    if (target) target.click();
  }

  Array.prototype.forEach.call(document.querySelectorAll('.stat-card'), function (card) {
    card.addEventListener('click', function () {
      var jump = card.getAttribute('data-jump');
      if (jump === 'tracker') {
        activateNav('.side-nav-item[data-nav="tracker"]');
      } else if (jump === 'overdue') {
        activateNav('.side-nav-item[data-nav="overdue"]');
      } else {
        activateNav('.side-nav-item[data-status="' + jump + '"]');
      }
    });
  });

  // ---------- add/edit form ----------
  /** Form input id -> task field. Mirrors the Sample Tracking List columns. */
  var SHEET_INPUTS = [
    { id: 'fTitle', field: 'title' },
    { id: 'fSeason', field: 'season' },
    { id: 'fDept', field: 'dept' },
    { id: 'fBuyer', field: 'buyer' },
    { id: 'fThread', field: 'thread' },
    { id: 'fSpec', field: 'spec' },
    { id: 'fButtonRivet', field: 'button_rivet' },
    { id: 'fWashDetail', field: 'wash_detail' },
    { id: 'fFabArt', field: 'fab_art' },
    { id: 'fSampleType', field: 'sample_type' },
    { id: 'fSampleQty', field: 'sample_qty' },
    { id: 'fReceivedAt', field: 'received_at' },
    { id: 'fTechpackHandover', field: 'techpack_handover_date' },
    { id: 'fCuttingStatus', field: 'cutting_status' },
    { id: 'fSewingStatus', field: 'sewing_status' },
    { id: 'fWashSendDate', field: 'wash_send_date' },
    { id: 'fWashRcvdDate', field: 'wash_rcvd_date' },
    { id: 'fSampleSubmitDate', field: 'sample_submit_date' },
    { id: 'fPriceNote', field: 'price_note' },
    { id: 'fBookFabric', field: 'booking_fabric' },
    { id: 'fBookBodyThread', field: 'booking_body_thread' },
    { id: 'fBookEmbThread', field: 'booking_emb_thread' },
    { id: 'fBookMetalwork', field: 'booking_metalwork' },
    { id: 'fBookLace', field: 'booking_lace' },
    { id: 'fRemarks', field: 'remarks' },
    { id: 'fDeadline', field: 'deadline' },
    { id: 'fDepartment', field: 'department' },
  ];

  var formImages = []; // {id: number|null, url: string, file: File|null, uploading: bool}

  function resetForm() {
    $('taskForm').reset();
    $('fPriority').value = 'medium';
    $('fStatus').value = 'new';
    editingTaskId = null;
    formImages = [];
    renderFormImages();
    setDescriptionHtml('');
  }

  function showFormView(titleText, subText) {
    $('listView').hidden = true;
    $('detailView').hidden = true;
    $('buyersView').hidden = true;
    $('formView').hidden = false;
    $('pageTitle').textContent = titleText;
    $('pageSub').textContent = subText;
    window.scrollTo(0, 0);
  }

  function openAddModal() {
    resetForm();
    showFormView('New Task', 'Fill in what you know now — you can always come back and edit');
    // preventScroll keeps focus from yanking the page back down
    $('fTitle').focus({ preventScroll: true });
  }

  function openEditModal(t) {
    resetForm();
    editingTaskId = t.id;
    SHEET_INPUTS.forEach(function (map) {
      var el = $(map.id);
      if (el) el.value = t[map.field] != null ? t[map.field] : '';
    });
    $('fStatus').value = t.status || 'new';
    $('fRowState').value = t.row_state || 'active';
    $('fPriority').value = t.priority || 'medium';
    setDescriptionHtml(t.description || '');
    formImages = (t.images || []).map(function (img) {
      return { id: img.id, url: img.url, name: '', file: null, uploading: false };
    });
    renderFormImages();
    showFormView('Edit Task', t.title || 'Update this style');
  }

  function renderFormImages() {
    var host = $('formImgPreviews');
    host.innerHTML = '';
    formImages.forEach(function (entry, idx) {
      host.appendChild(buildPreviewTile(entry, function () {
        if (entry.id) {
          api('/api/images/' + entry.id, { method: 'DELETE' }).then(function () {
            formImages.splice(idx, 1);
            renderFormImages();
            loadTasks();
          }).catch(function (err) {
            showBanner('Could not remove photo: ' + errorMessage(err));
          });
        } else {
          if (entry.file) URL.revokeObjectURL(entry.url);
          formImages.splice(idx, 1);
          renderFormImages();
        }
      }));
    });
  }

  $('formImageInput').addEventListener('change', function (e) {
    var files = Array.prototype.slice.call(e.target.files || []);
    e.target.value = '';
    if (!files.length) return;

    var rejected = [];
    var accepted = [];
    files.forEach(function (file) {
      var problem = validateImageFile(file);
      if (problem) { rejected.push(problem); return; }
      accepted.push(file);
    });

    if (editingTaskId) {
      accepted.forEach(function (file) {
        var entry = { id: null, url: URL.createObjectURL(file), name: file.name, file: null, uploading: true };
        formImages.push(entry);
        renderFormImages();
        var fd = new FormData();
        fd.append('image', file);
        api('/api/tasks/' + editingTaskId + '/images', { method: 'POST', body: fd }).then(function (res) {
          URL.revokeObjectURL(entry.url);
          entry.id = res.id;
          entry.url = res.url;
          entry.uploading = false;
          renderFormImages();
          loadTasks();
        }).catch(function (err) {
          var i = formImages.indexOf(entry);
          if (i >= 0) formImages.splice(i, 1);
          renderFormImages();
          showBanner('Photo upload failed: ' + errorMessage(err));
        });
      });
    } else {
      accepted.forEach(function (file) {
        formImages.push({ id: null, url: URL.createObjectURL(file), name: file.name, file: file, uploading: false });
      });
      renderFormImages();
    }

    if (rejected.length) showBanner(rejected[0]);
  });

  // ---------- reports ----------
  // Single-hue sequential ramp: more is darker. Identity always comes from the
  // written label beside the mark, never from the colour alone.
  var RAMP = ['#C7D2FE', '#A5B4FC', '#818CF8', '#6366F1', '#4F46E5', '#4338CA'];
  var INK_MUTED = '#6B7490';
  var GRID = '#E4E8F0';

  function rampStep(value, max) {
    if (!max) return RAMP[2];
    var idx = Math.round((value / max) * (RAMP.length - 1));
    return RAMP[Math.max(0, Math.min(RAMP.length - 1, idx))];
  }

  function svgEl(tag, attrs, text) {
    var parts = Object.keys(attrs).map(function (k) {
      return k + '="' + String(attrs[k]).replace(/"/g, '&quot;') + '"';
    }).join(' ');
    return '<' + tag + ' ' + parts + '>' + (text !== undefined ? escapeHtml(text) : '') +
      (text !== undefined ? '</' + tag + '>' : '</' + tag + '>');
  }

  function chartEmpty(host, message) {
    host.innerHTML = '<p class="chart-empty">' + escapeHtml(message) + '</p>';
  }

  /**
   * Vertical columns — monthly counts over time.
   * The viewBox matches the container's pixel width so label sizes stay true
   * instead of shrinking with the chart on small screens.
   */
  function measureBox(host, fallback, cap) {
    var w = Math.round(host.getBoundingClientRect().width) || fallback;
    return Math.max(280, Math.min(w, cap));
  }

  function renderColumnChart(host, rows) {
    if (!rows.length) { chartEmpty(host, 'No dated styles in this period yet.'); return; }

    var W = measureBox(host, 720, 960);
    if (W < 560 && rows.length > 6) rows = rows.slice(-6);

    var H = W < 480 ? 200 : 240, padL = 40, padR = 10, padT = 18, padB = 40;
    var plotW = W - padL - padR, plotH = H - padT - padB;
    var max = Math.max.apply(null, rows.map(function (r) { return r.value; }));
    var niceMax = Math.max(1, Math.ceil(max / 4) * 4);
    var band = plotW / rows.length;
    var barW = Math.min(24, band - 10);

    var svg = ['<svg viewBox="0 0 ' + W + ' ' + H + '" width="' + W + '" height="' + H +
      '" class="chart-svg" role="img" aria-label="Tech packs received per month">'];

    for (var t = 0; t <= 4; t++) {
      var val = (niceMax / 4) * t;
      var y = padT + plotH - (val / niceMax) * plotH;
      svg.push(svgEl('line', { x1: padL, y1: y, x2: W - padR, y2: y, stroke: GRID, 'stroke-width': 1 }));
      svg.push(svgEl('text', { x: padL - 9, y: y + 4, 'text-anchor': 'end', fill: INK_MUTED, 'font-size': 11, 'font-weight': 600 }, String(Math.round(val))));
    }

    rows.forEach(function (r, i) {
      var h = niceMax ? (r.value / niceMax) * plotH : 0;
      var x = padL + i * band + (band - barW) / 2;
      var y = padT + plotH - h;
      if (r.value > 0) {
        svg.push(svgEl('rect', {
          x: x, y: y, width: barW, height: Math.max(h, 3), rx: 4,
          fill: rampStep(r.value, max), class: 'chart-mark', 'data-tip': r.label + ': ' + r.value + ' styles',
        }));
        svg.push(svgEl('text', { x: x + barW / 2, y: y - 7, 'text-anchor': 'middle', fill: INK_MUTED, 'font-size': 11, 'font-weight': 700 }, String(r.value)));
      }
      svg.push(svgEl('text', { x: x + barW / 2, y: H - 14, 'text-anchor': 'middle', fill: INK_MUTED, 'font-size': 11, 'font-weight': 600 }, r.short));
    });

    svg.push('</svg>');
    host.innerHTML = svg.join('');
    wireChartTips(host);
  }

  /** Horizontal bars — labelled rows, longest first. */
  function renderBarChart(host, rows, unitLabel, emptyMsg) {
    if (!rows.length) { chartEmpty(host, emptyMsg); return; }

    var W = measureBox(host, 520, 620);
    var rowH = 36, padL = 4, padR = W < 420 ? 42 : 54;
    var labelW = W < 420 ? 104 : 132;
    var H = rows.length * rowH + 8;
    var plotW = Math.max(40, W - labelW - padR - padL);
    var max = Math.max.apply(null, rows.map(function (r) { return r.value; }));

    var svg = ['<svg viewBox="0 0 ' + W + ' ' + H + '" width="' + W + '" height="' + H +
      '" class="chart-svg" role="img" aria-label="' + escapeHtml(unitLabel) + '">'];

    rows.forEach(function (r, i) {
      var y = i * rowH + 6;
      var w = max ? Math.max((r.value / max) * plotW, r.value > 0 ? 4 : 0) : 0;
      var barY = y + (rowH - 22) / 2 - 3;

      if (r.dot) {
        svg.push(svgEl('circle', { cx: padL + 6, cy: barY + 11, r: 4, fill: r.dot }));
      }
      svg.push(svgEl('text', {
        x: padL + (r.dot ? 18 : 0), y: barY + 15, fill: '#1B2333', 'font-size': 12.5, 'font-weight': 700,
      }, r.label));

      if (r.value > 0) {
        svg.push(svgEl('rect', {
          x: labelW, y: barY, width: w, height: 22, rx: 4,
          fill: rampStep(r.value, max), class: 'chart-mark', 'data-tip': r.label + ': ' + r.tip,
        }));
      }
      svg.push(svgEl('text', {
        x: labelW + w + 9, y: barY + 15, fill: INK_MUTED, 'font-size': 12, 'font-weight': 700,
      }, r.valueLabel));
    });

    svg.push('</svg>');
    host.innerHTML = svg.join('');
    wireChartTips(host);
  }

  function wireChartTips(host) {
    var tip = document.createElement('div');
    tip.className = 'chart-tip';
    tip.hidden = true;
    host.appendChild(tip);

    Array.prototype.forEach.call(host.querySelectorAll('.chart-mark'), function (mark) {
      mark.addEventListener('mouseenter', function () {
        tip.textContent = mark.getAttribute('data-tip');
        tip.hidden = false;
      });
      mark.addEventListener('mousemove', function (e) {
        var box = host.getBoundingClientRect();
        tip.style.left = (e.clientX - box.left) + 'px';
        tip.style.top = (e.clientY - box.top - 12) + 'px';
      });
      mark.addEventListener('mouseleave', function () { tip.hidden = true; });
    });
  }

  var MONTH_SHORT = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

  function renderReports() {
    var list = applySoftFilters(tasks);

    // Hero figures
    var active = 0, submitted = 0, held = 0;
    list.forEach(function (t) {
      if (t.status !== 'done' && t.status !== 'cancelled') active++;
      if (t.sample_submit_date) submitted++;
      if (t.row_state && t.row_state !== 'active') held++;
    });
    $('reportHero').innerHTML =
      '<div class="hero-tile"><span class="hero-label">Styles in view</span><span class="hero-num">' + list.length + '</span></div>' +
      '<div class="hero-tile"><span class="hero-label">Still open</span><span class="hero-num">' + active + '</span></div>' +
      '<div class="hero-tile"><span class="hero-label">Samples submitted</span><span class="hero-num">' + submitted + '</span></div>' +
      '<div class="hero-tile"><span class="hero-label">Hold / Drop</span><span class="hero-num">' + held + '</span></div>';

    // Monthly intake — falls back to entry date when no received date was filled in
    var basis = $('periodBasis').value;
    var months = [];
    var now = new Date();
    for (var i = 11; i >= 0; i--) {
      var d = new Date(now.getFullYear(), now.getMonth() - i, 1);
      months.push({
        key: d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0'),
        label: MONTH_SHORT[d.getMonth()] + ' ' + d.getFullYear(),
        short: MONTH_SHORT[d.getMonth()],
        value: 0,
      });
    }
    var byKey = {};
    months.forEach(function (m) { byKey[m.key] = m; });
    list.forEach(function (t) {
      var d = taskDateFor(t, basis) || t.received_at || (t.created_at || '').slice(0, 10);
      if (!d) return;
      var bucket = byKey[d.slice(0, 7)];
      if (bucket) bucket.value++;
    });
    $('chartIntakeSub').textContent = 'Last 12 months · counted by ' +
      $('periodBasis').options[$('periodBasis').selectedIndex].text.replace('by ', '').toLowerCase();
    renderColumnChart($('chartIntake'), months);

    // Pipeline — ordered stages, single hue, stage dot carries the app's colour language
    var STAGE_DOTS = {
      new: '#6B7490', in_progress: '#4F46E5', waiting: '#D08700', done: '#17A673', cancelled: '#E05561',
    };
    var pipeline = ['new', 'in_progress', 'waiting', 'done', 'cancelled'].map(function (s) {
      var count = list.filter(function (t) { return t.status === s; }).length;
      return {
        label: STATUS_LABEL[s], value: count, valueLabel: String(count),
        tip: count + ' style' + (count === 1 ? '' : 's'), dot: STAGE_DOTS[s],
      };
    });
    renderBarChart($('chartPipeline'), pipeline, 'Styles at each stage', 'Nothing to show yet.');

    // Top buyers
    var counts = {};
    list.forEach(function (t) {
      var name = (t.buyer || '').trim();
      if (!name) return;
      counts[name] = (counts[name] || 0) + 1;
    });
    var buyerRows = Object.keys(counts).map(function (name) {
      return {
        label: name.length > 16 ? name.slice(0, 16) + '…' : name,
        value: counts[name], valueLabel: String(counts[name]),
        tip: counts[name] + ' style' + (counts[name] === 1 ? '' : 's'),
      };
    }).sort(function (a, b) { return b.value - a.value; }).slice(0, 8);
    renderBarChart($('chartBuyers'), buyerRows, 'Styles per buyer', 'No buyer has been set on a style yet.');
  }

  var reportResizeTimer = null;
  window.addEventListener('resize', function () {
    if ($('reportsView').hidden) return;
    clearTimeout(reportResizeTimer);
    reportResizeTimer = setTimeout(renderReports, 180);
  });

  // ---------- users & access (admin only) ----------
  var IS_ADMIN = !!$('usersView');

  function loadUsers() {
    if (!IS_ADMIN) return Promise.resolve();
    return api('/api/users').then(function (data) {
      renderUsers(data.users || []);
      var pending = data.pending || 0;
      $('navCountUsers').textContent = pending;
      $('navCountUsers').classList.toggle('danger', pending > 0);
    }).catch(function (err) {
      showBanner('Could not load users: ' + errorMessage(err));
    });
  }

  function userActionButton(label, cls, action, id) {
    return '<button type="button" class="track-btn ' + cls + '" data-user-act="' + action + '" data-id="' + id + '">' + label + '</button>';
  }

  function renderUsers(users) {
    var pending = users.filter(function (u) { return !u.is_approved; });
    var approved = users.filter(function (u) { return u.is_approved; });

    $('pendingUsersBody').innerHTML = pending.length
      ? pending.map(function (u) {
        return '<tr>' +
          '<td data-label="Name"><div class="cell-main">' + escapeHtml(u.name) + '</div></td>' +
          '<td data-label="Email">' + escapeHtml(u.email) + '</td>' +
          '<td data-label="Requested"><span class="cell-sub">' + escapeHtml(u.created_at || '') + '</span></td>' +
          '<td class="right"><div class="row-actions">' +
            userActionButton('✅ Approve', 'success', 'approve', u.id) +
            userActionButton('🗑 Reject', 'warn', 'delete', u.id) +
          '</div></td>' +
        '</tr>';
      }).join('')
      : '<tr><td colspan="4" class="table-empty">No one is waiting for approval.</td></tr>';

    $('approvedUsersBody').innerHTML = approved.length
      ? approved.map(function (u) {
        var roleBadge = u.role === 'admin'
          ? '<span class="pill pill-in_progress">Admin</span>'
          : '<span class="pill pill-new">Member</span>';
        var roleAction = u.role === 'admin'
          ? userActionButton('↓ Make Member', '', 'make_member', u.id)
          : userActionButton('↑ Make Admin', '', 'make_admin', u.id);
        return '<tr>' +
          '<td data-label="Name"><div class="cell-main">' + escapeHtml(u.name) + '</div></td>' +
          '<td data-label="Email">' + escapeHtml(u.email) + '</td>' +
          '<td data-label="Role">' + roleBadge + '</td>' +
          '<td data-label="Approved"><span class="cell-sub">' + escapeHtml(u.approved_at || '—') + '</span></td>' +
          '<td class="right"><div class="row-actions">' +
            roleAction +
            userActionButton('⛔ Revoke', 'warn', 'revoke', u.id) +
            userActionButton('🗑 Delete', 'warn', 'delete', u.id) +
          '</div></td>' +
        '</tr>';
      }).join('')
      : '<tr><td colspan="5" class="table-empty">No approved users yet.</td></tr>';

    wireUserActions();
  }

  function wireUserActions() {
    Array.prototype.forEach.call(document.querySelectorAll('[data-user-act]'), function (btn) {
      btn.addEventListener('click', function () {
        var id = btn.getAttribute('data-id');
        var act = btn.getAttribute('data-user-act');
        var request;

        if (act === 'approve') {
          request = api('/api/users/' + id + '/approve', { method: 'PATCH' });
        } else if (act === 'revoke') {
          if (!confirm('Revoke access? This person will not be able to log in until approved again.')) return;
          request = api('/api/users/' + id + '/revoke', { method: 'PATCH' });
        } else if (act === 'make_admin') {
          if (!confirm('Make this person an admin? They will be able to approve and remove other users.')) return;
          request = api('/api/users/' + id + '/role', { method: 'PATCH', json: { role: 'admin' } });
        } else if (act === 'make_member') {
          request = api('/api/users/' + id + '/role', { method: 'PATCH', json: { role: 'member' } });
        } else if (act === 'delete') {
          if (!confirm('Delete this account permanently?')) return;
          request = api('/api/users/' + id, { method: 'DELETE' });
        }
        if (!request) return;

        btn.disabled = true;
        request.then(function () {
          showSuccess('Access updated.');
          return loadUsers();
        }).catch(function (err) {
          btn.disabled = false;
          showBanner(errorMessage(err));
        });
      });
    });
  }

  // ---------- buyers (master data) ----------
  var buyers = [];
  var editingBuyerId = null;

  function loadBuyers() {
    return api('/api/buyers').then(function (data) {
      buyers = data.buyers || [];
      $('navCountBuyers').textContent = buyers.length;
      renderBuyersTable();
      populateBuyerFilter();
    }).catch(function (err) {
      showBanner('Could not load buyers: ' + errorMessage(err));
    });
  }

  function renderBuyersTable() {
    var body = $('buyersTableBody');
    if (!buyers.length) {
      body.innerHTML = '<tr><td colspan="5" class="table-empty">No buyers saved yet. Add your first one above.</td></tr>';
      return;
    }
    body.innerHTML = buyers.map(function (b) {
      var contact = [b.contact_person, b.email, b.phone].filter(Boolean);
      var notesPreview = '';
      if (b.notes && !isHtmlEmpty(b.notes)) {
        var plain = b.notes.replace(/<[^>]*>/g, ' ').replace(/&nbsp;/g, ' ').replace(/\s+/g, ' ').trim();
        notesPreview = '<div class="cell-note" title="' + escapeHtml(plain) + '">📝 ' +
          escapeHtml(plain.length > 60 ? plain.slice(0, 60) + '…' : plain) + '</div>';
      }
      return '<tr>' +
        '<td data-label="Buyer"><div class="cell-main">' + escapeHtml(b.name) + '</div>' +
          (b.brand ? '<div class="cell-sub">' + escapeHtml(b.brand) + '</div>' : '') +
          notesPreview + '</td>' +
        '<td data-label="Contact">' + (contact.length
          ? '<div class="cell-main">' + escapeHtml(contact[0]) + '</div>' +
            (contact[1] ? '<div class="cell-sub">' + escapeHtml(contact[1]) + '</div>' : '')
          : '<span class="cell-sub">—</span>') + '</td>' +
        '<td data-label="Country">' + (b.country ? escapeHtml(b.country) : '<span class="cell-sub">—</span>') + '</td>' +
        '<td class="num" data-label="Styles">' + b.task_count + '</td>' +
        '<td class="right"><div class="row-actions">' +
          '<button type="button" class="track-btn" data-buyer-edit="' + b.id + '">Edit</button>' +
          '<button type="button" class="track-btn warn" data-buyer-del="' + b.id + '">Delete</button>' +
        '</div></td>' +
      '</tr>';
    }).join('');

    Array.prototype.forEach.call(body.querySelectorAll('[data-buyer-edit]'), function (btn) {
      btn.addEventListener('click', function () {
        var b = buyers.find(function (x) { return x.id === parseInt(btn.getAttribute('data-buyer-edit'), 10); });
        if (b) startBuyerEdit(b);
      });
    });
    Array.prototype.forEach.call(body.querySelectorAll('[data-buyer-del]'), function (btn) {
      btn.addEventListener('click', function () {
        var id = parseInt(btn.getAttribute('data-buyer-del'), 10);
        var b = buyers.find(function (x) { return x.id === id; });
        if (!b) return;
        var warn = b.task_count
          ? 'Delete "' + b.name + '"? ' + b.task_count + ' style(s) already use this name — they will keep it, but it will leave the picker list.'
          : 'Delete "' + b.name + '"?';
        if (!confirm(warn)) return;
        btn.disabled = true;
        api('/api/buyers/' + id, { method: 'DELETE' }).then(function () {
          showSuccess('Buyer deleted.');
          return loadBuyers();
        }).catch(function (err) {
          btn.disabled = false;
          showBanner('Could not delete buyer: ' + errorMessage(err));
        });
      });
    });
  }

  function buyerFormValues() {
    return {
      name: $('bName').value.trim(),
      brand: $('bBrand').value.trim(),
      contact_person: $('bContact').value.trim(),
      email: $('bEmail').value.trim(),
      phone: $('bPhone').value.trim(),
      country: $('bCountry').value.trim(),
      notes: getBuyerNotesHtml(),
    };
  }

  function resetBuyerForm() {
    $('buyerForm').reset();
    setBuyerNotesHtml('');
    editingBuyerId = null;
    $('buyerFormTitle').textContent = 'Add a Buyer';
    $('buyerSaveBtn').textContent = 'Save Buyer';
    $('buyerResetBtn').hidden = true;
  }

  function startBuyerEdit(b) {
    editingBuyerId = b.id;
    $('bName').value = b.name || '';
    $('bBrand').value = b.brand || '';
    $('bContact').value = b.contact_person || '';
    $('bEmail').value = b.email || '';
    $('bPhone').value = b.phone || '';
    $('bCountry').value = b.country || '';
    setBuyerNotesHtml(b.notes || '');
    $('buyerFormTitle').textContent = 'Edit Buyer';
    $('buyerSaveBtn').textContent = 'Update Buyer';
    $('buyerResetBtn').hidden = false;
    window.scrollTo(0, 0);
  }

  $('buyerResetBtn').addEventListener('click', resetBuyerForm);

  $('buyerForm').addEventListener('submit', function (e) {
    e.preventDefault();
    var payload = buyerFormValues();
    if (!payload.name) return;
    $('buyerSaveBtn').disabled = true;

    var req = editingBuyerId
      ? api('/api/buyers/' + editingBuyerId, { method: 'PUT', json: payload })
      : api('/api/buyers', { method: 'POST', json: payload });

    req.then(function () {
      showSuccess(editingBuyerId ? 'Buyer updated.' : 'Buyer added.');
      $('buyerSaveBtn').disabled = false;
      resetBuyerForm();
      return Promise.all([loadBuyers(), loadTasks()]);
    }).catch(function (err) {
      $('buyerSaveBtn').disabled = false;
      showBanner('Could not save buyer: ' + errorMessage(err));
    });
  });

  // ---------- buyer picker on the task form ----------
  function renderBuyerOptions(filterText) {
    var list = $('buyerComboList');
    var q = (filterText || '').trim().toLowerCase();
    var matches = buyers.filter(function (b) { return !q || b.name.toLowerCase().indexOf(q) !== -1; });
    if (!matches.length) { list.hidden = true; return; }
    list.innerHTML = matches.map(function (b) {
      return '<div class="combo-item" data-value="' + escapeHtml(b.name) + '">' + escapeHtml(b.name) +
        (b.brand ? '<span class="combo-sub">' + escapeHtml(b.brand) + '</span>' : '') + '</div>';
    }).join('');
    list.hidden = false;
    Array.prototype.forEach.call(list.querySelectorAll('.combo-item'), function (item) {
      item.addEventListener('mousedown', function (ev) {
        ev.preventDefault();
        $('fBuyer').value = item.getAttribute('data-value');
        list.hidden = true;
      });
    });
  }

  $('fBuyer').addEventListener('focus', function () { renderBuyerOptions($('fBuyer').value); });
  $('fBuyer').addEventListener('input', function () { renderBuyerOptions($('fBuyer').value); });
  $('fBuyer').addEventListener('blur', function () { $('buyerComboList').hidden = true; });

  // ---------- department combo box ----------
  var DEPARTMENTS = ['Pattern', 'Sample', 'Washing', 'Embroidery/Print', 'Quality', 'Costing', 'Buyer Submission'];

  function renderDeptOptions(filterText) {
    var list = $('deptComboList');
    var q = (filterText || '').trim().toLowerCase();
    var matches = DEPARTMENTS.filter(function (d) { return !q || d.toLowerCase().indexOf(q) !== -1; });
    if (matches.length === 0) { list.hidden = true; return; }
    list.innerHTML = matches.map(function (d) {
      return '<div class="combo-item" data-value="' + escapeHtml(d) + '">' + escapeHtml(d) + '</div>';
    }).join('');
    list.hidden = false;
    Array.prototype.forEach.call(list.querySelectorAll('.combo-item'), function (item) {
      item.addEventListener('mousedown', function (e) {
        e.preventDefault();
        $('fDepartment').value = item.getAttribute('data-value');
        list.hidden = true;
      });
    });
  }

  $('fDepartment').addEventListener('focus', function () { renderDeptOptions($('fDepartment').value); });
  $('fDepartment').addEventListener('input', function () { renderDeptOptions($('fDepartment').value); });
  $('fDepartment').addEventListener('blur', function () { $('deptComboList').hidden = true; });

  function closeTaskModal() {
    $('formView').hidden = true;
    if (activeTaskId) {
      openDetail(activeTaskId);
    } else {
      $('listView').hidden = false;
      updatePageHead();
    }
  }
  // ---------- mobile sidebar drawer ----------
  function setSidebar(open) {
    $('sidebar').classList.toggle('open', open);
    $('sidebarBackdrop').hidden = !open;
  }
  $('sidebarToggle').addEventListener('click', function (e) {
    e.stopPropagation();
    setSidebar(!$('sidebar').classList.contains('open'));
  });
  $('sidebarBackdrop').addEventListener('click', function () { setSidebar(false); });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') setSidebar(false);
  });

  $('exportMenuBtn').addEventListener('click', function (e) {
    e.stopPropagation();
    $('exportMenuList').hidden = !$('exportMenuList').hidden;
  });
  document.addEventListener('click', function () { $('exportMenuList').hidden = true; });

  $('addTaskBtn').addEventListener('click', function () {
    activeTaskId = null;
    openAddModal();
  });
  $('taskCancelBtn').addEventListener('click', closeTaskModal);
  $('taskCancelBtn2').addEventListener('click', closeTaskModal);

  $('taskForm').addEventListener('submit', function (e) {
    e.preventDefault();
    var title = $('fTitle').value.trim();
    if (!title) return;
    var saveBtn = $('taskSaveBtn');
    saveBtn.disabled = true;

    var DATE_FIELDS = ['received_at', 'techpack_handover_date', 'wash_send_date',
      'wash_rcvd_date', 'sample_submit_date', 'deadline'];

    var payload = {
      status: $('fStatus').value,
      priority: $('fPriority').value,
      row_state: $('fRowState').value,
      description: getDescriptionHtml(),
    };

    SHEET_INPUTS.forEach(function (map) {
      var el = $(map.id);
      if (!el) return;
      var raw = el.value.trim();
      if (map.field === 'sample_qty') {
        payload[map.field] = raw === '' ? null : parseInt(raw, 10);
      } else if (DATE_FIELDS.indexOf(map.field) !== -1) {
        payload[map.field] = raw || null;
      } else {
        payload[map.field] = raw;
      }
    });

    var wasNew = !editingTaskId;
    var req = editingTaskId
      ? api('/api/tasks/' + editingTaskId, { method: 'PUT', json: payload })
      : api('/api/tasks', { method: 'POST', json: payload });

    req.then(function (res) {
      var staged = wasNew ? formImages.filter(function (img) { return img.file; }) : [];
      var uploads = staged.map(function (img) {
        var fd = new FormData();
        fd.append('image', img.file);
        return api('/api/tasks/' + res.id + '/images', { method: 'POST', body: fd });
      });
      return Promise.allSettled(uploads).then(function () {
        saveBtn.disabled = false;
        closeTaskModal();
        showSuccess(wasNew ? 'Task created.' : 'Task updated.');
        return loadTasks().then(function () {
          if (wasNew) { openDetail(res.id); }
        });
      });
    }).catch(function (err) {
      saveBtn.disabled = false;
      showBanner('Could not save: ' + errorMessage(err));
    });
  });

  // ---------- detail modal ----------
  function specRow(label, value) {
    if (value === null || value === undefined || value === '') return '';
    return '<div class="spec-item"><span class="spec-label">' + label + '</span><span class="spec-value">' + value + '</span></div>';
  }

  function money(n) {
    if (n === null || n === undefined) return '';
    return '$' + Number(n).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  }

  function openDetail(id) {
    var t = tasks.find(function (x) { return x.id === id; });
    if (!t) return;
    activeTaskId = id;
    $('detailTitle').textContent = t.title || '(Untitled)';
    var overdue = isOverdue(t);
    var metaParts = [];
    metaParts.push('<span class="pill pill-' + t.status + '">' + STATUS_LABEL[t.status] + '</span>');
    if (t.priority) metaParts.push('<span class="pill" style="background:var(--surface-2);color:var(--text-muted);">Priority: ' + PRIORITY_LABEL[t.priority] + '</span>');
    if (t.sample_stage_label) metaParts.push('<span class="pill" style="background:var(--accent-soft);color:var(--accent);">' + escapeHtml(t.sample_stage_label) + '</span>');
    if (overdue) metaParts.push('<span class="pill pill-cancelled">Overdue</span>');
    $('detailMeta').innerHTML = metaParts.join('');

    $('detailSpecs').innerHTML = [
      specRow('Buyer', escapeHtml(t.buyer || '')),
      specRow('Style No.', escapeHtml(t.style || '')),
      specRow('Season', escapeHtml(t.season || '')),
      specRow('PO Number', escapeHtml(t.po_number || '')),
      specRow('Department', escapeHtml(t.department || '')),
      specRow('Fabric', escapeHtml(t.fabric || '')),
      specRow('Colour', escapeHtml(t.color || '')),
      specRow('Order Qty', t.order_qty != null ? Number(t.order_qty).toLocaleString('en-US') + ' pcs' : ''),
      specRow('Unit Price', money(t.unit_price)),
      specRow('Order Value', money(t.order_value)),
      specRow('Samples', t.sample_qty != null ? t.sample_qty + ' pcs' : ''),
      specRow('Received On', t.received_at ? fmtDate(t.received_at) : ''),
      specRow('Deadline', t.deadline ? '<span' + (overdue ? ' style="color:var(--danger)"' : '') + '>' + fmtDate(t.deadline) + '</span>' : ''),
      specRow('Ship Date', t.ship_date ? fmtDate(t.ship_date) : ''),
      specRow('Created By', escapeHtml(t.created_by_name || '') + ' · ' + fmtDate((t.created_at || '').slice(0, 10))),
    ].join('') || '<p class="hint">No style information filled in yet.</p>';

    $('detailDescription').innerHTML = isHtmlEmpty(t.description) ? '<p class="hint">No details written.</p>' : t.description;
    var nextStatus = STATUS_SEQUENCE[STATUS_SEQUENCE.indexOf(t.status) + 1];
    if (nextStatus) {
      $('detailNextStageBtn').hidden = false;
      $('detailNextStageBtn').disabled = false;
      $('detailNextStageBtn').textContent = '➡️ Move to ' + STATUS_LABEL[nextStatus];
    } else {
      $('detailNextStageBtn').hidden = true;
    }

    var isClosed = t.status === 'done' || t.status === 'cancelled';
    $('detailFinishBtn').hidden = isClosed;
    $('detailFinishBtn').disabled = false;
    $('detailCancelBtn').hidden = isClosed;
    $('detailCancelBtn').disabled = false;
    renderGallery(t.images || []);
    commentImages = [];
    renderCommentImgPreviews();
    $('commentType').value = 'comment';
    setCommentHtml('');
    $('listView').hidden = true;
    $('detailView').hidden = false;
    $('pageTitle').textContent = t.title || '(Untitled)';
    $('pageSub').textContent = [t.buyer, t.style, t.season].filter(Boolean).join(' · ') || 'Style detail';
    window.scrollTo(0, 0);
    loadComments(id);
  }

  function renderGallery(images) {
    $('detailImages').innerHTML = images.map(function (img) {
      return '<div class="img-preview" data-img-id="' + img.id + '">' +
        '<img src="' + img.url + '" data-full="' + img.url + '">' +
        '<button type="button" class="remove" data-img-id="' + img.id + '">✕</button>' +
      '</div>';
    }).join('');
    Array.prototype.forEach.call($('detailImages').querySelectorAll('img'), function (img) {
      img.addEventListener('click', function () {
        $('lightboxImg').src = img.getAttribute('data-full');
        $('lightbox').hidden = false;
      });
    });
    Array.prototype.forEach.call($('detailImages').querySelectorAll('.remove'), function (btn) {
      btn.addEventListener('click', function (e) {
        e.stopPropagation();
        var imgId = btn.getAttribute('data-img-id');
        api('/api/images/' + imgId, { method: 'DELETE' }).then(function () {
          return loadTasks();
        }).then(function () {
          var t = tasks.find(function (x) { return x.id === activeTaskId; });
          if (t) renderGallery(t.images || []);
        }).catch(function (err) {
          showBanner('Could not remove photo: ' + errorMessage(err));
        });
      });
    });
  }

  function closeDetail() {
    $('detailView').hidden = true;
    $('listView').hidden = false;
    updatePageHead();
    activeTaskId = null;
  }
  $('detailBackBtn').addEventListener('click', closeDetail);

  $('detailImageInput').addEventListener('change', function (e) {
    var files = Array.prototype.slice.call(e.target.files || []);
    e.target.value = '';
    if (!activeTaskId || !files.length) return;
    $('detailUploadHint').textContent = 'Uploading...';
    var uploads = files.map(function (file) {
      var fd = new FormData();
      fd.append('image', file);
      return api('/api/tasks/' + activeTaskId + '/images', { method: 'POST', body: fd });
    });
    Promise.allSettled(uploads).then(function (results) {
      var failed = results.filter(function (r) { return r.status === 'rejected'; }).length;
      $('detailUploadHint').textContent = failed ? (failed + ' photo(s) failed to upload.') : '';
      return loadTasks();
    }).then(function () {
      var t = tasks.find(function (x) { return x.id === activeTaskId; });
      if (t) renderGallery(t.images || []);
    });
  });

  var COMMENT_TYPE_LABEL = {
    comment: '💬 Comment', correction: '🛠️ Correction',
    buyer_email: '📧 Buyer Email', reply: '↩️ My Reply', status_change: '🔄 Status Change',
  };
  var commentImages = []; // {file, url}

  function loadComments(taskId) {
    api('/api/tasks/' + taskId + '/comments').then(function (data) {
      var list = data.comments || [];
      var host = $('commentsList');
      if (list.length === 0) {
        host.innerHTML = '<p class="hint">No updates or comments yet.</p>';
        return;
      }
      host.innerHTML = list.map(function (c) {
        var badgeLabel = COMMENT_TYPE_LABEL[c.type] || COMMENT_TYPE_LABEL.comment;
        var images = c.images || [];
        var imagesHtml = images.length ? '<div class="comment-images">' + images.map(function (img) {
          return '<img src="' + img.url + '" data-full="' + img.url + '">';
        }).join('') + '</div>' : '';
        return '<div class="comment">' +
          '<div class="comment-head">' +
            '<div class="comment-head-left"><span class="comment-badge ' + c.type + '">' + badgeLabel + '</span><span class="comment-author">' + escapeHtml(c.author || 'Unknown') + '</span></div>' +
            '<span>' + fmtDate((c.created_at || '').slice(0, 10)) + '</span>' +
          '</div>' +
          '<div class="comment-text">' + (c.text || '') + '</div>' +
          imagesHtml +
        '</div>';
      }).join('');
      Array.prototype.forEach.call(host.querySelectorAll('.comment-images img'), function (img) {
        img.addEventListener('click', function () {
          $('lightboxImg').src = img.getAttribute('data-full');
          $('lightbox').hidden = false;
        });
      });
      host.scrollTop = host.scrollHeight;
    });
  }

  var ALLOWED_IMAGE_TYPES = ['image/png', 'image/jpeg', 'image/jpg', 'image/webp', 'image/gif'];
  var MAX_IMAGE_BYTES = 8 * 1024 * 1024;

  function validateImageFile(file) {
    if (ALLOWED_IMAGE_TYPES.indexOf((file.type || '').toLowerCase()) === -1) {
      return '"' + file.name + '" is not a supported image (use JPG, PNG, WEBP or GIF).';
    }
    if (file.size > MAX_IMAGE_BYTES) {
      return '"' + file.name + '" is larger than 8 MB.';
    }
    return null;
  }

  /** Builds a thumbnail that falls back to the file name if the image cannot be decoded. */
  function buildPreviewTile(entry, onRemove) {
    var tile = document.createElement('div');
    tile.className = 'img-preview' + (entry.uploading ? ' uploading' : '');

    var img = document.createElement('img');
    img.src = entry.url;
    img.alt = entry.name || '';
    img.addEventListener('error', function () {
      tile.classList.add('broken');
      tile.innerHTML = '';
      var label = document.createElement('span');
      label.className = 'img-preview-fallback';
      label.textContent = entry.name ? entry.name.slice(0, 14) : 'file';
      tile.appendChild(label);
      if (onRemove) tile.appendChild(makeRemoveBtn());
    });
    tile.appendChild(img);

    function makeRemoveBtn() {
      var btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'remove';
      btn.textContent = '✕';
      btn.addEventListener('click', function (ev) {
        ev.stopPropagation();
        onRemove();
      });
      return btn;
    }

    if (onRemove && !entry.uploading) tile.appendChild(makeRemoveBtn());
    return tile;
  }

  function renderCommentImgPreviews() {
    var host = $('commentImgPreviews');
    host.innerHTML = '';
    commentImages.forEach(function (entry, idx) {
      host.appendChild(buildPreviewTile(entry, function () {
        URL.revokeObjectURL(commentImages[idx].url);
        commentImages.splice(idx, 1);
        renderCommentImgPreviews();
      }));
    });
  }

  $('commentImageInput').addEventListener('change', function (e) {
    var files = Array.prototype.slice.call(e.target.files || []);
    e.target.value = '';
    var rejected = [];
    files.forEach(function (file) {
      var problem = validateImageFile(file);
      if (problem) { rejected.push(problem); return; }
      commentImages.push({ file: file, name: file.name, url: URL.createObjectURL(file) });
    });
    renderCommentImgPreviews();
    if (rejected.length) showBanner(rejected[0]);
  });

  $('commentSendBtn').addEventListener('click', function () {
    try {
      if (!activeTaskId) {
        showBanner('No task is open. Please reopen the task and try again.');
        return;
      }
      var text = getCommentHtml();
      if (isHtmlEmpty(text)) {
        showBanner('Please write something before sending.');
        return;
      }
      $('commentSendBtn').disabled = true;
      var fd = new FormData();
      fd.append('text', text);
      fd.append('type', $('commentType').value);
      commentImages.forEach(function (img) { fd.append('images[]', img.file); });
      api('/api/tasks/' + activeTaskId + '/comments', { method: 'POST', body: fd }).then(function () {
        setCommentHtml('');
        commentImages = [];
        renderCommentImgPreviews();
        $('commentType').value = 'comment';
        $('commentSendBtn').disabled = false;
        showSuccess('Update sent.');
        loadComments(activeTaskId);
        loadTasks();
      }).catch(function (err) {
        $('commentSendBtn').disabled = false;
        showBanner('Could not send update: ' + errorMessage(err));
      });
    } catch (ex) {
      $('commentSendBtn').disabled = false;
      showBanner('Send failed: ' + (ex && ex.message ? ex.message : ex));
    }
  });

  var STATUS_SEQUENCE = ['new', 'in_progress', 'waiting', 'done'];

  function detailStatusAction(btnId, resolveStatus, confirmMsg) {
    $(btnId).addEventListener('click', function () {
      var t = tasks.find(function (x) { return x.id === activeTaskId; });
      if (!t) return;
      var target = resolveStatus(t);
      if (!target) return;
      if (confirmMsg && !confirm(confirmMsg)) return;
      var id = t.id;
      $(btnId).disabled = true;
      changeStatus(id, target).then(function () {
        openDetail(id);
      }).catch(function () {
        $(btnId).disabled = false;
      });
    });
  }

  detailStatusAction('detailNextStageBtn', function (t) {
    return STATUS_SEQUENCE[STATUS_SEQUENCE.indexOf(t.status) + 1];
  });
  detailStatusAction('detailFinishBtn', function () { return 'done'; });
  detailStatusAction('detailCancelBtn', function () { return 'cancelled'; }, 'Cancel this task? You can reopen it later.');

  $('detailEditBtn').addEventListener('click', function () {
    var t = tasks.find(function (x) { return x.id === activeTaskId; });
    if (!t) return;
    $('detailView').hidden = true;
    openEditModal(t);
  });

  $('detailDeleteBtn').addEventListener('click', function () {
    var t = tasks.find(function (x) { return x.id === activeTaskId; });
    if (!t) return;
    if (!confirm('Are you sure you want to permanently delete this task?')) return;
    api('/api/tasks/' + t.id, { method: 'DELETE' }).then(function () {
      closeDetail();
      return loadTasks();
    }).catch(function (err) {
      showBanner('Could not delete: ' + errorMessage(err));
    });
  });

  // ---------- lightbox ----------
  $('lightbox').addEventListener('click', function () { $('lightbox').hidden = true; });

  Array.prototype.forEach.call(document.querySelectorAll('.modal-overlay'), function (ov) {
    ov.addEventListener('mousedown', function (e) {
      if (e.target === ov) ov.hidden = true;
    });
  });

  // ---------- boot ----------
  loadBuyers();
  loadUsers();
  loadTasks();
  pollTimer = setInterval(function () {
    if (!document.hidden && $('formView').hidden && $('detailView').hidden && $('buyersView').hidden
        && $('reportsView').hidden && (!IS_ADMIN || $('usersView').hidden)) {
      loadTasks();
    }
  }, 20000);
  window.addEventListener('focus', loadTasks);
})();
