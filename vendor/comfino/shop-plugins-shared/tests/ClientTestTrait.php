<?php

namespace Comfino;

use Comfino\Api\Client;
use Comfino\Api\Dto\Order\Cart\CartItem;
use Comfino\Api\Dto\Order\Customer;
use Comfino\Api\Dto\Order\Customer\Address;
use Comfino\Api\Dto\Payment\FinancialProduct;
use Comfino\Api\Dto\Payment\LoanQueryCriteria;
use Comfino\Api\Dto\Payment\LoanTypeEnum;
use Comfino\Api\Exception\AuthorizationError;
use Comfino\FinancialProduct\ProductTypesListTypeEnum;
use Comfino\Shop\Order\Cart;
use Comfino\Shop\Order\LoanParameters;
use Comfino\Shop\Order\Order;
use Comfino\Shop\Order\Seller;
use Comfino\Widget\WidgetTypeEnum;
use Http\Message\RequestMatcher\RequestMatcher;
use PHPUnit\Framework\MockObject\Exception;
use ComfinoExternal\Psr\Http\Client\ClientInterface;
use ComfinoExternal\Psr\Http\Message\RequestFactoryInterface;
use ComfinoExternal\Psr\Http\Message\RequestInterface;
use ComfinoExternal\Psr\Http\Message\ResponseInterface;
use ComfinoExternal\Psr\Http\Message\StreamFactoryInterface;
use ComfinoExternal\Sunrise\Http\Factory\ResponseFactory;
use ComfinoExternal\Sunrise\Http\Factory\StreamFactory;
use ComfinoExternal\Sunrise\Http\Factory\RequestFactory;

trait ClientTestTrait
{
    use ReflectionTrait;

    /**
     * @var string
     */
    protected $productionApiHost;

    /**
     * @throws Exception
     */
    public function testGetVersion(): void
    {
        $apiClient = new Client(
            $this->createMock(RequestFactoryInterface::class),
            $this->createMock(StreamFactoryInterface::class),
            $this->createMock(ClientInterface::class),
            'API-KEY'
        );

        $this->assertEquals($this->getConstantFromObject($apiClient, 'CLIENT_VERSION'), $apiClient->getVersion());
    }

    public function testIsShopAccountActive(): void
    {
        $apiClient = $this->initApiClient('/v1/user/is-active', 'GET', null, null, true, null, 'API-KEY');
        $response = $apiClient->isShopAccountActive();

        $this->assertTrue($response);

        $apiClient = $this->initApiClient('/v1/user/is-active', 'GET', null, null, false, null, 'API-KEY');
        $response = $apiClient->isShopAccountActive();

        $this->assertFalse($response);

        $this->expectException(AuthorizationError::class);
        $this->initApiClient('/v1/user/is-active', 'GET')->isShopAccountActive();
    }

