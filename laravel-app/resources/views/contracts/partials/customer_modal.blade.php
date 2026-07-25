<div class="modal fade" id="ct-add-customer-modal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Create customer</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="ct-qc-target-prefix" value="">
                <div class="form-group">
                    <label>Customer group</label>
                    <select id="ct-qc-group" class="form-control">
                        @foreach($customerGroups ?? [] as $g)
                            <option value="{{ $g->id }}">{{ $g->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label>Name *</label>
                    <input type="text" id="ct-qc-name" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Company name</label>
                    <input type="text" id="ct-qc-company" class="form-control">
                </div>
                <div class="form-group">
                    <label>Phone *</label>
                    <input type="text" id="ct-qc-phone" class="form-control phone-sanitize" required>
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" id="ct-qc-email" class="form-control">
                </div>
                <div class="form-group">
                    <label>Address</label>
                    <input type="text" id="ct-qc-address" class="form-control">
                </div>
                <div class="alert alert-danger d-none" id="ct-qc-error"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="ct-qc-save">Save customer</button>
            </div>
        </div>
    </div>
</div>
