@php
    $pub = $event->publication;
    $publicUrl = $event->slug ? url('/events/' . $event->slug) : null;
@endphp
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap">
        <span class="font-weight-bold">Public Website Information</span>
        @if($publicUrl)
            <a href="{{ $publicUrl }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-info">Preview public page</a>
        @endif
    </div>
    <div class="card-body">
        <form method="POST" action="{{ route('events.publication.update', $event->id) }}" enctype="multipart/form-data">
            @csrf
            <div class="row">
                <div class="col-md-4 form-group">
                    <label class="font-weight-bold">Publish on website</label>
                    <select name="publish_on_website" class="form-control">
                        <option value="0" {{ !optional($pub)->publish_on_website ? 'selected' : '' }}>No</option>
                        <option value="1" {{ optional($pub)->publish_on_website ? 'selected' : '' }}>Yes</option>
                    </select>
                </div>
                <div class="col-md-4 form-group">
                    <label>Public URL slug</label>
                    <input type="text" name="public_slug" class="form-control" value="{{ $event->slug }}">
                    <small class="text-muted">/events/{{ $event->slug }}</small>
                </div>
                <div class="col-md-4 form-group">
                    <label>Featured event</label>
                    <select name="is_featured" class="form-control">
                        <option value="0" {{ !optional($pub)->is_featured ? 'selected' : '' }}>No</option>
                        <option value="1" {{ optional($pub)->is_featured ? 'selected' : '' }}>Yes</option>
                    </select>
                </div>
                <div class="col-md-8 form-group">
                    <label>Public title</label>
                    <input type="text" name="public_title" class="form-control" value="{{ old('public_title', optional($pub)->public_title ?: $event->name) }}">
                </div>
                <div class="col-md-4 form-group">
                    <label>Display order</label>
                    <input type="number" name="display_order" class="form-control" value="{{ old('display_order', optional($pub)->display_order ?? 0) }}" min="0">
                </div>
                <div class="col-12 form-group">
                    <label>Short summary</label>
                    <textarea name="public_summary" class="form-control" rows="2" maxlength="500">{{ old('public_summary', optional($pub)->public_summary) }}</textarea>
                </div>
                <div class="col-12 form-group">
                    <label>Full public description</label>
                    <textarea name="public_description" class="form-control" rows="6">{{ old('public_description', optional($pub)->public_description ?: $event->internal_description) }}</textarea>
                    <small class="text-muted">Basic HTML allowed. Internal notes and worker details are never shown publicly.</small>
                </div>
                <div class="col-md-6 form-group">
                    <label>Public flyer (optional override)</label>
                    <input type="file" name="public_flyer" class="form-control-file" accept="image/*">
                </div>
                <div class="col-md-3 form-group">
                    <label>Public venue</label>
                    <input type="text" name="public_venue" class="form-control" value="{{ old('public_venue', optional($pub)->public_venue ?: $event->venue) }}">
                </div>
                <div class="col-md-3 form-group">
                    <label>Public location</label>
                    <input type="text" name="public_location" class="form-control" value="{{ old('public_location', optional($pub)->public_location ?: $event->city) }}">
                </div>
                <div class="col-md-4 form-group">
                    <label>Visibility date & time</label>
                    <input type="text" name="visibility_at" class="form-control datetime-picker" value="{{ optional(optional($pub)->visibility_at)->format('Y-m-d H:i') }}">
                </div>
                <div class="col-md-4 form-group">
                    <label>Unpublish date (optional)</label>
                    <input type="text" name="unpublish_at" class="form-control datetime-picker" value="{{ optional(optional($pub)->unpublish_at)->format('Y-m-d H:i') }}">
                </div>
                <div class="col-md-4 form-group">
                    <label>Public status override</label>
                    <select name="public_status_override" class="form-control">
                        <option value="">Auto-detect</option>
                        @foreach($publicStatuses as $k => $label)
                            <option value="{{ $k }}" {{ optional($pub)->public_status_override == $k ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4 form-group">
                    <label>Registration link</label>
                    <input type="url" name="registration_url" class="form-control" value="{{ optional($pub)->registration_url }}">
                </div>
                <div class="col-md-4 form-group">
                    <label>Ticket link</label>
                    <input type="url" name="ticket_url" class="form-control" value="{{ optional($pub)->ticket_url }}">
                </div>
                <div class="col-md-4 form-group">
                    <label>External website</label>
                    <input type="url" name="external_url" class="form-control" value="{{ optional($pub)->external_url }}">
                </div>
            </div>

            <hr>
            <h6 class="font-weight-bold text-primary">Countdown</h6>
            <div class="row">
                <div class="col-md-3 form-group">
                    <label>Show countdown</label>
                    <select name="show_countdown" class="form-control">
                        <option value="0" {{ !optional($pub)->show_countdown ? 'selected' : '' }}>No</option>
                        <option value="1" {{ optional($pub)->show_countdown ? 'selected' : '' }}>Yes</option>
                    </select>
                </div>
                <div class="col-md-3 form-group">
                    <label>Countdown target</label>
                    <select name="countdown_target_type" class="form-control">
                        @foreach($countdownTargets as $k => $label)
                            <option value="{{ $k }}" {{ optional($pub)->countdown_target_type == $k ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3 form-group">
                    <label>Custom countdown date</label>
                    <input type="text" name="countdown_custom_at" class="form-control datetime-picker" value="{{ optional(optional($pub)->countdown_custom_at)->format('Y-m-d H:i') }}">
                </div>
                <div class="col-md-3 form-group">
                    <label>Countdown visible from</label>
                    <input type="text" name="countdown_visible_from" class="form-control datetime-picker" value="{{ optional(optional($pub)->countdown_visible_from)->format('Y-m-d H:i') }}">
                </div>
                <div class="col-md-6 form-group">
                    <label>Completion message</label>
                    <input type="text" name="countdown_completion_message" class="form-control" value="{{ optional($pub)->countdown_completion_message }}" placeholder="Event Starting Now">
                </div>
                <div class="col-md-3 form-group">
                    <label>Show event time publicly</label>
                    <select name="show_event_time" class="form-control">
                        <option value="1" {{ optional($pub)->show_event_time !== false ? 'selected' : '' }}>Yes</option>
                        <option value="0" {{ optional($pub)->show_event_time === false ? 'selected' : '' }}>No</option>
                    </select>
                </div>
                <div class="col-md-3 form-group">
                    <label>Hide countdown after zero</label>
                    <select name="hide_countdown_after_completion" class="form-control">
                        <option value="1" {{ optional($pub)->hide_countdown_after_completion !== false ? 'selected' : '' }}>Yes</option>
                        <option value="0" {{ optional($pub)->hide_countdown_after_completion === false ? 'selected' : '' }}>No</option>
                    </select>
                </div>
                <div class="col-12 form-group">
                    <label>Public announcement</label>
                    <textarea name="public_announcement" class="form-control" rows="2">{{ optional($pub)->public_announcement }}</textarea>
                </div>
            </div>

            <button type="submit" class="btn btn-primary">Save public settings</button>
        </form>

        <div class="mt-3 d-flex gap-2 flex-wrap">
            @if(in_array('events.publish', $all_permission) || in_array('events.manage_publication', $all_permission))
                <form method="POST" action="{{ route('events.publish', $event->id) }}" class="d-inline">
                    @csrf
                    <button type="submit" class="btn btn-success">Publish now</button>
                </form>
            @endif
            @if(in_array('events.unpublish', $all_permission) || in_array('events.manage_publication', $all_permission))
                <form method="POST" action="{{ route('events.unpublish', $event->id) }}" class="d-inline">
                    @csrf
                    <button type="submit" class="btn btn-outline-danger">Unpublish</button>
                </form>
            @endif
            <span class="badge badge-secondary align-self-center ml-2">Status: {{ optional($pub)->publication_status ?? 'draft' }}</span>
        </div>
    </div>
</div>