    public function testGetFinancialProducts(): void
    {
        $queryCriteria = new LoanQueryCriteria(120000);

        $apiClient = $this->initApiClient(
            '/v1/financial-products',
            'GET',
            ['loanAmount' => $queryCriteria->loanAmount],
            null,
            json_decode('[{"name":"Raty 0%","description":"Szybkie i proste zakupy bez dodatkowych koszt\u00f3w. Sp\u0142acasz dok\u0142adnie tyle, ile po\u017cyczasz!","icon":"\u003C?xml version=\u00221.0\u0022 encoding=\u0022utf-8\u0022?\u003E\n\u003Csvg version=\u00221.1\u0022 id=\u0022Comfino_InstallmentsZero\u0022 xmlns=\u0022http:\/\/www.w3.org\/2000\/svg\u0022 xmlns:xlink=\u0022http:\/\/www.w3.org\/1999\/xlink\u0022 x=\u00220px\u0022 y=\u00220px\u0022\n\t viewBox=\u00220 -60 382.6 273.8\u0022 style=\u0022enable-background:new 0 0 382.6 213.8;\u0022 xml:space=\u0022preserve\u0022\u003E\n\u003Cstyle type=\u0022text\/css\u0022\u003E\n\t.st0{fill:#579D33;}\n\u003C\/style\u003E\n\u003Cg\u003E\n\t\u003Cg\u003E\n\t\t\u003Cpath class=\u0022st0\u0022 d=\u0022M0,71C0,29.7,27.4,3.7,74.1,3.7s73.8,26,73.8,67.3v71.8c0,41.6-27.1,67.6-73.8,67.6S0,184.5,0,142.8L0,71\n\t\t\tL0,71z M112.9,71c0-22.1-14.2-34.7-38.8-34.7S35.2,49,35.2,71v71.8c0,22.1,14.2,34.9,38.8,34.9s38.8-12.8,38.8-34.9V71z\u0022\/\u003E\n\t\t\u003Cpath class=\u0022st0\u0022 d=\u0022M171.6,45c0-26,16.5-41.6,46.4-41.6s46.4,15.6,46.4,41.6v19.8c0,25.7-16.5,41.1-46.4,41.1\n\t\t\ts-46.4-15.4-46.4-41.1L171.6,45L171.6,45z M356.6,7.9c6.4,0,7.8,4.5,4.5,9.2L228,197.9c-5.6,7.5-7.3,8.4-18.7,8.4h-10.9\n\t\t\tc-6.7,0-8.4-4.2-4.8-9.5l133-181.1c4.8-7,7.3-7.8,17.6-7.8H356.6z M237.2,46.4c0-11.5-6.4-17-19.3-17s-19.3,5.6-19.3,17v16.8\n\t\t\tc0,11.5,6.4,17,19.3,17s19.3-5.6,19.3-17V46.4z M289.8,149.8c0-26,16.5-41.6,46.4-41.6s46.4,15.7,46.4,41.6v19.8\n\t\t\tc0,25.7-16.5,41.1-46.4,41.1s-46.4-15.4-46.4-41.1V149.8z M355.4,151.2c0-11.5-6.4-17-19.3-17c-12.8,0-19.3,5.6-19.3,17v16.5\n\t\t\tc0,11.5,6.4,17.3,19.3,17.3c12.8,0,19.3-5.9,19.3-17.3V151.2z\u0022\/\u003E\n\t\u003C\/g\u003E\n\u003C\/g\u003E\n\u003C\/svg\u003E","type":"INSTALLMENTS_ZERO_PERCENT","creditorName":"inbank","instalmentAmount":40000,"representativeExample":"Przyk\u0142ad reprezentatywny dla AS InBank S.A Odzia\u0142 w Polsce na dzie\u0144 08-02-2021 r. : Rzeczywista Roczna Stopa Oprocentowania (RRSO) 0,00%, ca\u0142kowita kwota kredytu 3000,00 z\u0142, ca\u0142kowita kwota do zap\u0142aty przez klienta 3000,00 z\u0142, ca\u0142kowity koszt kredytu 0,00 z\u0142. Okres kredytowania 10 rat, raty r\u00f3wne w wysoko\u015bci 300,00 z\u0142.Niniejsze warunki nie stanowi\u0105 oferty w rozumieniu art. 66 Kodeksu Cywilnego. Przyznanie kredytu uzale\u017cnione jest od oceny zdolno\u015bci kredytowej.\n\nKredytodawca AS Inbank, kt\u00f3ry jest bankiem utworzonym za zezwoleniem esto\u0144skich w\u0142adz nadzorczych Finantsinspektsioon, posiada siedzib\u0119 przy Niine 11, 10414 Tallinn, w Estonii i prowadzi swoj\u0105 dzia\u0142alno\u015b\u0107 w Polsce poprzez Oddzia\u0142. Oddzia\u0142 Inbank w Polsce nadzorowany jest przez Finantsinspektsioon i nie jest nadzorowany przez Komisj\u0119 Nadzoru Finansowego, z zastrze\u017ceniem \u015brodk\u00f3w nadzorczych przewidzianych dla KNF w art. 141a ustawy z dnia 29 sierpnia 1997 r. Prawo bankowe. Istotne informacje dotycz\u0105ce prowadzenia przez Inbank dzia\u0142alno\u015bci na terytorium Rzeczypospolitej Polskiej zawarte s\u0105 w \u003Ca target=\u0022_blank\u0022 href=\u0022https:\/\/www.inbankpolska.pl\/documents\/pl\/decyzja_knf_z_dnia_10_01_2017.pdf\u0022\u003EDecyzji Komisji Nadzoru Finansowego\u003C\/a\u003E wskazuj\u0105cej warunki prowadzenia tej dzia\u0142alno\u015bci oraz w \u003Ca target=\u0022_blank\u0022 href=\u0022https:\/\/www.inbankpolska.pl\/inbank\/nota-prawna\/\u0022\u003Enocie prawnej\u003C\/a\u003E.","rrso":0,"toPay":120000,"remarks":null,"loanTerm":3,"loanParameters":[{"instalmentAmount":40000,"loanTerm":3,"toPay":120000,"rrso":0},{"instalmentAmount":20000,"loanTerm":6,"toPay":120000,"rrso":0},{"instalmentAmount":12000,"loanTerm":10,"toPay":120000,"rrso":0}]},{"name":"Niskie raty","description":"Zakupy bez obci\u0105\u017cania bud\u017cetu, dostosowane do Twoich potrzeb. Sam decydujesz o liczbie rat.","icon":"\u003C?xml version=\u00221.0\u0022 encoding=\u0022utf-8\u0022?\u003E\n\u003Csvg version=\u00221.1\u0022 id=\u0022Comfino_ConvenientInstallments\u0022 xmlns=\u0022http:\/\/www.w3.org\/2000\/svg\u0022 xmlns:xlink=\u0022http:\/\/www.w3.org\/1999\/xlink\u0022 x=\u00220px\u0022 y=\u00220px\u0022\n\t viewBox=\u00220 0 384 393\u0022 style=\u0022enable-background:new 0 0 384 393;\u0022 xml:space=\u0022preserve\u0022\u003E\n\u003Cstyle type=\u0022text\/css\u0022\u003E\n\t.st0{fill:#579D33;}\n\u003C\/style\u003E\n\u003Cpath class=\u0022st0\u0022 d=\u0022M80.3,389.6c-3.5,0-7,0-10.5,0c-0.8-0.2-1.6-0.6-2.5-0.7C46.3,385.4,30.4,367,30,345.8\n\tc-0.2-10,2.8-19.1,8.3-27.2c-0.2-0.5-0.3-0.7-0.5-0.9c-0.5-0.6-1-1.1-1.5-1.7c-16.5-17.7-27.6-38.3-32.9-62\n\tc-1.4-6.4-2.2-13-3.3-19.4c0-6.5,0-13,0-19.5c0.2-1,0.5-1.9,0.6-2.9c1.2-7,1.9-14.1,3.7-20.9C20,131.4,73.1,90.5,135.6,90.2\n\tc25.1-0.1,50.2,0,75.3-0.1c2.2,0,4.7-0.8,6.5-2c12-7.8,23.9-15.9,35.9-23.8c15.1-9.8,30.9-11.1,46.8-2.6\n\tc15.9,8.5,23.6,22.4,23.8,40.4c0.1,12-0.1,23.9,0.1,35.9c0,1.8,0.8,3.9,1.9,5.4c7.8,10.4,14.3,21.6,18.8,33.9\n\tc0.9,2.4,2.1,3.5,4.6,4.1c15.9,3.9,26.5,13.8,32.2,29c1.1,3,1.7,6.2,2.5,9.3c0,3.5,0,7,0,10.5c-0.2,0.7-0.5,1.4-0.7,2.1\n\tc-3.9,19.2-15.4,31.3-34.3,36.3c-2.4,0.6-3.4,1.8-4.2,3.9c-5.4,14.3-13.1,27.4-23.1,39c-2,2.3-4,4.6-6,6.9c0.6,1,1.1,1.7,1.5,2.5\n\tc15.1,24.7,4,56.3-23.2,66c-3.1,1.1-6.5,1.8-9.7,2.6c-3.5,0-7,0-10.5,0c-0.7-0.2-1.4-0.5-2.1-0.7c-12-2.1-21.6-8-28.9-17.7\n\tc-2.2-3-4.5-6-6.4-9.2c-1.6-2.8-3.7-3.5-6.8-3.1c-4,0.5-8,0.6-11.9,0.6c-27.1,0-54.2,0-81.2,0c-4,0-8-0.1-11.9-0.6\n\tc-3.1-0.4-5,0.4-6.8,3.1c-4.8,7.2-9.6,14.5-17.1,19.6C94.4,385.9,87.4,387.9,80.3,389.6z M174.5,120.1c-12.6,0-25.3-0.3-37.9,0.1\n\tc-8.6,0.3-17.2,1-25.6,2.9c-60.8,13.3-96.4,79.4-74.5,137.5c6.5,17.3,17,31.7,30.9,43.9c7,6.1,7.8,13.7,2.5,21.3\n\tc-2.3,3.4-5,6.6-7,10.2c-1.4,2.4-2.5,5.2-2.8,8c-0.7,6.5,3.6,12.6,9.8,14.8c6.2,2.2,13,0.3,17.1-5.1c4.7-6.3,9-12.8,13.6-19.2\n\tc3.9-5.5,9.2-7.5,16-6.5c7,1,14.1,1.7,21.2,1.8c26.1,0.2,52.2,0.2,78.4,0c7,0,14-0.7,20.9-1.7c7.3-1.1,12.7,1.1,16.8,7\n\tc4.3,6.1,8.5,12.3,12.9,18.3c5.1,6.9,14.1,8.4,20.9,3.5c6.7-4.7,8.3-13.5,3.7-20.6c-2.2-3.3-4.6-6.5-6.9-9.8\n\tc-5.9-8.4-5.1-15.7,2.6-22.5c16-14,27-31.1,32.8-51.7c2.5-8.7,7.5-12.4,16.7-12.4c1.1,0,2.2,0,3.4,0c7.2-0.5,13.2-6.2,13.9-13.4\n\tc0.7-7-3.7-13.8-10.5-15.8c-2.1-0.6-4.4-0.7-6.7-0.7c-9.4-0.1-14.4-3.7-16.9-12.7c-4.2-15-11.4-28.4-21.5-40.2\n\tc-3-3.5-4.4-7.4-4.3-12c0.1-14.1,0-28.2,0.1-42.3c0.1-6.4-2.1-11.5-7.9-14.6c-5.9-3.2-11.4-2.2-16.9,1.5\n\tc-13.5,9.2-27.1,18.3-40.9,27.1c-3.1,2-7.2,3.2-10.8,3.2C203.2,120.4,188.9,120.1,174.5,120.1z\u0022\/\u003E\n\u003Cpath class=\u0022st0\u0022 d=\u0022M0,73.7C1,69.5,1.6,65.2,2.9,61c9.5-30.1,29.8-49,61.1-53.6c32-4.7,57.3,7.9,75.1,35c5.2,8,3.4,17-3.9,21.6\n\tc-7.5,4.7-16.2,2.3-21.5-5.9c-10-15.5-24.4-23.3-42.8-21.5c-18.7,1.8-31.5,12.2-38.2,29.8c-3.1,8-3.3,16.3-1.5,24.7\n\tc2,9.3-2.5,17.2-11,19.1c-8.5,2-16.2-3.4-18.3-12.8c-0.6-2.9-1.2-5.8-1.8-8.7C0,83.7,0,78.7,0,73.7z\u0022\/\u003E\n\u003Cpath class=\u0022st0\u0022 d=\u0022M294,195c0,8.2-6.8,15-15,14.9c-8.2,0-15-6.8-15-15s6.8-15,15-14.9C287.3,180.1,294,186.8,294,195z\u0022\/\u003E\n\u003C\/svg\u003E","type":"CONVENIENT_INSTALLMENTS","creditorName":"inbank","instalmentAmount":10960,"representativeExample":"Przyk\u0142ad reprezentatywny dla AS InBank S.A Odzia\u0142 w Polsce na na dzie\u0144 08-02-2021 r. : Rzeczywista Roczna Stopa Oprocentowania (RRSO) 18,96%, oprocentowanie sta\u0142e 0,00%, ca\u0142kowita kwota kredytu 3000,00 z\u0142, ca\u0142kowita kwota do zap\u0142aty przez klienta 3576,00 z\u0142, ca\u0142kowity koszt kredytu 576,00 z\u0142 (w tym: \u0142\u0105czna kwota prowizji za udzielenie kredytu: 576,00 z\u0142, odsetki umowne w okresie kredytowania: 0,00 z\u0142). Okres kredytowania 24 raty, raty r\u00f3wne w wysoko\u015bci 149,00 z\u0142.Niniejsze warunki nie stanowi\u0105 oferty w rozumieniu art. 66 Kodeksu Cywilnego. Przyznanie kredytu uzale\u017cnione jest od oceny zdolno\u015bci kredytowej.\n\nKredytodawca AS Inbank, kt\u00f3ry jest bankiem utworzonym za zezwoleniem esto\u0144skich w\u0142adz nadzorczych Finantsinspektsioon, posiada siedzib\u0119 przy Niine 11, 10414 Tallinn, w Estonii i prowadzi swoj\u0105 dzia\u0142alno\u015b\u0107 w Polsce poprzez Oddzia\u0142. Oddzia\u0142 Inbank w Polsce nadzorowany jest przez Finantsinspektsioon i nie jest nadzorowany przez Komisj\u0119 Nadzoru Finansowego, z zastrze\u017ceniem \u015brodk\u00f3w nadzorczych przewidzianych dla KNF w art. 141a ustawy z dnia 29 sierpnia 1997 r. Prawo bankowe. Istotne informacje dotycz\u0105ce prowadzenia przez Inbank dzia\u0142alno\u015bci na terytorium Rzeczypospolitej Polskiej zawarte s\u0105 w \u003Ca target=\u0022_blank\u0022 href=\u0022https:\/\/www.inbankpolska.pl\/documents\/pl\/decyzja_knf_z_dnia_10_01_2017.pdf\u0022\u003EDecyzji Komisji Nadzoru Finansowego\u003C\/a\u003E wskazuj\u0105cej warunki prowadzenia tej dzia\u0142alno\u015bci oraz w \u003Ca target=\u0022_blank\u0022 href=\u0022https:\/\/www.inbankpolska.pl\/inbank\/nota-prawna\/\u0022\u003Enocie prawnej\u003C\/a\u003E.","rrso":0.1870572566986084,"toPay":131520,"remarks":null,"loanTerm":12,"loanParameters":[{"instalmentAmount":20960,"loanTerm":6,"toPay":125760,"rrso":0.1754462718963623},{"instalmentAmount":10960,"loanTerm":12,"toPay":131520,"rrso":0.1870572566986084},{"instalmentAmount":7627,"loanTerm":18,"toPay":137286,"rrso":0.1895129680633545},{"instalmentAmount":5960,"loanTerm":24,"toPay":143040,"rrso":0.1893460750579834},{"instalmentAmount":4293,"loanTerm":36,"toPay":154548,"rrso":0.18675923347473145},{"instalmentAmount":3460,"loanTerm":48,"toPay":166080,"rrso":0.18339753150939941}]},{"name":"Kup teraz i zap\u0142a\u0107 za 30 dni","description":"Kup teraz i zap\u0142a\u0107 za 30 dni","icon":"\u003C?xml version=\u00221.0\u0022 encoding=\u0022utf-8\u0022?\u003E\n\u003Csvg version=\u00221.1\u0022 id=\u0022Comfino_PayLater\u0022 xmlns=\u0022http:\/\/www.w3.org\/2000\/svg\u0022 xmlns:xlink=\u0022http:\/\/www.w3.org\/1999\/xlink\u0022 x=\u00220px\u0022 y=\u00220px\u0022\n\t viewBox=\u00220 -34 373 365\u0022 style=\u0022enable-background:new 0 0 373 337;\u0022 xml:space=\u0022preserve\u0022\u003E\n\u003Cstyle type=\u0022text\/css\u0022\u003E\n\t.st0{fill:#579D33;}\n\u003C\/style\u003E\n\u003Cpath class=\u0022st0\u0022 d=\u0022M254.5,163.2c11.4,0,22.4-0.1,33.4,0c8.5,0.1,14,8.1,10.7,15.5c-1.9,4.4-5.5,6.8-10.3,6.8c-15,0.1-30,0.1-45,0\n\tc-6.4,0-11.2-4.8-11.2-11.1c-0.1-15-0.1-29.9,0-44.9c0-6.4,5-11.2,11.1-11.3c6.3,0,11.2,4.9,11.3,11.5\n\tC254.6,140.8,254.5,151.7,254.5,163.2z\u0022\/\u003E\n\u003Cpath class=\u0022st0\u0022 d=\u0022M210.4,230.2c0-25.1-43.8-36.5-84.5-36.5c-38.5,0-79.8,10.2-84.1,32.6C20.3,232,4.4,242.1,4.4,257.2\n\tc0,11,8.4,19.3,21.2,25.2c-2.9,3.8-4.6,8.2-4.6,13c0,25.1,43.8,36.5,84.5,36.5s84.5-11.4,84.5-36.5c0-11-8.4-19.3-21.2-25.2\n\tc2.1-2.8,3.5-5.8,4.2-9.1C194.6,255.5,210.4,245.3,210.4,230.2z M76.1,220.1c13.2-4.7,30.9-7.3,49.8-7.3s36.7,2.6,49.8,7.3\n\tc12,4.3,15.7,9,15.7,10.2s-3.7,5.8-15.7,10.2c-13.2,4.7-30.9,7.3-49.8,7.3s-36.7-2.6-49.8-7.3c-12-4.3-15.7-9-15.7-10.2\n\tS64.1,224.4,76.1,220.1z M171.1,295.5c0,1.2-3.7,5.8-15.7,10.2c-13.2,4.7-30.9,7.3-49.8,7.3s-36.7-2.6-49.8-7.3\n\tc-12-4.3-15.7-9-15.7-10.2c0-0.8,1.8-3.3,6.7-6.1c13.1,2.9,27.8,4.4,42.2,4.4c21.8,0,44.5-3.3,60.9-10.2c1.9,0.6,3.8,1.2,5.5,1.8\n\tC167.4,289.6,171.1,294.3,171.1,295.5z M138.8,267.4c-13.2,4.7-30.9,7.3-49.8,7.3s-36.7-2.6-49.8-7.3c-12-4.3-15.7-9-15.7-10.2\n\ts3.7-5.8,15.7-10.2c2.5-0.9,5.2-1.7,8-2.5c13.1,15.1,46.9,22.1,78.8,22.1c5.3,0,10.7-0.2,16-0.6C141,266.5,139.9,267,138.8,267.4z\u0022\n\t\/\u003E\n\u003Cpath class=\u0022st0\u0022 d=\u0022M366.3,161.9c-7-51.1-34.8-85.8-82.9-104.2c-3.1-1.2-6.4-2.1-9.5-3.1c5.5-18.9,2.2-32.3-10.2-41.6\n\tc-11.2-8.4-26.3-9.5-37.9-1.8c-16.1,10.7-19.6,25.7-13.1,43.6c-1.1,0.4-2.1,0.7-3,0.9c-61.9,17.6-100,79.4-87.9,142.2\n\tc7-0.9,16.1,1.3,24.5,5.2c-1.6-5.6-2.8-11.4-3.4-17.3c3.2,0,6.4,0,9.6,0c7-0.1,12-4.7,12-11.2c0-6.4-5-11.2-11.9-11.3\n\tc-3.2,0-6.4,0-9.7,0c3.9-44.5,41.6-84.4,89.1-89c0,3.2,0,6.4,0,9.6c0.1,7,4.8,12,11.2,12c6.4,0,11.2-5,11.3-11.9c0-3.2,0-6.4,0-9.7\n\tc44.1,3.8,84.4,41,89.2,89c-3.2,0-6.4,0-9.6,0c-7,0.1-12,4.8-12,11.1c0,6.4,5,11.2,11.9,11.3c3.2,0,6.4,0,9.7,0\n\tc-4,44.7-41.8,84.4-89.1,89c0-3.3,0-6.2,0-9.2c0-7.3-4.6-12.4-11.2-12.4c-6.6,0-11.3,5-11.3,12.2c0,3.1,0,6.2,0,9.3\n\tc-20.1-1.8-39.3-10.4-54.7-23.7c-3.8,6.3-12.5,9.8-22,10.4c25.2,25.3,61,39.5,98.8,36.1c53.1-4.8,96.9-42.4,109.3-94.2\n\tc1.6-6.5,2.4-13.2,3.5-19.8c0-6.2,0-12.5,0-18.7C366.8,163.8,366.4,162.8,366.3,161.9z M243.1,50.9C237,50.8,232,45.6,232,39.6\n\ts5.2-11.1,11.2-11.1c6.1,0,11.2,5.1,11.2,11.1C254.5,45.8,249.3,51,243.1,50.9z\u0022\/\u003E\n\u003C\/svg\u003E","type":"PAY_LATER","creditorName":"twisto","instalmentAmount":120000,"representativeExample":"","rrso":0,"toPay":120000,"remarks":null,"loanTerm":1,"loanParameters":[{"instalmentAmount":120000,"loanTerm":1,"toPay":120000,"rrso":0}]},{"name":"Raty dla firm","description":"Zakupy bez obci\u0105\u017cania bud\u017cetu firmowego, dostosowane do Twoich potrzeb. Sam decydujesz o liczbie rat.","icon":"\u003C?xml version=\u00221.0\u0022 encoding=\u0022utf-8\u0022?\u003E\n\u003Csvg version=\u00221.1\u0022 id=\u0022Comfino_CompanyInstallments\u0022 xmlns=\u0022http:\/\/www.w3.org\/2000\/svg\u0022 xmlns:xlink=\u0022http:\/\/www.w3.org\/1999\/xlink\u0022 x=\u00220px\u0022 y=\u00220px\u0022\n\t viewBox=\u00220 0 388 361.4\u0022 style=\u0022enable-background:new 0 0 388 361.4;\u0022 xml:space=\u0022preserve\u0022\u003E\n\u003Cstyle type=\u0022text\/css\u0022\u003E\n\t.st0{fill:#579D33;}\n\u003C\/style\u003E\n\u003Cpath class=\u0022st0\u0022 d=\u0022M388,190.3c-1.8,10.4-8.4,17.7-15.6,24.8c-34.2,33.9-68.3,68-102.3,102.1c-3.5,3.5-7.2,5.1-12.3,5\n\tc-36.2-0.2-72.5-0.1-108.7-0.1c-2.6,0-4.4,0.8-6.2,2.6c-10.6,10.8-21.3,21.4-32,32c-6.2,6.2-10.8,6.1-16.9-0.3\n\tc-28.8-30.3-57.6-60.7-86.3-91c-1.5-1.6-2.5-3.7-3.8-5.5c0-1.5,0-3,0-4.5c1.7-2.2,3.2-4.7,5.2-6.7C23,234.9,36.9,221,50.8,207.2\n\tc29.3-29,73-30.2,103.9-3c1.3,1.2,3.5,1.8,5.3,1.8c26,0.1,52,0.1,77.9,0.1c17,0,23.6,3.2,34,16.8c1-0.9,2-1.7,2.9-2.7\n\tc18.9-18.9,37.8-37.8,56.7-56.6c14.5-14.3,36.6-13.7,48.8,1.7c3.6,4.5,5.1,10.6,7.6,16C388,184.3,388,187.3,388,190.3z M102.8,338.5\n\tc10.2-10.2,20.2-20,29.9-30c3.5-3.6,7.3-5,12.3-5c36.2,0.1,72.5,0.1,108.8,0c1.9,0,4.2-0.8,5.5-2.1c35.1-34.8,70.1-69.8,105.1-104.8\n\tc6.2-6.2,6.5-14.6,0.9-20.3c-5.7-5.7-14.4-5.4-20.7,0.9c-22.6,22.6-45.3,45.2-67.9,67.8c-1,1-2,2.3-2.5,3.6\n\tc-5.7,15.1-16.6,22.8-32.9,22.8c-19,0-38,0-57,0c-5.4,0-10.8,0.1-16.1-0.1c-4.7-0.2-8.1-3.7-8.6-8.3c-0.5-4.5,2.4-8.7,6.9-9.8\n\tc1.5-0.4,3.2-0.5,4.8-0.5c18.4,0,36.8,0,55.1,0c5.6,0,11.2,0.1,16.9,0c8.2-0.2,13.9-6.6,13.5-14.8c-0.4-7.7-6.5-12.9-15.1-12.9\n\tc-29.1,0-58.2,0-87.4,0c-3.7,0-6.8-1-9.4-3.6c-23.7-23.9-57.6-24-81.4-0.3c-11.5,11.5-23,22.9-34.5,34.4c-0.9,0.8-1.7,1.8-2.6,2.7\n\tC52,284.9,77.2,311.6,102.8,338.5z\u0022\/\u003E\n\u003Cpath class=\u0022st0\u0022 d=\u0022M222.8,0c53.4-0.1,96.9,43.2,96.9,96.5c0,53.2-43.7,96.7-96.9,96.7c-53.2-0.1-96.5-43.4-96.6-96.5\n\tC126.1,43.3,169.3,0.1,222.8,0z M222.9,18.7c-43.1,0-77.9,34.7-77.9,77.8c0,42.9,34.9,77.9,77.8,78c43,0.1,78.3-35.1,78.2-78.1\n\tC300.9,53.5,265.9,18.7,222.9,18.7z\u0022\/\u003E\n\u003Cpath class=\u0022st0\u0022 d=\u0022M204.9,110.6c3.8,0.2,6.6,2,8.3,5.4c0.5,1.1,0.9,2.3,1.4,3.5c2,3.8,6.2,5.8,10.4,4.8c4-0.9,6.9-4.5,7.1-8.6\n\tc0.2-4.4-2.3-8.1-6.5-9.3c-1.2-0.3-2.4-0.5-3.7-0.6c-12.9-0.9-24.2-10.6-26.6-22.7c-2.5-12.6,3.6-24.7,15.6-30.6\n\tc2.1-1,2.6-2.2,2.5-4.3c-0.1-2.2,0-4.5,0.4-6.7c0.9-4.6,4.9-7.4,9.6-7.1c4.6,0.2,8.7,3.7,8.4,8.3c-0.4,6,1.4,9.6,7.1,12.4\n\tc5.3,2.6,8.5,7.9,10.4,13.7c1.9,5.7-0.2,10.9-5.2,12.7c-5.1,1.9-9.9-0.5-12.3-6.1c-2-4.9-5.7-7.4-9.9-6.9c-4.3,0.5-7.6,3.6-8.3,7.9\n\tc-0.8,5.2,3.5,10.2,9.6,10.6c8.4,0.6,15.5,3.6,21,10.1c11.3,13.3,8,33.8-7.4,42.2c-4,2.2-5.2,4.5-4.8,8.6c0.4,4.2-1.1,7.6-5,9.7\n\tc-5.9,3.1-13.5-0.6-13.4-7.5c0.1-6.3-2.2-9.5-7.5-12.5c-5.8-3.3-9.1-9.1-10.6-15.7C194.3,116,198.9,110.5,204.9,110.6z\u0022\/\u003E\n\u003C\/svg\u003E\n","type":"COMPANY_INSTALLMENTS","creditorName":"brutto","instalmentAmount":46222,"representativeExample":"","rrso":1.4062000000000001,"toPay":138666,"remarks":"\u003Cb style=\u0022color: red\u0022\u003EUwaga!\u003C\/b\u003E \u003Cb\u003ETen partner udziela finansowania tylko dla firm. Je\u015bli nie posiadasz firmy, wr\u00f3\u0107 do wyboru innego produktu.\u003C\/b\u003E","loanTerm":3,"loanParameters":[{"instalmentAmount":46222,"loanTerm":3,"toPay":138666,"rrso":1.4062000000000001},{"instalmentAmount":23942,"loanTerm":6,"toPay":143652,"rrso":0.8782},{"instalmentAmount":16611,"loanTerm":9,"toPay":149499,"rrso":0.7219},{"instalmentAmount":12594,"loanTerm":12,"toPay":151128,"rrso":0.5528}]},{"name":"Odroczone p\u0142atno\u015bci dla firm","description":"Zakupy dla firmy z nawet 60-dniowym odroczeniem p\u0142atno\u015bci","icon":"\u003Csvg width=\u002230\u0022 height=\u002236\u0022 viewBox=\u00220 0 30 36\u0022 fill=\u0022none\u0022 xmlns=\u0022http:\/\/www.w3.org\/2000\/svg\u0022\u003E\n\u003Cpath fill-rule=\u0022evenodd\u0022 clip-rule=\u0022evenodd\u0022 d=\u0022M9.29673 1.24414C9.29673 0.557026 8.7397 0 8.05259 0C7.36548 0 6.80845 0.557026 6.80845 1.24414V2.48828H3.73242C1.67105 2.48828 0 4.15933 0 6.2207V28.6152C0 30.6765 1.67105 32.3476 3.73242 32.3476H7.5813V32.3048C8.13251 32.178 8.54355 31.6843 8.54355 31.0946C8.54355 30.5049 8.13251 30.0112 7.5813 29.8843V29.8594H7.43048C7.38817 29.855 7.34522 29.8528 7.30176 29.8528H5.56324C5.51978 29.8528 5.47683 29.855 5.43452 29.8594H3.73242C3.04531 29.8594 2.48828 29.3022 2.48828 28.6152V12.4414H24.239V14.0114H24.2423C24.2828 14.6607 24.8222 15.1747 25.4816 15.1747C26.1411 15.1747 26.6805 14.6607 26.721 14.0114H26.7273V6.2207C26.7273 4.15933 25.0562 2.48828 22.9949 2.48828H19.9189V1.24414C19.9189 0.557026 19.3617 0 18.6747 0C17.9877 0 17.4306 0.557026 17.4306 1.24414V2.48828H9.29673V1.24414ZM17.4306 6.2207V4.97656H9.29673V6.2207C9.29673 6.90781 8.7397 7.46484 8.05259 7.46484C7.36548 7.46484 6.80845 6.90781 6.80845 6.2207V4.97656H3.73242C3.04531 4.97656 2.48828 5.53358 2.48828 6.2207V9.95312H24.239V6.2207C24.239 5.53358 23.6819 4.97656 22.9949 4.97656H19.9189V6.2207C19.9189 6.90781 19.3617 7.46484 18.6747 7.46484C17.9877 7.46484 17.4306 6.90781 17.4306 6.2207ZM18.863 36C13.1616 36 8.54367 31.3988 8.54367 25.7179C8.54367 20.0371 13.1616 15.4359 18.863 15.4359C24.5644 15.4359 29.1823 20.0371 29.1823 25.7179C29.1823 31.3988 24.5644 36 18.863 36ZM18.863 18.0064C14.5934 18.0064 11.1235 21.4637 11.1235 25.7179C11.1235 29.9721 14.5934 33.4295 18.863 33.4295C23.1326 33.4295 26.6025 29.9721 26.6025 25.7179C26.6025 21.4637 23.1326 18.0064 18.863 18.0064ZM18.5956 19.9622C19.2814 19.9613 19.8382 20.5165 19.8392 21.2023L19.8448 25.1807L23.0418 26.7775C23.6553 27.0839 23.9043 27.8297 23.5978 28.4433C23.2914 29.0568 22.5455 29.3058 21.932 28.9993L18.0492 27.06C17.6288 26.85 17.363 26.4207 17.3623 25.9508L17.3556 21.2058C17.3546 20.52 17.9098 19.9632 18.5956 19.9622Z\u0022 fill=\u0022#599E33\u0022\/\u003E\n\u003C\/svg\u003E","type":"COMPANY_BNPL","creditorName":"pragmago","instalmentAmount":120000,"representativeExample":"","rrso":0,"toPay":121000,"remarks":null,"loanTerm":14,"loanParameters":[{"instalmentAmount":120000,"loanTerm":14,"toPay":121000,"rrso":0},{"instalmentAmount":120000,"loanTerm":21,"toPay":122400,"rrso":0},{"instalmentAmount":120000,"loanTerm":30,"toPay":123000,"rrso":0},{"instalmentAmount":120000,"loanTerm":45,"toPay":124200,"rrso":0},{"instalmentAmount":120000,"loanTerm":60,"toPay":126000,"rrso":0}]}]', true),
            null,
            'API-KEY'
        );

        $response = $apiClient->getFinancialProducts($queryCriteria);

        $this->assertCount(5, $response->financialProducts);
        $this->assertEquals(
            new FinancialProduct(
                'Raty 0%',
                new LoanTypeEnum(LoanTypeEnum::INSTALLMENTS_ZERO_PERCENT),
                'Szybkie i proste zakupy bez dodatkowych kosztów. Spłacasz dokładnie tyle, ile pożyczasz!',
                "<?xml version=\"1.0\" encoding=\"utf-8\"?>\n<svg version=\"1.1\" id=\"Comfino_InstallmentsZero\" xmlns=\"http://www.w3.org/2000/svg\" xmlns:xlink=\"http://www.w3.org/1999/xlink\" x=\"0px\" y=\"0px\"\n\t viewBox=\"0 -60 382.6 273.8\" style=\"enable-background:new 0 0 382.6 213.8;\" xml:space=\"preserve\">\n<style type=\"text/css\">\n\t.st0{fill:#579D33;}\n</style>\n<g>\n\t<g>\n\t\t<path class=\"st0\" d=\"M0,71C0,29.7,27.4,3.7,74.1,3.7s73.8,26,73.8,67.3v71.8c0,41.6-27.1,67.6-73.8,67.6S0,184.5,0,142.8L0,71\n\t\t\tL0,71z M112.9,71c0-22.1-14.2-34.7-38.8-34.7S35.2,49,35.2,71v71.8c0,22.1,14.2,34.9,38.8,34.9s38.8-12.8,38.8-34.9V71z\"/>\n\t\t<path class=\"st0\" d=\"M171.6,45c0-26,16.5-41.6,46.4-41.6s46.4,15.6,46.4,41.6v19.8c0,25.7-16.5,41.1-46.4,41.1\n\t\t\ts-46.4-15.4-46.4-41.1L171.6,45L171.6,45z M356.6,7.9c6.4,0,7.8,4.5,4.5,9.2L228,197.9c-5.6,7.5-7.3,8.4-18.7,8.4h-10.9\n\t\t\tc-6.7,0-8.4-4.2-4.8-9.5l133-181.1c4.8-7,7.3-7.8,17.6-7.8H356.6z M237.2,46.4c0-11.5-6.4-17-19.3-17s-19.3,5.6-19.3,17v16.8\n\t\t\tc0,11.5,6.4,17,19.3,17s19.3-5.6,19.3-17V46.4z M289.8,149.8c0-26,16.5-41.6,46.4-41.6s46.4,15.7,46.4,41.6v19.8\n\t\t\tc0,25.7-16.5,41.1-46.4,41.1s-46.4-15.4-46.4-41.1V149.8z M355.4,151.2c0-11.5-6.4-17-19.3-17c-12.8,0-19.3,5.6-19.3,17v16.5\n\t\t\tc0,11.5,6.4,17.3,19.3,17.3c12.8,0,19.3-5.9,19.3-17.3V151.2z\"/>\n\t</g>\n</g>\n</svg>",
                40000,
                120000,
                3,
                0,
                "Przykład reprezentatywny dla AS InBank S.A Odział w Polsce na dzień 08-02-2021 r. : Rzeczywista Roczna Stopa Oprocentowania (RRSO) 0,00%, całkowita kwota kredytu 3000,00 zł, całkowita kwota do zapłaty przez klienta 3000,00 zł, całkowity koszt kredytu 0,00 zł. Okres kredytowania 10 rat, raty równe w wysokości 300,00 zł.Niniejsze warunki nie stanowią oferty w rozumieniu art. 66 Kodeksu Cywilnego. Przyznanie kredytu uzależnione jest od oceny zdolności kredytowej.\n\nKredytodawca AS Inbank, który jest bankiem utworzonym za zezwoleniem estońskich władz nadzorczych Finantsinspektsioon, posiada siedzibę przy Niine 11, 10414 Tallinn, w Estonii i prowadzi swoją działalność w Polsce poprzez Oddział. Oddział Inbank w Polsce nadzorowany jest przez Finantsinspektsioon i nie jest nadzorowany przez Komisję Nadzoru Finansowego, z zastrzeżeniem środków nadzorczych przewidzianych dla KNF w art. 141a ustawy z dnia 29 sierpnia 1997 r. Prawo bankowe. Istotne informacje dotyczące prowadzenia przez Inbank działalności na terytorium Rzeczypospolitej Polskiej zawarte są w <a target=\"_blank\" href=\"https://www.inbankpolska.pl/documents/pl/decyzja_knf_z_dnia_10_01_2017.pdf\">Decyzji Komisji Nadzoru Finansowego</a> wskazującej warunki prowadzenia tej działalności oraz w <a target=\"_blank\" href=\"https://www.inbankpolska.pl/inbank/nota-prawna/\">nocie prawnej</a>.",
                null,
                [
                    new Api\Dto\Payment\LoanParameters(40000, 120000, 3, 0),
                    new Api\Dto\Payment\LoanParameters(20000, 120000, 6, 0),
                    new Api\Dto\Payment\LoanParameters(12000, 120000, 10, 0),
                ]
            ),
            $response->financialProducts[0]
        );

        $this->expectException(AuthorizationError::class);
        $this->initApiClient('/v1/financial-products', 'GET')->getFinancialProducts($queryCriteria);
    }

