<?php
namespace Sirius\Validation\Rule;

class EmailDomain extends AbstractValidator
{

    protected static $defaultMessageTemplate = 'This the email address does not belong to a valid domain';

    function validate($value, $valueIdentifier = null)
    {
        $this->value = $value;
        // Check if the email domain has a valid MX record
        $this->success = (bool)checkdnsrr(preg_replace('/^[^@]+@/', '', $value), 'MX');
        return $this->success;
    }
}