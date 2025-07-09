<?php

namespace Prahsys\ApiLogs\Redactors;

class PiiRedactor extends DotNotationRedactor
{
    public function __construct(array $additionalPaths = [], string $replacement = '[REDACTED]')
    {
        $piiPaths = [
            'ssn',
            'social_security_number',
            'socialSecurityNumber',
            'ein',
            'tax_id',
            'taxId',
            'driver_license',
            'driverLicense',
            'passport',
            'passport_number',
            'date_of_birth',
            'dateOfBirth',
            'dob',
            'birth_date',
            'birthDate',
            'phone',
            'phone_number',
            'phoneNumber',
            'mobile',
            'mobile_number',
            'email',
            'email_address',
            'address',
            'street_address',
            'home_address',
            'billing_address',
            'shipping_address',
            'address.street',
            'address.city',
            'address.state',
            'address.zip',
            'address.postal_code',
            'personal.ssn',
            'personal.phone',
            'personal.email',
            'contact.phone',
            'contact.email',
            'user.email',
            'user.phone',
            'customer.email',
            'customer.phone',
            'users.*.email',
            'users.*.phone',
            'customers.*.email',
            'customers.*.phone',
        ];

        parent::__construct(
            array_merge($piiPaths, $additionalPaths),
            $replacement
        );
    }
}