    public function testCreateOrder(): void
    {
        $jsonRequest = '{"notifyUrl":"https:\/\/comfino-shop.test\/notification","returnUrl":"https:\/\/comfino-shop.test","orderId":"11169b13-1f47-4b4a-801e-2934371bc098","loanParameters":{"amount":50000,"term":24,"type":"CONVENIENT_INSTALLMENTS","allowedProductTypes":["PAY_LATER"]},"cart":{"products":[{"name":"Lenovo Ideapad 120S-14IAP","quantity":1,"price":100000,"photoUrl":"https:\/\/beks.pl\/wp-content\/uploads\/2016\/10\/S-2282-popra_low_res_zlota_rama.jpg","ean":"9002490100070","externalId":"123","category":"testtesttest1"},{"name":"Lenovo Ideapad 120S-14IAP","quantity":1,"price":100000,"photoUrl":"https:\/\/mnwr.pl\/wp-content\/uploads\/2020\/06\/Beksinski_Obraz.jpg","ean":"9002490100070","externalId":"123","category":"testtesttest2"},{"name":"Lenovo Ideapad 120S-14IAP","quantity":1,"price":100000,"photoUrl":"https:\/\/beks.pl\/wp-content\/uploads\/2016\/10\/S-2282-popra_low_res_zlota_rama.jpg","ean":"9002490100070","externalId":"123","category":"testtesttest3"},{"name":"Lenovo Ideapad 120S-14IAP","quantity":1,"price":100000,"photoUrl":"https:\/\/mnwr.pl\/wp-content\/uploads\/2020\/06\/Beksinski_Obraz.jpg","ean":"9002490100070","externalId":"123","category":"testtesttest4"},{"name":"Rabat","quantity":1,"price":-352000,"category":"DISCOUNT"}],"totalAmount":50000,"deliveryCost":2000,"category":"elektronika"},"customer":{"firstName":"John","lastName":"Doe","email":"mail@test","phoneNumber":"333333333","taxId":"3559197034","ip":"127.0.0.1","address":{"street":"Test street","buildingNumber":"13","apartmentNumber":"21","postalCode":"10-899","city":"Test city","countryCode":"PL"}},"seller":{"taxId":"472156893"}}';
        $responseData = json_decode('{"status":"CREATED","externalId":"11169b13-1f47-4b4a-801e-2934371bc098","applicationUrl":"http:\/\/wniosek.comfino.test\/ecommerce\/verify?token=4037bf5c33d53a853183","_links":{"self":{"href":"http:\/\/api-ecommerce.comfino.test\/v1\/orders\/11169b13-1f47-4b4a-801e-2934371bc098","method":"GET"},"cancel":{"href":"http:\/\/api-ecommerce.comfino.test\/v1\/orders\/11169b13-1f47-4b4a-801e-2934371bc098\/cancel","method":"PUT"}}}', true);

        $order = new Order(
            '11169b13-1f47-4b4a-801e-2934371bc098',
            'https://comfino-shop.test',
            new LoanParameters(50000, 24, new LoanTypeEnum(LoanTypeEnum::CONVENIENT_INSTALLMENTS), [new LoanTypeEnum(LoanTypeEnum::PAY_LATER)]),
            new Cart(
                [
                    new Cart\CartItem(
                        new Cart\Product(
                            'Lenovo Ideapad 120S-14IAP',
                            100000,
                            '123',
                            'testtesttest1',
                            '9002490100070',
                            'https://beks.pl/wp-content/uploads/2016/10/S-2282-popra_low_res_zlota_rama.jpg'
                        ), 1
                    ),
                    new Cart\CartItem(
                        new Cart\Product(
                            'Lenovo Ideapad 120S-14IAP',
                            100000,
                            '123',
                            'testtesttest2',
                            '9002490100070',
                            'https://mnwr.pl/wp-content/uploads/2020/06/Beksinski_Obraz.jpg'
                        ), 1
                    ),
                    new Cart\CartItem(
                        new Cart\Product(
                            'Lenovo Ideapad 120S-14IAP',
                            100000,
                            '123',
                            'testtesttest3',
                            '9002490100070',
                            'https://beks.pl/wp-content/uploads/2016/10/S-2282-popra_low_res_zlota_rama.jpg'
                        ), 1
                    ),
                    new Cart\CartItem(
                        new Cart\Product(
                            'Lenovo Ideapad 120S-14IAP',
                            100000,
                            '123',
                            'testtesttest4',
                            '9002490100070',
                            'https://mnwr.pl/wp-content/uploads/2020/06/Beksinski_Obraz.jpg'
                        ), 1
                    ),
                ], 50000, 2000, 'elektronika'
            ),
            new Shop\Order\Customer(
                'John',
                'Doe',
                'mail@test',
                '333333333',
                '127.0.0.1',
                '3559197034',
                null,
                null,
                new Shop\Order\Customer\Address('Test street', '13', '21', '10-899', 'Test city', 'PL')
            ),
            'https://comfino-shop.test/notification',
            new Seller('472156893')
        );

        $apiClient = $this->initApiClient('/v1/orders', 'POST', null, $jsonRequest, $responseData, 'API-KEY');
        $response = $apiClient->createOrder($order);

        $this->assertEquals('http://wniosek.comfino.test/ecommerce/verify?token=4037bf5c33d53a853183', $response->applicationUrl);
        $this->assertEquals('CREATED', $response->status);
        $this->assertEquals('11169b13-1f47-4b4a-801e-2934371bc098', $response->externalId);
    }

