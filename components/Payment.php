<?php

namespace Plugin\dimater_jtl5\components;

require_once __DIR__ . '/../vendor/autoload.php';

use GingerPluginSdk\Client;
use GingerPluginSdk\Properties\ClientOptions;
use GingerPluginSdk\Properties\Currency;
use JTL\Cart\Cart;
use JTL\Checkout\Bestellung;
use JTL\Plugin\Payment\Method;
use JTL\Plugin\PluginInterface;
use JTL\Session\Frontend;
use JTL\Shop;
use Plugin\dimater_jtl5\redefiners\HelperRedefiner;
use Plugin\dimater_jtl5\redefiners\OrderBuilderRedefiner;
use JTL\Plugin\Helper;

class Payment extends Method
{
    protected Client $client;
    protected PluginInterface $plugin;

    public function __construct(string $moduleID)
    {
        $this->plugin = Helper::getPluginById(BankConfig::PLUGIN_ID);

        if (!HelperRedefiner::validateApiKey()) {
            HelperRedefiner::displayNotification($this->plugin->getLocalization()->getTranslation('api_key_missing_error'), \Alert::TYPE_ERROR);
            return false;
        }

        try {
            $this->client = new Client(
                new ClientOptions(
                    endpoint: BankConfig::BANK_ENDPOINT,
                    useBundle: $this->plugin->getConfig()->getValue('ginger_cacert') === 'on',
                    apiKey: $this->plugin->getConfig()->getValue('ginger_api_key')
                )
            );
        }catch (\Exception $exception) {
            $this->doLog($exception->getMessage(), \LOGLEVEL_ERROR);
        }


        parent::__construct($moduleID);
    }

    public function isValid(object $customer, Cart $cart): bool
    {

        if (!HelperRedefiner::validateApiKey()) {
            HelperRedefiner::displayNotification($this->plugin->getLocalization()->getTranslation('api_key_missing_error'), \Alert::TYPE_ERROR);
            return false;
        }

        if ($this->paymentName == 'apple-pay' && !$this->applePayDetection()) {
            return false;
        }

        if ($this->paymentName == 'afterpay' && !$this->countryValidation()) {
            return false;
        }

        if ($this->paymentName == 'afterpay' && !$this->ipValidation()) {
            return false;
        }

        return $this->client->checkAvailabilityForPaymentMethodUsingCurrency(
            $this->paymentName,
            new Currency(Frontend::getCurrency()->getCode())
        );
    }

    /**
     * @throws \Exception
     */
    public function handleAdditional(array $post): bool
    {
        if ($this->paymentName == 'ideal') {
            if (isset($post['ginger_payment']) && $post['issuer']) {
                $_SESSION[$post['ginger_payment']] = array_map('trim', $post);

                return true;
            }

            \Shop::Smarty()->assign('issuers', $this->client->getIdealIssuers()->toArray());
            return false;
        }

        if ($this->paymentName == 'afterpay') {
            if (isset($post['ginger_payment']) && $post['bday'] && $post['gender']) {
                $_SESSION[$post['ginger_payment']] = array_map('trim', $post);

                return true;
            }

            return false;
        }

        return parent::handleAdditional($post);
    }

    /**
     * @inheritDoc
     */
    public function preparePaymentProcess($order): void
    {
        parent::preparePaymentProcess($order);

        try{
            $gingerOrder = $this->client->sendOrder((new OrderBuilderRedefiner($this, $order))->createOrder());
        }catch(\Exception $exception) {
            HelperRedefiner::displayNotification($exception->getMessage(), \Alert::TYPE_ERROR);
            $this->doLog($exception->getMessage(), \LOGLEVEL_ERROR);
            HelperRedefiner::cleanUpSession($this->paymentName);

            // Redirecting to the checkout page
            header('Location:' . \Shop::getURL() . '/Bestellvorgang?editVersandart=1');
            exit();
        }

        HelperRedefiner::insertOrderDetailsIntoGingerTable($order, $gingerOrder);

        if ($this->paymentName == 'bank-transfer') {

            $_SESSION['ginger_banktransfer'] = [
                'iban' => $gingerOrder->getCurrentTransaction()->getPaymentMethodDetails()->creditor_iban->get(),
                'bic' => $gingerOrder->getCurrentTransaction()->getPaymentMethodDetails()->creditor_bic->get(),
                'holderName' => $gingerOrder->getCurrentTransaction()->getPaymentMethodDetails()->creditor_account_holder_name->get(),
                'holderCity' => $gingerOrder->getCurrentTransaction()->getPaymentMethodDetails()->creditor_account_holder_city->get(),
                'reference' => $gingerOrder->getCurrentTransaction()->getPaymentMethodDetails()->reference->get(),
            ];

            header('Location: ' . \Shop::Container()->getLinkService()->getStaticRoute('bestellabschluss.php') . '?i=' . $this->generateHash($order));
            exit;
        }

        header('Location: ' . $gingerOrder->getPaymentUrl());
        exit;
    }

