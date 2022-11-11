<?php

namespace Plugin\dimater_jtl5\components;

use GingerPluginSdk\Entities\Order;
use JTL\Checkout\Bestellung;

class Helper
{
    public static array $ORDER_STATUSES = [
          'completed' => \BESTELLUNG_STATUS_BEZAHLT,
          'processing' => \BESTELLUNG_STATUS_IN_BEARBEITUNG,
          'cancelled' => \BESTELLUNG_STATUS_STORNO,
          'new' => \BESTELLUNG_STATUS_OFFEN,
          'error' => \BESTELLUNG_STATUS_STORNO,
          'expired' => \BESTELLUNG_STATUS_STORNO,
          'shipped' => \BESTELLUNG_STATUS_VERSANDT,
          'see-transactions' => \BESTELLUNG_STATUS_OFFEN,
        ];

    public static function getOrderDetailsFromGingerTable(Bestellung $order, Order $gingerOrder): object|array|int
    {
        $query = "
            SELECT *
            FROM xplugin_ginger_transaction_details
            WHERE ginger_order_id = :gingerOrderId
            AND merchant_order_id = :merchantOrderId
        ";

        return \Shop::Container()->getDB()->queryPrepared(
            $query,
            ['gingerOrderId' => $gingerOrder->getId()->get(), 'merchantOrderId' => $order->cBestellNr],
            \JTL\DB\ReturnType::ARRAY_OF_OBJECTS
        );
    }

    public static function insertOrderDetailsIntoGingerTable(Bestellung $order, Order $gingerOrder): void
    {
        $insertOrder = new \stdClass();
        $insertOrder->merchant_order_id = $order->cBestellNr;
        $insertOrder->ginger_order_id = $gingerOrder->getId()->get();

        \Shop::Container()->getDB()->insert('xplugin_ginger_transaction_details', $insertOrder);
    }

    public static function validateApiKey()
    {
        return \JTL\Plugin\Helper::getPluginById(BankConfig::PLUGIN_ID)->getConfig()->getValue('ginger_api_key');
    }

    public static function displayNotification($message, $alertType)
    {
        \Shop::Container()->getAlertService()->addAlert(
            $alertType,
            $message,
            'ginger notification',
            ['saveInSession' => true]
        );
        return false;
    }

    public static function cleanUpSession($paymentName)
    {
        unset($_SESSION["ginger_$paymentName"]);
    }
}
