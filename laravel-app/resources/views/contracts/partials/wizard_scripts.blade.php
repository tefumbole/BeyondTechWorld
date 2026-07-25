<script>
(function () {
    var peopleUrl = @json(route('contracts.people_search'));
    var linkMetaUrl = @json(route('contracts.link_meta'));
    var quickCustomerUrl = @json(route('contracts.quick_customer'));
    var templateBodyUrlTpl = @json(url('/admin/contracts/templates'));
    var csrf = $('meta[name="csrf-token"]').attr('content');
    var catalog = @json($linkCatalog ?? []);
    var total = document.querySelectorAll('.ct-wizard-step').length || 8;
    var current = 1;

    /* ---------- wizard nav ---------- */
    var steps = document.querySelectorAll('.ct-wizard-step');
    var navBtns = document.querySelectorAll('#wizard-nav .ct-step-btn');
    var prevBtn = document.getElementById('wizard-prev');
    var nextBtn = document.getElementById('wizard-next');

    function showStep(n) {
        current = n;
        steps.forEach(function (el) {
            el.classList.toggle('is-visible', parseInt(el.getAttribute('data-step'), 10) === n);
        });
        navBtns.forEach(function (btn) {
            btn.classList.toggle('is-active', parseInt(btn.getAttribute('data-step'), 10) === n);
        });
        if (prevBtn) prevBtn.disabled = n <= 1;
        if (nextBtn) {
            nextBtn.style.display = n >= total ? 'none' : '';
            nextBtn.textContent = 'Next';
        }
        if (n === total) buildReview();
        if (n === 7) ensureContentLoaded();
    }
    if (navBtns.length) {
        navBtns.forEach(function (btn) {
            btn.addEventListener('click', function () {
                showStep(parseInt(btn.getAttribute('data-step'), 10));
            });
        });
    }
    if (prevBtn) prevBtn.addEventListener('click', function () { if (current > 1) showStep(current - 1); });
    if (nextBtn) nextBtn.addEventListener('click', function () { if (current < total) showStep(current + 1); });

    /* ---------- link catalog ---------- */
    var linkType = document.getElementById('link_type');
    var linkPick = document.getElementById('link_pick');
    var linkId = document.getElementById('link_id');

    function refillLinkPick() {
        if (!linkPick || !linkType) return;
        var type = linkType.value;
        var items = catalog[type] || [];
        var currentId = linkId ? linkId.value : '';
        linkPick.innerHTML = '<option value="">Select…</option>';
        items.forEach(function (it) {
            var opt = document.createElement('option');
            opt.value = it.id;
            opt.textContent = it.label;
            if (String(it.id) === String(currentId)) opt.selected = true;
            linkPick.appendChild(opt);
        });
    }

    function applyLinkMeta(meta) {
        if (!meta) return;
        var summary = document.getElementById('ct-link-summary');
        if (summary) {
            summary.classList.remove('d-none');
            summary.textContent = (meta.label || '') + (meta.value != null ? (' · Amount: ' + Number(meta.value).toLocaleString() + ' ' + (meta.currency || 'XAF')) : '');
        }
        if (meta.title && document.getElementById('ct-title') && !document.getElementById('ct-title').value) {
            document.getElementById('ct-title').value = meta.title;
        } else if (meta.title && document.getElementById('ct-title')) {
            document.getElementById('ct-title').value = meta.title;
        }
        if (meta.value != null && document.getElementById('ct-value')) {
            document.getElementById('ct-value').value = meta.value;
        }
        if (meta.effective_date && document.getElementById('ct-effective')) {
            document.getElementById('ct-effective').value = meta.effective_date;
        }
        if (meta.start_date && document.getElementById('ct-start')) {
            document.getElementById('ct-start').value = meta.start_date;
        }
        if (meta.end_date && document.getElementById('ct-end')) {
            document.getElementById('ct-end').value = meta.end_date;
        }
        if (meta.customer) {
            fillPerson('party_b', meta.customer);
        }
        if (meta.preferred_template_code) {
            var tplSel = document.getElementById('ct-template-id');
            if (tplSel) {
                for (var i = 0; i < tplSel.options.length; i++) {
                    if (tplSel.options[i].getAttribute('data-code') === meta.preferred_template_code) {
                        tplSel.selectedIndex = i;
                        tplSel.dispatchEvent(new Event('change'));
                        break;
                    }
                }
            }
        }
    }

    function fetchLinkMeta() {
        if (!linkType || !linkId || !linkType.value || !linkId.value) return;
        $.getJSON(linkMetaUrl, { type: linkType.value, id: linkId.value })
            .done(applyLinkMeta)
            .fail(function () {});
    }

    if (linkType) {
        linkType.addEventListener('change', function () {
            if (linkId) linkId.value = '';
            refillLinkPick();
        });
        refillLinkPick();
    }
    if (linkPick) {
        linkPick.addEventListener('change', function () {
            if (linkId) linkId.value = linkPick.value || '';
            fetchLinkMeta();
        });
    }
    if (linkId && linkId.value && linkType && linkType.value) {
        refillLinkPick();
        fetchLinkMeta();
    }

    /* ---------- template body ---------- */
    var contentLoadedFor = null;
    function ensureContentLoaded() {
        var sel = document.getElementById('ct-template-id');
        var ta = document.getElementById('ct-content-html');
        if (!sel || !ta || !sel.value) return;
        if (contentLoadedFor === sel.value && ta.value.trim() !== '') return;
        $.getJSON(templateBodyUrlTpl + '/' + sel.value + '/body')
            .done(function (data) {
                if (data.content_html && (ta.value.trim() === '' || contentLoadedFor !== sel.value)) {
                    ta.value = data.content_html;
                }
                contentLoadedFor = sel.value;
                if (data.party_a_label) {
                    var a = document.querySelector('[name="party_a_role"]');
                    if (a && !a.dataset.touched) a.value = data.party_a_label;
                }
                if (data.party_b_label) {
                    var b = document.querySelector('[name="party_b_role"]');
                    if (b && !b.dataset.touched) b.value = data.party_b_label;
                }
            });
    }
    var tplSel = document.getElementById('ct-template-id');
    if (tplSel) {
        tplSel.addEventListener('change', function () {
            contentLoadedFor = null;
            ensureContentLoaded();
            var opt = tplSel.options[tplSel.selectedIndex];
            if (opt) {
                var a = document.querySelector('[name="party_a_role"]');
                var b = document.querySelector('[name="party_b_role"]');
                if (a && opt.getAttribute('data-a-label')) a.value = opt.getAttribute('data-a-label');
                if (b && opt.getAttribute('data-b-label')) b.value = opt.getAttribute('data-b-label');
            }
        });
    }

    /* ---------- engineer rates ---------- */
    var rateSel = document.getElementById('ct-rate-category');
    if (rateSel) {
        rateSel.addEventListener('change', function () {
            var opt = rateSel.options[rateSel.selectedIndex];
            if (!opt || !opt.value) return;
            var role = document.getElementById('ct-worker-role');
            var rate = document.getElementById('ct-worker-rate');
            if (role) role.value = opt.getAttribute('data-name') || opt.value;
            if (rate) rate.value = opt.getAttribute('data-rate') || '';
        });
    }

    /* ---------- person pickers ---------- */
    function parseSubject(id) {
        if (!id) return { type: '', sid: '' };
        var parts = String(id).split(':');
        if (parts.length < 2) return { type: 'person', sid: id };
        return { type: parts[0] === 'customer' ? 'customer' : (parts[0] === 'user' ? 'user' : 'beyond'), sid: parts.slice(1).join(':') };
    }

    function fillPerson(prefix, person) {
        var box = document.querySelector('.ct-person-picker[data-prefix="' + prefix + '"]');
        if (!box || !person) return;
        var sub = parseSubject(person.id || (person.subject_type && person.subject_id ? (person.subject_type + ':' + person.subject_id) : ''));
        box.querySelector('.ct-pp-name').value = person.name || '';
        box.querySelector('.ct-pp-email').value = person.email || '';
        box.querySelector('.ct-pp-phone').value = person.phone || '';
        box.querySelector('.ct-pp-address').value = person.address || '';
        if (box.querySelector('.ct-pp-org')) box.querySelector('.ct-pp-org').value = person.organization || '';
        box.querySelector('.ct-pp-stype').value = person.subject_type || sub.type;
        box.querySelector('.ct-pp-sid').value = person.subject_id || sub.sid;
        var sel = box.querySelector('.ct-pp-selected');
        var lab = box.querySelector('.ct-pp-selected-label');
        if (sel && lab) {
            sel.classList.remove('d-none');
            lab.textContent = (person.name || '') + (person.source ? (' · ' + person.source) : '');
        }
    }

    function searchPeople(box) {
        var q = box.querySelector('.ct-pp-search').value || '';
        var filter = box.getAttribute('data-filter') || 'all';
        var list = box.querySelector('.ct-pp-list');
        list.innerHTML = '<div class="p-2 text-muted small">Searching…</div>';
        $.getJSON(peopleUrl, { q: q, filter: filter })
            .done(function (rows) {
                if (!rows || !rows.length) {
                    list.innerHTML = '<div class="p-2 text-muted small">No matches.</div>';
                    return;
                }
                list.innerHTML = '';
                rows.forEach(function (u) {
                    var row = document.createElement('button');
                    row.type = 'button';
                    row.className = 'btn btn-link btn-sm text-left d-block w-100 border-bottom rounded-0';
                    row.style.whiteSpace = 'normal';
                    row.innerHTML = '<strong>' + (u.name || '') + '</strong> <span class="text-muted">(' + (u.source || '') + ')</span><br><small>' +
                        [u.phone, u.email, u.organization || u.address].filter(Boolean).join(' · ') + '</small>';
                    row.addEventListener('click', function () {
                        fillPerson(box.getAttribute('data-prefix'), u);
                    });
                    list.appendChild(row);
                });
            })
            .fail(function () {
                list.innerHTML = '<div class="p-2 text-danger small">Search failed.</div>';
            });
    }

    document.querySelectorAll('.ct-person-picker').forEach(function (box) {
        box.setAttribute('data-filter', 'all');
        var timer = null;
        var search = box.querySelector('.ct-pp-search');
        if (search) {
            search.addEventListener('input', function () {
                clearTimeout(timer);
                timer = setTimeout(function () { searchPeople(box); }, 250);
            });
            search.addEventListener('focus', function () {
                if (!box.querySelector('.ct-pp-list').children.length) searchPeople(box);
            });
        }
        box.querySelectorAll('.ct-pp-filter').forEach(function (btn) {
            btn.addEventListener('click', function () {
                box.querySelectorAll('.ct-pp-filter').forEach(function (b) { b.classList.remove('active'); });
                btn.classList.add('active');
                box.setAttribute('data-filter', btn.getAttribute('data-filter') || 'all');
                searchPeople(box);
            });
        });
        var clearBtn = box.querySelector('.ct-pp-clear');
        if (clearBtn) {
            clearBtn.addEventListener('click', function () {
                ['name','email','phone','address','org'].forEach(function (k) {
                    var el = box.querySelector('.ct-pp-' + k);
                    if (el) el.value = '';
                });
                box.querySelector('.ct-pp-stype').value = '';
                box.querySelector('.ct-pp-sid').value = '';
                box.querySelector('.ct-pp-selected').classList.add('d-none');
            });
        }
        var createBtn = box.querySelector('.ct-pp-create');
        if (createBtn) {
            createBtn.addEventListener('click', function () {
                document.getElementById('ct-qc-target-prefix').value = box.getAttribute('data-prefix');
            });
        }
    });

    /* ---------- quick create customer ---------- */
    $('#ct-qc-save').on('click', function () {
        var prefix = $('#ct-qc-target-prefix').val();
        var payload = {
            _token: csrf,
            customer_group_id: $('#ct-qc-group').val(),
            name: $('#ct-qc-name').val(),
            company_name: $('#ct-qc-company').val(),
            phone: $('#ct-qc-phone').val(),
            email: $('#ct-qc-email').val(),
            address: $('#ct-qc-address').val()
        };
        $('#ct-qc-error').addClass('d-none');
        $.post(quickCustomerUrl, payload)
            .done(function (person) {
                fillPerson(prefix, person);
                $('#ct-add-customer-modal').modal('hide');
                $('#ct-qc-name,#ct-qc-company,#ct-qc-phone,#ct-qc-email,#ct-qc-address').val('');
            })
            .fail(function (xhr) {
                var msg = (xhr.responseJSON && (xhr.responseJSON.message || xhr.responseJSON.error)) || 'Could not create customer.';
                if (xhr.responseJSON && xhr.responseJSON.errors) {
                    msg = Object.values(xhr.responseJSON.errors).map(function (a) { return a.join(' '); }).join(' ');
                }
                $('#ct-qc-error').removeClass('d-none').text(msg);
            });
    });

    function buildReview() {
        var box = document.getElementById('ct-review-box');
        if (!box) return;
        var lines = [];
        lines.push('Template: ' + ($('#ct-template-id option:selected').text() || '—'));
        lines.push('Title: ' + ($('#ct-title').val() || '—'));
        lines.push('Link: ' + (($('#link_type').val() || 'none') + ' ' + ($('#link_id').val() || '')));
        lines.push('Value: ' + ($('#ct-value').val() || '—') + ' ' + ($('#ct-currency').val() || ''));
        lines.push('Effective: ' + ($('#ct-effective').val() || '—'));
        lines.push('Party A: ' + ($('[name="party_a[name]"]').val() || '—'));
        lines.push('Party B: ' + ($('[name="party_b[name]"]').val() || '—'));
        lines.push('Worker role / rate: ' + ($('#ct-worker-role').val() || '—') + ' / ' + ($('#ct-worker-rate').val() || '—'));
        lines.push('Content length: ' + (($('#ct-content-html').val() || '').length) + ' chars');
        box.textContent = lines.join('\n');
    }

    // Prefill Party A company defaults on create
    @if(($mode ?? '') === 'create')
    if (!$('[name="party_a[name]"]').val()) {
        fillPerson('party_a', {
            id: 'company:beyond',
            subject_type: 'company',
            subject_id: 'beyond',
            name: @json($company['name'] ?? 'Beyond Enterprise'),
            address: @json($company['address'] ?? ''),
            source: 'Company default'
        });
    }

    var remBox = document.getElementById('ct-create-reminders');
    var addRem = document.getElementById('ct-add-reminder-row');
    function addReminderRow(val) {
        if (!remBox) return;
        var row = document.createElement('div');
        row.className = 'd-flex mb-2';
        row.style.gap = '8px';
        row.style.alignItems = 'center';
        row.innerHTML = '<input type="datetime-local" name="reminders[]" class="ct-field" style="max-width:280px;">'
            + '<button type="button" class="btn btn-sm btn-outline-danger">×</button>';
        if (val) row.querySelector('input').value = val;
        row.querySelector('button').addEventListener('click', function () { row.remove(); });
        remBox.appendChild(row);
    }
    if (addRem) addRem.addEventListener('click', function () { addReminderRow(''); });
    @endif
})();
</script>
