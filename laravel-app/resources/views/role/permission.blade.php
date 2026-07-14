@extends('layout.main')
@section('content')
@if(session()->has('not_permitted'))
  <div class="alert alert-danger alert-dismissible text-center"><button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>{{ session()->get('not_permitted') }}</div>
@endif
<style>
    .permission-table thead th {
        background: #0b3f90;
        color: #fff;
        border-color: #0a3578;
        vertical-align: middle;
    }
    .permission-table tbody td,
    .permission-table tbody th {
        border-color: #e5eaf3;
    }
    tr.permission-section-header th {
        background: linear-gradient(90deg, #0b3f90 0%, #1456b8 100%);
        color: #fff;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        font-weight: 700;
        padding: 12px 16px;
    }
    tr.permission-section-header .checkbox label,
    tr.permission-section-header .checkbox input + label {
        color: #fff;
        font-weight: 700;
        margin-bottom: 0;
    }
    tr.permission-section-end th {
        background: #f4b41a;
        color: #0b3f90;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.06em;
    }
    .permission-table .checkbox input[type="checkbox"] {
        accent-color: #0b3f90;
    }
    .permission-subgroup {
        background: #f8fbff;
        border-left: 4px solid #f4b41a;
    }
    .permission-subgroup td:first-child {
        padding-left: 18px;
        font-weight: 600;
        color: #0b3f90;
    }
</style>
<section class="forms">
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header d-flex align-items-center">
                        <h4>{{trans('file.Group Permission')}}</h4>
                    </div>
                    {!! Form::open(['route' => 'role.setPermission', 'method' => 'post']) !!}
                    <div class="card-body">
                    	<input type="hidden" name="role_id" value="{{$lims_role_data->id}}" />
						<div class="table-responsive">
						    <table class="table table-bordered permission-table">
						        <thead>
						        <tr>
						            <th colspan="5" class="text-center">{{$lims_role_data->name}} {{trans('file.Group Permission')}}</th>
						        </tr>
						        <tr>
						            <th rowspan="2" class="text-center">Module Name</th>
						            <th colspan="4" class="text-center">
						            	<div class="checkbox">
						            		<input type="checkbox" id="select_all">
						            		<label for="select_all">{{trans('file.Permissions')}}</label>
						            	</div>
						            </th>
						        </tr>
						        <tr>
						            <th class="text-center">{{trans('file.View')}}</th>
						            <th class="text-center">{{trans('file.add')}}</th>
						            <th class="text-center">{{trans('file.edit')}}</th>
						            <th class="text-center">{{trans('file.delete')}}</th>
						        </tr>
						        </thead>
						        <tbody>
                                <tr>
                                    <td>{{trans('file.order')}}</td>
                                    <td class="text-center">
                                        <div class="icheckbox_square-blue checked" aria-checked="false" aria-disabled="false">
                                            <div class="checkbox">
                                                @if(in_array("orders-index", $all_permission))
                                                    <input type="checkbox" value="1" id="orders-index" name="orders-index" checked />
                                                @else
                                                    <input type="checkbox" value="1" id="orders-index" name="orders-index" />
                                                @endif
                                                <label for="orders-index"></label>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <div class="icheckbox_square-blue" aria-checked="false" aria-disabled="false">
                                            <div class="checkbox">
                                                @if(in_array("orders-add", $all_permission))
                                                    <input type="checkbox" value="1" id="orders-add" name="orders-add" checked>
                                                @else
                                                    <input type="checkbox" value="1" id="orders-add" name="orders-add">
                                                @endif
                                                <label for="orders-add"></label>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <div class="icheckbox_square-blue" aria-checked="false" aria-disabled="false">
                                            <div class="checkbox">
                                                @if(in_array("orders-edit", $all_permission))
                                                    <input type="checkbox" value="1" id="orders-edit" name="orders-edit" checked />
                                                @else
                                                    <input type="checkbox" value="1" id="orders-edit" name="orders-edit" />
                                                @endif
                                                <label for="orders-edit"></label>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <div class="icheckbox_square-blue" aria-checked="false" aria-disabled="false">
                                            <div class="checkbox">
                                                @if(in_array("orders-delete", $all_permission))
                                                    <input type="checkbox" value="1" id="orders-delete" name="orders-delete" checked />
                                                @else
                                                    <input type="checkbox" value="1" id="orders-delete" name="orders-delete" />
                                                @endif
                                                <label for="orders-delete"></label>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                                <tr>
                                    <td>Shops</td>
                                    <td class="text-center">
                                        <div class="icheckbox_square-blue checked" aria-checked="false" aria-disabled="false">
                                            <div class="checkbox">
                                                @if(in_array("shops-index", $all_permission))
                                                    <input type="checkbox" value="1" id="shops-index" name="shops-index" checked />
                                                @else
                                                    <input type="checkbox" value="1" id="shops-index" name="shops-index" />
                                                @endif
                                                <label for="shops-index"></label>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <div class="icheckbox_square-blue" aria-checked="false" aria-disabled="false">
                                            <div class="checkbox">
                                                @if(in_array("shops-add", $all_permission))
                                                    <input type="checkbox" value="1" id="shops-add" name="shops-add" checked>
                                                @else
                                                    <input type="checkbox" value="1" id="shops-add" name="shops-add">
                                                @endif
                                                <label for="shops-add"></label>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <div class="icheckbox_square-blue" aria-checked="false" aria-disabled="false">
                                            <div class="checkbox">
                                                @if(in_array("shops-edit", $all_permission))
                                                    <input type="checkbox" value="1" id="shops-edit" name="shops-edit" checked />
                                                @else
                                                    <input type="checkbox" value="1" id="shops-edit" name="shops-edit" />
                                                @endif
                                                <label for="shops-edit"></label>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <div class="icheckbox_square-blue" aria-checked="false" aria-disabled="false">
                                            <div class="checkbox">
                                                @if(in_array("shops-delete", $all_permission))
                                                    <input type="checkbox" value="1" id="shops-delete" name="shops-delete" checked />
                                                @else
                                                    <input type="checkbox" value="1" id="shops-delete" name="shops-delete" />
                                                @endif
                                                <label for="shops-delete"></label>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                                <tr>
                                    <td>Donations</td>
                                    <td class="text-center">
                                        <div class="icheckbox_square-blue checked" aria-checked="false" aria-disabled="false">
                                            <div class="checkbox">
                                                @if(in_array("donations-index", $all_permission))
                                                    <input type="checkbox" value="1" id="donations-index" name="donations-index" checked />
                                                @else
                                                    <input type="checkbox" value="1" id="donations-index" name="donations-index" />
                                                @endif
                                                <label for="donations-index"></label>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <div class="icheckbox_square-blue" aria-checked="false" aria-disabled="false">
                                            <div class="checkbox">
                                                @if(in_array("donations-add", $all_permission))
                                                    <input type="checkbox" value="1" id="donations-add" name="donations-add" checked>
                                                @else
                                                    <input type="checkbox" value="1" id="donations-add" name="donations-add">
                                                @endif
                                                <label for="donations-add"></label>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <div class="icheckbox_square-blue" aria-checked="false" aria-disabled="false">
                                            <div class="checkbox">
                                                @if(in_array("donations-edit", $all_permission))
                                                    <input type="checkbox" value="1" id="donations-edit" name="donations-edit" checked />
                                                @else
                                                    <input type="checkbox" value="1" id="donations-edit" name="donations-edit" />
                                                @endif
                                                <label for="donations-edit"></label>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <div class="icheckbox_square-blue" aria-checked="false" aria-disabled="false">
                                            <div class="checkbox">
                                                @if(in_array("donations-delete", $all_permission))
                                                    <input type="checkbox" value="1" id="donations-delete" name="donations-delete" checked />
                                                @else
                                                    <input type="checkbox" value="1" id="donations-delete" name="donations-delete" />
                                                @endif
                                                <label for="donations-delete"></label>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                                <tr>
                                    <td>Services</td>
                                    <td class="text-center">
                                        <div class="icheckbox_square-blue checked" aria-checked="false" aria-disabled="false">
                                            <div class="checkbox">
                                                @if(in_array("services-index", $all_permission))
                                                    <input type="checkbox" value="1" id="services-index" name="services-index" checked />
                                                @else
                                                    <input type="checkbox" value="1" id="services-index" name="services-index" />
                                                @endif
                                                <label for="services-index"></label>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <div class="icheckbox_square-blue" aria-checked="false" aria-disabled="false">
                                            <div class="checkbox">
                                                @if(in_array("services-add", $all_permission))
                                                    <input type="checkbox" value="1" id="services-add" name="services-add" checked>
                                                @else
                                                    <input type="checkbox" value="1" id="services-add" name="services-add">
                                                @endif
                                                <label for="services-add"></label>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <div class="icheckbox_square-blue" aria-checked="false" aria-disabled="false">
                                            <div class="checkbox">
                                                @if(in_array("services-edit", $all_permission))
                                                    <input type="checkbox" value="1" id="services-edit" name="services-edit" checked />
                                                @else
                                                    <input type="checkbox" value="1" id="services-edit" name="services-edit" />
                                                @endif
                                                <label for="services-edit"></label>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <div class="icheckbox_square-blue" aria-checked="false" aria-disabled="false">
                                            <div class="checkbox">
                                                @if(in_array("services-delete", $all_permission))
                                                    <input type="checkbox" value="1" id="services-delete" name="services-delete" checked />
                                                @else
                                                    <input type="checkbox" value="1" id="services-delete" name="services-delete" />
                                                @endif
                                                <label for="services-delete"></label>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                                <tr>
                                    <td>Payments</td>
                                    <td class="text-center">
                                        <div class="icheckbox_square-blue checked" aria-checked="false" aria-disabled="false">
                                            <div class="checkbox">
                                                @if(in_array("payments-index", $all_permission))
                                                    <input type="checkbox" value="1" id="payments-index" name="payments-index" checked />
                                                @else
                                                    <input type="checkbox" value="1" id="payments-index" name="payments-index" />
                                                @endif
                                                <label for="payments-index"></label>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <div class="icheckbox_square-blue" aria-checked="false" aria-disabled="false">
                                            <div class="checkbox">
                                                @if(in_array("payments-add", $all_permission))
                                                    <input type="checkbox" value="1" id="payments-add" name="payments-add" checked>
                                                @else
                                                    <input type="checkbox" value="1" id="payments-add" name="payments-add">
                                                @endif
                                                <label for="payments-add"></label>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <div class="icheckbox_square-blue" aria-checked="false" aria-disabled="false">
                                            <div class="checkbox">
                                                @if(in_array("payments-edit", $all_permission))
                                                    <input type="checkbox" value="1" id="payments-edit" name="payments-edit" checked />
                                                @else
                                                    <input type="checkbox" value="1" id="payments-edit" name="payments-edit" />
                                                @endif
                                                <label for="payments-edit"></label>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <div class="icheckbox_square-blue" aria-checked="false" aria-disabled="false">
                                            <div class="checkbox">
                                                @if(in_array("payments-delete", $all_permission))
                                                    <input type="checkbox" value="1" id="payments-delete" name="payments-delete" checked />
                                                @else
                                                    <input type="checkbox" value="1" id="payments-delete" name="payments-delete" />
                                                @endif
                                                <label for="payments-delete"></label>
                                            </div>
                                        </div>
                                    </td>
                                </tr>

                                <tr>
						            <td>{{trans('file.product')}}</td>
						            <td class="text-center">
						                <div class="icheckbox_square-blue checked" aria-checked="false" aria-disabled="false">
							                <div class="checkbox">
								                @if(in_array("products-index", $all_permission))
								                <input type="checkbox" value="1" id="products-index" name="products-index" checked />
								                @else
								                <input type="checkbox" value="1" id="products-index" name="products-index" />
								                @endif
								                <label for="products-index"></label>
							            	</div>
						            	</div>
						            </td>
						            <td class="text-center">
						                <div class="icheckbox_square-blue" aria-checked="false" aria-disabled="false">
							                <div class="checkbox">
								                @if(in_array("products-add", $all_permission))
								               	<input type="checkbox" value="1" id="products-add" name="products-add" checked>
								                @else
								                <input type="checkbox" value="1" id="products-add" name="products-add">
								                @endif
								                <label for="products-add"></label>
							                </div>
							            </div>
						            </td>
						            <td class="text-center">
						                <div class="icheckbox_square-blue" aria-checked="false" aria-disabled="false">
							                <div class="checkbox">
								                @if(in_array("products-edit", $all_permission))
								                <input type="checkbox" value="1" id="products-edit" name="products-edit" checked />
								                @else
								                <input type="checkbox" value="1" id="products-edit" name="products-edit" />
								                @endif
								                <label for="products-edit"></label>
							                </div>
							            </div>
						            </td>
						            <td class="text-center">
						                <div class="icheckbox_square-blue" aria-checked="false" aria-disabled="false">
							                <div class="checkbox">
								                @if(in_array("products-delete", $all_permission))
								                <input type="checkbox" value="1" id="products-delete" name="products-delete" checked />
								                @else
								                <input type="checkbox" value="1" id="products-delete" name="products-delete" />
								                @endif
								                <label for="products-delete"></label>
							                </div>
							            </div>
						            </td>
						        </tr>

						        <tr>
						            <td>{{trans('file.Purchase')}}</td>
						            <td class="text-center">
						                <div class="icheckbox_square-blue" aria-checked="false" aria-disabled="false">
							                <div class="checkbox">
								                @if(in_array("purchases-index", $all_permission))
								                <input type="checkbox" value="1" id="purchases-index" name="purchases-index" checked>
								                @else
								                <input type="checkbox" value="1" id="purchases-index" name="purchases-index">
								                @endif
								                <label for="purchases-index"></label>
							                </div>
							            </div>
						            </td>
						            <td class="text-center">
						                <div class="icheckbox_square-blue" aria-checked="false" aria-disabled="false">
							                <div class="checkbox">
								                @if(in_array("purchases-add", $all_permission))
								                <input type="checkbox" value="1" id="purchases-add" name="purchases-add" checked>
								                @else
								                <input type="checkbox" value="1" id="purchases-add" name="purchases-add">
								                @endif
								                <label for="purchases-add"></label>
							                </div>
							            </div>
						            </td>
						            <td class="text-center">
						                <div class="icheckbox_square-blue" aria-checked="false" aria-disabled="false">
							                <div class="checkbox">
								                @if(in_array("purchases-edit", $all_permission))
								                <input type="checkbox" value="1" id="purchases-edit" name="purchases-edit" checked />
								                @else
								                <input type="checkbox" value="1" id="purchases-edit" name="purchases-edit">
								                @endif
								                <label for="purchases-edit"></label>
							                </div>
							            </div>
						            </td>
						            <td class="text-center">
						                <div class="icheckbox_square-blue" aria-checked="false" aria-disabled="false">
							                <div class="checkbox">
								                @if(in_array("purchases-delete", $all_permission))
								                <input type="checkbox" value="1" id="purchases-delete" name="purchases-delete" checked>
								                @else
								                <input type="checkbox" value="1" id="purchases-delete" name="purchases-delete">
								                @endif
								                <label for="purchases-delete"></label>
							            	</div>
						            	</div>
						            </td>
						        </tr>

						        <tr>
						            <td>{{trans('file.Sale')}}</td>
						            <td class="text-center">
						                <div class="icheckbox_square-blue checked" aria-checked="false" aria-disabled="false">
							                <div class="checkbox">
								                @if(in_array("sales-index", $all_permission))
								                <input type="checkbox" value="1" id="sales-index" name="sales-index" checked />
								                @else
								                <input type="checkbox" value="1" id="sales-index" name="sales-index">
								                @endif
								                <label for="sales-index"></label>
								            </div>
						            	</div>
						            </td>
						            <td class="text-center">
						                <div class="icheckbox_square-blue checked" aria-checked="false" aria-disabled="false">
							                <div class="checkbox">
								                @if(in_array("sales-add", $all_permission))
								                <input type="checkbox" value="1" id="sales-add" name="sales-add" checked />
								                @else
								                <input type="checkbox" value="1" id="sales-add" name="sales-add">
								                @endif
								                <label for="sales-add"></label>
								            </div>
						            	</div>
						            </td>
						            <td class="text-center">
						                <div class="icheckbox_square-blue" aria-checked="false" aria-disabled="false">
							                <div class="checkbox">
								                @if(in_array("sales-edit", $all_permission))
								                <input type="checkbox" value="1" id="sales-edit" name="sales-edit" checked>
								                @else
								                <input type="checkbox" value="1" id="sales-edit" name="sales-edit">
								                @endif
								                <label for="sales-edit"></label>
								            </div>
						            	</div>
						            </td>
						            <td class="text-center">
						                <div class="icheckbox_square-blue" aria-checked="false" aria-disabled="false">
							                <div class="checkbox">
								                @if(in_array("sales-delete", $all_permission))
								                <input type="checkbox" value="1" id="sales-delete" name="sales-delete" checked>
								                @else
								                <input type="checkbox" value="1" id="sales-delete" name="sales-delete">
								                @endif
								                <label for="sales-delete"></label>
								            </div>
						            	</div>
						            </td>
						        </tr>

						        <tr>
						            <td>{{trans('file.Expense')}}</td>
						            <td class="text-center">
						                <div class="icheckbox_square-blue checked" aria-checked="false" aria-disabled="false">
							                <div class="checkbox">
								                @if(in_array("expenses-index", $all_permission))
								                <input type="checkbox" value="1" id="expenses-index" name="expenses-index" checked />
								                @else
								                <input type="checkbox" value="1" id="expenses-index" name="expenses-index">
								                @endif
								                <label for="expenses-index"></label>
								            </div>
						            	</div>
						            </td>
						            <td class="text-center">
						                <div class="icheckbox_square-blue checked" aria-checked="false" aria-disabled="false">
							                <div class="checkbox">
								                @if(in_array("expenses-add", $all_permission))
								                <input type="checkbox" value="1" id="expenses-add" name="expenses-add" checked />
								                @else
								                <input type="checkbox" value="1" id="expenses-add" name="expenses-add">
								                @endif
								                <label for="expenses-add"></label>
								            </div>
						            	</div>
						            </td>
						            <td class="text-center">
						                <div class="icheckbox_square-blue" aria-checked="false" aria-disabled="false">
							                <div class="checkbox">
								                @if(in_array("expenses-edit", $all_permission))
								                <input type="checkbox" value="1" id="expenses-edit" name="expenses-edit" checked>
								                @else
								                <input type="checkbox" value="1" id="expenses-edit" name="expenses-edit">
								                @endif
								                <label for="expenses-edit"></label>
								            </div>
						            	</div>
						            </td>
						            <td class="text-center">
						                <div class="icheckbox_square-blue" aria-checked="false" aria-disabled="false">
							                <div class="checkbox">
								                @if(in_array("expenses-delete", $all_permission))
								                <input type="checkbox" value="1" id="expenses-delete" name="expenses-delete" checked>
								                @else
								                <input type="checkbox" value="1" id="expenses-delete" name="expenses-delete">
								                @endif
								                <label for="expenses-delete"></label>
								            </div>
						            	</div>
						            </td>
						        </tr>

						        <tr>
						            <td>{{trans('file.Quotation')}}</td>
						            <td class="text-center">
						                <div class="icheckbox_square-blue checked" aria-checked="false" aria-disabled="false">
							                <div class="checkbox">
								                @if(in_array("quotes-index", $all_permission))
								                <input type="checkbox" value="1" id="quotes-index" name="quotes-index" checked>
								                @else
								                <input type="checkbox" value="1" id="quotes-index" name="quotes-index">
								                @endif
								                <label for="quotes-index"></label>
								            </div>
						            	</div>
						            </td>
						            <td class="text-center">
						                <div class="icheckbox_square-blue checked" aria-checked="false" aria-disabled="false">
							                <div class="checkbox">
								                @if(in_array("quotes-add", $all_permission))
								                <input type="checkbox" value="1" id="quotes-add" name="quotes-add" checked>
								                @else
								                <input type="checkbox" value="1" id="quotes-add" name="quotes-add">
								                @endif
								                <label for="quotes-add"></label>
								            </div>
						            	</div>
						            </td>
						            <td class="text-center">
						                <div class="icheckbox_square-blue checked" aria-checked="false" aria-disabled="false">
							                <div class="checkbox">
								                @if(in_array("quotes-edit", $all_permission))
								                <input type="checkbox" value="1" id="quotes-edit" name="quotes-edit" checked>
								                @else
								                <input type="checkbox" value="1" id="quotes-edit" name="quotes-edit">
								                @endif
								                <label for="quotes-edit"></label>
								            </div>
						            	</div>
						            </td>
						            <td class="text-center">
						                <div class="icheckbox_square-blue" aria-checked="false" aria-disabled="false">
							                <div class="checkbox">
								                @if(in_array("quotes-delete", $all_permission))
								                <input type="checkbox" value="1" id="quotes-delete" name="quotes-delete" checked>
								                @else
								                <input type="checkbox" value="1" id="quotes-delete" name="quotes-delete">
								                @endif
								                <label for="quotes-delete"></label>
								            </div>
						            	</div>
						            </td>
						        </tr>

						        <tr>
						            <td>{{trans('file.Transfer')}}</td>
						            <td class="text-center">
						                <div class="icheckbox_square-blue" aria-checked="false" aria-disabled="false">
							                <div class="checkbox">
								                @if(in_array("transfers-index", $all_permission))
								                <input type="checkbox" value="1" id="transfers-index" name="transfers-index" checked>
								                @else
								                <input type="checkbox" value="1" id="transfers-index" name="transfers-index">
								                @endif
								                <label for="transfers-index"></label>
								            </div>
						            	</div>
						            </td>
						            <td class="text-center">
						                <div class="icheckbox_square-blue" aria-checked="false" aria-disabled="false">
							                <div class="checkbox">
								                @if(in_array("transfers-add", $all_permission))
								                <input type="checkbox" value="1" id="transfers-add" name="transfers-add" checked>
								                @else
								                <input type="checkbox" value="1" id="transfers-add" name="transfers-add">
								                @endif
								                <label for="transfers-add"></label>
								            </div>
						            	</div>
						            </td>
						            <td class="text-center">
						                <div class="icheckbox_square-blue" aria-checked="false" aria-disabled="false">
							                <div class="checkbox">
								                @if(in_array("transfers-edit", $all_permission))
								                <input type="checkbox" value="1" id="transfers-edit" name="transfers-edit" checked>
								                @else
								                <input type="checkbox" value="1" id="transfers-edit" name="transfers-edit">
								                @endif
								                <label for="transfers-edit"></label>
								            </div>
						            	</div>
						            </td>
						            <td class="text-center">
						                <div class="icheckbox_square-blue" aria-checked="false" aria-disabled="false">
							                <div class="checkbox">
								                @if(in_array("transfers-delete", $all_permission))
								                <input type="checkbox" value="1" id="transfers-delete" name="transfers-delete" checked>
								                @else
								                <input type="checkbox" value="1" id="transfers-delete" name="transfers-delete">
								                @endif
								                <label for="transfers-delete"></label>
								            </div>
						            	</div>
						            </td>
						        </tr>

						        <tr>
						            <td>{{trans('file.Sale Return')}}</td>
						            <td class="text-center">
						                <div class="icheckbox_square-blue" aria-checked="false" aria-disabled="false">
							                <div class="checkbox">
								                @if(in_array("returns-index", $all_permission))
								                <input type="checkbox" value="1" id="returns-index" name="returns-index" checked>
								                @else
								                <input type="checkbox" value="1" id="returns-index" name="returns-index">
								                @endif
								                <label for="returns-index"></label>
								            </div>
						            	</div>
						            </td>
						            <td class="text-center">
						                <div class="icheckbox_square-blue" aria-checked="false" aria-disabled="false">
							                <div class="checkbox">
								                @if(in_array("returns-add", $all_permission))
								                <input type="checkbox" value="1" id="returns-add" name="returns-add" checked>
								                @else
								                <input type="checkbox" value="1" id="returns-add" name="returns-add">
								                @endif
								                <label for="returns-add"></label>
							                </div>
							            </div>
						            </td>
						            <td class="text-center">
						                <div class="icheckbox_square-blue" aria-checked="false" aria-disabled="false">
							                <div class="checkbox">
								                @if(in_array("returns-edit", $all_permission))
								                <input type="checkbox" value="1" id="returns-edit" name="returns-edit" checked>
								                @else
								                <input type="checkbox" value="1" id="returns-edit" name="returns-edit">
								                @endif
								                <label for="returns-edit"></label>
								            </div>
						            	</div>
						            </td>
						            <td class="text-center">
						                <div class="icheckbox_square-blue" aria-checked="false" aria-disabled="false">
							                <div class="checkbox">
								                @if(in_array("returns-delete", $all_permission))
								                <input type="checkbox" value="1" id="returns-delete" name="returns-delete" checked>
								                @else
								                <input type="checkbox" value="1" id="returns-delete" name="returns-delete">
								                @endif
								                <label for="returns-delete"></label>
								            </div>
						            	</div>
						            </td>
						        </tr>

						        <tr>
						            <td>{{trans('file.Purchase Return')}}</td>
						            <td class="text-center">
						                <div class="icheckbox_square-blue" aria-checked="false" aria-disabled="false">
							                <div class="checkbox">
								                @if(in_array("purchase-return-index", $all_permission))
								                <input type="checkbox" value="1" id="purchase-return-index" name="purchase-return-index" checked>
								                @else
								                <input type="checkbox" value="1" id="purchase-return-index" name="purchase-return-index">
								                @endif
								                <label for="purchase-return-index"></label>
								            </div>
						            	</div>
						            </td>
						            <td class="text-center">
						                <div class="icheckbox_square-blue" aria-checked="false" aria-disabled="false">
							                <div class="checkbox">
								                @if(in_array("purchase-return-add", $all_permission))
								                <input type="checkbox" value="1" id="purchase-return-add" name="purchase-return-add" checked>
								                @else
								                <input type="checkbox" value="1" id="purchase-return-add" name="purchase-return-add">
								                @endif
								                <label for="purchase-return-add"></label>
								            </div>
						                </div>
						            </td>
						            <td class="text-center">
						                <div class="icheckbox_square-blue" aria-checked="false" aria-disabled="false">
							                <div class="checkbox">
								                @if(in_array("purchase-return-edit", $all_permission))
								                <input type="checkbox" value="1" id="purchase-return-edit" name="purchase-return-edit" checked>
								                @else
								                <input type="checkbox" value="1" id="purchase-return-edit" name="purchase-return-edit">
								                @endif
								                <label for="purchase-return-edit"></label>
								            </div>
						            	</div>
						            </td>
						            <td class="text-center">
						                <div class="icheckbox_square-blue" aria-checked="false" aria-disabled="false">
						                	<div class="checkbox">
								                @if(in_array("purchase-return-delete", $all_permission))
								                <input type="checkbox" value="1" id="purchase-return-delete" name="purchase-return-delete" checked>
								                @else
								                <input type="checkbox" value="1" id="purchase-return-delete" name="purchase-return-delete">
								                @endif
								                <label for="purchase-return-delete"></label>
								            </div>
						            	</div>
						            </td>
						        </tr>
						        <tr>
						            <td>{{trans('file.Employee')}}</td>
						            <td class="text-center">
						                <div class="icheckbox_square-blue checked" aria-checked="false" aria-disabled="false">
							                <div class="checkbox">
								                @if(in_array("employees-index", $all_permission))
								                <input type="checkbox" value="1" id="employees-index" name="employees-index" checked>
								                @else
								                <input type="checkbox" value="1" id="employees-index" name="employees-index">
								                @endif
								                <label for="employees-index"></label>
								            </div>
						            	</div>
						            </td>
						            <td class="text-center">
						                <div class="icheckbox_square-blue checked" aria-checked="false" aria-disabled="false">
							                <div class="checkbox">
								                @if(in_array("employees-add", $all_permission))
								                <input type="checkbox" value="1" id="employees-add" name="employees-add" checked>
								                @else
								                <input type="checkbox" value="1" id="employees-add" name="employees-add">
								                @endif
								                <label for="employees-add"></label>
								            </div>
						            	</div>
						            </td>
						            <td class="text-center">
						                <div class="icheckbox_square-blue checked" aria-checked="false" aria-disabled="false">
							                <div class="checkbox">
								                @if(in_array("employees-edit", $all_permission))
								                <input type="checkbox" value="1" id="employees-edit" name="employees-edit" checked>
								                @else
								                <input type="checkbox" value="1" id="employees-edit" name="employees-edit">
								                @endif
								                <label for="employees-edit"></label>
								            </div>
						            	</div>
						            </td>
						            <td class="text-center">
						                <div class="icheckbox_square-blue" aria-checked="false" aria-disabled="false">
							                <div class="checkbox">
								                @if(in_array("employees-delete", $all_permission))
								                <input type="checkbox" value="1" id="employees-delete" name="employees-delete" checked>
								                @else
								                <input type="checkbox" value="1" id="employees-delete" name="employees-delete">
								                @endif
								                <label for="employees-delete"></label>
								            </div>
						            	</div>
						            </td>
						        </tr>
						        <tr>
						            <td>{{trans('file.User')}}</td>
						            <td class="text-center">
						                <div class="icheckbox_square-blue checked" aria-checked="false" aria-disabled="false">
							                <div class="checkbox">
								                @if(in_array("users-index", $all_permission))
								                <input type="checkbox" value="1" id="users-index" name="users-index" checked>
								                @else
								                <input type="checkbox" value="1" id="users-index" name="users-index">
								                @endif
								                <label for="users-index"></label>
								            </div>
						            	</div>
						            </td>
						            <td class="text-center">
						                <div class="icheckbox_square-blue checked" aria-checked="false" aria-disabled="false">
							                <div class="checkbox">
								                @if(in_array("users-add", $all_permission))
								                <input type="checkbox" value="1" id="users-add" name="users-add" checked>
								                @else
								                <input type="checkbox" value="1" id="users-add" name="users-add">
								                @endif
								                <label for="users-add"></label>
								            </div>
						            	</div>
						            </td>
						            <td class="text-center">
						                <div class="icheckbox_square-blue checked" aria-checked="false" aria-disabled="false">
							                <div class="checkbox">
								                @if(in_array("users-edit", $all_permission))
								                <input type="checkbox" value="1" id="users-edit" name="users-edit" checked>
								                @else
								                <input type="checkbox" value="1" id="users-edit" name="users-edit">
								                @endif
								                <label for="users-edit"></label>
								            </div>
						            	</div>
						            </td>
						            <td class="text-center">
						                <div class="icheckbox_square-blue" aria-checked="false" aria-disabled="false">
							                <div class="checkbox">
								                @if(in_array("users-delete", $all_permission))
								                <input type="checkbox" value="1" id="users-delete" name="users-delete" checked>
								                @else
								                <input type="checkbox" value="1" id="users-delete" name="users-delete">
								                @endif
								                <label for="users-delete"></label>
								            </div>
						            	</div>
						            </td>
						        </tr>
						        <tr>
						            <td>{{trans('file.customer')}}</td>
						            <td class="text-center">
						                <div class="icheckbox_square-blue checked" aria-checked="false" aria-disabled="false">
							                <div class="checkbox">
								                @if(in_array("customers-index", $all_permission))
								                <input type="checkbox" value="1" id="customers-index" name="customers-index" checked>
								                @else
								                <input type="checkbox" value="1" id="customers-index" name="customers-index">
								                @endif
								                <label for="customers-index"></label>
								            </div>
						            	</div>
						            </td>
						            <td class="text-center">
						                <div class="icheckbox_square-blue checked" aria-checked="false" aria-disabled="false">
							                <div class="checkbox">
								                @if(in_array("customers-add", $all_permission))
								                <input type="checkbox" value="1" id="customers-add" name="customers-add" checked>
								                @else
								                <input type="checkbox" value="1" id="customers-add" name="customers-add">
								                @endif
								                <label for="customers-add"></label>
								            </div>
						            	</div>
						            </td>
						            <td class="text-center">
						                <div class="icheckbox_square-blue checked" aria-checked="false" aria-disabled="false">
							                <div class="checkbox">
								                @if(in_array("customers-edit", $all_permission))
								                <input type="checkbox" value="1" id="customers-edit" name="customers-edit" checked>
								                @else
								                <input type="checkbox" value="1" id="customers-edit" name="customers-edit">
								                @endif
								                <label for="customers-edit"></label>
								            </div>
						            	</div>
						            </td>
						            <td class="text-center">
						                <div class="icheckbox_square-blue" aria-checked="false" aria-disabled="false">
							                <div class="checkbox">
								                @if(in_array("customers-delete", $all_permission))
								                <input type="checkbox" value="1" id="customers-delete" name="customers-delete" checked>
								                @else
								                <input type="checkbox" value="1" id="customers-delete" name="customers-delete">
								                @endif
								                <label for="customers-delete"></label>
								            </div>
						            	</div>
						            </td>
						        </tr>
						        <tr>
						            <td>{{trans('file.Biller')}}</td>
						            <td class="text-center">
						                <div class="icheckbox_square-blue checked" aria-checked="false" aria-disabled="false">
							                <div class="checkbox">
								                @if(in_array("billers-index", $all_permission))
								                <input type="checkbox" value="1" id="billers-index" name="billers-index" checked>
								                @else
								                <input type="checkbox" value="1" id="billers-index" name="billers-index">
								                @endif
								                <label for="billers-index"></label>
								            </div>
						            	</div>
						            </td>
						            <td class="text-center">
						                <div class="icheckbox_square-blue checked" aria-checked="false" aria-disabled="false">
							                <div class="checkbox">
								                @if(in_array("billers-add", $all_permission))
								                <input type="checkbox" value="1" id="billers-add" name="billers-add" checked>
								                @else
								                <input type="checkbox" value="1" id="billers-add" name="billers-add">
								                @endif
								                <label for="billers-add"></label>
								            </div>
						            	</div>
						            </td>
						            <td class="text-center">
						                <div class="icheckbox_square-blue checked" aria-checked="false" aria-disabled="false">
							                <div class="checkbox">
								                @if(in_array("billers-edit", $all_permission))
								                <input type="checkbox" value="1" id="billers-edit" name="billers-edit" checked>
								                @else
								                <input type="checkbox" value="1" id="billers-edit" name="billers-edit">
								                @endif
								                <label for="billers-edit"></label>
								            </div>
						            	</div>
						            </td>
						            <td class="text-center">
						                <div class="icheckbox_square-blue" aria-checked="false" aria-disabled="false">
							                <div class="checkbox">
								                @if(in_array("billers-delete", $all_permission))
								                <input type="checkbox" value="1" id="billers-delete" name="billers-delete" checked>
								                @else
								                <input type="checkbox" value="1" id="billers-delete" name="billers-delete">
								                @endif
								                <label for="billers-delete"></label>
								            </div>
						            	</div>
						            </td>
						        </tr>
						        <tr>
						            <td>{{trans('file.Supplier')}}</td>
						            <td class="text-center">
						                <div class="icheckbox_square-blue" aria-checked="false" aria-disabled="false">
							                <div class="checkbox">
								                @if(in_array("suppliers-index", $all_permission))
								                <input type="checkbox" value="1" id="suppliers-index" name="suppliers-index" checked>
								                @else
								                <input type="checkbox" value="1" id="suppliers-index" name="suppliers-index">
								                @endif
								                <label for="suppliers-index"></label>
								            </div>
						            	</div>
						            </td>
						            <td class="text-center">
						                <div class="icheckbox_square-blue" aria-checked="false" aria-disabled="false">
							                <div class="checkbox">
								                @if(in_array("suppliers-add", $all_permission))
								                <input type="checkbox" value="1" id="suppliers-add" name="suppliers-add" checked>
								                @else
								                <input type="checkbox" value="1" id="suppliers-add" name="suppliers-add">
								                @endif
								                <label for="suppliers-add"></label>
								            </div>
						            	</div>
						            </td>
						            <td class="text-center">
						                <div class="icheckbox_square-blue" aria-checked="false" aria-disabled="false">
							                <div class="checkbox">
								                @if(in_array("suppliers-edit", $all_permission))
								                <input type="checkbox" value="1" id="suppliers-edit" name="suppliers-edit" checked>
								                @else
								                <input type="checkbox" value="1" id="suppliers-edit" name="suppliers-edit">
								                @endif
								                <label for="suppliers-edit"></label>
								            </div>
						            	</div>
						            </td>
						            <td class="text-center">
						                <div class="icheckbox_square-blue" aria-checked="false" aria-disabled="false">
							                <div class="checkbox">
								                @if(in_array("suppliers-delete", $all_permission))
								                <input type="checkbox" value="1" id="suppliers-delete" name="suppliers-delete" checked>
								                @else
								                <input type="checkbox" value="1" id="suppliers-delete" name="suppliers-delete">
								                @endif
								                <label for="suppliers-delete"></label>
								            </div>
						            	</div>
						            </td>
						        </tr>
                                <tr class="permission-section-header">
                                    <th colspan="5">
                                        <div class="checkbox">
                                            <input type="checkbox" class="section-select-all" id="section-announcements">
                                            <label for="section-announcements">{{trans('file.Announcement')}}</label>
                                        </div>
                                    </th>
                                </tr>
                                <tr class="permission-subgroup">
                                    <td>{{trans('file.Announcement')}}</td>
                                    <td class="text-center">
                                        <div class="icheckbox_square-blue checked" aria-checked="false" aria-disabled="false">
                                            <div class="checkbox">
                                                @if(in_array("announcement_index", $all_permission))
                                                    <input type="checkbox" value="1" id="announcement_index" name="announcement_index" checked>
                                                @else
                                                    <input type="checkbox" value="1" id="announcement_index" name="announcement_index">
                                                @endif
                                                <label for="announcement_index"></label>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <div class="icheckbox_square-blue checked" aria-checked="false" aria-disabled="false">
                                            <div class="checkbox">
                                                @if(in_array("announcement_add", $all_permission))
                                                    <input type="checkbox" value="1" id="announcement_add" name="announcement_add" checked>
                                                @else
                                                    <input type="checkbox" value="1" id="announcement_add" name="announcement_add">
                                                @endif
                                                <label for="announcement_add"></label>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <div class="icheckbox_square-blue checked" aria-checked="false" aria-disabled="false">
                                            <div class="checkbox">
                                                @if(in_array("announcement_edit", $all_permission))
                                                    <input type="checkbox" value="1" id="announcement_edit" name="announcement_edit" checked>
                                                @else
                                                    <input type="checkbox" value="1" id="announcement_edit" name="announcement_edit">
                                                @endif
                                                <label for="announcement_edit"></label>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <div class="icheckbox_square-blue" aria-checked="false" aria-disabled="false">
                                            <div class="checkbox">
                                                @if(in_array("announcement_delete", $all_permission))
                                                    <input type="checkbox" value="1" id="announcement_delete" name="announcement_delete" checked>
                                                @else
                                                    <input type="checkbox" value="1" id="announcement_delete" name="announcement_delete">
                                                @endif
                                                <label for="announcement_delete"></label>
                                            </div>
                                        </div>
                                    </td>
                                </tr>

                                {{--                                fixed assets--}}
                                <tr class="permission-section-header">
                                    <th colspan="5">
                                        <div class="checkbox">
                                            <input type="checkbox" class="section-select-all" id="section-fixed-assets">
                                            <label for="section-fixed-assets">{{trans('file.Fixed Assets')}}</label>
                                        </div>
                                    </th>
                                </tr>
                                <tr>
                                    <td>{{trans('file.Fixed Assets')}}</td>
                                    <td class="text-center">
                                        <div class="icheckbox_square-blue checked" aria-checked="false" aria-disabled="false">
                                            <div class="checkbox">
                                                @if(in_array("asset-index", $all_permission))
                                                    <input type="checkbox" value="1" id="asset-index" name="asset-index" checked />
                                                @else
                                                    <input type="checkbox" value="1" id="asset-index" name="asset-index" />
                                                @endif
                                                <label for="asset-index"></label>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <div class="icheckbox_square-blue" aria-checked="false" aria-disabled="false">
                                            <div class="checkbox">
                                                @if(in_array("asset-add", $all_permission))
                                                    <input type="checkbox" value="1" id="asset-add" name="asset-add" checked>
                                                @else
                                                    <input type="checkbox" value="1" id="asset-add" name="asset-add">
                                                @endif
                                                <label for="asset-add"></label>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <div class="icheckbox_square-blue" aria-checked="false" aria-disabled="false">
                                            <div class="checkbox">
                                                @if(in_array("asset-edit", $all_permission))
                                                    <input type="checkbox" value="1" id="asset-edit" name="asset-edit" checked />
                                                @else
                                                    <input type="checkbox" value="1" id="asset-edit" name="asset-edit" />
                                                @endif
                                                <label for="asset-edit"></label>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <div class="icheckbox_square-blue" aria-checked="false" aria-disabled="false">
                                            <div class="checkbox">
                                                @if(in_array("asset-delete", $all_permission))
                                                    <input type="checkbox" value="1" id="asset-delete" name="asset-delete" checked />
                                                @else
                                                    <input type="checkbox" value="1" id="asset-delete" name="asset-delete" />
                                                @endif
                                                <label for="asset-delete"></label>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                                <tr>
                                    <td>{{trans('file.Asset Type')}}</td>
                                    <td class="text-center">
                                        <div class="icheckbox_square-blue checked" aria-checked="false" aria-disabled="false">
                                            <div class="checkbox">
                                                @if(in_array("asset-type-index", $all_permission))
                                                    <input type="checkbox" value="1" id="asset-type-index" name="asset-type-index" checked />
                                                @else
                                                    <input type="checkbox" value="1" id="asset-type-index" name="asset-type-index" />
                                                @endif
                                                <label for="asset-type-index"></label>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <div class="icheckbox_square-blue" aria-checked="false" aria-disabled="false">
                                            <div class="checkbox">
                                                @if(in_array("asset-type-add", $all_permission))
                                                    <input type="checkbox" value="1" id="asset-type-add" name="asset-type-add" checked>
                                                @else
                                                    <input type="checkbox" value="1" id="asset-type-add" name="asset-type-add">
                                                @endif
                                                <label for="asset-type-add"></label>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <div class="icheckbox_square-blue" aria-checked="false" aria-disabled="false">
                                            <div class="checkbox">
                                                @if(in_array("asset-type-edit", $all_permission))
                                                    <input type="checkbox" value="1" id="asset-type-edit" name="asset-type-edit" checked />
                                                @else
                                                    <input type="checkbox" value="1" id="asset-type-edit" name="asset-type-edit" />
                                                @endif
                                                <label for="asset-type-edit"></label>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <div class="icheckbox_square-blue" aria-checked="false" aria-disabled="false">
                                            <div class="checkbox">
                                                @if(in_array("asset-type-delete", $all_permission))
                                                    <input type="checkbox" value="1" id="asset-type-delete" name="asset-type-delete" checked />
                                                @else
                                                    <input type="checkbox" value="1" id="asset-type-delete" name="asset-type-delete" />
                                                @endif
                                                <label for="asset-type-delete"></label>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                                <tr>
                                    <td>{{trans('file.Donor')}}</td>
                                    <td class="text-center">
                                        <div class="icheckbox_square-blue checked" aria-checked="false" aria-disabled="false">
                                            <div class="checkbox">
                                                @if(in_array("donor-index", $all_permission))
                                                    <input type="checkbox" value="1" id="donor-index" name="donor-index" checked />
                                                @else
                                                    <input type="checkbox" value="1" id="donor-index" name="donor-index" />
                                                @endif
                                                <label for="donor-index"></label>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <div class="icheckbox_square-blue" aria-checked="false" aria-disabled="false">
                                            <div class="checkbox">
                                                @if(in_array("donor-add", $all_permission))
                                                    <input type="checkbox" value="1" id="donor-add" name="donor-add" checked>
                                                @else
                                                    <input type="checkbox" value="1" id="donor-add" name="donor-add">
                                                @endif
                                                <label for="donor-add"></label>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <div class="icheckbox_square-blue" aria-checked="false" aria-disabled="false">
                                            <div class="checkbox">
                                                @if(in_array("donor-edit", $all_permission))
                                                    <input type="checkbox" value="1" id="donor-edit" name="donor-edit" checked />
                                                @else
                                                    <input type="checkbox" value="1" id="donor-edit" name="donor-edit" />
                                                @endif
                                                <label for="donor-edit"></label>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <div class="icheckbox_square-blue" aria-checked="false" aria-disabled="false">
                                            <div class="checkbox">
                                                @if(in_array("donor-delete", $all_permission))
                                                    <input type="checkbox" value="1" id="donor-delete" name="donor-delete" checked />
                                                @else
                                                    <input type="checkbox" value="1" id="donor-delete" name="donor-delete" />
                                                @endif
                                                <label for="donor-delete"></label>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                                <tr>
                                    <td>{{trans('file.Station')}}</td>
                                    <td class="text-center">
                                        <div class="icheckbox_square-blue checked" aria-checked="false" aria-disabled="false">
                                            <div class="checkbox">
                                                @if(in_array("station-index", $all_permission))
                                                    <input type="checkbox" value="1" id="station-index" name="station-index" checked />
                                                @else
                                                    <input type="checkbox" value="1" id="station-index" name="station-index" />
                                                @endif
                                                <label for="station-index"></label>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <div class="icheckbox_square-blue" aria-checked="false" aria-disabled="false">
                                            <div class="checkbox">
                                                @if(in_array("station-add", $all_permission))
                                                    <input type="checkbox" value="1" id="station-add" name="station-add" checked>
                                                @else
                                                    <input type="checkbox" value="1" id="station-add" name="station-add">
                                                @endif
                                                <label for="station-add"></label>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <div class="icheckbox_square-blue" aria-checked="false" aria-disabled="false">
                                            <div class="checkbox">
                                                @if(in_array("station-edit", $all_permission))
                                                    <input type="checkbox" value="1" id="station-edit" name="station-edit" checked />
                                                @else
                                                    <input type="checkbox" value="1" id="station-edit" name="station-edit" />
                                                @endif
                                                <label for="station-edit"></label>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <div class="icheckbox_square-blue" aria-checked="false" aria-disabled="false">
                                            <div class="checkbox">
                                                @if(in_array("station-delete", $all_permission))
                                                    <input type="checkbox" value="1" id="station-delete" name="station-delete" checked />
                                                @else
                                                    <input type="checkbox" value="1" id="station-delete" name="station-delete" />
                                                @endif
                                                <label for="station-delete"></label>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                                <tr>
                                    <td>{{trans('file.Region')}}</td>
                                    <td class="text-center">
                                        <div class="icheckbox_square-blue checked" aria-checked="false" aria-disabled="false">
                                            <div class="checkbox">
                                                @if(in_array("region-index", $all_permission))
                                                    <input type="checkbox" value="1" id="region-index" name="region-index" checked />
                                                @else
                                                    <input type="checkbox" value="1" id="region-index" name="region-index" />
                                                @endif
                                                <label for="region-index"></label>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <div class="icheckbox_square-blue" aria-checked="false" aria-disabled="false">
                                            <div class="checkbox">
                                                @if(in_array("region-add", $all_permission))
                                                    <input type="checkbox" value="1" id="region-add" name="region-add" checked>
                                                @else
                                                    <input type="checkbox" value="1" id="region-add" name="region-add">
                                                @endif
                                                <label for="region-add"></label>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <div class="icheckbox_square-blue" aria-checked="false" aria-disabled="false">
                                            <div class="checkbox">
                                                @if(in_array("region-edit", $all_permission))
                                                    <input type="checkbox" value="1" id="region-edit" name="region-edit" checked />
                                                @else
                                                    <input type="checkbox" value="1" id="region-edit" name="region-edit" />
                                                @endif
                                                <label for="region-edit"></label>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <div class="icheckbox_square-blue" aria-checked="false" aria-disabled="false">
                                            <div class="checkbox">
                                                @if(in_array("region-delete", $all_permission))
                                                    <input type="checkbox" value="1" id="region-delete" name="region-delete" checked />
                                                @else
                                                    <input type="checkbox" value="1" id="region-delete" name="region-delete" />
                                                @endif
                                                <label for="region-delete"></label>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                                <tr>
                                    <td>{{trans('file.Asset Activity')}}</td>
                                    <td class="text-center">
                                        <div class="icheckbox_square-blue checked" aria-checked="false" aria-disabled="false">
                                            <div class="checkbox">
                                                @if(in_array("activity-index", $all_permission))
                                                    <input type="checkbox" value="1" id="activity-index" name="activity-index" checked />
                                                @else
                                                    <input type="checkbox" value="1" id="activity-index" name="activity-index" />
                                                @endif
                                                <label for="activity-index"></label>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <div class="icheckbox_square-blue" aria-checked="false" aria-disabled="false">
                                            <div class="checkbox">
                                                @if(in_array("activity-add", $all_permission))
                                                    <input type="checkbox" value="1" id="activity-add" name="activity-add" checked>
                                                @else
                                                    <input type="checkbox" value="1" id="activity-add" name="activity-add">
                                                @endif
                                                <label for="activity-add"></label>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <div class="icheckbox_square-blue" aria-checked="false" aria-disabled="false">
                                            <div class="checkbox">
                                                @if(in_array("activity-edit", $all_permission))
                                                    <input type="checkbox" value="1" id="activity-edit" name="activity-edit" checked />
                                                @else
                                                    <input type="checkbox" value="1" id="activity-edit" name="activity-edit" />
                                                @endif
                                                <label for="activity-edit"></label>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <div class="icheckbox_square-blue" aria-checked="false" aria-disabled="false">
                                            <div class="checkbox">
                                                @if(in_array("activity-delete", $all_permission))
                                                    <input type="checkbox" value="1" id="activity-delete" name="activity-delete" checked />
                                                @else
                                                    <input type="checkbox" value="1" id="activity-delete" name="activity-delete" />
                                                @endif
                                                <label for="activity-delete"></label>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                                <tr>
                                    <td>{{trans('file.Asset Expense')}}</td>
                                    <td class="text-center">
                                        <div class="icheckbox_square-blue checked" aria-checked="false" aria-disabled="false">
                                            <div class="checkbox">
                                                @if(in_array("asset-expense-index", $all_permission))
                                                    <input type="checkbox" value="1" id="asset-expense-index" name="asset-expense-index" checked />
                                                @else
                                                    <input type="checkbox" value="1" id="asset-expense-index" name="asset-expense-index" />
                                                @endif
                                                <label for="asset-expense-index"></label>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <div class="icheckbox_square-blue" aria-checked="false" aria-disabled="false">
                                            <div class="checkbox">
                                                @if(in_array("asset-expense-add", $all_permission))
                                                    <input type="checkbox" value="1" id="asset-expense-add" name="asset-expense-add" checked>
                                                @else
                                                    <input type="checkbox" value="1" id="asset-expense-add" name="asset-expense-add">
                                                @endif
                                                <label for="asset-expense-add"></label>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <div class="icheckbox_square-blue" aria-checked="false" aria-disabled="false">
                                            <div class="checkbox">
                                                @if(in_array("asset-expense-edit", $all_permission))
                                                    <input type="checkbox" value="1" id="asset-expense-edit" name="asset-expense-edit" checked />
                                                @else
                                                    <input type="checkbox" value="1" id="asset-expense-edit" name="asset-expense-edit" />
                                                @endif
                                                <label for="asset-expense-edit"></label>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <div class="icheckbox_square-blue" aria-checked="false" aria-disabled="false">
                                            <div class="checkbox">
                                                @if(in_array("asset-expense-delete", $all_permission))
                                                    <input type="checkbox" value="1" id="asset-expense-delete" name="asset-expense-delete" checked />
                                                @else
                                                    <input type="checkbox" value="1" id="asset-expense-delete" name="asset-expense-delete" />
                                                @endif
                                                <label for="asset-expense-delete"></label>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                                <tr>
                                    <td>Other Related to Assets</td>
                                    <td class="report-permissions" colspan="5">
						            	<span>
						                    <div aria-checked="false" aria-disabled="false">
								                <div class="checkbox">
							                    	@if(in_array("fixed_assets", $all_permission))
                                                        <input type="checkbox" value="1" id="fixed_assets" name="fixed_assets" checked>
                                                    @else
                                                        <input type="checkbox" value="1" id="fixed_assets" name="fixed_assets">
                                                    @endif
								                    <label for="fixed_assets" class="padding05">Fixed Assets Complete Module &nbsp;&nbsp;</label>
								                </div>
								            </div>
						                </span>
                                        <span>
						                    <div aria-checked="false" aria-disabled="false">
								                <div class="checkbox">
							                    	@if(in_array("asset-sale", $all_permission))
                                                        <input type="checkbox" value="1" id="asset-sale" name="asset-sale" checked>
                                                    @else
                                                        <input type="checkbox" value="1" id="asset-sale" name="asset-sale">
                                                    @endif
								                    <label for="asset-sale" class="padding05">Assets Sale &nbsp;&nbsp;</label>
								                </div>
								            </div>
						                </span>
                                        <span>
						                    <div aria-checked="false" aria-disabled="false">
								                <div class="checkbox">
							                    	@if(in_array("asset-transfer", $all_permission))
                                                        <input type="checkbox" value="1" id="asset-transfer" name="asset-transfer" checked>
                                                    @else
                                                        <input type="checkbox" value="1" id="asset-transfer" name="asset-transfer">
                                                    @endif
								                    <label for="asset-transfer" class="padding05">Asset Transfer &nbsp;&nbsp;</label>
								                </div>
								            </div>
						                </span>
                                        <span>
						                    <div aria-checked="false" aria-disabled="false">
								                <div class="checkbox">
							                    	@if(in_array("asset-disppose", $all_permission))
                                                        <input type="checkbox" value="1" id="asset-disppose" name="asset-disppose" checked>
                                                    @else
                                                        <input type="checkbox" value="1" id="asset-disppose" name="asset-disppose">
                                                    @endif
								                    <label for="asset-disppose" class="padding05"> Assets Disppose &nbsp;&nbsp;</label>
								                </div>
								            </div>
						                </span>
                                        <span>
						                    <div aria-checked="false" aria-disabled="false">
								                <div class="checkbox">
							                    	@if(in_array("fixed_assets_report", $all_permission))
                                                        <input type="checkbox" value="1" id="fixed_assets_report" name="fixed_assets_report" checked>
                                                    @else
                                                        <input type="checkbox" value="1" id="fixed_assets_report" name="fixed_assets_report">
                                                    @endif
								                    <label for="fixed_assets_report" class="padding05">{{trans('file.Fixed Assets Report')}} &nbsp;&nbsp;</label>
								                </div>
								            </div>
						                </span>
                                    </td>
                                </tr>
                                <tr class="permission-section-end">
                                    <th colspan="5">{{trans('file.Fixed Assets')}} End</th>
                                </tr>
                                {{--                                fixed assets end--}}

                                {{--                                Booking Module--}}
                                <tr class="permission-section-header">
                                    <th colspan="5">
                                        <div class="checkbox">
                                            <input type="checkbox" class="section-select-all" id="section-rental-module">
                                            <label for="section-rental-module">Rental Module</label>
                                        </div>
                                    </th>
                                </tr>

                                <tr>
                                    <td>Rental</td>
                                    <td class="report-permissions" colspan="5">
						            	<span>
						                    <div aria-checked="false" aria-disabled="false">
								                <div class="checkbox">
							                    	@if(in_array("booking_module", $all_permission))
                                                        <input type="checkbox" value="1" id="booking_module" name="booking_module" checked>
                                                    @else
                                                        <input type="checkbox" value="1" id="booking_module" name="booking_module">
                                                    @endif
								                    <label for="booking_module" class="padding05">{{trans('file.Booking Module')}} &nbsp;&nbsp;</label>
								                </div>
								            </div>
						                </span>
                                        <span>
						                    <div aria-checked="false" aria-disabled="false">
								                <div class="checkbox">
							                    	@if(in_array("booking_index", $all_permission))
                                                        <input type="checkbox" value="1" id="booking_index" name="booking_index" checked>
                                                    @else
                                                        <input type="checkbox" value="1" id="booking_index" name="booking_index">
                                                    @endif
								                    <label for="booking_index" class="padding05"> View </label>
								                </div>
								            </div>
						                </span>
                                        <span>
						                    <div aria-checked="false" aria-disabled="false">
								                <div class="checkbox">
							                    	@if(in_array("booking_create", $all_permission))
                                                        <input type="checkbox" value="1" id="booking_create" name="booking_create" checked>
                                                    @else
                                                        <input type="checkbox" value="1" id="booking_create" name="booking_create">
                                                    @endif
								                    <label for="booking_create" class="padding05"> Add </label>
								                </div>
								            </div>
						                </span>
                                        <span>
						                    <div aria-checked="false" aria-disabled="false">
								                <div class="checkbox">
							                    	@if(in_array("booking_edit", $all_permission))
                                                        <input type="checkbox" value="1" id="booking_edit" name="booking_edit" checked>
                                                    @else
                                                        <input type="checkbox" value="1" id="booking_edit" name="booking_edit">
                                                    @endif
								                    <label for="booking_edit" class="padding05"> Edit </label>
								                </div>
								            </div>
						                </span>
                                        <span>
						                    <div aria-checked="false" aria-disabled="false">
								                <div class="checkbox">
							                    	@if(in_array("booking_delete", $all_permission))
                                                        <input type="checkbox" value="1" id="booking_delete" name="booking_delete" checked>
                                                    @else
                                                        <input type="checkbox" value="1" id="booking_delete" name="booking_delete">
                                                    @endif
								                    <label for="booking_delete" class="padding05"> Delete </label>
								                </div>
								            </div>
						                </span>
                                        <span>
						                    <div aria-checked="false" aria-disabled="false">
								                <div class="checkbox">
							                    	@if(in_array("booking_return", $all_permission))
                                                        <input type="checkbox" value="1" id="booking_return" name="booking_return" checked>
                                                    @else
                                                        <input type="checkbox" value="1" id="booking_return" name="booking_return">
                                                    @endif
								                    <label for="booking_return" class="padding05"> Return </label>
								                </div>
								            </div>
						                </span>
                                        <span>
						                    <div aria-checked="false" aria-disabled="false">
								                <div class="checkbox">
							                    	@if(in_array("booking_report", $all_permission))
                                                        <input type="checkbox" value="1" id="booking_report" name="booking_report" checked>
                                                    @else
                                                        <input type="checkbox" value="1" id="booking_report" name="booking_report">
                                                    @endif
								                    <label for="booking_report" class="padding05"> Booking Report </label>
								                </div>
								            </div>
						                </span>
                                        <span>
						                    <div aria-checked="false" aria-disabled="false">
								                <div class="checkbox">
							                    	@if(in_array("booking_awaiting_signature", $all_permission))
                                                        <input type="checkbox" value="1" id="booking_awaiting_signature" name="booking_awaiting_signature" checked>
                                                    @else
                                                        <input type="checkbox" value="1" id="booking_awaiting_signature" name="booking_awaiting_signature">
                                                    @endif
								                    <label for="booking_awaiting_signature" class="padding05"> Awaiting Signature </label>
								                </div>
								            </div>
						                </span>
                                        <span>
						                    <div aria-checked="false" aria-disabled="false">
								                <div class="checkbox">
							                    	@if(in_array("booking_pending_review", $all_permission))
                                                        <input type="checkbox" value="1" id="booking_pending_review" name="booking_pending_review" checked>
                                                    @else
                                                        <input type="checkbox" value="1" id="booking_pending_review" name="booking_pending_review">
                                                    @endif
								                    <label for="booking_pending_review" class="padding05"> Pending Review </label>
								                </div>
								            </div>
						                </span>
                                        <span>
						                    <div aria-checked="false" aria-disabled="false">
								                <div class="checkbox">
							                    	@if(in_array("booking_signed_contracts", $all_permission))
                                                        <input type="checkbox" value="1" id="booking_signed_contracts" name="booking_signed_contracts" checked>
                                                    @else
                                                        <input type="checkbox" value="1" id="booking_signed_contracts" name="booking_signed_contracts">
                                                    @endif
								                    <label for="booking_signed_contracts" class="padding05"> Signed Contracts </label>
								                </div>
								            </div>
						                </span>
                                        <span>
						                    <div aria-checked="false" aria-disabled="false">
								                <div class="checkbox">
							                    	@if(in_array("booking_goods_received", $all_permission))
                                                        <input type="checkbox" value="1" id="booking_goods_received" name="booking_goods_received" checked>
                                                    @else
                                                        <input type="checkbox" value="1" id="booking_goods_received" name="booking_goods_received">
                                                    @endif
								                    <label for="booking_goods_received" class="padding05"> Goods Received </label>
								                </div>
								            </div>
						                </span>
                                        <span>
						                    <div aria-checked="false" aria-disabled="false">
								                <div class="checkbox">
							                    	@if(in_array("booking_contract_approve", $all_permission))
                                                        <input type="checkbox" value="1" id="booking_contract_approve" name="booking_contract_approve" checked>
                                                    @else
                                                        <input type="checkbox" value="1" id="booking_contract_approve" name="booking_contract_approve">
                                                    @endif
								                    <label for="booking_contract_approve" class="padding05"> Approve Contracts </label>
								                </div>
								            </div>
						                </span>
                                    </td>
                                </tr>
                                <tr class="permission-section-end">
                                    <th colspan="5">Rental Module End</th>
                                </tr>
                                {{--                                Booking Module end--}}

                                {{--                                Events Module--}}
                                <tr class="permission-section-header">
                                    <th colspan="5">
                                        <div class="checkbox">
                                            <input type="checkbox" class="section-select-all" id="section-events-module">
                                            <label for="section-events-module">Events Module</label>
                                        </div>
                                    </th>
                                </tr>
                                <tr>
                                    <td>Events</td>
                                    <td class="report-permissions" colspan="5">
                                        @foreach([
                                            'events_module' => 'Events Module',
                                            'events.view' => 'View',
                                            'events.create' => 'Create',
                                            'events.update' => 'Update',
                                            'events.delete' => 'Delete',
                                            'events.approve' => 'Approve',
                                            'events.manage_workforce' => 'Manage Workforce',
                                            'events.manage_budget' => 'Manage Budget',
                                            'events.change_status' => 'Change Status',
                                            'events.manage_publication' => 'Manage Publication',
                                            'events.publish' => 'Publish to Website',
                                            'events.unpublish' => 'Unpublish from Website',
                                            'events.settings' => 'Event Settings',
                                            'event_workers.view' => 'View Worker Profiles',
                                            'event_workers.create' => 'Create Worker Profiles',
                                            'event_workers.update' => 'Update Worker Profiles',
                                            'event_contracts.view' => 'View Contracts',
                                            'event_contracts.create' => 'Create Contracts',
                                            'event_contracts.send' => 'Send Contracts',
                                            'event_contracts.approve' => 'Approve Contracts',
                                            'event_reminders.view' => 'View Reminders',
                                            'event_reminders.create' => 'Create Reminders',
                                            'event_reminders.send' => 'Send Reminders',
                                            'event_timesheets.view' => 'View Timesheets',
                                            'event_timesheets.manage' => 'Manage Timesheets',
                                            'event_timesheets.approve' => 'Approve Timesheets',
                                            'event_payments.view' => 'View Payments',
                                            'event_payments.create' => 'Create Payments',
                                            'event_payments.approve' => 'Approve Payments',
                                        ] as $perm => $label)
                                        @php $pid = str_replace('.', '_', $perm); @endphp
                                        <span>
                                            <div class="checkbox">
                                                @if(in_array($perm, $all_permission))
                                                    <input type="checkbox" value="1" id="{{ $pid }}" name="{{ $perm }}" checked>
                                                @else
                                                    <input type="checkbox" value="1" id="{{ $pid }}" name="{{ $perm }}">
                                                @endif
                                                <label for="{{ $pid }}" class="padding05">{{ $label }}</label>
                                            </div>
                                        </span>
                                        @endforeach
                                    </td>
                                </tr>
                                <tr class="permission-section-end">
                                    <th colspan="5">Events Module End</th>
                                </tr>

                                {{--                                Booking Module--}}
                                <tr class="permission-section-header">
                                    <th colspan="5">
                                        <div class="checkbox">
                                            <input type="checkbox" class="section-select-all" id="section-letter-module">
                                            <label for="section-letter-module">Letter Module</label>
                                        </div>
                                    </th>
                                </tr>
                                <tr>
                                    <td>Letters</td>
                                    <td class="report-permissions" colspan="5">
						            	<span>
						                    <div aria-checked="false" aria-disabled="false">
								                <div class="checkbox">
							                    	@if(in_array("letter_module", $all_permission))
                                                        <input type="checkbox" value="1" id="letter_module" name="letter_module" checked>
                                                    @else
                                                        <input type="checkbox" value="1" id="letter_module" name="letter_module">
                                                    @endif
								                    <label for="letter_module" class="padding05">Letter Module &nbsp;&nbsp;</label>
								                </div>
								            </div>
						                </span>
                                        <span>
						                    <div aria-checked="false" aria-disabled="false">
								                <div class="checkbox">
							                    	@if(in_array("letter_category", $all_permission))
                                                        <input type="checkbox" value="1" id="letter_category" name="letter_category" checked>
                                                    @else
                                                        <input type="checkbox" value="1" id="letter_category" name="letter_category">
                                                    @endif
								                    <label for="letter_category" class="padding05">Letter Category &nbsp;&nbsp;</label>
								                </div>
								            </div>
						                </span>
                                        <span>
						                    <div aria-checked="false" aria-disabled="false">
								                <div class="checkbox">
							                    	@if(in_array("letter_create", $all_permission))
                                                        <input type="checkbox" value="1" id="letter_create" name="letter_create" checked>
                                                    @else
                                                        <input type="checkbox" value="1" id="letter_create" name="letter_create">
                                                    @endif
								                    <label for="letter_create" class="padding05"> Create letter </label>
								                </div>
								            </div>
						                </span>
                                        <span>
						                    <div aria-checked="false" aria-disabled="false">
								                <div class="checkbox">
							                    	@if(in_array("letter_index", $all_permission))
                                                        <input type="checkbox" value="1" id="letter_index" name="letter_index" checked>
                                                    @else
                                                        <input type="checkbox" value="1" id="letter_index" name="letter_index">
                                                    @endif
								                    <label for="letter_index" class="padding05"> All Letter Listing </label>
								                </div>
								            </div>
						                </span>
                                        <span>
						                    <div aria-checked="false" aria-disabled="false">
								                <div class="checkbox">
							                    	@if(in_array("letter_edit", $all_permission))
                                                        <input type="checkbox" value="1" id="letter_edit" name="letter_edit" checked>
                                                    @else
                                                        <input type="checkbox" value="1" id="letter_edit" name="letter_edit">
                                                    @endif
								                    <label for="letter_edit" class="padding05"> Edit </label>
								                </div>
								            </div>
						                </span>
                                        <span>
						                    <div aria-checked="false" aria-disabled="false">
								                <div class="checkbox">
							                    	@if(in_array("letter_delete", $all_permission))
                                                        <input type="checkbox" value="1" id="letter_delete" name="letter_delete" checked>
                                                    @else
                                                        <input type="checkbox" value="1" id="letter_delete" name="letter_delete">
                                                    @endif
								                    <label for="letter_delete" class="padding05"> Delete </label>
								                </div>
								            </div>
						                </span>
                                        <span>
						                    <div aria-checked="false" aria-disabled="false">
								                <div class="checkbox">
							                    	@if(in_array("letter_rejected", $all_permission))
                                                        <input type="checkbox" value="1" id="letter_rejected" name="letter_rejected" checked>
                                                    @else
                                                        <input type="checkbox" value="1" id="letter_rejected" name="letter_rejected">
                                                    @endif
								                    <label for="letter_rejected" class="padding05"> Rejected Letters </label>
								                </div>
								            </div>
						                </span>
                                        <span>
						                    <div aria-checked="false" aria-disabled="false">
								                <div class="checkbox">
							                    	@if(in_array("letter_awaiting_edit", $all_permission))
                                                        <input type="checkbox" value="1" id="letter_awaiting_edit" name="letter_awaiting_edit" checked>
                                                    @else
                                                        <input type="checkbox" value="1" id="letter_awaiting_edit" name="letter_awaiting_edit">
                                                    @endif
								                    <label for="letter_awaiting_edit" class="padding05"> Awaiting Editing </label>
								                </div>
								            </div>
						                </span>
                                        <span>
						                    <div aria-checked="false" aria-disabled="false">
								                <div class="checkbox">
							                    	@if(in_array("letter_edited_index", $all_permission))
                                                        <input type="checkbox" value="1" id="letter_edited_index" name="letter_edited_index" checked>
                                                    @else
                                                        <input type="checkbox" value="1" id="letter_edited_index" name="letter_edited_index">
                                                    @endif
								                    <label for="letter_edited_index" class="padding05">Awaitng Approve </label>
								                </div>
								            </div>
						                </span>
                                        <span>
						                    <div aria-checked="false" aria-disabled="false">
								                <div class="checkbox">
							                    	@if(in_array("letter_approve", $all_permission))
                                                        <input type="checkbox" value="1" id="letter_approve" name="letter_approve" checked>
                                                    @else
                                                        <input type="checkbox" value="1" id="letter_approve" name="letter_approve">
                                                    @endif
								                    <label for="letter_approve" class="padding05"> Approve </label>
								                </div>
								            </div>
						                </span>
                                        <span>
						                    <div aria-checked="false" aria-disabled="false">
								                <div class="checkbox">
							                    	@if(in_array("letter_approve_index", $all_permission))
                                                        <input type="checkbox" value="1" id="letter_approve_index" name="letter_approve_index" checked>
                                                    @else
                                                        <input type="checkbox" value="1" id="letter_approve_index" name="letter_approve_index">
                                                    @endif
								                    <label for="letter_approve_index" class="padding05">Awaitng Signature </label>
								                </div>
								            </div>
						                </span>
                                        <span>
						                    <div aria-checked="false" aria-disabled="false">
								                <div class="checkbox">
							                    	@if(in_array("letter_sign", $all_permission))
                                                        <input type="checkbox" value="1" id="letter_sign" name="letter_sign" checked>
                                                    @else
                                                        <input type="checkbox" value="1" id="letter_sign" name="letter_sign">
                                                    @endif
								                    <label for="letter_sign" class="padding05"> Sign </label>
								                </div>
								            </div>
						                </span>
                                        <span>
						                    <div aria-checked="false" aria-disabled="false">
								                <div class="checkbox">
							                    	@if(in_array("letter_sign_index", $all_permission))
                                                        <input type="checkbox" value="1" id="letter_sign_index" name="letter_sign_index" checked>
                                                    @else
                                                        <input type="checkbox" value="1" id="letter_sign_index" name="letter_sign_index">
                                                    @endif
								                    <label for="letter_sign_index" class="padding05">Ready to send </label>
								                </div>
								            </div>
						                </span>
                                        <span>
						                    <div aria-checked="false" aria-disabled="false">
								                <div class="checkbox">
							                    	@if(in_array("letter_send", $all_permission))
                                                        <input type="checkbox" value="1" id="letter_send" name="letter_send" checked>
                                                    @else
                                                        <input type="checkbox" value="1" id="letter_send" name="letter_send">
                                                    @endif
								                    <label for="letter_send" class="padding05"> Send </label>
								                </div>
								            </div>
						                </span>
                                        <span>
						                    <div aria-checked="false" aria-disabled="false">
								                <div class="checkbox">
							                    	@if(in_array("letter_send_index", $all_permission))
                                                        <input type="checkbox" value="1" id="letter_send_index" name="letter_send_index" checked>
                                                    @else
                                                        <input type="checkbox" value="1" id="letter_send_index" name="letter_send_index">
                                                    @endif
								                    <label for="letter_send_index" class="padding05"> Sent Letters </label>
								                </div>
								            </div>
						                </span>
                                        <span>
						                    <div aria-checked="false" aria-disabled="false">
								                <div class="checkbox">
							                    	@if(in_array("letter_template", $all_permission))
                                                        <input type="checkbox" value="1" id="letter_template" name="letter_template" checked>
                                                    @else
                                                        <input type="checkbox" value="1" id="letter_template" name="letter_template">
                                                    @endif
								                    <label for="letter_template" class="padding05"> Letter Templates </label>
								                </div>
								            </div>
						                </span>
                                        <span>
						                    <div aria-checked="false" aria-disabled="false">
								                <div class="checkbox">
							                    	@if(in_array("one_time_otp", $all_permission))
                                                        <input type="checkbox" value="1" id="one_time_otp" name="one_time_otp" checked>
                                                    @else
                                                        <input type="checkbox" value="1" id="one_time_otp" name="one_time_otp">
                                                    @endif
								                    <label for="one_time_otp" class="padding05"> OTP At Login Only </label>
								                </div>
								            </div>
						                </span>
                                        <span>
						                    <div aria-checked="false" aria-disabled="false">
								                <div class="checkbox">
							                    	@if(in_array("forward_letter", $all_permission))
                                                        <input type="checkbox" value="1" id="forward_letter" name="forward_letter" checked>
                                                    @else
                                                        <input type="checkbox" value="1" id="forward_letter" name="forward_letter">
                                                    @endif
								                    <label for="forward_letter" class="padding05"> Forward Letter To Any </label>
								                </div>
								            </div>
						                </span>
                                    </td>
                                </tr>
                                <tr class="permission-section-end">
                                    <th colspan="5">Letter Module End</th>
                                </tr>
                                {{--                                Booking Module end--}}
						        <tr>
						            <td>{{trans('file.Accounting')}}</td>
						            <td class="report-permissions" colspan="5">
						            	<span>
						                    <div aria-checked="false" aria-disabled="false">
								                <div class="checkbox">
							                    	@if(in_array("account-index", $all_permission))
							                    	<input type="checkbox" value="1" id="account-index" name="account-index" checked>
							                    	@else
							                    	<input type="checkbox" value="1" id="account-index" name="account-index">
							                    	@endif
								                    <label for="account-index" class="padding05">{{trans('file.Account')}} &nbsp;&nbsp;</label>
								                </div>
								            </div>
						                </span>
						                <span>
						                    <div aria-checked="false" aria-disabled="false">
								                <div class="checkbox">
							                    	@if(in_array("money-transfer", $all_permission))
							                    	<input type="checkbox" value="1" id="money-transfer" name="money-transfer" checked>
							                    	@else
							                    	<input type="checkbox" value="1" id="money-transfer" name="money-transfer">
							                    	@endif
								                    <label for="money-transfer" class="padding05">{{trans('file.Money Transfer')}} &nbsp;&nbsp;</label>
								                </div>
								            </div>
						                </span>
						                <span>
						                    <div aria-checked="false" aria-disabled="false">
								                <div class="checkbox">
							                    	@if(in_array("balance-sheet", $all_permission))
							                    	<input type="checkbox" value="1" id="balance-sheet" name="balance-sheet" checked>
							                    	@else
							                    	<input type="checkbox" value="1" id="balance-sheet" name="balance-sheet">
							                    	@endif
								                    <label for="balance-sheet" class="padding05">{{trans('file.Balance Sheet')}} &nbsp;&nbsp;</label>
								                </div>
								            </div>
						                </span>
						                <span>
						                    <div aria-checked="false" aria-disabled="false">
						                    	<div class="checkbox">
							                    	@if(in_array("account-statement", $all_permission))
							                    	<input type="checkbox" value="1" id="account-statement-permission" name="account-statement" checked>
							                    	@else
							                    	<input type="checkbox" value="1" id="account-statement-permission" name="account-statement">
							                    	@endif
								                    <label for="account-statement-permission" class="padding05">{{trans('file.Account Statement')}} &nbsp;&nbsp;</label>
								                </div>
								            </div>
						                </span>
						            </td>
						        </tr>
						        <tr>
						            <td>HRM</td>
						            <td class="report-permissions" colspan="5">
                                        <span>
						                    <div aria-checked="false" aria-disabled="false">
								                <div class="checkbox">
							                    	@if(in_array("hrm", $all_permission))
                                                        <input type="checkbox" value="1" id="hrm" name="hrm" checked>
                                                    @else
                                                        <input type="checkbox" value="1" id="hrm" name="hrm">
                                                    @endif
								                    <label for="hrm" class="padding05">HRM Module &nbsp;&nbsp;</label>
								                </div>
								            </div>
						                </span>
						            	<span>
						                    <div aria-checked="false" aria-disabled="false">
								                <div class="checkbox">
							                    	@if(in_array("department", $all_permission))
							                    	<input type="checkbox" value="1" id="department" name="department" checked>
							                    	@else
							                    	<input type="checkbox" value="1" id="department" name="department">
							                    	@endif
								                    <label for="department" class="padding05">{{trans('file.Department')}} &nbsp;&nbsp;</label>
								                </div>
								            </div>
						                </span>
						                <span>
						                    <div aria-checked="false" aria-disabled="false">
								                <div class="checkbox">
							                    	@if(in_array("attendance", $all_permission))
							                    	<input type="checkbox" value="1" id="attendance" name="attendance" checked>
							                    	@else
							                    	<input type="checkbox" value="1" id="attendance" name="attendance">
							                    	@endif
								                    <label for="attendance" class="padding05">{{trans('file.Attendance')}} &nbsp;&nbsp;</label>
								                </div>
								            </div>
						                </span>
						                <span>
						                    <div aria-checked="false" aria-disabled="false">
								                <div class="checkbox">
							                    	@if(in_array("payroll", $all_permission))
							                    	<input type="checkbox" value="1" id="payroll" name="payroll" checked>
							                    	@else
							                    	<input type="checkbox" value="1" id="payroll" name="payroll">
							                    	@endif
								                    <label for="payroll" class="padding05">{{trans('file.Payroll')}} &nbsp;&nbsp;</label>
								                </div>
								            </div>
						                </span>
						                <span>
						                    <div aria-checked="false" aria-disabled="false">
								                <div class="checkbox">
							                    	@if(in_array("holiday", $all_permission))
							                    	<input type="checkbox" value="1" id="holiday" name="holiday" checked>
							                    	@else
							                    	<input type="checkbox" value="1" id="holiday" name="holiday">
							                    	@endif
								                    <label for="holiday" class="padding05">{{trans('file.Holiday Approve')}} &nbsp;&nbsp;</label>
								                </div>
								            </div>
						                </span>
						            </td>
						        </tr>
						        <tr>
						            <td>{{trans('file.Reports')}}</td>
						            <td class="report-permissions" colspan="5">
						            	<span>
						                    <div aria-checked="false" aria-disabled="false">
								                <div class="checkbox">
							                    	@if(in_array("profit-loss", $all_permission))
							                    	<input type="checkbox" value="1" id="profit-loss" name="profit-loss" checked>
							                    	@else
							                    	<input type="checkbox" value="1" id="profit-loss" name="profit-loss">
							                    	@endif
								                    <label for="profit-loss" class="padding05">{{trans('file.Summary Report')}} &nbsp;&nbsp;</label>
								                </div>
								            </div>
						                </span>
						                <span>
						                    <div aria-checked="false" aria-disabled="false">
								                <div class="checkbox">
							                    	@if(in_array("best-seller", $all_permission))
							                    	<input type="checkbox" value="1" id="best-seller" name="best-seller" checked>
							                    	@else
							                    	<input type="checkbox" value="1" id="best-seller" name="best-seller">
							                    	@endif
								                    <label for="best-seller" class="padding05">{{trans('file.Best Seller')}} &nbsp;&nbsp;</label>
								                </div>
								            </div>
						                </span>
						                <span>
						                    <div aria-checked="false" aria-disabled="false">
								                <div class="checkbox">
							                    	@if(in_array("daily-sale", $all_permission))
							                    	<input type="checkbox" value="1" id="daily-sale" name="daily-sale" checked>
							                    	@else
							                    	<input type="checkbox" value="1" id="daily-sale" name="daily-sale">
							                    	@endif
								                    <label for="daily-sale" class="padding05">{{trans('file.Daily Sale')}} &nbsp;&nbsp;</label>
								                </div>
								            </div>
						                </span>
						                <span>
						                    <div aria-checked="false" aria-disabled="false">
								                <div class="checkbox">
							                    	@if(in_array("monthly-sale", $all_permission))
							                    	<input type="checkbox" value="1" id="monthly-sale" name="monthly-sale" checked>
							                    	@else
							                    	<input type="checkbox" value="1" id="monthly-sale" name="monthly-sale">
							                    	@endif
								                    <label for="monthly-sale" class="padding05">{{trans('file.Monthly Sale')}} &nbsp;&nbsp;</label>
								                </div>
								            </div>
						                </span>
						                <span>
						                    <div aria-checked="false" aria-disabled="false">
								                <div class="checkbox">
							                    	@if(in_array("daily-purchase", $all_permission))
							                    	<input type="checkbox" value="1" id="daily-purchase" name="daily-purchase" checked>
							                    	@else
							                    	<input type="checkbox" value="1" id="daily-purchase" name="daily-purchase">
							                    	@endif
								                    <label for="daily-purchase" class="padding05">{{trans('file.Daily Purchase')}} &nbsp;&nbsp;</label>
								                </div>
								            </div>
						                </span>
						                <span>
						                    <div aria-checked="false" aria-disabled="false">
						                    	<div class="checkbox">
							                    	@if(in_array("monthly-purchase", $all_permission))
							                    	<input type="checkbox" value="1" id="monthly-purchase" name="monthly-purchase" checked>
							                    	@else
							                    	<input type="checkbox" value="1" id="monthly-purchase" name="monthly-purchase">
							                    	@endif
								                    <label for="monthly-purchase" class="padding05">{{trans('file.Monthly Purchase')}} &nbsp;&nbsp;</label>
								                </div>
								            </div>
						                </span>
						                <span>
						                    <div aria-checked="false" aria-disabled="false">
								                <div class="checkbox">
							                    	@if(in_array("product-report", $all_permission))
							                    	<input type="checkbox" value="1" id="product-report" name="product-report" checked>
							                    	@else
							                    	<input type="checkbox" value="1" id="product-report" name="product-report">
							                    	@endif
								                    <label for="product-report" class="padding05">{{trans('file.Product Report')}} &nbsp;&nbsp;</label>
								                </div>
								            </div>
						                </span>
						                <span>
						                    <div aria-checked="false" aria-disabled="false">
								                <div class="checkbox">
							                    	@if(in_array("payment-report", $all_permission))
							                    	<input type="checkbox" value="1" id="payment-report" name="payment-report" checked>
							                    	@else
							                    	<input type="checkbox" value="1" id="payment-report" name="payment-report">
							                    	@endif
								                    <label for="payment-report" class="padding05">{{trans('file.Payment Report')}} &nbsp;&nbsp;</label>
								                </div>
								            </div>
						                </span>
						                <span>
						                    <div aria-checked="false" aria-disabled="false">
								                <div class="checkbox">
							                    	@if(in_array("purchase-report", $all_permission))
							                    	<input type="checkbox" value="1" id="purchase-report" name="purchase-report" checked>
							                    	@else
							                    	<input type="checkbox" value="1" id="purchase-report" name="purchase-report">
							                    	@endif
								                    <label for="purchase-report" class="padding05"> {{trans('file.Purchase Report')}} &nbsp;&nbsp;</label>
								                </div>
								            </div>
						                </span>
						                <span>
						                    <div aria-checked="false" aria-disabled="false">
								                <div class="checkbox">
							                    	@if(in_array("sale-report", $all_permission))
							                    	<input type="checkbox" value="1" id="sale-report" name="sale-report" checked>
							                    	@else
							                    	<input type="checkbox" value="1" id="sale-report" name="sale-report">
							                    	@endif
								                    <label for="sale-report" class="padding05">{{trans('file.Sale Report')}} &nbsp;&nbsp;</label>
								                </div>
								            </div>
						                </span>
						                <span>
						                    <div aria-checked="false" aria-disabled="false">
						                    	<div class="checkbox">
							                    	@if(in_array("warehouse-report", $all_permission))
							                    	<input type="checkbox" value="1" id="warehouse-report" name="warehouse-report" checked>
							                    	@else
							                    	<input type="checkbox" value="1" id="warehouse-report" name="warehouse-report">
							                    	@endif
								                    <label for="warehouse-report" class="padding05">{{trans('file.Warehouse Report')}} &nbsp;&nbsp;</label>
								                </div>
								            </div>
						                </span>
						                <span>
						                    <div aria-checked="false" aria-disabled="false">
						                    	<div class="checkbox">
							                    	@if(in_array("warehouse-stock-report", $all_permission))
							                    	<input type="checkbox" value="1" id="warehouse-stock-report" name="warehouse-stock-report" checked>
							                    	@else
							                    	<input type="checkbox" value="1" id="warehouse-stock-report" name="warehouse-stock-report">
							                    	@endif
								                    <label for="warehouse-stock-report" class="padding05">{{trans('file.Warehouse Stock Chart')}} &nbsp;&nbsp;</label>
								                </div>
								            </div>
						                </span>
						                <span>
						                    <div aria-checked="false" aria-disabled="false">
						                    	<div class="checkbox">
							                    	@if(in_array("product-qty-alert", $all_permission))
							                    	<input type="checkbox" value="1" id="product-qty-alert" name="product-qty-alert" checked>
							                    	@else
							                    	<input type="checkbox" value="1" id="product-qty-alert" name="product-qty-alert">
							                    	@endif
													<label for="product-qty-alert" class="padding05">{{trans('file.Product Quantity Alert')}} &nbsp;&nbsp;</label>
								                </div>
								            </div>
								        </span>
								        <span>
						                    <div aria-checked="false" aria-disabled="false">
								                <div class="checkbox">
							                    	@if(in_array("user-report", $all_permission))
							                    	<input type="checkbox" value="1" id="user-report" name="user-report" checked>
							                    	@else
							                    	<input type="checkbox" value="1" id="user-report" name="user-report">
							                    	@endif
								                    <label for="user-report" class="padding05">{{trans('file.User Report')}} &nbsp;&nbsp;</label>
								                </div>
								            </div>
						                </span>
						                <span>
						                    <div aria-checked="false" aria-disabled="false">
								                <div class="checkbox">
							                    	@if(in_array("customer-report", $all_permission))
							                    	<input type="checkbox" value="1" id="customer-report" name="customer-report" checked>
							                    	@else
							                    	<input type="checkbox" value="1" id="customer-report" name="customer-report">
							                    	@endif
								                    <label for="customer-report" class="padding05">{{trans('file.Customer Report')}} &nbsp;&nbsp;</label>
								                </div>
								            </div>
						                </span>
						                <span>
						                    <div aria-checked="false" aria-disabled="false">
								                <div class="checkbox">
							                    	@if(in_array("supplier-report", $all_permission))
							                    	<input type="checkbox" value="1" id="supplier-report" name="supplier-report" checked>
							                    	@else
							                    	<input type="checkbox" value="1" id="supplier-report" name="supplier-report">
							                    	@endif
								                    <label for="Supplier-report" class="padding05">{{trans('file.Supplier Report')}} &nbsp;&nbsp;</label>
								                </div>
								            </div>
						                </span>
						                <span>
						                    <div aria-checked="false" aria-disabled="false">
								                <div class="checkbox">
							                    	@if(in_array("due-report", $all_permission))
							                    	<input type="checkbox" value="1" id="due-report" name="due-report" checked>
							                    	@else
							                    	<input type="checkbox" value="1" id="due-report" name="due-report">
							                    	@endif
								                    <label for="due-report" class="padding05">{{trans('file.Due Report')}} &nbsp;&nbsp;</label>
								                </div>
								            </div>
						                </span>
                                        <span>
						                    <div aria-checked="false" aria-disabled="false">
								                <div class="checkbox">
							                    	@if(in_array("average-report", $all_permission))
                                                        <input type="checkbox" value="1" id="average-report" name="average-report" checked>
                                                    @else
                                                        <input type="checkbox" value="1" id="average-report" name="average-report">
                                                    @endif
								                    <label for="average-report" class="padding05">{{trans('file.Average Report')}} &nbsp;&nbsp;</label>
								                </div>
								            </div>
						                </span>
						            </td>
						        </tr>
						        <tr>
						            <td>{{trans('file.settings')}}</td>
						            <td class="report-permissions" colspan="5">
						            	<span>
						                    <div aria-checked="false" aria-disabled="false">
								                <div class="checkbox">
							                    	@if(in_array("dashboard", $all_permission))
                                                        <input type="checkbox" value="1" id="dashboard" name="dashboard" checked>
                                                    @else
                                                        <input type="checkbox" value="1" id="dashboard" name="dashboard">
                                                    @endif
								                    <label for="dashboard" class="padding05">{{trans('file.dashboard')}} &nbsp;&nbsp;</label>
								                </div>
								            </div>
						                </span>
                                        <span>
						                    <div aria-checked="false" aria-disabled="false">
								                <div class="checkbox">
							                    	@if(in_array("send_notification", $all_permission))
							                    	<input type="checkbox" value="1" id="send_notification" name="send_notification" checked>
							                    	@else
							                    	<input type="checkbox" value="1" id="send_notification" name="send_notification">
							                    	@endif
								                    <label for="send_notification" class="padding05">{{trans('file.Send Notification')}} &nbsp;&nbsp;</label>
								                </div>
								            </div>
						                </span>
						            	<span>
						                    <div aria-checked="false" aria-disabled="false">
								                <div class="checkbox">
							                    	@if(in_array("warehouse", $all_permission))
							                    	<input type="checkbox" value="1" id="warehouse" name="warehouse" checked>
							                    	@else
							                    	<input type="checkbox" value="1" id="warehouse" name="warehouse">
							                    	@endif
								                    <label for="warehouse" class="padding05">{{trans('file.Warehouse')}} &nbsp;&nbsp;</label>
								                </div>
								            </div>
						                </span>
						            	<span>
						                    <div aria-checked="false" aria-disabled="false">
								                <div class="checkbox">
							                    	@if(in_array("customer_group", $all_permission))
							                    	<input type="checkbox" value="1" id="customer_group" name="customer_group" checked>
							                    	@else
							                    	<input type="checkbox" value="1" id="customer_group" name="customer_group">
							                    	@endif
								                    <label for="customer_group" class="padding05">{{trans('file.Customer Group')}} &nbsp;&nbsp;</label>
								                </div>
								            </div>
						                </span>
						                <span>
								            <div aria-checked="false" aria-disabled="false">
								                <div class="checkbox">
							                    	@if(in_array("brand", $all_permission))
							                    	<input type="checkbox" value="1" id="brand" name="brand" checked>
							                    	@else
							                    	<input type="checkbox" value="1" id="brand" name="brand">
							                    	@endif
								                    <label for="brand" class="padding05">{{trans('file.Brand')}} &nbsp;&nbsp;</label>
								                </div>
								            </div>
						                </span>
						                <span>
								            <div aria-checked="false" aria-disabled="false">
								                <div class="checkbox">
							                    	@if(in_array("unit", $all_permission))
							                    	<input type="checkbox" value="1" id="unit" name="unit" checked>
							                    	@else
							                    	<input type="checkbox" value="1" id="unit" name="unit">
							                    	@endif
								                    <label for="unit" class="padding05">{{trans('file.Unit')}} &nbsp;&nbsp;</label>
								                </div>
								            </div>
						                </span>
						                <span>
								            <div aria-checked="false" aria-disabled="false">
								                <div class="checkbox">
							                    	@if(in_array("currency", $all_permission))
							                    	<input type="checkbox" value="1" id="currency" name="currency" checked>
							                    	@else
							                    	<input type="checkbox" value="1" id="currency" name="currency">
							                    	@endif
								                    <label for="currency" class="padding05">{{trans('file.Currency')}} &nbsp;&nbsp;</label>
								                </div>
								            </div>
						                </span>
						                <span>
								            <div aria-checked="false" aria-disabled="false">
								                <div class="checkbox">
							                    	@if(in_array("tax", $all_permission))
							                    	<input type="checkbox" value="1" id="tax" name="tax" checked>
							                    	@else
							                    	<input type="checkbox" value="1" id="tax" name="tax">
							                    	@endif
								                    <label for="tax" class="padding05">{{trans('file.Tax')}} &nbsp;&nbsp;</label>
								                </div>
								            </div>
						                </span>
						                <span>
						                    <div aria-checked="false" aria-disabled="false">
								                <div class="checkbox">
							                    	@if(in_array("backup_database", $all_permission))
							                    	<input type="checkbox" value="1" id="backup_database" name="backup_database" checked>
							                    	@else
							                    	<input type="checkbox" value="1" id="backup_database" name="backup_database">
							                    	@endif
								                    <label for="backup_database" class="padding05">{{trans('file.Backup Database')}} &nbsp;&nbsp;</label>
								                </div>
								            </div>
						                </span>
						            	<span>
						                    <div aria-checked="false" aria-disabled="false">
								                <div class="checkbox">
							                    	@if(in_array("general_setting", $all_permission))
							                    	<input type="checkbox" value="1" id="general_setting" name="general_setting" checked>
							                    	@else
							                    	<input type="checkbox" value="1" id="general_setting" name="general_setting">
							                    	@endif
								                    <label for="general_setting" class="padding05">{{trans('file.General Setting')}} &nbsp;&nbsp;</label>
								                </div>
								            </div>
						                </span>
                                        <span>
						                    <div aria-checked="false" aria-disabled="false">
								                <div class="checkbox">
							                    	@if(in_array("env_setting", $all_permission))
							                    	<input type="checkbox" value="1" id="env_setting" name="env_setting" checked>
							                    	@else
							                    	<input type="checkbox" value="1" id="env_setting" name="env_setting">
							                    	@endif
								                    <label for="env_setting" class="padding05">Environment (.env) Settings &nbsp;&nbsp;</label>
								                </div>
								            </div>
						                </span>
						                <span>
						                    <div aria-checked="false" aria-disabled="false">
								                <div class="checkbox">
							                    	@if(in_array("mail_setting", $all_permission))
							                    	<input type="checkbox" value="1" id="mail_setting" name="mail_setting" checked>
							                    	@else
							                    	<input type="checkbox" value="1" id="mail_setting" name="mail_setting">
							                    	@endif
								                    <label for="mail_setting" class="padding05">{{trans('file.Mail Setting')}} &nbsp;&nbsp;</label>
								                </div>
								            </div>
						                </span>
						                <span>
						                    <div aria-checked="false" aria-disabled="false">
								                <div class="checkbox">
							                    	@if(in_array("sms_setting", $all_permission))
							                    	<input type="checkbox" value="1" id="sms_setting" name="sms_setting" checked>
							                    	@else
							                    	<input type="checkbox" value="1" id="sms_setting" name="sms_setting">
							                    	@endif
								                    <label for="sms_setting" class="padding05">{{trans('file.SMS Setting')}} &nbsp;&nbsp;</label>
								                </div>
								            </div>
						                </span>
						                <span>
						                    <div aria-checked="false" aria-disabled="false">
								                <div class="checkbox">
							                    	@if(in_array("create_sms", $all_permission))
							                    	<input type="checkbox" value="1" id="create_sms" name="create_sms" checked>
							                    	@else
							                    	<input type="checkbox" value="1" id="create_sms" name="create_sms">
							                    	@endif
								                    <label for="create_sms" class="padding05">{{trans('file.Create SMS')}} &nbsp;&nbsp;</label>
								                </div>
								            </div>
						                </span>
						                <span>
						                    <div aria-checked="false" aria-disabled="false">
								                <div class="checkbox">
							                    	@if(in_array("pos_setting", $all_permission))
							                    	<input type="checkbox" value="1" id="pos_setting" name="pos_setting" checked>
							                    	@else
							                    	<input type="checkbox" value="1" id="pos_setting" name="pos_setting">
							                    	@endif
								                    <label for="pos_setting" class="padding05">{{trans('file.POS Setting')}} &nbsp;&nbsp;</label>
								                </div>
								            </div>
						                </span>
						                <span>
						                    <div aria-checked="false" aria-disabled="false">
								                <div class="checkbox">
							                    	@if(in_array("hrm_setting", $all_permission))
							                    	<input type="checkbox" value="1" id="hrm_setting" name="hrm_setting" checked>
							                    	@else
							                    	<input type="checkbox" value="1" id="hrm_setting" name="hrm_setting">
							                    	@endif
								                    <label for="hrm_setting" class="padding05">{{trans('file.HRM Setting')}} &nbsp;&nbsp;</label>
								                </div>
								            </div>
						                </span>
						                <span>
						                    <div aria-checked="false" aria-disabled="false">
								                <div class="checkbox">
							                    	@if(in_array("reward_point_setting", $all_permission))
							                    	<input type="checkbox" value="1" id="reward_point_setting" name="reward_point_setting" checked>
							                    	@else
							                    	<input type="checkbox" value="1" id="reward_point_setting" name="reward_point_setting">
							                    	@endif
								                    <label for="reward_point_setting" class="padding05">{{trans('file.Reward Point Setting')}} &nbsp;&nbsp;</label>
								                </div>
								            </div>
						                </span>
                                        <span>
						                    <div aria-checked="false" aria-disabled="false">
								                <div class="checkbox">
							                    	@if(in_array("price-change", $all_permission))
                                                        <input type="checkbox" value="1" id="price-change" name="price-change" checked>
                                                    @else
                                                        <input type="checkbox" value="1" id="price-change" name="price-change">
                                                    @endif
								                    <label for="price-change" class="padding05">{{trans('file.Price change')}} &nbsp;&nbsp;</label>
								                </div>
								            </div>
						                </span>
                                        <span>
						                    <div aria-checked="false" aria-disabled="false">
								                <div class="checkbox">
							                    	@if(in_array("JE-method", $all_permission))
                                                        <input type="checkbox" value="1" id="JE-method" name="JE-method" checked>
                                                    @else
                                                        <input type="checkbox" value="1" id="JE-method" name="JE-method">
                                                    @endif
								                    <label for="JE-method" class="padding05">{{trans('file.JE method')}} &nbsp;&nbsp;</label>
								                </div>
								            </div>
						                </span>
                                        <span>
						                    <div aria-checked="false" aria-disabled="false">
								                <div class="checkbox">
							                    	@if(in_array("multiple_batch", $all_permission))
                                                        <input type="checkbox" value="1" id="multiple_batch" name="multiple_batch" checked>
                                                    @else
                                                        <input type="checkbox" value="1" id="multiple_batch" name="multiple_batch">
                                                    @endif
								                    <label for="multiple_batch" class="padding05">{{trans('file.Multiple Batch')}} &nbsp;&nbsp;</label>
								                </div>
								            </div>
						                </span>
                                        <span>
						                    <div aria-checked="false" aria-disabled="false">
								                <div class="checkbox">
							                    	@if(in_array("developed_by", $all_permission))
                                                        <input type="checkbox" value="1" id="developed_by" name="developed_by" checked>
                                                    @else
                                                        <input type="checkbox" value="1" id="developed_by" name="developed_by">
                                                    @endif
								                    <label for="developed_by" class="padding05">{{trans('file.Developed By')}} &nbsp;&nbsp;</label>
								                </div>
								            </div>
						                </span>
                                        <span>
						                    <div aria-checked="false" aria-disabled="false">
								                <div class="checkbox">
							                    	@if(in_array("zero_stock", $all_permission))
                                                        <input type="checkbox" value="1" id="zero_stock" name="zero_stock" checked>
                                                    @else
                                                        <input type="checkbox" value="1" id="zero_stock" name="zero_stock">
                                                    @endif
								                    <label for="zero_stock" class="padding05">Search All Products &nbsp;&nbsp;</label>
								                </div>
								            </div>
						                </span>
{{--                                        <span>--}}
{{--						                    <div aria-checked="false" aria-disabled="false">--}}
{{--								                <div class="checkbox">--}}
{{--							                    	@if(in_array("search_all_products", $all_permission))--}}
{{--                                                        <input type="checkbox" value="1" id="search_all_products" name="search_all_products" checked>--}}
{{--                                                    @else--}}
{{--                                                        <input type="checkbox" value="1" id="search_all_products" name="search_all_products">--}}
{{--                                                    @endif--}}
{{--								                    <label for="search_all_products" class="padding05">Search All Products Product &nbsp;&nbsp;</label>--}}
{{--								                </div>--}}
{{--								            </div>--}}
{{--						                </span>--}}
                                        <span>
						                    <div aria-checked="false" aria-disabled="false">
								                <div class="checkbox">
							                    	@if(in_array("star_product", $all_permission))
                                                        <input type="checkbox" value="1" id="star_product" name="star_product" checked>
                                                    @else
                                                        <input type="checkbox" value="1" id="star_product" name="star_product">
                                                    @endif
								                    <label for="star_product" class="padding05">{{trans('file.Star Sale')}} &nbsp;&nbsp;</label>
								                </div>
								            </div>
						                </span>
                                        <span>
						                    <div aria-checked="false" aria-disabled="false">
								                <div class="checkbox">
							                    	@if(in_array("search_on_click", $all_permission))
                                                        <input type="checkbox" value="1" id="search_on_click" name="search_on_click" checked>
                                                    @else
                                                        <input type="checkbox" value="1" id="search_on_click" name="search_on_click">
                                                    @endif
								                    <label for="search_on_click" class="padding05">{{trans('file.Search On Click')}} &nbsp;&nbsp;</label>
								                </div>
								            </div>
						                </span>
						            </td>
						        </tr>
						        <tr>
						            <td>{{trans('file.Miscellaneous')}}</td>
						            <td class="report-permissions" colspan="5">
						            	<span>
								            <div aria-checked="false" aria-disabled="false">
								                <div class="checkbox">
							                    	@if(in_array("category", $all_permission))
							                    	<input type="checkbox" value="1" id="category" name="category" checked>
							                    	@else
							                    	<input type="checkbox" value="1" id="category" name="category">
							                    	@endif
								                    <label for="category" class="padding05">{{trans('file.category')}} &nbsp;&nbsp;</label>
								                </div>
								            </div>
						            	</span>
						            	<span>
						            		<div aria-checked="false" aria-disabled="false">
								                <div class="checkbox">
							                    	@if(in_array("delivery", $all_permission))
							                    	<input type="checkbox" value="1" id="delivery" name="delivery" checked>
							                    	@else
							                    	<input type="checkbox" value="1" id="delivery" name="delivery">
							                    	@endif
								                    <label for="delivery" class="padding05">{{trans('file.Delivery')}} &nbsp;&nbsp;</label>
								                </div>
								            </div>
						            	</span>
						            	<span>
						                    <div aria-checked="false" aria-disabled="false">
								                <div class="checkbox">
							                    	@if(in_array("stock_count", $all_permission))
							                    	<input type="checkbox" value="1" id="stock_count" name="stock_count" checked>
							                    	@else
							                    	<input type="checkbox" value="1" id="stock_count" name="stock_count">
							                    	@endif
								                    <label for="stock_count" class="padding05">{{trans('file.Stock Count')}} &nbsp;&nbsp;</label>
								                </div>
								            </div>
						                </span>
						                <span>
						                    <div aria-checked="false" aria-disabled="false">
								                <div class="checkbox">
							                    	@if(in_array("adjustment", $all_permission))
							                    	<input type="checkbox" value="1" id="adjustment" name="adjustment" checked>
							                    	@else
							                    	<input type="checkbox" value="1" id="adjustment" name="adjustment">
							                    	@endif
								                    <label for="adjustment" class="padding05">{{trans('file.Adjustment')}} &nbsp;&nbsp;</label>
								                </div>
								            </div>
						                </span>
						                <span>
						                    <div aria-checked="false" aria-disabled="false">
								                <div class="checkbox">
							                    	@if(in_array("gift_card", $all_permission))
							                    	<input type="checkbox" value="1" id="gift_card" name="gift_card" checked>
							                    	@else
							                    	<input type="checkbox" value="1" id="gift_card" name="gift_card">
							                    	@endif
								                    <label for="gift_card" class="padding05">{{trans('file.Gift Card')}} &nbsp;&nbsp;</label>
								                </div>
								            </div>
						                </span>
						                <span>
						                    <div aria-checked="false" aria-disabled="false">
								                <div class="checkbox">
							                    	@if(in_array("coupon", $all_permission))
							                    	<input type="checkbox" value="1" id="coupon" name="coupon" checked>
							                    	@else
							                    	<input type="checkbox" value="1" id="coupon" name="coupon">
							                    	@endif
								                    <label for="coupon" class="padding05">{{trans('file.Coupon')}} &nbsp;&nbsp;</label>
								                </div>
								            </div>
						                </span>
						                <span>
						                    <div aria-checked="false" aria-disabled="false">
								                <div class="checkbox">
							                    	@if(in_array("print_barcode", $all_permission))
							                    	<input type="checkbox" value="1" id="print_barcode" name="print_barcode" checked>
							                    	@else
							                    	<input type="checkbox" value="1" id="print_barcode" name="print_barcode">
							                    	@endif
								                    <label for="print_barcode" class="padding05">{{trans('file.print_barcode')}} &nbsp;&nbsp;</label>
								                </div>
								            </div>
						                </span>
						                <span>
						                    <div aria-checked="false" aria-disabled="false">
								                <div class="checkbox">
							                    	@if(in_array("empty_database", $all_permission))
							                    	<input type="checkbox" value="1" id="empty_database" name="empty_database" checked>
							                    	@else
							                    	<input type="checkbox" value="1" id="empty_database" name="empty_database">
							                    	@endif
								                    <label for="empty_database" class="padding05">{{trans('file.Empty Database')}} &nbsp;&nbsp;</label>
								                </div>
								            </div>
						                </span>
						                <span>
						                    <div aria-checked="false" aria-disabled="false">
								                <div class="checkbox">
							                    	@if(in_array("today_sale", $all_permission))
							                    	<input type="checkbox" value="1" id="today_sale" name="today_sale" checked>
							                    	@else
							                    	<input type="checkbox" value="1" id="today_sale" name="today_sale">
							                    	@endif
								                    <label for="today_sale" class="padding05">{{trans('file.Today Sale')}} &nbsp;&nbsp;</label>
								                </div>
								            </div>
						                </span>
						                <span>
						                    <div aria-checked="false" aria-disabled="false">
								                <div class="checkbox">
							                    	@if(in_array("today_profit", $all_permission))
							                    	<input type="checkbox" value="1" id="today_profit" name="today_profit" checked>
							                    	@else
							                    	<input type="checkbox" value="1" id="today_profit" name="today_profit">
							                    	@endif
								                    <label for="today_profit" class="padding05">{{trans('file.Today Profit')}} &nbsp;&nbsp;</label>
								                </div>
								            </div>
						                </span>
						            </td>
						        </tr>
						        </tbody>
						    </table>
						</div>
						<div class="form-group">
	                        <input type="submit" value="{{trans('file.submit')}}" class="btn btn-primary">
	                    </div>
                    </div>
                    {!! Form::close() !!}
                </div>
            </div>
        </div>
    </div>
</section>

<script type="text/javascript">

	$("ul#setting").siblings('a').attr('aria-expanded','true');
    $("ul#setting").addClass("show");
    $("ul#setting #role-menu").addClass("active");

	$("#select_all").on( "change", function() {
	    if ($(this).is(':checked')) {
	        $("tbody input[type='checkbox']").prop('checked', true);
	    }
	    else {
	        $("tbody input[type='checkbox']").prop('checked', false);
	    }
	});

	$(".section-select-all").on("change", function() {
	    var checked = $(this).is(':checked');
	    var $header = $(this).closest('tr.permission-section-header');
	    var $row = $header.next();
	    while ($row.length && !$row.hasClass('permission-section-header') && !$row.hasClass('permission-section-end')) {
	        $row.find('input[type="checkbox"]').prop('checked', checked);
	        $row = $row.next();
	    }
	});
</script>
@endsection
