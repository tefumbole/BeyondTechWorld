{{-- Shared Delete Multiple button + select-all / show-hide helpers for letter lists --}}
@if(in_array('letter_delete', $all_permission ?? []))
    <button type="submit"
            class="btn btn-danger letter-delete-multiple"
            formaction="{{ route('letter.multiple.delete') }}"
            formmethod="POST"
            style="display:none"
            onclick="return confirm('Delete selected letters? This cannot be undone.');">
        <i class="dripicons-trash"></i> Delete Multiple
    </button>
@endif
<script>
(function () {
    function syncLetterMultiButtons() {
        var any = $('.checkbox-options:checked').length > 0;
        if (any) {
            $('.approve-btn').show();
            $('.letter-delete-multiple').show();
        } else {
            $('.approve-btn').hide();
            $('.letter-delete-multiple').hide();
        }
        var $all = $('.letter-check-all');
        if ($all.length) {
            var total = $('.checkbox-options').length;
            var checked = $('.checkbox-options:checked').length;
            $all.prop('checked', total > 0 && checked === total);
            $all.prop('indeterminate', checked > 0 && checked < total);
        }
    }
    $(document).off('click.letterMultiDelete change.letterMultiDelete');
    $(document).on('click.letterMultiDelete change.letterMultiDelete', '.checkbox-options', syncLetterMultiButtons);
    $(document).on('change.letterMultiDelete', '.letter-check-all', function () {
        var on = $(this).is(':checked');
        $('.checkbox-options').prop('checked', on);
        syncLetterMultiButtons();
    });
})();
</script>
