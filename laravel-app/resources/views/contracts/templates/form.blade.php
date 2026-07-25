@extends('layout.main')

@section('content')
@php
    $editing = isset($template) && $template;
    $contentHtml = old('content_html', $editing ? optional($template->currentVersion)->content_html : '');
@endphp
<section class="forms">
    <div class="container-fluid ct-shell">
        @include('contracts.partials.tabs')

        <div class="mb-4">
            <h1 class="ct-title">{{ $editing ? 'Edit Template' : 'New Template' }}</h1>
            <p class="ct-subtitle">{{ $editing ? $template->name : 'Create a contract template with HTML body and metadata.' }}</p>
        </div>

        @if($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
            </div>
        @endif

        <div class="ct-card">
            <form method="POST" action="{{ $editing ? route('contracts.templates.update', $template->id) : route('contracts.templates.store') }}">
                @csrf

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="ct-label">Name *</label>
                        <input type="text" name="name" class="ct-field" required value="{{ old('name', optional($template)->name) }}">
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="ct-label">Code *</label>
                        <input type="text" name="code" class="ct-field" required value="{{ old('code', optional($template)->code) }}" @if($editing) readonly @endif>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="ct-label">Type *</label>
                        <select name="type_id" class="ct-field" required>
                            <option value="">Select…</option>
                            @foreach($types ?? [] as $type)
                                <option value="{{ $type->id }}" @if((string) old('type_id', optional($template)->type_id) === (string) $type->id) selected @endif>{{ $type->name ?? $type->code }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-12 mb-3">
                        <label class="ct-label">Description</label>
                        <textarea name="description" class="ct-field" rows="2">{{ old('description', optional($template)->description) }}</textarea>
                    </div>
                    <div class="col-md-12 mb-2">
                        <div class="d-flex justify-content-between align-items-center flex-wrap" style="gap:8px;">
                            <div>
                                <label class="ct-label mb-0">Content *</label>
                                <p class="text-muted small mb-0">Edit HTML on the left. Preview shows how the contract will look (sample data).</p>
                            </div>
                            <button type="button" class="ct-btn-secondary" id="ct-tpl-preview-window">
                                <i class="dripicons-preview"></i> Open full preview
                            </button>
                        </div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="ct-label">HTML source</label>
                        <div class="d-flex mb-2" style="gap:8px;">
                            <select id="ct-clause-pick" class="ct-field">
                                <option value="">Insert clause from library…</option>
                            </select>
                            <button type="button" class="ct-btn-secondary" id="ct-clause-insert">Insert</button>
                        </div>
                        <textarea name="content_html" id="ct-tpl-content" class="ct-field" rows="22" required>{{ $contentHtml }}</textarea>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="ct-label">Live preview</label>
                        <div id="ct-tpl-preview" class="border rounded bg-white p-3" style="min-height:480px;max-height:560px;overflow:auto;font-family:Georgia,serif;font-size:14px;line-height:1.5;">
                            <p class="text-muted">Click “Refresh preview” or edit content to render…</p>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-primary mt-2" id="ct-tpl-preview-refresh">Refresh preview</button>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="ct-label d-flex align-items-center" style="gap:8px;">
                            <input type="hidden" name="active" value="0">
                            <input type="checkbox" name="active" value="1" @if(old('active', optional($template)->active ?? true)) checked @endif>
                            Active
                        </label>
                    </div>
                </div>

                <div class="d-flex flex-wrap mt-3" style="gap:8px;">
                    <button type="submit" class="ct-btn"><i class="dripicons-checkmark"></i> {{ $editing ? 'Save Template' : 'Create Template' }}</button>
                    <a href="{{ route('contracts.templates') }}" class="ct-btn-secondary">Back to list</a>
                </div>
            </form>

            @if($editing)
                <form method="POST" action="{{ route('contracts.templates.publish', $template->id) }}" class="mt-2" id="ct-publish-form">
                    @csrf
                    <input type="hidden" name="content_html" id="ct-publish-content" value="">
                    <button type="submit" class="ct-btn-secondary" onclick="document.getElementById('ct-publish-content').value=document.querySelector('[name=content_html]').value;">Publish new version</button>
                </form>
                <p class="text-muted small mt-2 mb-0">Publishing creates a new version. Existing contracts keep their copied text.</p>
            @endif
        </div>
    </div>
</section>
@endsection

@section('scripts')
<script>
(function () {
    var previewUrl = @json(route('contracts.templates.preview'));
    var csrf = $('meta[name="csrf-token"]').attr('content');
    var timer = null;

    function refreshPreview() {
        var html = $('#ct-tpl-content').val() || '';
        $('#ct-tpl-preview').html('<p class="text-muted">Rendering…</p>');
        $.ajax({
            url: previewUrl,
            method: 'POST',
            data: { _token: csrf, content_html: html, name: $('[name=name]').val() },
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
        }).done(function (res) {
            $('#ct-tpl-preview').html(res.html || '<p class="text-muted">Empty template.</p>');
        }).fail(function () {
            $('#ct-tpl-preview').html('<p class="text-danger">Preview failed.</p>');
        });
    }

    $('#ct-tpl-preview-refresh').on('click', refreshPreview);
    $('#ct-tpl-content').on('input', function () {
        clearTimeout(timer);
        timer = setTimeout(refreshPreview, 600);
    });
    $('#ct-tpl-preview-window').on('click', function () {
        var form = document.createElement('form');
        form.method = 'POST';
        form.action = previewUrl;
        form.target = '_blank';
        var token = document.createElement('input');
        token.type = 'hidden'; token.name = '_token'; token.value = csrf;
        var content = document.createElement('input');
        content.type = 'hidden'; content.name = 'content_html'; content.value = $('#ct-tpl-content').val() || '';
        var name = document.createElement('input');
        name.type = 'hidden'; name.name = 'name'; name.value = $('[name=name]').val() || 'Template preview';
        form.appendChild(token); form.appendChild(content); form.appendChild(name);
        document.body.appendChild(form);
        form.submit();
        document.body.removeChild(form);
    });

    if (($('#ct-tpl-content').val() || '').trim() !== '') refreshPreview();

    var clauseUrl = @json(route('contracts.clauses.json'));
    var clauseMap = {};
    $.getJSON(clauseUrl).done(function (rows) {
        var sel = $('#ct-clause-pick');
        (rows || []).forEach(function (c) {
            clauseMap[c.id] = c.body_html || '';
            sel.append($('<option>').val(c.id).text((c.code ? c.code + ' — ' : '') + c.title));
        });
    });
    $('#ct-clause-insert').on('click', function () {
        var id = $('#ct-clause-pick').val();
        if (!id || !clauseMap[id]) return;
        var ta = document.getElementById('ct-tpl-content');
        var insert = '\n' + clauseMap[id] + '\n';
        var start = ta.selectionStart || ta.value.length;
        ta.value = ta.value.slice(0, start) + insert + ta.value.slice(start);
        ta.focus();
        refreshPreview();
    });
})();
</script>
@endsection
