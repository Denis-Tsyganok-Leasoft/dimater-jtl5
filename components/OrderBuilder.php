<?php

namespace Plugin\dimater_jtl5\components;

use GingerPluginSdk\Collections\AdditionalAddresses;
use GingerPluginSdk\Collections\OrderLines;
use GingerPluginSdk\Collections\PhoneNumbers;
use GingerPluginSdk\Collections\Transactions;
use GingerPluginSdk\Entities\Address;
use GingerPluginSdk\Entities\Client;
use GingerPluginSdk\Entities\Customer;
use GingerPluginSdk\Entities\Line;
use GingerPluginSdk\Entities\Order;
use GingerPluginSdk\Entities\PaymentMethodDetails;
use GingerPluginSdk\Entities\Transaction;
use GingerPluginSdk\Properties\Amount;
use GingerPluginSdk\Properties\Birthdate;
use GingerPluginSdk\Properties\Country;
use GingerPluginSdk\Properties\Currency;
use GingerPluginSdk\Properties\EmailAddress;
use GingerPluginSdk\Properties\Locale;
use GingerPluginSdk\Properties\Percentage;
use GingerPluginSdk\Properties\RawCost;
use GingerPluginSdk\Properties\VatPercentage;
use JTL\Plugin\Helper;
use JTL\Session\Frontend;
use Plugin\dimater_jtl5\redefiners\HelperRedefiner;

class OrderBuilder
{
    public function __construct(
        protected object $paymentMethod,
        protected object $order
    ) {
    }

    /**
     * @throws \JTL\Exceptions\ServiceNotFoundException
     * @throws \JTL\Exceptions\CircularReferenceException
     */
    public function createOrder(): Order
    {
        return new Order(
            currency: $this->getCurrency(),
            amount: $this->getAmount(),
            transactions: $this->getTransactions(),
            customer: $this->getCustomer(),
            orderLines:  $this->getOrderLines(),
            client: $this->getClient(),
            webhook_url: $this->getWebhookURL(),
            return_url: $this->getReturnURL(),
            merchantOrderId: $this->getMerchantOrderId(),
            description: $this->getDescription(),
        );
    }

    /**
     * @throws \JTL\Exceptions\ServiceNotFoundException
     * @throws \JTL\Exceptions\CircularReferenceException
     */
    public function getDescription(): string
    {
        return sprintf("Your order %s at %s", $this->getMerchantOrderId(), \Shop::getHomeURL());
    }

    public function getMerchantOrderId(): int
    {
        return $this->order->cBestellNr;
    }

    public function getReturnURL(): string
    {
        return $this->paymentMethod->getNotificationURL($this->paymentMethod->generateHash($this->order));
    }

    public function getWebhookURL(): string
    {
        return $this->paymentMethod->getNotificationURL($this->paymentMethod->generateHash($this->order));
    }

    public function getCurrency(): Currency
    {
        return new Currency(Frontend::getCurrency()->getCode());
    }

    public function getAmount(): Amount
    {
        return new Amount(new RawCost($_SESSION['Warenkorb']->gibGesamtsummeWaren(true)));
    }

    public function getTransactions(): Transactions
    {
        return new Transactions(
            new Transaction(
                paymentMethod: $this->paymentMethod->paymentName,
                paymentMethodDetails: new PaymentMethodDetails(array_filter([
                    'cutomer' => 'cutommr',
                    'issuer_id' => $this->paymentMethod->paymentName == 'ideal' ? $this->getIdealIssuers() : '',
                    'verified_terms_of_service' => $this->paymentMethod->paymentName == 'afterpay' ? $this->getAfterPayToC() : '',
                ])),
            ),
        );
    }

    public function getCustomer(): Customer
    {
        return new Customer(
            additionalAddresses: new AdditionalAddresses(
                new Address(
                    addressType: 'billing',
                    postalCode: $this->getBillingPostalCode(),
                    country: new Country($this->getBillingCountry()),
                    street: $this->getBillingStreet(),
                    city: $this->getBillingCity()
                ),
                new Address(
                    addressType: 'customer',
                    postalCode: $this->getShippingPostalCode(),
                    country: new Country($this->getShippingCountry()),
                    street: $this->getShippingStreet(),
                    city: $this->getShippingCity()
                )
            ),
            firstName: $this->getFirstName(),
            lastName: $this->getLastName(),
            emailAddress: new EmailAddress($this->getEmail()),
            gender: $this->getGender(),
            phoneNumbers: new PhoneNumbers(
                $this->getMobileNumber(),
                $this->getTelephoneNumber()
            ),
            birthdate: $this->getBirthdate() ? new Birthdate($this->getBirthdate()) : null,
            ipAddress: $this->getIPAddress(),
            locale: new Locale($this->getLocale()),
            merchantCustomerId: $this->getMerchantCustomerId()
        );
    }

