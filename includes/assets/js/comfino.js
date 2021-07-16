var $ = jQuery.noConflict();

var Comfino = Comfino || {};

(function () {
    "use strict";

    Comfino.Gateway = {
        init: function () {

            $('body').on('updated_checkout', function () {
                Comfino.Gateway.initConfino();

                $('input[name="payment_method"]').on('change', function () {
                    Comfino.Gateway.initConfino();
                });
            });
        },

        initConfino: function () {
            $("div[id^='comfino_offer_']").each(function () {
                $(this).on('click', function () {
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
                $(this).on('click', function (event) {
                    event.stopPropagation();
                    $('#representative-example-modal-' + $(this).data('type')).show();

                    return false;
                });
            });

            $("span[class^='comfino-close']").each(function () {
                $(this).on('click', function (event) {
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
