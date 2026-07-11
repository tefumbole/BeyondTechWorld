<script type="text/javascript">


    var warehouse_id = 1;
    $('.product-report-filter select[name="warehouse_id"]').val(warehouse_id);
    $('.selectpicker').selectpicker('refresh');

    $(".daterangepicker-field").daterangepicker({
        callback: function(startDate, endDate, period){
            var start_date = startDate.format('YYYY-MM-DD');
            var end_date = endDate.format('YYYY-MM-DD');
            var title = start_date + ' To ' + end_date;
            $(this).val(title);
            $(".product-report-filter input[name=start_date]").val(start_date);
            $(".product-report-filter input[name=end_date]").val(end_date);
        }
    });

    var start_date = $(".product-report-filter input[name=start_date]").val();
    var end_date = $(".product-report-filter input[name=end_date]").val();
    var warehouse_id = $(".product-report-filter select[name=warehouse_id]").val();
    var selectedCategoryId = $('#cat-value').val() || 'All';
    $('#product-report-table').DataTable( {
        "processing": false,
        "serverSide": false,

        dom: '<"row"lfB>rtip',
        'lengthMenu': [[10, 25, 50, -1], [10, 25, 50, "All"]],
        buttons: [
            {
                extend: 'pdfHtml5',
                text: '<i title="export to pdf" class="fa fa-file-pdf-o"></i>',
                orientation: 'landscape',
                pageSize:  'TABLOID',
                customize: function (doc) {
                    // Get the start and end date values from hidden inputs by name
                    const startDate = start_date;
                    const endDate = end_date;
                    const categoryId = selectedCategoryId;

                    // Function to format the date as "4-March-2024"
                    function formatDate(dateString) {
                        const date = new Date(dateString);
                        const options = { day: 'numeric', month: 'long', year: 'numeric' };
                        return date.toLocaleDateString('en-GB', options).replace(/ /g, '-');
                    }

                    // Format the start and end date
                    const formattedStartDate = formatDate(startDate);
                    const formattedEndDate = formatDate(endDate);

                    // Construct date range string
                    const dateRange = `${formattedStartDate} to ${formattedEndDate}`;

                    // Example department details (you can dynamically fetch these values)
                    const deptNo = '____________'; // Static for now
                    const deptName = '____________'; // Static for now

                    const depreciationSum = <?= json_encode($depreciation_value_sum ?? 0) ?>;
                    const priceSum = <?= json_encode($price_sum ?? 0) ?>;
                    const bookingsSum = <?= json_encode($book_value_sum ?? 0) ?>;

                    const roundedPrice = parseFloat(priceSum).toFixed(2);
                    const roundedBooking = parseFloat(bookingsSum).toFixed(2);
                    const roundedDepreciation = parseFloat(depreciationSum).toFixed(2);


                    // Set page margins
                    doc.pageMargins = [4, 80, 4, 80];

                    // Add title and date range at the top
                    doc.content.unshift({
                        text: `CBC Fixed Asset Recount List (${categoryId}) – ${dateRange}`,
                        fontSize: 14,
                        bold: true,
                        margin: [0, 0, 0, 20],
                        alignment: 'center'
                    });

                    // Add department details (Dept No and Dept Name)
                    doc.content.push({
                        columns: [
                            { text: `Dept No: ${deptNo}`, fontSize: 12 },
                            { text: `Department Name: ${deptName}`, fontSize: 12, alignment: 'right' }
                        ],
                        margin: [0, 10, 0, 20]
                    });

                    // Here, the table content will be inserted after this

                    // Add Prepared By / Checked By section after table (if needed)
                    doc.content.push({
                        columns: [
                            { text: 'Prepared By: ___________________', fontSize: 11 },
                            { text: '', width: '*' },
                            { text: 'Checked By: ___________________', fontSize: 11, alignment: 'right' }
                        ],
                        margin: [0, 30, 0, 10]
                    });

                    // Add summary section in the center with formatted values
                    doc.content.push({
                        margin: [0, 20, 0, 0],
                        alignment: 'center',
                        fontSize: 11,
                        columns: [
                            {
                                width: '*',
                                text: [
                                    { text: 'Summary: ', bold: true },
                                    { text: `Total Purchase: ${roundedPrice} | Booking Value: ${roundedBooking} | Depreciation Value: ${roundedDepreciation}` }
                                ]
                            }
                        ]
                    });


                    // Add final note after the signature
                    doc.content.push({
                        text: 'Send 1 copy to FDD; Keep 1 copy for your records, 1 for Department Head',
                        fontSize: 10,
                        italics: true,
                        margin: [0, 10, 0, 0],
                        alignment: 'center'
                    });
                },
                exportOptions: {
                    columns: ':visible',
                    rows: function (idx, data, node) {
                        return !$(node).parent().is('tfoot'); // Exclude HTML footer
                    },
                    format: {
                        body: function (data, row, column, node) {
                            return data.replace(/\s+/g, ' ').trim();
                        }
                    }
                },
                action: function (e, dt, button, config) {
                    $.fn.dataTable.ext.buttons.pdfHtml5.action.call(this, e, dt, button, config);
                },
                footer: false
            },
            {
                extend: 'csv',
                text: '<i title="export to csv" class="fa fa-file-text-o"></i>',
                exportOptions: {
                    columns: ':visible:not(.not-exported)',
                    rows: ':visible'
                },
                action: function(e, dt, button, config) {
                    $.fn.dataTable.ext.buttons.csvHtml5.action.call(this, e, dt, button, config);
                },
                footer:true
            },
            {
                extend: 'print',
                text: '<i title="print" class="fa fa-print"></i>',
                exportOptions: {
                    columns: ':visible:not(.not-exported)',
                    rows: ':visible'
                },
                action: function(e, dt, button, config) {
                    $.fn.dataTable.ext.buttons.print.action.call(this, e, dt, button, config);
                },
                footer:true
            },
            {
                extend: 'colvis',
                text: '<i title="column visibility" class="fa fa-eye"></i>',
                columns: ':gt(0)'
            },
        ],
        drawCallback: function () {
            var api = this.api();
        }
    } );

</script>
<script type="text/javascript">

    $("ul#assets").siblings('a').attr('aria-expanded','true');
    $("ul#assets").addClass("show");
    $("ul#assets #assets-report-menu-refined").addClass("active");

    $('#warehouse_id').val($('input[name="warehouse_id_hidden"]').val());
    $('.selectpicker').selectpicker('refresh');

    $('#warehouse_id').on("change", function(){
        $('#report-form').submit();
    });
</script>