    public function getIPAddress()
    {
        return $_SERVER['REMOTE_ADDR'];
    }

    public function getMerchantCustomerId()
    {
        return Frontend::getCustomer()->kKunde;
    }

    public function getOrderLines()
    {
        $orderLines = new OrderLines();
        foreach ($this->order->Positionen as $orderItem)
        {
            if (!$orderItem->Artikel) continue; //to skip shipping method item
            $orderLines->addLine(new Line(
                type: 'physical',
                merchantOrderLineId: $orderItem->kArtikel,
                name: $orderItem->Artikel->cName,
                quantity: $orderItem->nAnzahl,
                amount: new Amount(new RawCost($orderItem->Artikel->Preise->fVKBrutto)),
                vatPercentage: new VatPercentage(new Percentage($orderItem->Artikel->Preise->fUst)),
                currency: new Currency($this->order->Waehrung->code),
                url: $orderItem->Artikel->cURLFull,
            //image_url: orderItem->Artikel->cVorschaubildURL TODO Add when the field will be available in sdk
            ));
        }

        if ($this->order->oVersandart) {
            $orderLines->addLine(new Line(
                type: 'shipping_fee',
                merchantOrderLineId: $this->order->oVersandart->kVersandart,
                name: $this->order->oVersandart->cName,
                quantity: 1,
                amount: new Amount(new RawCost($this->order->oVersandart->fPreis)),
                vatPercentage: new VatPercentage(new Percentage(0)),
                currency: $this->getCurrency()
            ));
        }

        return $orderLines;
    }

    public function getFirstName(): string
    {
        return Frontend::getCustomer()->cVorname;
    }

    public function getLastName(): string
    {
        return Frontend::getCustomer()->cNachname;
    }

    public function getEmail(): string
    {
        return Frontend::getCustomer()->cMail;
    }

    public function getTelephoneNumber(): string
    {
        return Frontend::getCustomer()->cTel ?: '';
    }

    public function getMobileNumber(): string
    {
        return Frontend::getCustomer()->cMobil ?: '';
    }

    public function getIdealIssuers()
    {
        return $_SESSION['ginger_ideal']['issuer'];
    }

    public function getAfterPayToC(): bool
    {
        return isset($_SESSION['ginger_afterpay']['toc']);
    }

    public function getBirthdate(): string
    {
        return $_SESSION['ginger_afterpay']['bday'] ?? '';
    }

    public function getGender(): string
    {
        return $_SESSION['ginger_afterpay']['gender'] ?? '';
    }

    public function getLocale(): string
    {
        return Frontend::getCustomer()->cLand;
    }

    public function getBillingPostalCode(): string
    {
        return Frontend::getCustomer()->cPLZ;
    }

    public function getBillingCountry(): string
    {
        return Frontend::getCustomer()->cLand;
    }

    public function getBillingCity(): string
    {
        return Frontend::getCustomer()->cOrt;
    }

    public function getBillingStreet(): string
    {
        return implode(' ', [Frontend::getCustomer()->cStrasse, Frontend::getCustomer()->cHausnummer]);
    }

    public function getShippingStreet(): string
    {
        return implode(' ', [$_SESSION['Lieferadresse']->cStrasse, $_SESSION['Lieferadresse']->cHausnummer]);
    }

    public function getShippingCountry(): string
    {
        return $_SESSION['Lieferadresse']->cLand;
    }

    public function getShippingPostalCode(): string
    {
        return $_SESSION['Lieferadresse']->cPLZ;
    }

    public function getShippingCity(): string
    {
        return $_SESSION['Lieferadresse']->cOrt;
    }

    public function getClient(): Client
    {
        return new Client(
            userAgent: $_SERVER['HTTP_USER_AGENT'],
            platformName: 'JTLShop5',
            platformVersion: \Shop::getApplicationVersion(),
            pluginName: BankConfig::PLUGIN_NAME,
            pluginVersion: Helper::getPluginById(BankConfig::PLUGIN_ID)->getMeta()->getVersion(),
        );
    }
}