    /**
     * @inheritDoc
     * @throws \Exception
     */
    public function handleNotification(Bestellung $order, string $hash, array $args): void
    {
        if (!filter_input(INPUT_GET,'order_id')) {
            $this->handleWebhook();
        }

        $gingerOrder = $this->client->getOrder(filter_input(INPUT_GET,'order_id'));
        HelperRedefiner::cleanUpSession($this->paymentName);

        Shop::Container()->getDB()->update('tbestellung', 'kBestellung', $order->kBestellung, (object)['cStatus'  => HelperRedefiner::$ORDER_STATUSES[$gingerOrder->getStatus()->get()], 'dBezahltDatum' => 'NOW()']);

        if ($gingerOrder->getStatus()->get() == 'completed' || $gingerOrder->getStatus()->get() == 'processing') {

            $this->sendConfirmationMail($order);

            // Redirecting to the thankyou page
            header('Location: ' . \Shop::Container()->getLinkService()->getStaticRoute('bestellabschluss.php') . '?i=' . $this->generateHash($order));
            exit;
        }

        HelperRedefiner::displayNotification($gingerOrder->getCurrentTransaction()->toArray()['customer_message'], \Alert::TYPE_ERROR);

        // Redirecting to the checkout page
        header('Location:' . \Shop::getURL() . '/Bestellvorgang?editVersandart=1');
        exit;
    }

    /**
     * @throws \Exception
     */
    public function handleWebhook()
    {
        $input = json_decode(file_get_contents("php://input"), true);
        if (!in_array($input['event'], ["status_changed"])) die("Only work to do if the status changed");

        $gingerOrder = $this->client->getOrder($input['order_id']);
        $order = new Bestellung($gingerOrder->getMerchantOrderId()->get());

        $dbRecord = HelperRedefiner::getOrderDetailsFromGingerTable($order, $gingerOrder);

        if($gingerOrder->getId()->get() !== $dbRecord[0]->ginger_order_id) exit;

        Shop::Container()->getDB()->update('tbestellung', 'kBestellung', (int)$order->kBestellung, (object)['cStatus'  => HelperRedefiner::$ORDER_STATUSES[$gingerOrder->getStatus()->get()], 'dBezahltDatum' => 'NOW()']);
        exit;
    }

    public function countryValidation(): bool
    {
        $countries = array_map("trim", explode(',', $this->plugin->getConfig()->getValue('ginger_afterpay_countries')));

        return in_array(Frontend::getCustomer()->cLand, $countries);
    }

    public function ipValidation(): bool
    {
        if (!$this->plugin->getConfig()->getValue('ginger_afterpay_ips')) {
            return true;
        }

        $ips = array_map("trim", explode(',', $this->plugin->getConfig()->getValue('ginger_afterpay_ips')));

        return in_array($_SERVER['REMOTE_ADDR'], $ips);
    }

    public function applePayDetection(): bool
    {
        echo "<script>
        if (!window.ApplePaySession) {
        document.cookie = 'ginger_apple_pay_status = false' 
        } else {
        document.cookie = 'ginger_apple_pay_status = true' 
        };
        </script>";

        return $_COOKIE["ginger_apple_pay_status"] === 'true';
    }
}
