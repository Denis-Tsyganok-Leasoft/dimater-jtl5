<?php
namespace Plugin\dimater_jtl5;

use JTL\Events\Dispatcher;
use JTL\Plugin\Bootstrapper;
use Plugin\dimater_jtl5\frontend\GingerHookHandler;

class Bootstrap extends Bootstrapper
{
    /**
     * Boot additional services for the payment method
     */
    public function boot(Dispatcher $dispatcher): void
    {
        parent::boot($dispatcher);

        if (\Shop::isFrontend()) {

            $dispatcher->listen('shop.hook.' . \HOOK_SMARTY_OUTPUTFILTER, [new GingerHookHandler(), 'orderConfirmationPage']);
        }
    }
}