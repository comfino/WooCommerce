'use strict';

window.Comfino = {
    offerList: { elements: null, data: null },
    selectedOffer: 0,

    selectTerm(loanTermBox, termElement)
    {
        loanTermBox.querySelectorAll('div > div.comfino-installments-quantity').forEach(function (item) {
            item.classList.remove('comfino-active');
        });

        if (termElement !== null) {
            termElement.classList.add('comfino-active');

            for (let loanParams of Comfino.offerList.data[Comfino.selectedOffer].loanParameters) {
                if (loanParams.loanTerm === parseInt(termElement.dataset.term)) {
                    document.getElementById('comfino-total-payment').innerHTML = loanParams.sumAmount + ' zł';
                    document.getElementById('comfino-monthly-rate').innerHTML = loanParams.instalmentAmount + ' zł';
                    document.getElementById('comfino-summary-total').innerHTML = loanParams.toPay + ' zł';
                    document.getElementById('comfino-rrso').innerHTML = loanParams.rrso + '%';
                    document.getElementById('comfino-description-box').innerHTML = Comfino.offerList.data[Comfino.selectedOffer].description;
                    document.getElementById('comfino-repr-example').innerHTML = Comfino.offerList.data[Comfino.selectedOffer].representativeExample;

                    Comfino.offerList.elements[Comfino.selectedOffer].dataset.sumamount = loanParams.sumAmount;
                    Comfino.offerList.elements[Comfino.selectedOffer].dataset.term = loanParams.loanTerm;
                    Comfino.offerList.elements[Comfino.selectedOffer].dataset.rrso = loanParams.rrso;

                    document.getElementById('comfino-loan-term').value = loanParams.loanTerm;

                    break;
                }
            }
        } else {
            document.getElementById('comfino-loan-term').value = '';
            document.getElementById('comfino-total-payment').innerHTML = Comfino.offerList.data[Comfino.selectedOffer].sumAmount + ' zł';
        }
    },

    selectCurrentTerm(loanTermBox, term)
    {
        let termElement = loanTermBox.querySelector('div > div[data-term="' + term + '"]');

        if (termElement !== null) {
            loanTermBox.querySelectorAll('div > div.comfino-installments-quantity').forEach(function (item) {
                item.classList.remove('comfino-active');
            });

            termElement.classList.add('comfino-active');

            for (let loanParams of Comfino.offerList.data[Comfino.selectedOffer].loanParameters) {
                if (loanParams.loanTerm === parseInt(term)) {
                    document.getElementById('comfino-total-payment').innerHTML = loanParams.sumAmount + ' zł';
                    document.getElementById('comfino-monthly-rate').innerHTML = loanParams.instalmentAmount + ' zł';
                    document.getElementById('comfino-summary-total').innerHTML = loanParams.toPay + ' zł';
                    document.getElementById('comfino-rrso').innerHTML = loanParams.rrso + '%';
                    document.getElementById('comfino-description-box').innerHTML = Comfino.offerList.data[Comfino.selectedOffer].description;
                    document.getElementById('comfino-repr-example').innerHTML = Comfino.offerList.data[Comfino.selectedOffer].representativeExample;

                    document.getElementById('comfino-loan-term').value = term;

                    break;
                }
            }
        } else {
            document.getElementById('comfino-loan-term').value = '';
        }
    },

    fetchProductDetails(offerData)
    {
        if (offerData.type === 'PAY_LATER') {
            document.getElementById('comfino-payment-delay').style.display = 'block';
            document.getElementById('comfino-installments').style.display = 'none';
        } else {
            let loanTermBox = document.getElementById('comfino-quantity-select');
            let loanTermBoxContents = ``;

            offerData.loanParameters.forEach(function (item, index) {
                if (index === 0) {
                    loanTermBoxContents += `<div class="comfino-select-box">`;
                } else if (index % 3 === 0) {
                    loanTermBoxContents += `</div><div class="comfino-select-box">`;
                }

                loanTermBoxContents += `<div data-term="` + item.loanTerm + `" class="comfino-installments-quantity">` + item.loanTerm + `</div>`;

                if (index === offerData.loanParameters.length - 1) {
                    loanTermBoxContents += `</div>`;
                }
            });

            loanTermBox.innerHTML = loanTermBoxContents;

            loanTermBox.querySelectorAll('div > div.comfino-installments-quantity').forEach(function (item) {
                item.addEventListener('click', function (event) {
                    event.preventDefault();
                    Comfino.selectTerm(loanTermBox, event.target);
                });
            });

            document.getElementById('comfino-payment-delay').style.display = 'none';
            document.getElementById('comfino-installments').style.display = 'block';
        }
    },

    putDataIntoSection(data)
    {
        let offerElements = [];
        let offerData = [];

        data.forEach(function (item, index) {
            let comfinoOffer = document.createElement('div');

            comfinoOffer.dataset.type = item.type;
            comfinoOffer.dataset.sumamount = item.sumAmount;
            comfinoOffer.dataset.term = item.loanTerm;

            comfinoOffer.classList.add('comfino-order');

            let comfinoOptId = 'comfino-opt-' + item.type;

            comfinoOffer.innerHTML = `
                <div class="comfino-single-payment">
                    <input type="radio" id="` + comfinoOptId + `" class="comfino-input" name="comfino" />
                    <label for="` + comfinoOptId + `">
                        <div class="comfino-icon">` + item.icon + `</div> 
                        <span class="comfino-single-payment__text">` + item.name + `</span>
                    </label>
                </div>
            `;

            if (index === 0) {
                let paymentOption = comfinoOffer.querySelector('#' + comfinoOptId);

                comfinoOffer.classList.add('selected');
                paymentOption.setAttribute('checked', 'checked');

                document.getElementById('comfino-type').value = item.type;

                Comfino.fetchProductDetails(item);
            }

            offerData[index] = item;
            offerElements[index] = document.getElementById('comfino-offer-items').appendChild(comfinoOffer);
        });

        return { elements: offerElements, data: offerData };
    },

    /**
     * Get offers from API.
     */
    initPayments(data)
    {
        let loanTermBox = document.getElementById('comfino-quantity-select');

        Comfino.offerList = Comfino.putDataIntoSection(data);

        Comfino.selectTerm(loanTermBox, loanTermBox.querySelector('div > div[data-term="' + Comfino.offerList.data[Comfino.selectedOffer].loanTerm + '"]'));

        Comfino.offerList.elements.forEach(function (item, index) {
            item.querySelector('label').addEventListener('click', function () {
                Comfino.selectedOffer = index;

                Comfino.fetchProductDetails(Comfino.offerList.data[Comfino.selectedOffer]);

                Comfino.offerList.elements.forEach(function () {
                    item.classList.remove('selected');
                });

                item.classList.add('selected');

                document.getElementById('comfino-type').value = Comfino.offerList.data[Comfino.selectedOffer].type;

                Comfino.selectCurrentTerm(loanTermBox, Comfino.offerList.elements[Comfino.selectedOffer].dataset.term);
            });
        });

        document.getElementById('comfino-repr-example-link').addEventListener('click', function (event) {
            event.preventDefault();
            document.getElementById('modal-repr-example').classList.add('open');
        });

        document.getElementById('modal-repr-example').querySelector('button.comfino-modal-exit').addEventListener('click', function (event) {
            event.preventDefault();
            document.getElementById('modal-repr-example').classList.remove('open');
        });

        document.getElementById('modal-repr-example').querySelector('div.comfino-modal-exit').addEventListener('click', function (event) {
            event.preventDefault();
            document.getElementById('modal-repr-example').classList.remove('open');
        });

        document.getElementById('payment_method_comfino').addEventListener('click', function (event) {
            document.getElementById('payment').querySelector('div.payment_box.payment_method_comfino').setAttribute('style', 'display: block !important;');
        });
    }
}
