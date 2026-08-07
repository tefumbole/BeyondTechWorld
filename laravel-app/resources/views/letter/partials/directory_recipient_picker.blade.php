{{-- Announcement-style To / CC picker for letters --}}
<style>
    .lt-pill {
        border:0; border-radius:999px; padding:6px 12px; font-size:12px; font-weight:600;
        background:#f1f5f9; color:#334155; cursor:pointer; margin:0 4px 6px 0;
    }
    .lt-pill.active { background:#0b3f90; color:#fff; }
    .lt-user-list { max-height:220px; overflow:auto; border:1px solid #e3e9f4; border-radius:10px; background:#fff; }
    .lt-user-item { display:block; width:100%; text-align:left; padding:10px 12px; border:0; border-bottom:1px solid #f0f3f8; background:#fff; cursor:pointer; }
    .lt-user-item:hover, .lt-user-item.selected { background:#f0f6ff; }
    .lt-user-item .meta { color:#6b7280; font-size:12px; }
    .lt-chip {
        display:inline-flex; align-items:center; gap:6px; border:1px solid #0b3f90; color:#0b3f90;
        background:#eef4ff; border-radius:999px; padding:4px 10px; font-size:12px; font-weight:600; margin:2px;
    }
    .lt-chip button { border:0; background:transparent; color:#0b3f90; font-weight:800; cursor:pointer; }
    .lt-field { width:100%; border:1px solid #d7deea; border-radius:8px; padding:9px 12px; font-size:14px; }
    .lt-btn-outline {
        border:1px solid #0b3f90; color:#0b3f90; background:#fff; border-radius:8px;
        padding:8px 12px; font-weight:600; font-size:13px; cursor:pointer;
    }
</style>

<div class="letter-directory-picker letter-recipient-panel">
    <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap" style="gap:8px;">
        <h5 class="mb-0" style="color:#0b3f90;font-weight:700;">Select Recipients *</h5>
        <div>
            <small class="text-muted">Selected</small>
            <strong id="lt-count" style="color:#0b3f90;font-size:1.15rem;margin-left:6px;">0</strong>
        </div>
    </div>
    <p class="text-muted small mb-2">Choose customers, system users, applicants, or mix them — same as Announcements. Deleted applications are not listed.</p>
    <div class="mb-2">
        <button type="button" class="lt-pill active lt-rf" data-role="customers">Customers</button>
        <button type="button" class="lt-pill lt-rf" data-role="staff">System Users</button>
        <button type="button" class="lt-pill lt-rf" data-role="applicants">Applicants</button>
        <button type="button" class="lt-pill lt-rf" data-role="all">All</button>
    </div>
    <div class="d-flex mb-2" style="gap:8px;">
        <input type="search" class="lt-field lt-rsearch" placeholder="Search name, email, phone…">
        <button type="button" class="lt-btn-outline lt-rselect-all" style="white-space:nowrap;">Select all</button>
    </div>
    <div class="lt-user-list lt-rlist"></div>
    <div class="lt-rchips mt-2"></div>
    <div class="lt-rhiddens"></div>

    <hr>
    <h6 style="font-weight:700;">CC</h6>
    <p class="text-muted small">Copied recipients also receive the letter.</p>
    <div class="mb-2">
        <button type="button" class="lt-pill active lt-cf" data-role="all">All</button>
        <button type="button" class="lt-pill lt-cf" data-role="staff">System Users</button>
        <button type="button" class="lt-pill lt-cf" data-role="customers">Customers</button>
        <button type="button" class="lt-pill lt-cf" data-role="applicants">Applicants</button>
    </div>
    <input type="search" class="lt-field lt-csearch mb-2" placeholder="Search CC…">
    <div class="lt-user-list lt-clist" style="max-height:140px;"></div>
    <div class="lt-cchips mt-2"></div>
    <div class="lt-chiddens"></div>
</div>

<script>
window.LT_USERS = @json($directoryPeople ?? []);
window.LT_USERS_SEARCH = @json($directorySearchUrl ?? route('letter.people.search'));
window.LT_PRESELECT = @json([
    'recipients' => $cloneDirectoryToIds ?? [],
    'cc' => $cloneDirectoryCcIds ?? [],
]);
(function () {
    if (!document.querySelector('.letter-directory-picker')) return;

    var recipients = (window.LT_PRESELECT.recipients || []).slice();
    var ccs = (window.LT_PRESELECT.cc || []).slice();
    var rRole = 'customers', cRole = 'all';
    var searchTimers = {};

    function esc(s) {
        return String(s || '').replace(/[&<>"']/g, function (c) {
            return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]);
        });
    }

    function mergeUsers(list) {
        var map = {};
        (window.LT_USERS || []).forEach(function (u) { map[u.id] = u; });
        (list || []).forEach(function (u) { map[u.id] = u; });
        window.LT_USERS = Object.keys(map).map(function (k) { return map[k]; });
    }

    function matchesRole(u, roleFilter) {
        var role = (u.role || '').toLowerCase();
        var source = (u.source || '').toLowerCase();
        if (roleFilter === 'staff') {
            return source !== 'customer' && source !== 'applicant' && role !== 'customer' && role !== 'client' && role !== 'applicant';
        }
        if (roleFilter === 'customers') {
            return source === 'customer' || role === 'customer' || role === 'client';
        }
        if (roleFilter === 'applicants') {
            return source === 'applicant' || role === 'applicant';
        }
        return true;
    }

    function filterUsersLocal(query, roleFilter) {
        var q = (query || '').toLowerCase();
        return (window.LT_USERS || []).filter(function (u) {
            if (!matchesRole(u, roleFilter)) return false;
            if (!q) return true;
            return (u.name||'').toLowerCase().indexOf(q) !== -1
                || (u.email||'').toLowerCase().indexOf(q) !== -1
                || (u.phone||'').toLowerCase().indexOf(q) !== -1;
        });
    }

    function searchUsers(query, roleFilter, done) {
        var q = (query || '').trim();
        if (!q || q.length < 2 || !window.LT_USERS_SEARCH) {
            done(filterUsersLocal(query, roleFilter));
            return;
        }
        var filter = roleFilter === 'staff' ? 'staff'
            : (roleFilter === 'customers' ? 'customers'
            : (roleFilter === 'applicants' ? 'applicants' : 'all'));
        fetch(window.LT_USERS_SEARCH + '?q=' + encodeURIComponent(q) + '&filter=' + encodeURIComponent(filter), {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin'
        }).then(function (r) { return r.json(); }).then(function (rows) {
            mergeUsers(rows);
            done(filterUsersLocal(query, roleFilter));
        }).catch(function () {
            done(filterUsersLocal(query, roleFilter));
        });
    }

    function renderList(el, users, selected, onToggle) {
        el.innerHTML = users.map(function (u) {
            var sel = selected.indexOf(u.id) !== -1 ? ' selected' : '';
            return '<button type="button" class="lt-user-item'+sel+'" data-id="'+esc(u.id)+'">'
                + '<div class="font-weight-bold">'+esc(u.name||'Untitled')+' <span class="badge badge-light">'+esc(u.source||u.role||'')+'</span></div>'
                + '<div class="meta">'+esc(u.email||'')+' · '+esc(u.phone||'')+'</div>'
                + '</button>';
        }).join('') || '<div class="p-3 text-muted small text-center">No people found.</div>';
        el.querySelectorAll('.lt-user-item').forEach(function (item) {
            item.addEventListener('click', function () { onToggle(item.getAttribute('data-id')); });
        });
    }

    function renderChips(el, selected, onRemove, prefix) {
        var map = {};
        (window.LT_USERS || []).forEach(function (u) { map[u.id] = u; });
        el.innerHTML = selected.map(function (id) {
            var u = map[id] || { name: id };
            return '<span class="lt-chip" data-id="'+esc(id)+'">'+esc((prefix?prefix+' ':'')+(u.name||id))
                + ' <button type="button">×</button></span>';
        }).join('');
        el.querySelectorAll('.lt-chip button').forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                onRemove(btn.parentNode.getAttribute('data-id'));
            });
        });
    }

    function syncHiddens(el, selected, name) {
        el.innerHTML = selected.map(function (id) {
            return '<input type="hidden" name="'+name+'[]" value="'+esc(id)+'">';
        }).join('');
        var countEl = document.getElementById('lt-count');
        if (countEl) countEl.textContent = String(recipients.length + ccs.length);
    }

    function refreshRecipients() {
        var q = document.querySelector('.lt-rsearch').value;
        searchUsers(q, rRole, function (users) {
            renderList(document.querySelector('.lt-rlist'), users, recipients, function (id) {
                var i = recipients.indexOf(id);
                if (i === -1) recipients.push(id); else recipients.splice(i, 1);
                refreshRecipients();
            });
            renderChips(document.querySelector('.lt-rchips'), recipients, function (id) {
                recipients = recipients.filter(function (x) { return x !== id; });
                refreshRecipients();
            });
            syncHiddens(document.querySelector('.lt-rhiddens'), recipients, 'recipient_ids');
        });
    }

    function refreshCc() {
        var q = document.querySelector('.lt-csearch').value;
        searchUsers(q, cRole, function (users) {
            renderList(document.querySelector('.lt-clist'), users, ccs, function (id) {
                var i = ccs.indexOf(id);
                if (i === -1) ccs.push(id); else ccs.splice(i, 1);
                refreshCc();
            });
            renderChips(document.querySelector('.lt-cchips'), ccs, function (id) {
                ccs = ccs.filter(function (x) { return x !== id; });
                refreshCc();
            }, 'CC:');
            syncHiddens(document.querySelector('.lt-chiddens'), ccs, 'cc_ids');
        });
    }

    document.querySelector('.lt-rsearch').addEventListener('input', function () {
        clearTimeout(searchTimers.r);
        searchTimers.r = setTimeout(refreshRecipients, 250);
    });
    document.querySelector('.lt-csearch').addEventListener('input', function () {
        clearTimeout(searchTimers.c);
        searchTimers.c = setTimeout(refreshCc, 250);
    });
    document.querySelectorAll('.lt-rf').forEach(function (btn) {
        btn.addEventListener('click', function () {
            rRole = btn.getAttribute('data-role');
            document.querySelectorAll('.lt-rf').forEach(function (b) { b.classList.remove('active'); });
            btn.classList.add('active');
            refreshRecipients();
        });
    });
    document.querySelectorAll('.lt-cf').forEach(function (btn) {
        btn.addEventListener('click', function () {
            cRole = btn.getAttribute('data-role');
            document.querySelectorAll('.lt-cf').forEach(function (b) { b.classList.remove('active'); });
            btn.classList.add('active');
            refreshCc();
        });
    });
    document.querySelector('.lt-rselect-all').addEventListener('click', function () {
        filterUsersLocal(document.querySelector('.lt-rsearch').value, rRole).forEach(function (u) {
            if (recipients.indexOf(u.id) === -1) recipients.push(u.id);
        });
        refreshRecipients();
    });

    var form = document.getElementById('product-form');
    if (form) {
        form.addEventListener('submit', function (e) {
            var peopleType = (document.querySelector('select[name="people_type"]') || {}).value;
            if (peopleType === 'directory' && !recipients.length) {
                e.preventDefault();
                alert('Select at least one recipient (To).');
            }
        });
    }

    refreshRecipients();
    refreshCc();
})();
</script>
