<style>
    .letter-compose {
        --letter-ink: #1f2a44;
        --letter-blue: #0b3f90;
        --letter-gold: #c6ab47;
        --letter-line: #e6ebf5;
    }
    .letter-compose > .container-fluid > .row > .col-md-12 > .btn,
    .letter-compose > .container-fluid > .row > .col-md-12 > a.btn {
        border: 0;
        border-radius: 999px;
        font-weight: 700;
        margin: 0 8px 14px 0;
        padding: 8px 16px;
    }
    .letter-compose > .container-fluid > .row > .col-md-12 > a.btn-info {
        background: var(--letter-blue);
    }
    .letter-compose .card {
        border: 0;
        border-radius: 18px;
        box-shadow: 0 16px 40px rgba(15, 35, 80, 0.08);
        overflow: hidden;
    }
    .letter-compose .card-header {
        background: linear-gradient(100deg, #072f6b 0%, #0b3f90 58%, #1456b8 100%);
        border: 0;
        padding: 16px 22px;
    }
    .letter-compose .card-header h4 {
        margin: 0;
        color: #fff;
        font-weight: 800;
        font-size: 1.2rem;
        letter-spacing: -0.01em;
    }
    .letter-compose .card-header h4::before {
        content: "";
        display: inline-block;
        width: 8px;
        height: 8px;
        margin-right: 10px;
        border-radius: 50%;
        background: var(--letter-gold);
        vertical-align: middle;
    }
    .letter-compose .card-body { padding: 20px 22px 28px; background: #f7f9fc; }
    .letter-compose .italic { display: none; }
    .letter-compose label {
        font-size: 12px;
        font-weight: 700;
        letter-spacing: .02em;
        color: #3d4d6b;
        margin-bottom: 6px;
    }
    .letter-compose .form-control,
    .letter-compose .bootstrap-select > .dropdown-toggle {
        border-radius: 10px;
        border-color: #d7deea;
        min-height: 42px;
    }
    .letter-compose .form-control:focus {
        border-color: var(--letter-blue);
        box-shadow: 0 0 0 3px rgba(11, 63, 144, .12);
    }
    .letter-compose #submit-btn {
        background: var(--letter-blue);
        border: 0;
        border-radius: 999px;
        padding: 10px 28px;
        font-weight: 700;
    }
    .letter-compose-section {
        border: 1px solid var(--letter-line, #e5eaf3);
        border-radius: 12px;
        background: #fff;
        padding: 12px;
        margin-bottom: 14px;
        box-shadow: none;
    }
    .letter-compose-section.letter-editor-compact {
        padding: 8px 10px;
        margin-bottom: 10px;
    }
    .letter-compose-section .section-label {
        display: inline-block;
        font-size: 11px;
        font-weight: 700;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        color: #0b3f90;
        background: #f4f8ff;
        border: 1px solid #d7e4fb;
        border-radius: 999px;
        padding: 3px 10px;
        margin-bottom: 8px;
    }
    .letter-compose-section .section-label.body {
        background: #fff8e8;
        border-color: #f4dfb0;
        color: #8a6400;
    }
    .letter-compose-section .section-label.footer {
        background: #fff1f1;
        border-color: #f2c6c6;
        color: #8a1f1f;
    }
    .letter-editor-wrap .tox-tinymce {
        border-radius: 10px !important;
        border-color: #d7e4fb !important;
    }
    .letter-editor-compact .tox-tinymce {
        height: 45px !important;
        min-height: 45px !important;
        border-radius: 8px !important;
    }
    .letter-editor-compact .tox .tox-editor-header,
    .letter-editor-compact .tox .tox-toolbar-overlord,
    .letter-editor-compact .tox .tox-toolbar__primary {
        background: #f8fafc;
    }
    .letter-editor-compact .tox .tox-tbtn {
        height: 22px;
        width: 22px;
        margin: 0;
    }
    .letter-compose #imageUpload.dropzone {
        min-height: 56px;
        padding: 6px 12px;
        border: 1px dashed #c5d0e4;
        border-radius: 10px;
        background: #f8fafc;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .letter-compose #imageUpload.dropzone .dz-message {
        margin: 6px 0;
        font-size: 13px;
        color: #5c6b86;
    }
    .letter-compose #imageUpload.dropzone.dz-started {
        min-height: 0;
        display: block;
        padding: 8px;
    }
    .letter-compose #imageUpload .dz-preview {
        margin: 6px;
    }
    .letter-compose #imageUpload .dz-preview .dz-image {
        width: 72px;
        height: 72px;
    }
    .letter-compose .letter-recipient-panel {
        background: #fff;
        border: 1px solid var(--letter-line);
        border-radius: 14px;
        padding: 14px 14px 8px;
        box-shadow: 0 1px 0 rgba(15, 35, 80, 0.03);
    }
    .letter-compose .letter-recipient-panel h5 {
        font-size: 15px;
    }
    .letter-compose .card-body .form-group {
        background: #fff;
        border: 1px solid var(--letter-line);
        border-radius: 12px;
        padding: 10px 12px 12px;
        margin-bottom: 12px;
    }
    .letter-compose .modal .form-group {
        background: transparent;
        border: 0;
        border-radius: 0;
        padding: 0;
        margin-bottom: 1rem;
    }
    #product-form .is-invalid,
    #product-form .form-control.is-invalid,
    #product-form select.is-invalid,
    #product-form .bootstrap-select > .dropdown-toggle.is-invalid {
        border-color: #dc3545 !important;
        box-shadow: 0 0 0 0.15rem rgba(220, 53, 69, 0.2);
    }
    #product-form .letter-panel-invalid {
        border: 2px solid #dc3545 !important;
        background: #fff5f5 !important;
        box-shadow: 0 0 0 0.15rem rgba(220, 53, 69, 0.12);
    }
    #product-form .letter-field-error-msg {
        color: #dc3545;
        font-size: 12px;
        font-weight: 600;
        margin-top: 6px;
    }
    #product-form label.letter-label-invalid,
    #product-form .section-label.letter-label-invalid {
        color: #dc3545 !important;
    }
</style>
