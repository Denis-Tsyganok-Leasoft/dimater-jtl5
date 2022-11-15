<?php

namespace Plugin\dimater_jtl5\paymentmethod;

use Plugin\dimater_jtl5\redefiners\PaymentRedefiner;

class CreditCard extends PaymentRedefiner
{
    public string $paymentName = 'credit-card';
}