    public function testGetOrder(): void
    {
        $orderId = 'e43ae07a-2f41-4720-9d93-7103acee3c96';
        $responseData = json_decode('{"status":"CREATED","applicationUrl":"http:\/\/wniosek.comfino.test\/ecommerce\/verify?token=1dfe75af337c87829fc3","createdAt":"2023-12-04T14:40:04+01:00","notifyUrl":"https:\/\/comfino-shop.test\/notification","returnUrl":"https:\/\/comfino-shop.test","orderId":"e43ae07a-2f41-4720-9d93-7103acee3c96","loanParameters":{"amount":50000,"maxAmount":null,"term":24,"type":"CONVENIENT_INSTALLMENTS","allowedProductTypes":null},"cart":{"totalAmount":50000,"deliveryCost":2000,"products":[{"name":"Lenovo Ideapad 120S-14IAP","quantity":1,"price":100000,"photoUrl":"https:\/\/beks.pl\/wp-content\/uploads\/2016\/10\/S-2282-popra_low_res_zlota_rama.jpg","ean":"9002490100070","externalId":"123","category":null},{"name":"Lenovo Ideapad 120S-14IAP","quantity":1,"price":100000,"photoUrl":"https:\/\/mnwr.pl\/wp-content\/uploads\/2020\/06\/Beksinski_Obraz.jpg","ean":"9002490100070","externalId":"123","category":null},{"name":"Lenovo Ideapad 120S-14IAP","quantity":1,"price":100000,"photoUrl":"https:\/\/beks.pl\/wp-content\/uploads\/2016\/10\/S-2282-popra_low_res_zlota_rama.jpg","ean":"9002490100070","externalId":"123","category":null},{"name":"Lenovo Ideapad 120S-14IAP","quantity":1,"price":150000,"photoUrl":"https:\/\/mnwr.pl\/wp-content\/uploads\/2020\/06\/Beksinski_Obraz.jpg","ean":"9002490100070","externalId":"123","category":null}],"category":null},"customer":{"email":"mail@test","phoneNumber":"333333333","firstName":"John","lastName":"Doe","taxId":"3559197034","address":{"street":"Test street","buildingNumber":"13","postalCode":"10-899","city":"Test city","apartmentNumber":"21","countryCode":"PL"},"ip":"127.0.0.1","regular":null,"logged":null},"_links":{"self":{"href":"http:\/\/api-ecommerce.comfino.test\/v1\/orders\/e43ae07a-2f41-4720-9d93-7103acee3c96","method":"GET"},"cancel":{"href":"http:\/\/api-ecommerce.comfino.test\/v1\/orders\/e43ae07a-2f41-4720-9d93-7103acee3c96\/cancel","method":"PUT"}}}', true);

        $apiClient = $this->initApiClient(sprintf('/v1/orders/%s', $orderId), 'GET', null, null, $responseData, 'API-KEY');
        $response = $apiClient->getOrder($orderId);

        $this->assertEquals($orderId, $response->orderId);
        $this->assertInstanceOf(\DateTime::class, $response->createdAt);
        $this->assertEquals('2023-12-04 14:40:04', $response->createdAt->format('Y-m-d H:i:s'));
        $this->assertEquals('CREATED', $response->status);
        $this->assertEquals('http://wniosek.comfino.test/ecommerce/verify?token=1dfe75af337c87829fc3', $response->applicationUrl);
        $this->assertEquals('https://comfino-shop.test/notification', $response->notifyUrl);
        $this->assertEquals('https://comfino-shop.test', $response->returnUrl);

        $this->assertEquals(50000, $response->loanParameters->amount);
        $this->assertNull($response->loanParameters->maxAmount);
        $this->assertEquals(LoanTypeEnum::CONVENIENT_INSTALLMENTS, $response->loanParameters->type);
        $this->assertEquals(24, $response->loanParameters->term);
        $this->assertNull($response->loanParameters->allowedProductTypes);

        $this->assertEquals(50000, $response->cart->totalAmount);
        $this->assertEquals(2000, $response->cart->deliveryCost);
        $this->assertNull($response->cart->category);
        $this->assertCount(4, $response->cart->products);
        $this->assertEquals(
            [
                new CartItem(
                    'Lenovo Ideapad 120S-14IAP',
                    100000,
                    1,
                    123,
                    'https://beks.pl/wp-content/uploads/2016/10/S-2282-popra_low_res_zlota_rama.jpg',
                    '9002490100070',
                    null
                ),
                new CartItem(
                    'Lenovo Ideapad 120S-14IAP',
                    100000,
                    1,
                    123,
                    'https://mnwr.pl/wp-content/uploads/2020/06/Beksinski_Obraz.jpg',
                    '9002490100070',
                    null
                ),
                new CartItem(
                    'Lenovo Ideapad 120S-14IAP',
                    100000,
                    1,
                    123,
                    'https://beks.pl/wp-content/uploads/2016/10/S-2282-popra_low_res_zlota_rama.jpg',
                    '9002490100070',
                    null
                ),
                new CartItem(
                    'Lenovo Ideapad 120S-14IAP',
                    150000,
                    1,
                    123,
                    'https://mnwr.pl/wp-content/uploads/2020/06/Beksinski_Obraz.jpg',
                    '9002490100070',
                    null
                ),
            ],
            $response->cart->products
        );

        $this->assertEquals(
            new Customer(
                'John',
                'Doe',
                'mail@test',
                '333333333',
                '127.0.0.1',
                '3559197034',
                null,
                null,
                new Address('Test street', '13', '21', '10-899', 'Test city', 'PL')
            ),
            $response->customer
        );

        $this->expectException(AuthorizationError::class);
        $this->initApiClient(sprintf('/v1/orders/%s', $orderId), 'GET')->getOrder($orderId);
    }

