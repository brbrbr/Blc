jQuery(document).ready(function ($) {
    let blcTimer;
    $('.blcstatus').removeClass('d-none');
    $('.blcclose').removeClass('d-none');
    blcCheck();
    function blcCheck()
    {
        //just in case the server response is invalid.
        //might occur van several checkers run in paralel.
        blcTimer = setTimeout(blcCheck, window.blcInterval * 10);
        //alternive would be   Joomla.request
        $.ajax({
            url: window.blcCronUrl,
            cache: !1,
            dataType: 'json'
        })
            .done(function (response) {
                clearTimeout(blcTimer);
                let { status, msglong, msgshort, count } = response.data;
                if (parseInt(count) > 0) {
                    $('.blcicon').css('--fa-rotate-angle', '-' + (String(count * 22.5)) + 'deg');
                    blcTimer = setTimeout(blcCheck, window.blcInterval);
                } else {
                    $('.blcstatus').delay(window.blcInterval * 5).fadeOut(1000);
                    $('.blcclose').delay(window.blcInterval * 5).fadeOut(1000);
                }
                $('.blcresponse.short').html(msgshort);
                $('.blcresponse.long').html(msglong);
                $('.blcresponse.count').html(count);
                $('.blcstatus').removeClass('Broken Good Redirect Unable').addClass(status);
            });
    }
});