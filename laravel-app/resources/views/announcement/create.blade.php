@extends('layout.main') @section('content')
    @if($errors->has('name'))
        <div class="alert alert-danger alert-dismissible text-center">
            <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>{{ $errors->first('name') }}</div>
    @endif
    @if(session()->has('message'))
        <div class="alert alert-success alert-dismissible text-center"><button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>{{ session()->get('message') }}</div>
    @endif
    @if(session()->has('not_permitted'))
        <div class="alert alert-danger alert-dismissible text-center"><button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>{{ session()->get('not_permitted') }}</div>
    @endif
    <section class="forms">
        <div class="container-fluid">
            <div class="row">
                <div class="col-md-12">
                    @if(in_array("announcement-index", $all_permission))
                        <a href="{{route('announcement.index')}}" class="btn btn-info"><i class="dripicons-list"></i> {{trans('file.Announcement List')}} </a>
                    @endif
                    <div class="card">
                        <div class="card-header d-flex align-items-center">
                            <h4>{{trans('file.Create Announcement')}}</h4>
                        </div>
                        <div class="card-body">
                            <p class="italic"><small>{{trans('file.The field labels marked with * are required input fields')}}.</small></p>
                            <form id="product-form" enctype="multipart/form-data">
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label>Select People Type</label>
                                            <select class="form-control" name="people_type" required>
                                                <option value="">--Choose--</option>
                                                <option value="user">--Employee--</option>
                                                <option value="customer">--Customer--</option>
                                                <option value="csv">--CSV--</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label>{{trans('file.Author Name')}} <strong>*</strong> </label>
                                            <input type="text" name="name" required class="form-control">
                                            <input type="hidden" name="is_active" value="1">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label>{{trans('file.Subject')}} <strong>*</strong> </label>
                                            <input type="text" name="subject" required class="form-control">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label>Schedule Date & Time</label>
                                            <input type="datetime-local" name="date_time" class="form-control">
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-12 users">
                                        <div class="form-group">
                                            <label>{{trans('file.To')}} <strong>*</strong></label>
                                            <select name="to[]" required class="selectpicker form-control to-user" data-select="false" data-live-search="true" multiple>
                                                <option value="">-- Select All --</option>
                                                @foreach($user as $u)
                                                    <option value="{{$u->id}}">{{$u->name}}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-12 customers">
                                        <div class="form-group">
                                            <label>{{trans('file.To')}} <strong>*</strong></label>
                                            <select name="to_customer[]" class="selectpicker form-control to-customer" data-select="false" data-live-search="true" multiple>
                                                <option value="">-- Select All --</option>
                                                @foreach($customer as $u)
                                                    <option value="{{$u->id}}">{{$u->name}}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-6 csv">
                                        <div class="form-group">
                                            <label>{{trans('file.To')}} <strong>*</strong></label>
                                            <input type="file" name="to_csv" required class="form-control to-csv" accept=".csv">
                                            <small style="color:#6c757d;display:block;margin-top:6px;">CSV columns (in order): name, phone_number, email, column1, column2, column3, column4, column5, column6, column7, column8, column9, column10.</small>
                                        </div>
                                    </div>
                                    <div class="col-md-6 csv">
                                        <div class="form-group">
                                            <label>Sample file <strong>*</strong></label>
                                            <a target=_blank"" href="{{ asset('public/sample_file/announcement_csv_sample.csv') }}"><span class="fa fa-download"></span> Download CSV Sample</a>
                                        </div>
                                    </div>
                                    <div class="col-md-12 users">
                                        <div class="form-group">
                                            <label>{{trans('file.CC')}}</label>
                                            <select name="cc[]" class="selectpicker form-control" data-select="false" data-live-search="true" multiple>
                                                <option value="">-- Select All --</option>
                                                @foreach($user as $u)
                                                    <option value="{{$u->id}}">{{$u->name}}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-12 customers customers-cc">
                                        <div class="form-group">
                                            <label>{{trans('file.CC')}} </label>
                                            <select name="cc_customer[]" class="selectpicker form-control" data-select="false" data-live-search="true" multiple>
                                                <option value="">-- Select All --</option>
                                                @foreach($customer as $u)
                                                    <option value="{{$u->id}}">{{$u->name}}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-12">
                                        <div class="form-group">
                                            <label>Attachments </label> <i class="dripicons-question" data-toggle="tooltip" title="{{trans('file.You can upload multiple attachments. Only .jpeg, .jpg, .png, .gif file can be uploaded.')}}"></i>
                                            <div id="imageUpload" class="dropzone"></div>
                                            @if($errors->has('attachments'))
                                                <span>
                                       <strong>{{ $errors->first('attachments') }}</strong>
                                    </span>
                                            @endif
                                        </div>
                                    </div>

                                    <div class="col-md-12">
                                        <div class="form-group">
                                            <label class="bg-success"><b>{{trans('file.Header')}}</b> </label>
                                            <textarea name="header" class="form-control" id="header"></textarea>
                                        </div>
                                    </div>
                                    <div class="col-md-12">
                                        <div class="form-group">
                                            <label class="bg-warning"><b>{{trans('file.Body')}}</b> </label>
                                            <div class="mb-2" style="font-size:12px;color:#6c757d;">
                                                Use placeholders to personalize your message: [name], [phone_number], [email], [column1] .. [column10] in csv.
                                            </div>
                                            <textarea name="body" class="form-control" id="body"></textarea>
                                        </div>
                                    </div>
                                    <div class="col-md-12">
                                        <div class="form-group">
                                            <label class="bg-danger"><b>{{trans('file.Footer')}}</b> </label>
                                            <textarea name="footer" class="form-control" id="footer"></textarea>
                                        </div>
                                    </div>

                                    <div class="col-md-12">
                                        <div class="form-group mt-4">
                                            <input id="submit-btn" value="{{trans('file.submit')}}" class="btn btn-primary">
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </section>

    <script type="text/javascript">

        $(".customers").hide();
        $(".customers-group").hide();
        $(".csv").hide();
        $('select[name="people_type"]').on('change', function() {

            $('.to-customer').prop('required',false);
            $('.to-user').prop('required',false);
            $('.to-csv').prop('required',false);
            $(".customers").hide(300);
            $(".users").hide(300);
            $(".csv").hide(300);
            if ($(this).val() == "user") {
                $('.to-user').prop('required',true);
                $(".users").show(300);
            }else if ($(this).val() == "customer") {
                $('.to-customer').prop('required',true);
                $(".customers").show(300);
            }else if ($(this).val() == "csv") {
                $('.to-csv').prop('required',true);
                $(".csv").show(300);
            }
        });

        $('select[name="customer_type"]').on('change', function() {
            if ($(this).val() == "customer_group") {
                $('.to-customer').prop('required',false);
                $(".customers").hide(300);
                $(".customers-cc").show(300);
                $('.to-customer-group').prop('required',true);
                $(".customers-group").show(300);
            }else{
                $('.to-customer').prop('required',true);
                $(".customers").show(300);
                $('.to-customer-group').prop('required',false);
                $(".customers-group").hide(300);
            }
        });

        $("ul#letter").siblings('a').attr('aria-expanded','true');
        $("ul#letter").addClass("show");
        $("ul#letter #announcement-menu").addClass("active");

        tinymce.init({
            selector: '#header',
            height: 130,
            plugins: [
                'advlist autolink lists link image charmap print preview anchor textcolor',
                'searchreplace visualblocks code fullscreen',
                'insertdatetime media table contextmenu paste code wordcount'
            ],
            toolbar: 'insert | undo redo |  formatselect | bold italic backcolor  | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | removeformat',
            branding:false
        });

        tinymce.init({
            selector: '#body',
            toolbar: 'pasteimage | insert | undo redo |  formatselect | bold italic backcolor  | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | removeformat',
            height: 400,
            skin: 'oxide-dark',
            content_css: 'dark',
            paste_data_images: true,
            automatic_uploads: true,
            plugins: [
                'advlist autolink lists link image charmap print preview anchor textcolor',
                'searchreplace visualblocks code fullscreen','paste image',
                'insertdatetime media table contextmenu paste code wordcount'
            ],
            paste_image_handler: function (blobInfo, success, failure) {
                var formData = new FormData();
                formData.append('image', blobInfo.blob(), blobInfo.filename());

                fetch('/announcement/upload/image', {
                    method: 'POST',
                    body: formData
                })
                    .then(response => response.json())
                    .then(data => {
                        success(data.location);
                    })
                    .catch(error => {
                        console.error('Error uploading image:', error);
                        failure('Image upload failed');
                    });
            }
        });

        tinymce.init({
            name: 'footer',
            selector: '#footer',
            height: 130,
            plugins: [
                'advlist autolink lists link image charmap print preview anchor textcolor',
                'searchreplace visualblocks code fullscreen',
                'insertdatetime media table contextmenu paste code wordcount'
            ],
            toolbar: 'insert | undo redo |  formatselect | bold italic backcolor  | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | removeformat',
            branding:false
        });

        $("select").on("change", function(){
            if ($(this).find(":selected").val() == "") {
                if ($(this).attr("data-select") == "false") {
                    $(this).selectpicker('selectAll');
                    var firstOption = $(this).find('option:first');
                    firstOption.prop('selected', false);
                    $(this).selectpicker('refresh');
                    $(this).attr("data-select", "true");
                } else {
                    $(this).selectpicker('deselectAll');
                    $(this).attr("data-select", "false");
                }
            }
        });

        //dropzone portion
        Dropzone.autoDiscover = false;

        $.ajaxSetup({
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            }
        });

        $(".dropzone").sortable({
            items:'.dz-preview',
            cursor: 'grab',
            opacity: 0.5,
            containment: '.dropzone',
            distance: 20,
            tolerance: 'pointer',
            stop: function () {
                var queue = myDropzone.getAcceptedFiles();
                newQueue = [];
                $('#imageUpload .dz-preview .dz-filename [data-dz-name]').each(function (count, el) {
                    var name = el.innerHTML;
                    queue.forEach(function(file) {
                        if (file.name === name) {
                            newQueue.push(file);
                        }
                    });
                });
                myDropzone.files = newQueue;
            }
        });

        // Intercept paste events on the document
        $(document).on('paste', function(e) {
            var items = (e.clipboardData || e.originalEvent.clipboardData).items;
            for (var index in items) {
                var item = items[index];
                if (item.kind === 'file') {
                    var blob = item.getAsFile();
                    if (blob.type.indexOf('image') !== -1) {
                        var pasteFile = new File([blob], 'pasted-image.png', { type: blob.type });
                        myDropzone.addFile(pasteFile);
                    }
                }
            }
        });

        myDropzone = new Dropzone('div#imageUpload', {
            addRemoveLinks: true,
            autoProcessQueue: false,
            uploadMultiple: true,
            parallelUploads: 100,
            maxFilesize: 12,
            processData: false,
            contentType: false,
            paramName: 'attachments',
            clickable: true,
            method: 'POST',
            url: "{{ route('announcement.store') }}",
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            renameFile: function(file) {
                var dt = new Date();
                var time = dt.getTime();
                return time + file.name;
            },
            acceptedFiles: ".jpeg,.jpg,.png,.gif,.pdf",
            init: function () {
                var myDropzone = this;
                $('#submit-btn').on("click", function (e) {
                    e.preventDefault();
                    var isValid = true;
                    $("#loader").css('display', 'block');

                    $('#product-form [required]').each(function() {
                        if ($(this).val()  == '') {
                            alert('please fill all required fields');
                            isValid = false;
                            $("#loader").css('display', 'none');
                            return false; // Stop the loop when a required field is empty
                        }
                    });
                    if (isValid) {
                        
                        var tinyHeader = tinymce.get('header').getContent();
                        var tinyBody = tinymce.get('body').getContent();
                        var tinyFooter = tinymce.get('footer').getContent();
                        $("#header").val(tinyHeader);
                        $("#body").val(tinyBody);
                        $("#footer").val(tinyFooter);
                        var formData = new FormData($("#product-form")[0]);

                        if (myDropzone.getAcceptedFiles().length) {
                            myDropzone.processQueue();
                        } else {
                            $.ajax({
                                method: 'POST',
                                url: "{{ route('announcement.store') }}",
                                data: formData,
                                processData: false,
                                contentType: false,
                                success: function (response) {
                                    console.log(response);
                                    location.href = "{{ route('announcement.index') }}";
                                },
                                error: function (response) {
                                    console.log(response);
                                },
                            });
                        }
                    }
                });

                this.on('sending', function (file, xhr, formData) {
                    var data = $("#product-form").serializeArray();
                    $.each(data, function (key, el) {
                        formData.append(el.name, el.value);  // 🔴 This line is appending same field name multiple times
                    });
                });
            },
            error: function (file, response) {
                console.log(response);
                if(response.errors.name) {
                    $("#name-error").text(response.errors.name);
                    this.removeAllFiles(true);
                }
                else if(response.errors.code) {
                    $("#code-error").text(response.errors.code);
                    this.removeAllFiles(true);
                }
                else {
                    try {
                        var res = JSON.parse(response);
                        if (typeof res.message !== 'undefined' && !$modal.hasClass('in')) {
                            $("#success-icon").attr("class", "fas fa-thumbs-down");
                            $("#success-text").html(res.message);
                            $modal.modal("show");
                        } else {
                            if ($.type(response) === "string")
                                var message = response; //dropzone sends it's own error messages in string
                            else
                                var message = response.message;
                            file.previewElement.classList.add("dz-error");
                            _ref = file.previewElement.querySelectorAll("[data-dz-errormessage]");
                            _results = [];
                            for (_i = 0, _len = _ref.length; _i < _len; _i++) {
                                node = _ref[_i];
                                _results.push(node.textContent = message);
                            }
                            return _results;
                        }
                    } catch (error) {
                        console.log(error);
                    }
                }
            },
            successmultiple: function (file, response) {
                location.href = "{{ route('announcement.index') }}";
                console.log(file, response);
            },
            completemultiple: function (file, response) {
                console.log(file, response, "completemultiple");
            },
            reset: function () {
                console.log("resetFiles");
                this.removeAllFiles(true);
            }
        });
    </script>
@endsection
