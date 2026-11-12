function saveProductToList(row, done) {
    var id = row.find('.product-id').val();
    var name = $.trim($('input[name="edit_product_name"]').val());
    var price = $('input[name="edit_unit_price"]').val();
    if (!id) {
        done(false, 'This line has no product to update.');
        return;
    }
    $.ajax({
        url: '{{ route('products.update-from-document') }}',
        type: 'POST',
        data: {
            id: id,
            name: name,
            price: price,
            booking_method: row.find('.booking_method').val(),
            warehouse_id: $('#warehouse_id').val() || $('select[name="warehouse_id"]').val()
        },
        success: function (res) {
            if (!res || !res.ok) {
                done(false, (res && res.error) ? res.error : 'Could not update the product.');
                return;
            }
            var code = row.find('.product-code').val();
            $('table.order-list tbody tr').each(function () {
                var line = $(this);
                if (line.find('.product-code').val() === code) {
                    line.find('.product-name-label').text(res.name);
                }
            });
            if (typeof product_code !== 'undefined') {
                var idx = product_code.indexOf(code);
                if (idx >= 0) {
                    if (typeof product_name !== 'undefined') {
                        product_name[idx] = res.name;
                    }
                    if (typeof product_warehouse_price !== 'undefined') {
                        product_warehouse_price[idx] = res.price;
                    }
                    if (typeof lims_product_array !== 'undefined') {
                        lims_product_array[idx] = product_code[idx] + ' (' + res.name + ')';
                    }
                }
            }
            done(true);
        },
        error: function (xhr) {
            var message = 'Could not update the product.';
            if (xhr.responseJSON && xhr.responseJSON.error) {
                message = xhr.responseJSON.error;
            }
            done(false, message);
        }
    });
}
