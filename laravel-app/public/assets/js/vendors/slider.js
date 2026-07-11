// Requires jQuery

// Initialize slider:
$(document).ready(function () {
    $(".noUi-handle").on("click", function () {
        $(this).width(50);
    });
    var rangeSlider = document.getElementById("slider-range");
    var rangeSlider2 = $("#slider-range");
    if (rangeSlider2.length > 0) {
        var moneyFormat = wNumb({
            decimals: 0,
            thousand: ",",
            prefix: "CFA "
        });
        noUiSlider.create(rangeSlider, {
            start: [200, 5000],
            step: 1,
            range: {
                min: [0],
                max: [20000]
            },
            format: moneyFormat,
            connect: true
        });


        rangeSlider.noUiSlider.on("end", function (values, handle) {
            filter_data(values[0], values[1]);
        });
        // Set visual min and max values and also update value hidden form inputs
        rangeSlider.noUiSlider.on("update", function (values, handle) {
            $(".min-value-money").html(values[0]);
            $(".max-value-money").html(values[1]);
            $(".min-value").val(moneyFormat.from(values[0]));
            $(".max-value").val(moneyFormat.from(values[1]));
        });
    }
});

function filter_data(min, max){
    $("#preloader-active").css('display', 'block');
    $.ajax({
        type: 'GET',
        url: '/product/price',
        data: {
            min: $('.min-value').val(),
            max: $('.max-value').val()
        },
        success: function (data) {
            $("#shop-products").html('');
            $("#shop-products").html(data);
            $("#preloader-active").css('display', 'none');
            $(".pagination").css('display', 'none');
        }
    });
}
