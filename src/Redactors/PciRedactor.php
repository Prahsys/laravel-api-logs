<?php

namespace Prahsys\ApiLogs\Redactors;

class PciRedactor extends DotNotationRedactor
{
    public function __construct(array $additionalPaths = [], string $replacement = '[REDACTED]')
    {
        $pciPaths = [
            'card_number',
            'cardNumber',
            'card.number',
            'payment.card_number',
            'payment.cardNumber',
            'card_cvv',
            'cardCvv',
            'cvv',
            'card.cvv',
            'payment.cvv',
            'card_expiry',
            'cardExpiry',
            'card.expiry',
            'payment.card_expiry',
            'expiry_date',
            'expiryDate',
            'card_holder',
            'cardHolder',
            'card.holder',
            'payment.card_holder',
            'pan',
            'primary_account_number',
            'track_data',
            'trackData',
            'magnetic_stripe',
            'chip_data',
            'pin',
            'payment.*.card_number',
            'payment.*.cvv',
            'cards.*.number',
            'cards.*.cvv',
            '**.card.number',
            '**.card.cvv',
            '**.card.expiry',
        ];

        parent::__construct(
            array_merge($pciPaths, $additionalPaths),
            $replacement
        );
    }
}
