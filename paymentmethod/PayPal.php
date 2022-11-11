<?php

namespace Plugin\dimater_jtl5\paymentmethod;

use Plugin\dimater_jtl5\redefiners\PaymentRedefiner;

class PayPal extends PaymentRedefiner
{
    public string $paymentName = 'paypal';
}