    public function testCancelOrder(): void
    {
        $orderId = 'ORDER-ID';

        $apiClient = $this->initApiClient(sprintf('/v1/orders/%s/cancel', $orderId), 'PUT', null, null, null, 'API-KEY');
        $apiClient->cancelOrder($orderId);

        $this->expectException(AuthorizationError::class);
        $this->initApiClient(sprintf('/v1/orders/%s/cancel', $orderId), 'PUT')->cancelOrder($orderId);
    }

    public function testGetProductTypes(): void
    {
        $productTypes = [
            new LoanTypeEnum(LoanTypeEnum::INSTALLMENTS_ZERO_PERCENT),
            new LoanTypeEnum(LoanTypeEnum::PAY_LATER),
            new LoanTypeEnum(LoanTypeEnum::CONVENIENT_INSTALLMENTS),
            new LoanTypeEnum(LoanTypeEnum::COMPANY_BNPL),
        ];
        $productTypesWithNames = [
            'INSTALLMENTS_ZERO_PERCENT' => 'Raty zero procent',
            'PAY_LATER' => 'Zapłać później',
            'CONVENIENT_INSTALLMENTS' => 'Niskie raty',
            'COMPANY_BNPL' => 'Odroczone płatności dla firm',
        ];
        $listType = new ProductTypesListTypeEnum(ProductTypesListTypeEnum::LIST_TYPE_PAYWALL);

        $apiClient = $this->initApiClient('/v1/product-types', 'GET', ['listType' => (string) $listType], null, $productTypesWithNames, 'API-KEY');
        $response = $apiClient->getProductTypes($listType);

        $this->assertEquals($productTypes, $response->productTypes);
        $this->assertEquals($productTypesWithNames, $response->productTypesWithNames);

        $this->expectException(AuthorizationError::class);
        $this->initApiClient('/v1/product-types', 'GET', ['listType' => (string) $listType])->getProductTypes($listType);
    }

