(function () {
  "use strict";

  var CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
  var tasks = [];
  var editingTaskId = null;
  var activeTaskId = null;
  var pollTimer = null;
  var activeStatus = 'all';
  var activeOverdueOnly = false;

  var $ = function (id) { return document.getElementById(id); };

  window.addEventListener('error', function (e) {
    var loc = e.filename ? ' [' + e.filename.split('/').pop() + ':' + e.lineno + ']' : '';
    showBanner('Script error: ' + (e.message || 'unknown error') + loc);
  });
  window.addEventListener('unhandledrejection', function (e) {
    var reason = e.reason;
    var msg = (reason && (reason.message || reason.error)) || (typeof reason === 'string' ? reason : JSON.stringify(reason));
    showBanner('Unhandled error: ' + msg);
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
  function getDescriptionHtml() {
    var html = jQuery('#fDescription').summernote('code');
    return isHtmlEmpty(html) ? '' : html;
  }
  function setDescriptionHtml(html) { jQuery('#fDescription').summernote('code', html || ''); }
  function getCommentHtml() {
    var html = jQuery('#commentInput').summernote('code');
    return isHtmlEmpty(html) ? '' : html;
  }
  function setCommentHtml(html) { jQuery('#commentInput').summernote('code', html || ''); }

  jQuery('#fDescription').summernote(Object.assign({ height: 140, placeholder: 'Fabric, wash, comments, or any other details...' }, RICH_TEXT_OPTS));
  jQuery('#commentInput').summernote(Object.assign({ height: 90, placeholder: 'Write the update, correction note, or email content...' }, RICH_TEXT_OPTS));
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

  var STATUS_LABEL = { new: 'New', in_progress: 'In Progress', waiting: 'Waiting', done: 'Done' };
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
    var total = tasks.length, newCount = 0, running = 0, waiting = 0, done = 0, overdue = 0;
    tasks.forEach(function (t) {
      if (t.status === 'new') newCount++;
      if (t.status === 'in_progress') running++;
      if (t.status === 'waiting') waiting++;
      if (t.status === 'done') done++;
      if (isOverdue(t)) overdue++;
    });
    $('statTotal').textContent = total;
    $('statRunning').textContent = running;
    $('statWaiting').textContent = waiting;
    $('statOverdue').textContent = overdue;

    $('navCountAll').textContent = total;
    $('navCountNew').textContent = newCount;
    $('navCountRunning').textContent = running;
    $('navCountWaiting').textContent = waiting;
    $('navCountDone').textContent = done;
    $('navCountOverdue').textContent = overdue;
  }

  function populateBuyerFilter() {
    var sel = $('buyerFilter');
    var current = sel.value;
    var buyers = Array.from(new Set(tasks.map(function (t) { return (t.buyer || '').trim(); }).filter(Boolean))).sort();
    sel.innerHTML = '<option value="all">All Buyers</option>' + buyers.map(function (b) {
      return '<option value="' + escapeHtml(b) + '">' + escapeHtml(b) + '</option>';
    }).join('');
    if (buyers.indexOf(current) >= 0) sel.value = current;
  }

  function applySoftFilters(list) {
    var q = $('searchInput').value.trim().toLowerCase();
    var pr = $('priorityFilter').value;
    var by = $('buyerFilter').value;
    return list.filter(function (t) {
      if (pr !== 'all' && t.priority !== pr) return false;
      if (by !== 'all' && (t.buyer || '') !== by) return false;
      if (q) {
        var hay = [t.title, t.description, t.buyer, t.style, t.department].join(' ').toLowerCase();
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
  ];

  function renderGrid() {
    var grid = $('taskGrid');
    var soft = applySoftFilters(tasks);
    var isBoardView = activeStatus === 'all' && !activeOverdueOnly;

    if (isBoardView) {
      renderKanban(grid, soft);
      return;
    }

    var filtered = soft.filter(function (t) {
      if (activeOverdueOnly) return isOverdue(t);
      return t.status === activeStatus;
    });

    if (filtered.length === 0) {
      grid.innerHTML = '<div class="empty-state">' +
        '<div>No tasks found.</div>' +
        (tasks.length === 0 ? '<div class="hint" style="margin-top:6px;">Click "New Task" to add your first entry.</div>' : '') +
        '</div>';
      return;
    }
    grid.className = 'grid';
    grid.innerHTML = filtered.map(cardHtml).join('');
    wireCardClicks(grid);
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

  function renderKanban(grid, list) {
    grid.className = 'kanban';
    grid.innerHTML = KANBAN_COLUMNS.map(function (col) {
      var colTasks = list.filter(function (t) { return t.status === col.status; });
      var cardsHtml = colTasks.length
        ? colTasks.map(cardHtml).join('')
        : '<div class="kanban-empty">No tasks</div>';
      return '<div class="kanban-col ' + col.status + '" data-status="' + col.status + '">' +
        '<div class="kanban-col-head"><span class="kanban-col-title">' + col.label + '</span><span class="kanban-col-count">' + colTasks.length + '</span></div>' +
        '<div class="kanban-cards">' + cardsHtml + '</div>' +
      '</div>';
    }).join('');

    wireCardClicks(grid);

    Array.prototype.forEach.call(grid.querySelectorAll('.kanban-col'), function (col) {
      col.addEventListener('dragover', function (e) {
        e.preventDefault();
        e.dataTransfer.dropEffect = 'move';
        col.classList.add('drag-over');
      });
      col.addEventListener('dragleave', function () { col.classList.remove('drag-over'); });
      col.addEventListener('drop', function (e) {
        e.preventDefault();
        col.classList.remove('drag-over');
        var id = parseInt(e.dataTransfer.getData('text/plain'), 10);
        var newStatus = col.getAttribute('data-status');
        var t = tasks.find(function (x) { return x.id === id; });
        if (!t || t.status === newStatus) return;
        t.status = newStatus;
        renderKanban(grid, applySoftFilters(tasks));
        computeStats();
        api('/api/tasks/' + id + '/status', { method: 'PATCH', json: { status: newStatus } }).then(function () {
          showSuccess('Moved to ' + STATUS_LABEL[newStatus] + '.');
          loadTasks();
        }).catch(function (err) {
          showBanner('Could not move task: ' + errorMessage(err));
          loadTasks();
        });
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

  ['searchInput', 'priorityFilter', 'buyerFilter'].forEach(function (id) {
    $(id).addEventListener('input', renderGrid);
    $(id).addEventListener('change', renderGrid);
  });

  var navButtons = Array.prototype.slice.call(document.querySelectorAll('.side-nav-item'));
  navButtons.forEach(function (btn) {
    btn.addEventListener('click', function () {
      navButtons.forEach(function (b) { b.classList.remove('active'); });
      btn.classList.add('active');
      var kind = btn.getAttribute('data-nav');
      if (kind === 'status') {
        activeStatus = btn.getAttribute('data-status');
        activeOverdueOnly = false;
      } else if (kind === 'overdue') {
        activeStatus = 'all';
        activeOverdueOnly = true;
      } else {
        activeStatus = 'all';
        activeOverdueOnly = false;
      }
      renderGrid();
    });
  });

  // ---------- add/edit modal ----------
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

  function openAddModal() {
    resetForm();
    $('taskModalTitle').textContent = 'Add New Task';
    $('taskModal').hidden = false;
    $('fTitle').focus();
  }

  function openEditModal(t) {
    resetForm();
    editingTaskId = t.id;
    $('taskModalTitle').textContent = 'Edit Task';
    $('fTitle').value = t.title || '';
    $('fBuyer').value = t.buyer || '';
    $('fStyle').value = t.style || '';
    $('fDepartment').value = t.department || '';
    $('fSampleQty').value = t.sample_qty != null ? t.sample_qty : '';
    $('fStatus').value = t.status || 'new';
    $('fPriority').value = t.priority || 'medium';
    $('fDeadline').value = t.deadline || '';
    setDescriptionHtml(t.description || '');
    formImages = (t.images || []).map(function (img) {
      return { id: img.id, url: img.url, file: null, uploading: false };
    });
    renderFormImages();
    $('taskModal').hidden = false;
  }

  function renderFormImages() {
    var host = $('formImgPreviews');
    host.innerHTML = formImages.map(function (img, idx) {
      return '<div class="img-preview' + (img.uploading ? ' uploading' : '') + '" data-idx="' + idx + '">' +
        '<img src="' + img.url + '">' +
        (img.uploading ? '' : '<button type="button" class="remove" data-idx="' + idx + '">✕</button>') +
      '</div>';
    }).join('');
    Array.prototype.forEach.call(host.querySelectorAll('.remove'), function (btn) {
      btn.addEventListener('click', function () {
        var idx = parseInt(btn.getAttribute('data-idx'), 10);
        var img = formImages[idx];
        if (img.id) {
          btn.disabled = true;
          api('/api/images/' + img.id, { method: 'DELETE' }).then(function () {
            formImages.splice(idx, 1);
            renderFormImages();
            loadTasks();
          }).catch(function (err) {
            showBanner('Could not remove photo: ' + errorMessage(err));
          });
        } else {
          formImages.splice(idx, 1);
          renderFormImages();
        }
      });
    });
  }

  $('formImageInput').addEventListener('change', function (e) {
    var files = Array.prototype.slice.call(e.target.files || []);
    e.target.value = '';
    if (!files.length) return;

    if (editingTaskId) {
      files.forEach(function (file) {
        var entry = { id: null, url: URL.createObjectURL(file), file: null, uploading: true };
        formImages.push(entry);
        renderFormImages();
        var fd = new FormData();
        fd.append('image', file);
        api('/api/tasks/' + editingTaskId + '/images', { method: 'POST', body: fd }).then(function (res) {
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
      files.forEach(function (file) {
        formImages.push({ id: null, url: URL.createObjectURL(file), file: file, uploading: false });
      });
      renderFormImages();
    }
  });

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

  function closeTaskModal() { $('taskModal').hidden = true; }
  $('addTaskBtn').addEventListener('click', openAddModal);
  $('taskCancelBtn').addEventListener('click', closeTaskModal);
  $('taskModalCloseBtn').addEventListener('click', closeTaskModal);

  $('taskForm').addEventListener('submit', function (e) {
    e.preventDefault();
    var title = $('fTitle').value.trim();
    if (!title) return;
    var saveBtn = $('taskSaveBtn');
    saveBtn.disabled = true;

    var payload = {
      title: title,
      buyer: $('fBuyer').value.trim(),
      style: $('fStyle').value.trim(),
      department: $('fDepartment').value.trim(),
      sample_qty: $('fSampleQty').value === '' ? null : parseInt($('fSampleQty').value, 10),
      status: $('fStatus').value,
      priority: $('fPriority').value,
      deadline: $('fDeadline').value || null,
      description: getDescriptionHtml(),
    };

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
  function openDetail(id) {
    var t = tasks.find(function (x) { return x.id === id; });
    if (!t) return;
    activeTaskId = id;
    $('detailTitle').textContent = t.title || '(Untitled)';
    var overdue = isOverdue(t);
    var metaParts = [];
    metaParts.push('<span class="pill pill-' + t.status + '">' + STATUS_LABEL[t.status] + '</span>');
    if (t.priority) metaParts.push('<span class="pill" style="background:var(--surface-2);color:var(--text-muted);">Priority: ' + PRIORITY_LABEL[t.priority] + '</span>');
    if (t.buyer) metaParts.push('<span class="dept-chip">Buyer: ' + escapeHtml(t.buyer) + '</span>');
    if (t.style) metaParts.push('<span class="dept-chip">Style: ' + escapeHtml(t.style) + '</span>');
    if (t.department) metaParts.push('<span class="dept-chip">Department: ' + escapeHtml(t.department) + '</span>');
    if (t.sample_qty != null) metaParts.push('<span class="dept-chip">Samples: ' + t.sample_qty + '</span>');
    if (t.deadline) metaParts.push('<span class="dept-chip" style="' + (overdue ? 'color:var(--danger);' : '') + '">Deadline: ' + fmtDate(t.deadline) + '</span>');
    if (t.created_by_name) metaParts.push('<span class="dept-chip">Started by: ' + escapeHtml(t.created_by_name) + ' • ' + fmtDate((t.created_at || '').slice(0, 10)) + '</span>');
    $('detailMeta').innerHTML = metaParts.join('');
    $('detailDescription').innerHTML = isHtmlEmpty(t.description) ? 'No details written.' : t.description;
    var nextIdx = STATUS_SEQUENCE.indexOf(t.status) + 1;
    var nextStatus = STATUS_SEQUENCE[nextIdx];
    if (nextStatus) {
      $('detailNextStageBtn').hidden = false;
      $('detailNextStageBtn').disabled = false;
      $('detailNextStageBtn').textContent = '➡️ Move to ' + STATUS_LABEL[nextStatus];
    } else {
      $('detailNextStageBtn').hidden = true;
    }
    renderGallery(t.images || []);
    commentImages = [];
    renderCommentImgPreviews();
    $('commentType').value = 'comment';
    setCommentHtml('');
    $('detailModal').hidden = false;
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
    $('detailModal').hidden = true;
    activeTaskId = null;
  }
  $('detailCloseBtn').addEventListener('click', closeDetail);

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

  function renderCommentImgPreviews() {
    var host = $('commentImgPreviews');
    host.innerHTML = commentImages.map(function (img, idx) {
      return '<div class="img-preview" data-idx="' + idx + '"><img src="' + img.url + '"><button type="button" class="remove" data-idx="' + idx + '">✕</button></div>';
    }).join('');
    Array.prototype.forEach.call(host.querySelectorAll('.remove'), function (btn) {
      btn.addEventListener('click', function () {
        commentImages.splice(parseInt(btn.getAttribute('data-idx'), 10), 1);
        renderCommentImgPreviews();
      });
    });
  }

  $('commentImageInput').addEventListener('change', function (e) {
    var files = Array.prototype.slice.call(e.target.files || []);
    e.target.value = '';
    files.forEach(function (file) { commentImages.push({ file: file, url: URL.createObjectURL(file) }); });
    renderCommentImgPreviews();
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

  $('detailNextStageBtn').addEventListener('click', function () {
    var t = tasks.find(function (x) { return x.id === activeTaskId; });
    if (!t) return;
    var idx = STATUS_SEQUENCE.indexOf(t.status);
    var next = STATUS_SEQUENCE[idx + 1];
    if (!next) return;
    $('detailNextStageBtn').disabled = true;
    api('/api/tasks/' + t.id + '/status', { method: 'PATCH', json: { status: next } }).then(function () {
      showSuccess('Moved to ' + STATUS_LABEL[next] + '.');
      return loadTasks().then(function () { openDetail(t.id); });
    }).catch(function (err) {
      $('detailNextStageBtn').disabled = false;
      showBanner('Could not move task: ' + errorMessage(err));
    });
  });

  $('detailEditBtn').addEventListener('click', function () {
    var t = tasks.find(function (x) { return x.id === activeTaskId; });
    if (!t) return;
    closeDetail();
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
      if (e.target === ov) {
        ov.hidden = true;
        if (ov.id === 'detailModal') closeDetail();
      }
    });
  });

  // ---------- boot ----------
  loadTasks();
  pollTimer = setInterval(function () {
    if (!document.hidden && $('taskModal').hidden && $('detailModal').hidden) {
      loadTasks();
    }
  }, 20000);
  window.addEventListener('focus', loadTasks);
})();
