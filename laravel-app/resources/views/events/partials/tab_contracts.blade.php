<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span class="font-weight-bold">Event Contracts</span>
        <a href="{{ route('events.settings.contract-templates') }}" class="btn btn-sm btn-outline-secondary">Manage templates</a>
    </div>
    <div class="card-body">
        @if($event->assignments->isEmpty())
            <p class="text-muted mb-0">Assign workers first, then generate contracts.</p>
        @else
            <form method="POST" action="{{ route('events.contracts.generate', $event->id) }}" class="mb-4 border-bottom pb-3">
                @csrf
                <div class="row align-items-end">
                    <div class="col-md-5 form-group mb-0">
                        <label>Assignment</label>
                        <select name="assignment_id" class="form-control" required>
                            @foreach($event->assignments as $a)
                                <option value="{{ $a->id }}">{{ $a->workerProfile->displayName() }} — {{ $a->assignment_role }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4 form-group mb-0">
                        <label>Template</label>
                        <select name="template_id" class="form-control">
                            @foreach($contractTemplates as $t)
                                <option value="{{ $t->id }}">{{ $t->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        @if(in_array('event_contracts.create', $all_permission))
                            <button type="submit" class="btn btn-primary btn-block">Generate contract</button>
                        @endif
                    </div>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0">
                    <thead style="background:#0b3f90;color:#fff;">
                        <tr><th>Ref</th><th>Worker</th><th>Role</th><th>Status</th><th>Actions</th></tr>
                    </thead>
                    <tbody>
                        @foreach($event->contracts as $c)
                            <tr>
                                <td><code>{{ $c->reference_no }}</code></td>
                                <td>{{ optional($c->assignment->workerProfile)->displayName() }}</td>
                                <td>{{ optional($c->assignment)->assignment_role }}</td>
                                <td><span class="badge badge-info">{{ $c->statusLabel() }}</span></td>
                                <td>
                                    <a href="{{ route('events.contracts.preview', $c->id) }}" target="_blank" class="btn btn-xs btn-light">Preview</a>
                                    @if(in_array('event_contracts.send', $all_permission) && in_array($c->status, ['draft','sent']))
                                        <form method="POST" action="{{ route('events.contracts.send', [$event->id, $c->id]) }}" class="d-inline">
                                            @csrf
                                            <button type="submit" class="btn btn-xs btn-success">Send</button>
                                        </form>
                                    @endif
                                    @if(in_array('event_contracts.approve', $all_permission) && $c->status === 'worker_signed')
                                        <a href="{{ route('events.contracts.review', $c->id) }}" class="btn btn-xs btn-warning">Review</a>
                                    @endif
                                    @if($c->signed_pdf_path)
                                        <a href="{{ url($c->signed_pdf_path) }}" target="_blank" class="btn btn-xs btn-primary">PDF</a>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
