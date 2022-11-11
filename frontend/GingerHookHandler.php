<?php

namespace Plugin\dimater_jtl5\frontend;

use Plugin\dimater_jtl5\components\BankConfig;
use JTL\Plugin\Helper;
use Plugin\dimater_jtl5\redefiners\HelperRedefiner;

class GingerHookHandler
{

    public function orderConfirmationPage()
    {

        if ($_SESSION['ginger_banktransfer']) {

            $plugin = Helper::getPluginById(BankConfig::PLUGIN_ID);
            $paymentInformation = $plugin->getLocalization()->getTranslation('banktransfer_payment_information');
            $paymentReference = $plugin->getLocalization()->getTranslation('banktransfer_payment_reference');
            $accountHolder = $plugin->getLocalization()->getTranslation('banktransfer_account_holder');
            $residence = $plugin->getLocalization()->getTranslation('banktransfer_residence');

            pq('#order-confirmation')->append('<div class="card-body">
                <b>'.$paymentInformation.'</b>'.
                '<p>'. $paymentReference . ' ' . $_SESSION['ginger_banktransfer']['reference'] . '</p>'.
                '<p>IBAN: '. $_SESSION['ginger_banktransfer']['iban'] . '</p>'.
                '<p>BIC: '. $_SESSION['ginger_banktransfer']['bic'] . '</p>'.
                '<p>'. $accountHolder . ' ' . $_SESSION['ginger_banktransfer']['holderName'] . '</p>'.
                '<p>'. $residence . ' ' . $_SESSION['ginger_banktransfer']['holderCity'] . '</p>'.
                '</div>');

            HelperRedefiner::cleanUpSession('banktransfer');
        }
    }
}
