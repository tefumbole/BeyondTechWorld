{{--
    Image field with paste + preview (enhanced globally by image_paste_script).
    Params: $name (file input name), $current (existing image URL or null).
--}}
<input type="file" name="{{ $name }}" accept="image/*" class="form-control-file"
       @if(!empty($current)) data-current="{{ $current }}" @endif>
