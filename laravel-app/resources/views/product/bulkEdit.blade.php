@php use Illuminate\Support\Facades\DB; @endphp
@extends('layout.main')

@section('content')
    <style>
        .bg-info {
            color: white;
        }
    </style>
    @if(session()->has('not_permitted'))
        <div class="alert alert-danger alert-dismissible text-center"><button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>{{ session()->get('not_permitted') }}</div>
    @endif
    @if(session()->has('message'))
        <div class="alert alert-success alert-dismissible text-center"><button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>{{ session()->get('message') }}</div>
    @endif
    <section class="forms">
        <div class="container-fluid">
            <div class="row">
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-header d-flex align-items-center">
                            <h4>{{trans('file.Update Bulk Product')}}</h4> &nbsp; &nbsp; &nbsp; &nbsp; &nbsp;
                            <input id="myInput" type="text" placeholder="Search..">
                        </div>
                        <div class="btn-group px-4" style="justify-content: flex-end" role="group" aria-label="Basic example">
                            <a href="{{ route('product.edit.by.selection.page', ['total' => count($lims_product_data), 'last' => $lims_product_data[count($lims_product_data) - 1]->id, 'dir' => 0, 'warehouse' => $warehouse_id]) }}" type="button" class="btn btn-warning"> <span class="fa fa-angle-left"></span> Back</a>
                            <a href="{{ route('product.edit.by.selection.page', ['total' => count($lims_product_data), 'last' => $lims_product_data[count($lims_product_data) - 1]->id, 'dir' => 1, 'warehouse' => $warehouse_id]) }}" type="button" class="btn btn-info">Next <span class="fa fa-angle-right"></span></a>
                        </div>
                        <div class="card-body">
                            <form action="{{ route('product.update.by.selection') }}" method="post">
                                @csrf
                                <table class="table table-bordered">
                                    <thead style="background: lightslategray; color: white">
                                    <tr>
                                        <th style="width: 15%">Name</th>
                                        <th style="width: 8%">Type</th>
                                        <th style="width: 10%">Category</th>
                                        <th style="width: 10%">Warehouse</th>
                                        <th style="width: 10%">Qty</th>
                                        <th style="width: 10%">Unit</th>
                                        <th style="width: 10%">Cost</th>
                                        <th style="width: 10%">Price</th>
                                        <th style="width: 10%">Product Location</th>
                                        <th style="width: 5%">Batch</th>
                                    </tr>
                                    </thead>
                                    <tbody id="myTable">
                                    @foreach($lims_product_data as $product)
                                        <tr class="product-data">
                                            <td>
                                                <span style="display: none">{{ $product->name }}</span>
                                                <input type="hidden" class="product-id" value="{{ $product->id }}" name="id[{{ $product->id }}]">
                                                <input type="hidden" value="{{ $warehouse_id }}" name="warehouse">
                                                <input class="form-control mb-3 product-name-{{ $product->id }}" type="text" name="name[{{ $product->id }}]" value="{{ $product->name }}"></td>
                                            <td>
                                                <select name="type[{{ $product->id }}]" class="form-control selectpicker" data-live-search="true" data-live-search-style="begins" onchange="updateQtyInput(this, {{ $product->id }})">
                                                    <option value="standard" {{ $product->type == 'standard' ? 'selected' : '' }}>Standard</option>
                                                    <option value="digital" {{ $product->type == 'digital' ? 'selected' : '' }}>Service</option>
                                                </select>
                                            </td>
                                            <td>
                                                <select name="category[{{ $product->id }}]" class="form-control selectpicker" data-live-search="true" data-live-search-style="begins">
                                                    @foreach($lims_category_list as $cat)
                                                        <option value="{{ $cat->id }}" {{ $cat->id == $product->category_id ? 'selected' : '' }}>{{ $cat->name }}</option>
                                                    @endforeach
                                                </select>
                                            </td>

                                            <td>
                                                <select name="warehouse_id[{{ $product->id }}]" {{ $product->type == 'digital' ? 'disabled' : '' }} class="form-control selectpicker" data-live-search="true" data-live-search-style="begins" onchange="changeWarehouse(this, {{ $product->code }}, {{ $product->is_batch }}, {{ $product->id }})">
                                                    <option value="" data-quantity="{{ $product->SumWarehouseQty($product->id, 0) }}"> -- All Warehouses -- </option>
                                                    @foreach($lims_warehouse_list as $warehouse_single)
                                                        <option data-quantity="{{ $product->SumWarehouseQty($product->id, $warehouse_single->id) }}" value="{{ $warehouse_single->id }}" {{ $warehouse_single->id ==  $warehouse_id ? 'selected' : ''}}>{{ $warehouse_single->name }}</option>
                                                    @endforeach
                                                </select>
                                            </td>
                                            <td>
                                                <input class="form-control qty-{{ $product->id }}" name="qty[{{ $product->id }}]" id="product-qty-{{$product->id}}" type="text" {{ $product->type == 'digital' ? 'readonly' : '' }} {{ $product->is_batch == 1 ? 'readonly' : '' }} value="{{ $product->SumWarehouseQty($product->id, $warehouse_id) }}" {{ $warehouse_id == 0 ? 'readonly' : '' }}>
                                            </td>
                                            <td>
                                                <select name="unit[{{ $product->id }}]" class="form-control selectpicker" data-live-search="true" data-live-search-style="begins">
                                                    @foreach($lims_unit_list as $unit)
                                                        <option value="{{ $unit->id }}" {{ $unit->id == $product->unit_id ? 'selected' : '' }}>{{ $unit->unit_name }}</option>
                                                    @endforeach
                                                </select>
                                            </td>
                                            <td><input class="form-control" type="text" name="cost[{{ $product->id }}]" value="{{ $product->cost }}"></td>
                                            <td><input class="form-control" type="text" name="price[{{ $product->id }}]" value="{{ $product->price }}"></td>
                                            <td><input class="form-control" type="text" placeholder="Product Location" name="product_location[{{ $product->id }}]" value="{{ $product->location }}"></td>
                                            <td>
                                                @if($product->type == 'standard')
                                                    @if($product->is_batch == 0)
                                                        <input class="form-control" type="hidden" value="0" name="is_batch[{{ $product->id }}]">
                                                        <input class="form-control is-batch-{{ $product->id }}" type="checkbox" name="is_batch[{{ $product->id }}]">
                                                    @else
                                                        <input class="form-control is-batch-{{ $product->id }}" type="checkbox" value="1" name="is_batch[{{ $product->id }}]" checked>
                                                    @endif
                                                @else
                                                    <input class="form-control" type="hidden" value="0" name="is_batch[{{ $product->id }}]">
                                                @endif

                                            </td>
                                        </tr>
                                        @if($product->is_batch == 1)
                                            @php
                                                $batches = DB::table('product_warehouse')->join('product_batches', 'product_batches.id', 'product_warehouse.product_batch_id')
                                                ->where('product_warehouse.product_id', $product->id)
                                                ->where('product_warehouse.warehouse_id', $warehouse_id)
                                                ->where('product_batch_id', '!=', null)
                                                ->select('product_warehouse.qty', 'product_warehouse.id', 'product_batches.batch_no', 'product_batches.expired_date')
                                                ->get();
                                            @endphp
                                            <tr class="batch-wrapper-{{$product->id}}">
                                                <td colspan="12">
                                                    <table class="table table-bordered">
                                                        <tr class="bg-info batch-tr" data-id="{{ $product->id }}">
                                                            <td colspan="12">
                                                                <div class="sub-tr-content text-center">
                                                                    <strong>Batch Details for {{ $product->name }}:</strong>
                                                                </div>
                                                            </td>
                                                        </tr>
                                                        @foreach($batches as $batch)
                                                            <tr class="bg-warning batch-data-{{ $batch->id }}">
                                                                <td colspan="3">
                                                                    <input class="form-control" required type="text" value="{{ $batch->batch_no }}" name="batch_no[{{ $product->id }}][]">
                                                                </td>
                                                                <td colspan="3">
                                                                    <input class="form-control" required type="date" value="{{ $batch->expired_date }}" name="batch_expire[{{ $product->id }}][]">
                                                                </td>
                                                                <td colspan="2">
                                                                    <input class="form-control batch-qty-{{ $product->id }}" required onkeyup="updateBatchQty({{ $product->id }})" type="number" value="{{ $batch->qty }}" name="batch_qty[{{ $product->id }}][]">
                                                                </td>
                                                                <td colspan="2">
                                                                    <i class="btn btn-danger" onclick="removeTR(this, {{ $product->id }})">X</i>
                                                                </td>
                                                            </tr>
                                                        @endforeach
                                                        <tr>
                                                            <td colspan="12">
                                                                <div class="text-center">
                                                                    <a class="btn btn-success text-white add-new-batch">+ Add New</a>
                                                                </div>
                                                            </td>
                                                        </tr>
                                                    </table>
                                                </td>
                                            </tr>
                                        @endif
                                    @endforeach
                                    </tbody>
                                </table>
                                <button type="submit" class="btn btn-primary pull-right mb-3"> <i class="fa fa-pencil"></i> Update</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <script type="text/javascript">

        $("ul#product").siblings('a').attr('aria-expanded','true');
        $("ul#product").addClass("show");

        $(document).ready(function(){
            $("#myInput").on("keyup", function() {
                var value = $(this).val().toLowerCase();
                $("#myTable tr").filter(function() {
                    $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1)
                });
            });

            $(document).on('click', '.add-new-batch', function(e) {
                e.preventDefault(); // Prevent the default action

                // Get the closest <tr> element with the class 'batch-tr'
                var closestTr = $(this).closest('tr').prevAll('.batch-tr').first();
                var productId = closestTr.attr('data-id');

                newRow = addNewRow(productId);
                closestTr.after(newRow);
            });

        });


        function changeWarehouse(warehouseElement, productCode, isBatch, productId) {
            const warehouseId = warehouseElement.value;
            const batchWrapper = document.querySelector(`.batch-wrapper-${productId}`);

            // Check if batchWrapper exists before manipulating it
            if (isBatch) {
                // Remove all existing batch rows with class "bg-warning"
                const batchRows = batchWrapper.querySelectorAll('.bg-warning');
                batchRows.forEach(row => row.remove());

                $.ajax({
                    type: 'GET',
                    url: '{{ route('edit.by.selection.warehouse.products') }}',
                    data: {
                        product_id: productId,
                        warehouse_id: warehouseId
                    },
                    dataType: 'json',
                    success: function(response) {
                        const batches = response['batches'];

                        // Loop through each batch
                        batches.forEach(function(batch) {
                            const newRow = document.createElement('tr');
                            newRow.classList.add('bg-warning');
                            newRow.innerHTML = `
                            <td colspan="3">
                                <input class="form-control" required type="text" name="batch_no[${productId}][]" placeholder="Batch No" value="${batch.batch_no}">
                            </td>
                            <td colspan="3">
                                <input class="form-control" required type="date" name="batch_expire[${productId}][]" placeholder="Expire Date" value="${batch.expired_date}">
                            </td>
                            <td colspan="2">
                                <input class="form-control batch-qty-${productId}" required onkeyup="updateBatchQty(${productId})" type="number" name="batch_qty[${productId}][]" placeholder="Qty" value="${batch.qty}">
                            </td>
                            <td colspan="2">
                                <i class="btn btn-danger" onclick="removeTR(this, ${productId})">X</i>
                            </td>
                        `;

                            const addNewButtonRow = batchWrapper.querySelector('.add-new-batch').closest('tr');
                            if (addNewButtonRow) {
                                batchWrapper.querySelector('tbody').insertBefore(newRow, addNewButtonRow);
                            } else {
                                batchWrapper.querySelector('tbody').appendChild(newRow);
                            }
                        });
                        updateBatchQty(productId);
                    }
                });
            } else {
                const qty =  $(warehouseElement).find('option:selected').data('quantity');
                $("#product-qty-"+productId).val(qty);
                $("#product-qty-"+productId).prop('readonly', false);
            }
        }

        // Function to remove a row when "X" button is clicked
        function removeTR(element, productId) {
            const row = element.closest('tr');
            row.remove();
            updateBatchQty(productId);
        }


        function updateQtyInput(selectElement, productId) {
            var selectedValue = selectElement.value;
            var qty_input = $("#product-qty-"+productId);

            if (selectedValue == 'standard') {
                qty_input.prop('readonly', false);
                $('.is-batch-'+productId) .prop('disabled', false);
                $('.batch-wrapper-' + productId).show(300);
            } else {
                qty_input.prop('readonly', true);
                $('.is-batch-'+productId) .prop('disabled', true);
                $('.batch-wrapper-' + productId).hide(300);
            }
        }

        function updateBatchQty(id) {
            var totalQty = 0;
            $('.batch-qty-' + id).each(function() {
                totalQty += parseFloat($(this).val()) || 0;
            });
            $('.qty-' + id).val(totalQty);
        }

        $('input[name^="is_batch["]').on('change', function() {
            var productId = $(this).attr('name').match(/\d+/)[0]; // Extract the product ID from the name attribute
            var $batchPositionWrapper = $('.batch-wrapper-' + productId);
            updateBatchQty(productId);
            if ($(this).is(':checked')) {
                if($batchPositionWrapper.length > 0) {
                    $batchPositionWrapper.show(300);
                } else {
                    batchHtml = addBatchArea(productId);
                    $(this).closest('tr').after(batchHtml);
                }
                $("#product-qty-"+productId).prop('readonly', true);
            } else {
                $batchPositionWrapper.hide(300);
                $("#product-qty-"+productId).prop('readonly', false);
            }
        });

        function addNewRow(productId) {
            // Example: Adding a new row after the closest <tr>
            var newRow = `
                <tr class="bg-warning">
                    <td colspan="3">
                        <input class="form-control" required type="text" name="batch_no[`+productId+`][]" placeholder="batch no">
                    </td>
                    <td colspan="3">
                        <input class="form-control" required type="date" name="batch_expire[`+productId+`][]" placeholder="batch expiry">
                    </td>
                    <td colspan="2">
                        <input class="form-control batch-qty-`+productId+`" required onkeyup="updateBatchQty(`+productId+`)" value="0" type="number" name="batch_qty[`+productId+`][]" placeholder="batch qty">
                    </td>
                    <td colspan="2"><i class="btn btn-danger" onclick="removeTR(this, ${productId})"> X</i></td>
                </tr>
            `;

            return newRow;
        }

        function addBatchArea(productId) {
            var productName = $('.product-name-'+productId).val();
            var batchHtml = `
            <tr class="batch-wrapper-${productId}">
                <td colspan="12">
                    <table class="table table-bordered">
                        <tr class="bg-info batch-tr" data-id="${productId}">
                            <td colspan="12">
                                <div class="sub-tr-content text-center">
                                    <strong>Batch Details for Product: ${productName}:</strong>
                                </div>
                            </td>
                        </tr>
                        <tr class="bg-warning">
                            <td colspan="3">
                                <input class="form-control" required type="text" name="batch_no[${productId}][]" placeholder="Batch No">
                            </td>
                            <td colspan="3">
                                <input class="form-control" required type="date" name="batch_expire[${productId}][]" placeholder="Expire Date">
                            </td>
                            <td colspan="2">
                                <input class="form-control batch-qty-${productId}" required onkeyup="updateBatchQty(${productId})" type="number" name="batch_qty[${productId}][]" placeholder="Qty">
                            </td>
                            <td colspan="2">
                                <i class="btn btn-danger" onclick="removeTR(this, ${productId})">X</i>
                            </td>
                        </tr>
                        <tr>
                            <td colspan="12">
                                <div class="text-center">
                                    <a class="btn btn-success text-white add-new-batch">+ Add New</a>
                                </div>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>`;

            return batchHtml;
        }

    </script>
@endsection
