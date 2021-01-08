# revolut-php
One file. No dependencies for security reasons.

Revolut API has next bugs:

1. scope does not work. When I ask READ I also can WRITE and send payments. It's shame.

2. No possibility to revoke token. Again it's shame. You can ask for new token and not to save it, but it is crutch.

3. No possibility to add account to counterparty. Delete and create again is bad solution due to old payments linked to old id of counterparty.
So we need add and delete accounts to counterparty.

4. It's really very bad that when I send payment via API no double authentication. I need second independed channel to confirm (sign) payments.
It can be SMS or ENUM.

Revolut API is very unsafe. 

[Revolut Business API Documentation](https://developer.revolut.com/docs/manage-accounts/#introduction-to-the-business-api).

## Installation

```bash
composer require itsoft/revolut
```

## Usage

```bash
cd your_secret_keys_dir/revolut
openssl genrsa -out privatekey.pem 1024
openssl req -new -x509 -key privatekey.pem -out publickey.cer -days 1825
more publickey.cer
```

Add publickey.cer content to [Revolut Business API settings page](https://business.revolut.com/settings/api) and point there your OAuth redirect URI.
Save ClientId and Enable API access to your account.



