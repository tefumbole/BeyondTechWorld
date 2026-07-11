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
        @include('letter.partials.compose_styles')
        <div class="container-fluid">
            <div class="row">
                <div class="col-md-12">
                    @if(in_array("donor-index", $all_permission))
                        <a href="{{route('letter.index')}}" class="btn btn-info"><i class="dripicons-list"></i> {{trans('file.Letters List')}} </a>
                    @endif
                    @if(in_array("customers-add", $all_permission))
                        <button type="button" class="btn btn-default " data-toggle="modal" data-target="#addCustomer"><i class="dripicons-plus"></i> Add Customer</button>
                    @endif
                    @if(in_array("employees-add", $all_permission))
                        <button type="button" class="btn btn-default " data-toggle="modal" data-target="#addEmployee"><i class="dripicons-plus"></i> Add Employee</button>
                    @endif
                    <div class="card">
                        <div class="card-header d-flex align-items-center">
                            <h4>@if(!empty($clone)) Clone Letter @else {{trans('file.Add Letter')}} @endif</h4>
                        </div>
                        <div class="card-body">
                            @php
                                $isClone = !empty($clone);
                                $clonePeopleType = $clonePeopleType ?? '';
                            @endphp
                            <p class="italic"><small>{{trans('file.The field labels marked with * are required input fields')}}.</small></p>
                            <form id="product-form" enctype="multipart/form-data">
                                <div class="row">
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label>Select People Type</label>
                                            <select class="form-control" name="people_type" required>
                                                <option value="">--Choose--</option>
                                                <option value="user" @if($clonePeopleType === 'user') selected @endif>--Employee--</option>
                                                <option value="customer" @if($clonePeopleType === 'customer') selected @endif>--Customer--</option>
                                                <option value="all" @if($clonePeopleType === 'all') selected @endif>-- Select All (Everyone) --</option>
                                                <option value="csv" @if($clonePeopleType === 'csv') selected @endif>--CSV--</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-3 customers customers-cc" @if(!$isClone || $clonePeopleType !== 'customer') style="display:none" @endif>
                                        <div class="form-group">
                                            <label>Select Customer Type</label>
                                            <select class="form-control" name="customer_type">
                                                <option value="customer">-- Customer --</option>
                                                <option value="customer_group">-- Customer Group --</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label>{{trans('file.Letter Category')}} </label>
                                            <select class="form-control" name="category_id">
                                                <option value="">-- Default --</option>
                                                @foreach($category as $cat)
                                                    <option value="{{$cat->id}}" @if(!empty($clone) && $clone->category_id == $cat->id) selected @endif>{{$cat->name}}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label>{{trans('file.Letter Template')}} </label>
                                            <select class="form-control" name="template_id">
                                                <option value="">-- Blank --</option>
                                                @foreach($template as $tem)
                                                    <option value="{{$tem->id}}">{{$tem->subject}}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-12 all-recipients letter-recipient-panel" @if(!$isClone || $clonePeopleType !== 'all') style="display:none" @endif>
                                        <p class="mb-0"><strong>Everyone</strong> — this letter will be sent to all active customers and employees in the system.</p>
                                        <input type="hidden" name="people_type_mode" value="">
                                    </div>
                                    <div class="col-md-12">
                                        <div class="form-group">
                                            <label>{{trans('file.Author Name')}} <strong>*</strong> </label>
                                            <input type="text" name="name" required class="form-control" value="{{ !empty($clone) ? $clone->name : '' }}">
                                            <input type="hidden" name="is_active" value="1">
                                            <input type="hidden" name="is_edit" value="0">
                                            <input type="hidden" name="is_approve" value="0">
                                            <input type="hidden" name="is_sign" value="0">
                                            <input type="hidden" name="is_sent" value="0">
                                        </div>
                                    </div>
                                    <div class="col-md-12 users" @if($isClone && $clonePeopleType !== 'user') style="display:none" @endif>
                                        <div class="form-group">
                                            <label>{{trans('file.To')}} <strong>*</strong></label>
                                            <select name="to[]" @if(!$isClone || $clonePeopleType === 'user') required @endif class="selectpicker form-control to-user" data-select="false" data-live-search="true" multiple>
                                                <option value="">-- Select All --</option>
                                                @foreach($user as $u)
                                                    <option value="{{$u->id}}" @if($clonePeopleType === 'user' && in_array((string) $u->id, $cloneToIds, true)) selected @endif>{{$u->name}}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-12 customers" @if(!$isClone || $clonePeopleType !== 'customer') style="display:none" @endif>
                                        <div class="form-group">
                                            <label>{{trans('file.To')}} <strong>*</strong></label>
                                            <select name="to_customer[]" @if($clonePeopleType === 'customer') required @endif class="selectpicker form-control to-customer" data-select="false" data-live-search="true" multiple>
                                                <option value="">-- Select All --</option>
                                                @foreach($customer as $u)
                                                    <option value="{{$u->id}}" @if($clonePeopleType === 'customer' && in_array((string) $u->id, $cloneToIds, true)) selected @endif>{{$u->name}}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-6 csv" @if(!$isClone || $clonePeopleType !== 'csv') style="display:none" @endif>
                                        <div class="form-group">
                                            <label>{{trans('file.To')}} <strong>*</strong></label>
                                            <input type="file" name="to_csv" required class="form-control to-csv" accept=".csv">
                                            <small style="color:#6c757d;display:block;margin-top:6px;">CSV columns (in order): name, phone_number, email, address, column1, column2, column3, column4, column5, column6, column7, column8, column9, column10.</small>
                                        </div>
                                    </div>
                                    <div class="col-md-6 csv" @if(!$isClone || $clonePeopleType !== 'csv') style="display:none" @endif>
                                        <div class="form-group">
                                            <label>Sample file <strong>*</strong></label>
                                            <a target=_blank"" href="{{ asset('public/sample_file/letter_csv_sample.csv') }}"><span class="fa fa-download"></span> Download CSV Sample</a>
                                        </div>
                                    </div>
                                    <div class="col-md-12 customers-group" style="display:none">
                                        <div class="form-group">
                                            <label>{{trans('file.To')}} <strong>*</strong></label>
                                            <select name="to_customer_group[]" class="selectpicker form-control to-customer-group" data-select="false" data-live-search="true" multiple>
                                                <option value="">-- Select All --</option>
                                                @foreach($customerGroups as $u)
                                                    <option value="{{$u->id}}">{{$u->name}}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-12 users" @if($isClone && $clonePeopleType !== 'user') style="display:none" @endif>
                                        <div class="form-group">
                                            <label>{{trans('file.CC')}}</label>
                                            <select name="cc[]" class="selectpicker form-control" data-select="false" data-live-search="true" multiple>
                                                <option value="">-- Select All --</option>
                                                @foreach($user as $u)
                                                    <option value="{{$u->id}}" @if($clonePeopleType === 'user' && in_array((string) $u->id, $cloneCcIds, true)) selected @endif>{{$u->name}}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-12 customers customers-cc" @if(!$isClone || $clonePeopleType !== 'customer') style="display:none" @endif>
                                        <div class="form-group">
                                            <label>{{trans('file.CC')}} </label>
                                            <select name="cc_customer[]" class="selectpicker form-control" data-select="false" data-live-search="true" multiple>
                                                <option value="">-- Select All --</option>
                                                @foreach($customer as $u)
                                                    <option value="{{$u->id}}" @if($clonePeopleType === 'customer' && in_array((string) $u->id, $cloneCcIds, true)) selected @endif>{{$u->name}}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label>{{trans('file.Subject')}} <strong>*</strong> </label>
                                            <input type="text" name="subject" required class="form-control" value="{{ !empty($clone) ? $clone->subject : '' }}">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label>Schedule Date & Time</label>
                                            <input type="datetime-local" name="date_time" class="form-control" value="{{ !empty($clone) && $clone->date_time ? \Carbon\Carbon::parse($clone->date_time)->format('Y-m-d\TH:i') : '' }}">
                                        </div>
                                    </div>
                                    @if(in_array("forward_letter", $all_permission))
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label>Forward Letter To Any </label>
                                                <select name="forward_letter" class="form-control" data-live-search="true" required>
                                                    <option value="editor">Editor</option>
                                                    <option value="approver">Approver</option>
                                                    <option value="signer">Signer</option>
                                                    <option value="sender">Sender</option>
                                                </select>
                                            </div>
                                        </div>
                                    @endif
                                    {{--                            <div class="col-md-4">--}}
                                    {{--                                <div class="form-group">--}}
                                    {{--                                    <label>Attachment </label>--}}
                                    {{--                                    <input type="file" name="attachment" class="form-control">--}}
                                    {{--                                </div>--}}
                                    {{--                            </div>--}}
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
                                        <div class="letter-compose-section letter-editor-wrap">
                                            <span class="section-label">{{trans('file.Header')}}</span>
                                            <textarea name="header" class="form-control" id="header">@if(!empty($clone)){!! $clone->header !!}@endif</textarea>
                                        </div>
                                    </div>
                                    <div class="col-md-12">
                                        <div class="letter-compose-section letter-editor-wrap">
                                            <span class="section-label body">{{trans('file.Body')}}</span>
                                            <div class="mb-2" style="font-size:12px;color:#6c757d;">
                                                Use placeholders to personalize your message: [name], [phone_number], [email], [column1] .. [column10] in csv.
                                            </div>
                                            <textarea name="body" class="form-control" id="body">@if(!empty($clone)){!! $clone->body !!}@endif</textarea>
                                        </div>
                                    </div>
                                    <div class="col-md-12">
                                        <div class="letter-compose-section letter-editor-wrap">
                                            <span class="section-label footer">{{trans('file.Footer')}}</span>
                                            <textarea name="footer" class="form-control" id="footer">@if(!empty($clone)){!! $clone->footer !!}@endif</textarea>
                                        </div>
                                    </div>
                                    <div class="col-md-12">
                                        <div class="form-group">
                                            <input type="checkbox" name="is_template"> &nbsp;
                                            <label><b>{{trans('file.Is Template')}}</b> </label>
                                        </div>
                                    </div>
                                    <div class="col-md-12"><hr></div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>{{trans('file.Comment')}} </label>
                                            <textarea name="comment" class="form-control" placeholder="{{trans('file.Comment')}}">{{ !empty($clone) ? $clone->comment : '' }}</textarea>
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

        <!-- add customer modal -->
        <div id="addCustomer" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true" class="modal fade text-left">
            <div role="document" class="modal-dialog">
                <div class="modal-content">
                    {!! Form::open(['route' => 'customer.store', 'method' => 'post', 'files' => true]) !!}
                    <div class="modal-header">
                        <h5 id="exampleModalLabel" class="modal-title">{{trans('file.Add Customer')}}</h5>
                        <button type="button" data-dismiss="modal" aria-label="Close" class="close"><span aria-hidden="true"><i class="dripicons-cross"></i></span></button>
                    </div>
                    <div class="modal-body">
                        <p class="italic"><small>{{trans('file.The field labels marked with * are required input fields')}}.</small></p>
                        <div class="form-group">
                            <label>{{trans('file.Customer Group')}} *</strong> </label>
                            <select required class="form-control selectpicker" name="customer_group_id">
                                @foreach($customerGroups as $customer_group)
                                    <option value="{{$customer_group->id}}">{{$customer_group->name}}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group">
                            <label>{{trans('file.name')}} *</strong> </label>
                            <input type="text" name="customer_name" required class="form-control">
                        </div>
                        <div class="form-group">
                            <label>{{trans('file.Phone Number')}} *</label>
                            <input type="text" name="phone_number" required class="form-control" value="237">
                        </div>
                        <div class="form-group">
                            <label>{{trans('file.Email')}}</label>
                            <input type="text" name="email" placeholder="example@example.com" class="form-control">
                        </div>
                        <div class="form-group">
                            <label>{{trans('file.Address')}}</label>
                            <input type="text" name="address" class="form-control">
                        </div>
                        <div class="form-group">
                            <label>{{trans('file.City')}}</label>
                            <input type="text" name="city" class="form-control">
                        </div>
                        <div class="form-group">
                            <input type="hidden" name="letter" value="1">
                            <input type="submit" value="{{trans('file.submit')}}" class="btn btn-primary">
                        </div>
                    </div>
                    {{ Form::close() }}
                </div>
            </div>
        </div>


        <!-- add employee modal -->
        <div id="addEmployee" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true" class="modal fade text-left">
            <div role="document" class="modal-dialog">
                <div class="modal-content">
                    {!! Form::open(['route' => 'employees.store.letter', 'method' => 'post', 'files' => true]) !!}
                    <div class="modal-header">
                        <h5 id="exampleModalLabel" class="modal-title">{{trans('file.Add Employee')}}</h5>
                        <button type="button" data-dismiss="modal" aria-label="Close" class="close"><span aria-hidden="true"><i class="dripicons-cross"></i></span></button>
                    </div>
                    <div class="modal-body">
                        <p class="italic"><small>{{trans('file.The field labels marked with * are required input fields')}}.</small></p>
                        <div class="form-group">
                            <label>Departments *</strong> </label>
                            <select required class="form-control selectpicker" name="department_id">
                                @foreach($departments as $department)
                                    <option value="{{$department->id}}">{{$department->name}}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group">
                            <label>{{trans('file.name')}} *</strong> </label>
                            <input type="text" name="name" required class="form-control">
                        </div>
                        <div class="form-group">
                            <label>{{trans('file.Image')}}</label>
                            <input type="file" name="image" class="form-control">
                            @if($errors->has('image'))
                                <span>
                               <strong>{{ $errors->first('image') }}</strong>
                            </span>
                            @endif
                        </div>
                        <div class="form-group">
                            <label>{{trans('file.Email')}} *</label>
                            <input type="email" name="email" placeholder="example@example.com" required class="form-control">
                            @if($errors->has('email'))
                                <span>
                               <strong>{{ $errors->first('email') }}</strong>
                            </span>
                            @endif
                        </div>
                        <div class="form-group">
                            <label>{{trans('file.Phone Number')}} *</label>
                            <input type="text" name="phone_number" required class="form-control">
                        </div>
                        <div class="form-group">
                            <label>{{trans('file.Address')}}</label>
                            <input type="text" name="address" class="form-control">
                        </div>
                        <div class="form-group">
                            <label>{{trans('file.City')}}</label>
                            <input type="text" name="city" class="form-control">
                        </div>
                        <div class="form-group">
                            <label>{{trans('file.Country')}}</label>
                            <input type="text" name="country" class="form-control">
                        </div>
                        <div class="form-group">
                            <input type="submit" value="{{trans('file.submit')}}" class="btn btn-primary">
                        </div>
                    </div>
                    {{ Form::close() }}
                </div>
            </div>
        </div>

    </section>

    <script type="text/javascript">

        $(document).ready(function() {
            $('select[name="template_id"]').on('change', function() {
                var url = "/letters/template/info/"
                var id = $(this).val();
                url = url.concat(id);
                $.get(url, function(data) {
                    $("input[name='subject']").val(data['subject']);
                    $("input[name='name']").val(data['name']);
                    tinymce.get("header").setContent(data['header']);
                    tinymce.get("body").setContent(data['body']);
                    tinymce.get("footer").setContent(data['footer']);
                });
            });
        })


        @if(empty($clone))
        $(".customers").hide();
        $(".customers-group").hide();
        $(".csv").hide();
        $(".all-recipients").hide();
        @endif
        $('select[name="people_type"]').on('change', function() {

            $('.to-customer').prop('required',false);
            $('.to-user').prop('required',false);
            $('.to-csv').prop('required',false);
            $(".customers").hide(300);
            $(".users").hide(300);
            $(".csv").hide(300);
            $(".all-recipients").hide(300);
            $('input[name="people_type_mode"]').val('');
            if ($(this).val() == "user") {
                $('.to-user').prop('required',true);
                $(".users").show(300);
            }else if ($(this).val() == "customer") {
                $('.to-customer').prop('required',true);
                $(".customers").show(300);
            }else if ($(this).val() == "all") {
                $(".all-recipients").show(300);
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
        $("ul#letter #letter-create-menu").addClass("active");

        function initLetterEditors() {
            if (typeof tinymce === 'undefined') {
                console.error('TinyMCE failed to load. Check /public/vendor/tinymce assets.');
                $('#header, #body, #footer').addClass('form-control').css({
                    minHeight: '160px',
                    width: '100%',
                    padding: '12px',
                    border: '1px solid #d7e4fb',
                    borderRadius: '10px'
                });
                return;
            }

            var letterEditorDefaults = {
                plugins: 'advlist autolink lists link image charmap preview anchor searchreplace visualblocks code fullscreen insertdatetime media table wordcount',
                toolbar: 'undo redo | formatselect | bold italic underline | forecolor backcolor | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | link image | removeformat',
                branding: false,
                menubar: false,
                statusbar: true,
                resize: true,
                content_style: 'body { font-family: Nunito, sans-serif; font-size: 15px; color: #1f2a44; }'
            };

            tinymce.init(Object.assign({}, letterEditorDefaults, {
                selector: '#header',
                height: 160
            }));

            tinymce.init(Object.assign({}, letterEditorDefaults, {
                selector: '#body',
                height: 360,
                paste_data_images: true,
                automatic_uploads: true,
                images_upload_handler: function (blobInfo, success, failure) {
                    var formData = new FormData();
                    formData.append('image', blobInfo.blob(), blobInfo.filename());

                    fetch('/letters/upload/image', {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                        },
                        body: formData
                    })
                        .then(function(response) { return response.json(); })
                        .then(function(data) {
                            if (data.location) {
                                success(data.location);
                            } else {
                                failure('Image upload failed');
                            }
                        })
                        .catch(function() {
                            failure('Image upload failed');
                        });
                }
            }));

            tinymce.init(Object.assign({}, letterEditorDefaults, {
                selector: '#footer',
                height: 160
            }));
        }

        initLetterEditors();

        $("select").on("change", function(){
            if ($(this).find(":selected").val() == "") {
                if ($(this).attr("data-select") == "false") {
                    $(this).selectpicker('selectAll');
                    var firstOption = $(this).find('option:first');
                    firstOption.prop('selected', false);
                    $(this).selectpicker('refresh');
                    $(this).attr("data-select", "true");
                    if ($(this).hasClass('to-user')) {
                        $('input[name="people_type_mode"]').val('all_employees');
                    }
                    if ($(this).hasClass('to-customer')) {
                        $('input[name="people_type_mode"]').val('all_customers');
                    }
                } else {
                    $(this).selectpicker('deselectAll');
                    $(this).attr("data-select", "false");
                    if ($(this).hasClass('to-user') || $(this).hasClass('to-customer')) {
                        $('input[name="people_type_mode"]').val('');
                    }
                }
            }
        });

        @if(!empty($clone))
        $(function() {
            var clonePeopleType = @json($clonePeopleType);
            if (clonePeopleType) {
                $('select[name="people_type"]').val(clonePeopleType).trigger('change');
            }
            $('.selectpicker').selectpicker('refresh');
        });
        @endif

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
            url: "{{ route('letter.store') }}",
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                'X-Requested-With': 'XMLHttpRequest'
            },
            renameFile: function(file) {
                var dt = new Date();
                var time = dt.getTime();
                return time + file.name;
            },
            acceptedFiles: ".jpeg,.jpg,.png,.gif,.pdf",
            init: function () {
                var myDropzone = this;
                var letterSubmitting = false;
                var submitLabel = $('#submit-btn').val();

                function syncLetterEditors() {
                    var tinyHeader = (tinymce.get('header') ? tinymce.get('header').getContent() : $("#header").val());
                    var tinyBody = (tinymce.get('body') ? tinymce.get('body').getContent() : $("#body").val());
                    var tinyFooter = (tinymce.get('footer') ? tinymce.get('footer').getContent() : $("#footer").val());
                    $("#header").val(tinyHeader);
                    $("#body").val(tinyBody);
                    $("#footer").val(tinyFooter);
                }

                function finishLetterSubmit(success, message) {
                    letterSubmitting = false;
                    $("#loader").css('display', 'none');
                    $('#submit-btn').prop('disabled', false).val(submitLabel);
                    if (success) {
                        window.location.href = "{{ route('letter.index') }}";
                    } else if (message) {
                        alert(message);
                    }
                }

                function validateLetterForm() {
                    var isValid = true;
                    $('#product-form').find('[required]:visible').each(function() {
                        if ($(this).val() === '' || $(this).val() === null) {
                            alert('Please fill all required fields');
                            isValid = false;
                            return false;
                        }
                    });
                    return isValid;
                }

                $('#submit-btn').on("click", function (e) {
                    e.preventDefault();
                    if (letterSubmitting) {
                        return false;
                    }

                    if (!validateLetterForm()) {
                        return false;
                    }

                    letterSubmitting = true;
                    $('#submit-btn').prop('disabled', true).val('Submitting...');
                    $("#loader").css('display', 'block');
                    syncLetterEditors();

                    if (myDropzone.getAcceptedFiles().length) {
                        myDropzone.processQueue();
                        return;
                    }

                    var formData = new FormData($("#product-form")[0]);
                    $.ajax({
                        method: 'POST',
                        url: "{{ route('letter.store') }}",
                        data: formData,
                        processData: false,
                        contentType: false,
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        success: function (response) {
                            if (response && response.success) {
                                finishLetterSubmit(true);
                            } else {
                                finishLetterSubmit(false, (response && response.message) ? response.message : 'Failed to save letter.');
                            }
                        },
                        error: function (xhr) {
                            var message = 'Failed to save letter. Please try again.';
                            if (xhr.responseJSON && xhr.responseJSON.message) {
                                message = xhr.responseJSON.message;
                            }
                            finishLetterSubmit(false, message);
                        }
                    });
                });

                this.on('sending', function (file, xhr, formData) {
                    syncLetterEditors();
                    var data = $("#product-form").serializeArray();
                    $.each(data, function (key, el) {
                        formData.append(el.name, el.value);
                    });
                    var fileInput = $(".to-csv")[0];
                    if (fileInput && fileInput.files && fileInput.files[0]) {
                        formData.append('to_csv', fileInput.files[0]);
                    }
                });
            },
            error: function (file, response) {
                $("#loader").css('display', 'none');
                $('#submit-btn').prop('disabled', false);
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
                var payload = response;
                if (typeof response === 'string') {
                    try {
                        payload = JSON.parse(response);
                    } catch (e) {
                        payload = null;
                    }
                }
                if (payload && payload.success) {
                    window.location.href = "{{ route('letter.index') }}";
                    return;
                }
                $("#loader").css('display', 'none');
                $('#submit-btn').prop('disabled', false);
                alert((payload && payload.message) ? payload.message : 'Failed to save letter.');
            },
            errormultiple: function () {
                $("#loader").css('display', 'none');
                $('#submit-btn').prop('disabled', false);
                alert('Failed to upload attachments. Please try again.');
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