    public function testGetWidgetKey(): void
    {
        $widgetKey = 'WIDGET-KEY';

        $apiClient = $this->initApiClient('/v1/widget-key', 'GET', null, null, $widgetKey, 'API-KEY');
        $response = $apiClient->getWidgetKey();

        $this->assertEquals($widgetKey, $response);

        $this->expectException(AuthorizationError::class);
        $this->initApiClient('/v1/widget-key', 'GET')->getWidgetKey();
    }

    public function testGetWidgetTypes(): void
    {
        $widgetTypes = [
            WidgetTypeEnum::WIDGET_SIMPLE,
            WidgetTypeEnum::WIDGET_MIXED,
            WidgetTypeEnum::WIDGET_WITH_CALCULATOR,
            WidgetTypeEnum::WIDGET_WITH_EXTENDED_CALCULATOR,
        ];
        $widgetTypesWithNames = [
            'simple' => 'Widget tekstowy',
            'mixed' => 'Widget graficzny z banerem',
            'with-modal' => 'Widget graficzny z kalkulatorem rat',
            'extended-modal' => 'Widget graficzny z rozszerzonym kalkulatorem rat',
        ];

        $apiClient = $this->initApiClient('/v1/widget-types', 'GET', null, null, $widgetTypesWithNames, 'API-KEY');
        $response = $apiClient->getWidgetTypes();

        $this->assertEquals($widgetTypes, $response->widgetTypes);
        $this->assertEquals($widgetTypesWithNames, $response->widgetTypesWithNames);

        $this->expectException(AuthorizationError::class);
        $this->initApiClient('/v1/widget-types', 'GET')->getWidgetTypes();
    }

