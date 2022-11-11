<?php

namespace Plugin\dimater_jtl5\paymentmethod;

use Plugin\dimater_jtl5\redefiners\PaymentRedefiner;

class Sofort extends PaymentRedefiner
{
    public string $paymentName = 'sofort';
}