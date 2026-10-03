<form method="post" action="{{ route('whatsapp.contact.voice', $contact->id) }}" class="mt-2">
    @csrf
    <p class="small text-muted mb-2">WhatsApp has this number saved as <strong>{{ $contact->wa_name ?: 'no name' }}</strong>. Tell the assistant who they are, even when that saved name is different.</p>
    <div class="form-group">
        <label>Who they are</label>
        <input type="text" name="relationship" class="form-control" maxlength="80" value="{{ old('relationship', $contact->relationship) }}" placeholder="My wife, my brother, my girlfriend">
    </div>
    <div class="form-group">
        <label>What I call them</label>
        <input type="text" name="call_name" class="form-control" maxlength="80" value="{{ old('call_name', $contact->call_name) }}" placeholder="Mii">
    </div>
    <div class="form-group">
        <label>Language they speak</label>
        <input type="text" name="preferred_language" class="form-control" maxlength="80" value="{{ old('preferred_language', $contact->preferred_language) }}" placeholder="French, Pidgin, English">
    </div>
    <div class="form-group">
        <label>How the assistant should speak to them</label>
        <textarea name="voice_note" class="form-control" rows="3" maxlength="1000" placeholder="Warm and short. Do not use their WhatsApp name.">{{ old('voice_note', $contact->voice_note) }}</textarea>
    </div>
    <button class="btn btn-primary btn-sm" type="submit">Save for the assistant</button>
</form>
