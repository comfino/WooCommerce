var $ = jQuery.noConflict();

var Comfino = Comfino || {};

(function () {
    "use strict";

    Comfino.Gateway = {
        init: function () {

            $("div[id^='comfino_offer_']").each(function () {
                $(this).click(function () {
                    $("div[id^='comfino_offer_']").each(function () {
                        $(this).removeClass("confino-selected");
                        $(this).addClass("confino-unselected");
                    });

                    $(this).removeClass("confino-unselected");
                    $(this).addClass("confino-selected");
                    $('#comfino-type').val($(this).data('type'));
                });
            });

            $("a[id^='representative-example-link-']").each(function () {
                $(this).click(function (event) {
                    event.stopPropagation();
                    $('#representative-example-modal-' + $(this).data('type')).show();

                    return false;
                });
            });

            $("span[class^='comfino-close']").each(function () {
                $(this).click(function (event) {
                    event.stopPropagation();
                    $('.comfino-alertbar').hide();
                })
            });
        },
    };

    $(function () {
        Comfino.Gateway.init();
    });
}());