    public function testGetPaywall(): void
    {
        $paywallPageContents = 'PAYWALL_PAGE_CONTENTS';

        $apiClient = $this->initApiClient('/v1/shop-plugin-paywall', 'GET', null, null, $paywallPageContents, 'API-KEY', false, 200, 'text/html');
        $response = $apiClient->getPaywall();

        $this->assertEquals($paywallPageContents, $response->paywallPage);
    }

    public function testGetPaywallFragments(): void
    {
        $paywallPageFragments = [
            'template' => 'TEMPLATE_CONTENTS',
            'style' => 'STYLE_CONTENTS',
            'script' => 'SCRIPT_CONTENTS',
        ];

        $apiClient = $this->initApiClient('/v1/shop-plugin-paywall-fragments', 'GET', null, null, $paywallPageFragments, 'API-KEY');
        $response = $apiClient->getPaywallFragments();

        $this->assertEquals($paywallPageFragments, $response->paywallFragments);
    }

    protected function setUp(): void
    {
        $this->productionApiHost = parse_url($this->getConstantFromClass(Client::class, 'PRODUCTION_HOST'), PHP_URL_HOST);
    }

    private function initApiClient(string $endpointPath, string $method, ?array $queryParameters = null, ?string $requestBody = null, $responseData = null, ?string $apiKey = null, bool $isPublicEndpoint = false, int $responseStatus = 200, string $contentType = 'application/json'): Client
    {
        $client = new \Http\Mock\Client();
        $client->on(
            new RequestMatcher($endpointPath, $this->productionApiHost, $method, 'https'),
            function (RequestInterface $request) use ($queryParameters, $requestBody, $responseData, $apiKey, $isPublicEndpoint, $responseStatus) {
                return $this->processRequest($request, $queryParameters, $requestBody, $responseData, $apiKey, $isPublicEndpoint, $responseStatus);
            }
        );

        return new Client(new RequestFactory(), new StreamFactory(), $client, $apiKey);
    }

