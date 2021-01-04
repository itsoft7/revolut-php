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
