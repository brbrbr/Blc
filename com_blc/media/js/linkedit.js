jQuery(document).ready(function ($) {
    function isNewUrl()
    {
        $('button.link-replace').prop("disabled", true);
        $('.newurl').each(function (index) {
            let a = $(this).data('oldurl');
            let b = $(this).val();
            if (a != b) {
                let el = $(this).closest('ul').find('button.link-replace');
                el.prop("disabled", false);
                el.attr('title', "Replace all links with: " + b);
                el = $('#toolbar-link-replace').find('button.link-replace');
                el.prop("disabled", false);
                el.attr('title', "Replace all links with: " + b);
            }
        });
        $('.newurl').each(function () {
            $(this).data('url', $(this).val())
        });
    }
    $('.newurl').on('change blur keyup', isNewUrl).change();

    $('button.cancel-edit').on(
        'click',
        function (event) {
            event.preventDefault()
            let parent = $(this).closest('div.newurlform');
            parent.removeClass('editurl');
            let child = parent.find('.newurl');
            if (child) {
                child.val(child.data('url'));
                child.change();
            }
            parent.closest('td').find('li.final').show();
            $(this).blur();
            return false
        }
    );
    $('input.newurl').prop("disabled", true);


    $('div.newurlform button.link-replace').on(
        'mousedown touchstart',
        function (event) {
            $('input.newurl').prop("disabled", true);
            let parent = $(this).closest('div.newurlform');
            parent.find('input.newurl').prop("disabled", false);
            return true;
        }
    );

    $('button.link-edit').on(
        'click',
        function (event) {
            $('button.cancel-edit').click();
            $('input.newurl').prop("disabled", true);
            event.preventDefault();
            let parent = $(this).closest('div.newurlform');
            parent.addClass('editurl')
                .closest('td').find('li.final').hide();
            parent.find('input.newurl').prop("disabled", false);
            $(this).blur();
            return false
        }
    )

    $('span.blccopylink').on('click',blccopylink);
    function blccopylink(event)
    {
        var href = $(this).siblings('a').first().attr('href') || false;
        if ( href ) {
            navigator.clipboard.writeText(href);
            $(this).addClass('clicked');
            setTimeout(() => {
                  $(this).removeClass('clicked');
            },500)
        }
    }
});