    private function processRequest(RequestInterface $request, ?array $queryParameters = null, ?string $requestBody = null, $responseData = null, ?string $apiKey = null, bool $isPublicEndpoint = false, int $responseStatus = 200, string $contentType = 'application/json'): ResponseInterface
    {
        if (!$isPublicEndpoint && (!$request->hasHeader('Api-Key') || $request->getHeaderLine('Api-Key') !== $apiKey)) {
            return (new ResponseFactory())->createJsonResponse(401, ['message' => 'Invalid credentials.']);
        }

        if ($requestBody !== null) {
            $this->assertEquals($requestBody, $request->getBody()->getContents(), 'Request body is invalid.');
        } else {
            $this->assertEquals('', $request->getBody()->getContents(), 'Request body is invalid.');
        }

        if (is_array($queryParameters) && count($queryParameters)) {
            $this->assertEquals(http_build_query($queryParameters), $request->getUri()->getQuery(), 'Request URL query string is invalid.');
        } else {
            $this->assertEquals('', $request->getUri()->getQuery(), 'Request URL query string is invalid.');
        }

        if ($contentType === 'application/json') {
            return (new ResponseFactory())->createJsonResponse($responseStatus, $responseData);
        }

        if ($contentType === 'text/html') {
            return (new ResponseFactory())->createHtmlResponse($responseStatus, $responseData);
        }

        return (new ResponseFactory())->createResponse($responseStatus, $responseData)->withHeader('Content-Type', $contentType);
    }
}
