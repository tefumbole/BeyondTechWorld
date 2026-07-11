@php
    use App\Support\LetterSignature;
    $editUser = $data->edit_by ? \App\User::find($data->edit_by) : null;
    $approveUser = $data->approved_by ? \App\User::find($data->approved_by) : null;
    $signUser = $data->signed_by ? \App\User::find($data->signed_by) : null;
    $editSrc = LetterSignature::resolveEditSrc($data, $editUser);
    $approveSrc = LetterSignature::resolveApproveSrc($data, $approveUser);
    $signSrc = LetterSignature::resolveSignSrc($data, $signUser);
@endphp
<style>
    .letter-signature-img {
        max-height: 22px;
        width: auto;
        display: inline-block;
        vertical-align: middle;
        background: transparent;
    }
    .letter-signature-img.sign {
        max-height: 36px;
    }
</style>
@if($data->is_edit == 1 && $editSrc)
    <img class="letter-signature-img edit" src="{{ $editSrc }}" alt="Editor signature">
@endif
@if($data->is_approve == 1 && $approveSrc)
    <img class="letter-signature-img approve" src="{{ $approveSrc }}" alt="Approver signature">
@endif
