<div class="id-options">
    <label class="btn btn-outline" for="id_card_file">Attach ID (1 file)</label>
    <button type="button" class="btn btn-outline" id="snap-id-card-btn">Snap ID (front + back)</button>
    <input class="hidden-input" type="file" name="id_card" id="id_card_file" accept="image/*,.pdf,application/pdf">
    <input class="hidden-input" type="file" name="id_card_front" id="id_card_front" accept="image/*" capture="environment">
    <input class="hidden-input" type="file" name="id_card_back" id="id_card_back" accept="image/*" capture="environment">
</div>
<p style="font-size:12px;margin:8px 0 0;color:#c9d4e8;">
    Attach one photo or PDF of the ID, <strong>or</strong> snap the front and then the back with the camera.
</p>
<div id="id-file-name" style="margin-top:8px;font-size:13px;"></div>
