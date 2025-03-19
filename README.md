
![Logo](abraflexi-raiffeisenbank.svg?raw=true)

The main purpose is to import bank transactions into the Bank agenda using two methods.

These two methods, i.e. Transactions and Statements, can be run through each other, and duplicates are resolved through the received number.

However, the two methods differ in several ways. Transactions are instant, making it possible to use them throughout the day and have a completed payment recorded in AbraFlex within minutes.

(However, transactions do not include, for example, direct debits, and are only available for 89 days.)

On the other hand, bank statements are processed over a period of time and contain all events on the account.

(The recommended setting is for MAINLINE series statements to be generated monthly and ADDITIONAL series statements to be generated on a daily basis)

Therefore, when it comes to time, it is possible to collect movements through transactions and then complete them with statements the next day. If time is not an issue, only listings can be sucked.

A third add-on tool saves PDF bank statements to events in AbraFlexi.

Configuration
-------------

Environment or .env file contents:

```
ABRAFLEXI_URL=https://demo.flexibee.eu:5434
ABRAFLEXI_LOGIN=winstrom
ABRAFLEXI_PASSWORD=winstrom
ABRAFLEXI_COMPANY=demo_de

CERT_FILE=test_cert.p12   # grab yours on https://developers.rb.cz/
CERT_PASS=test12345678      
XIBMCLIENTID=FbboLD2r1WHDRcuKS4wWUbSRHxlDloWX
ACCOUNT_NUMBER=12324567
JOB_ID=xxx
STATEMENT_LINE=MAIN|ADDITIONAL
ABRAFLEXI_EVENT=Statement
```


Advanced configuration

```env
IMPORT_SCOPE=last_month
EASE_EMAILTO=info@vitexsoftware.cz
LANG=cs_CZ
EASE_LOGGER=syslog|console # For standalone use
API_DEBUG=True
TYP_DOKLADU=STANDARD
```

Import Scopes
-------------

  * `today` 
  * `yesterday`
  * `current_month`
  * `last_month`
  * `last_two_months`
  * `previous_month`
  * `two_months_ago`
  * `this_year`
  * `January`
  * `February`
  * `March`
  * `April`
  * `May`
  * `June`
  * `July`
  * `August`
  * `September`
  * `October`
  * `November`
  * `December`
  * `auto`
  * `2024-08-05>2024-08-11` - custom scope 
  * `2024-10-11` - only specific day



After installing the package, the following new commands are available in the system:

  * **abraflexi-raiffeisenbank-setup**        - check and/or prepare Bank account setup in AbraFlexi
  * **abraflexi-raiffeisenbank-transactions** - Import transactions. From latest imported or within the given scope
  * **abraflexi-raiffeisenbank-statements**   - Import transactions from Account Statements.
  * **abraflexi-raiffeisenbank-events**       - Import pdf statement to Events in AbraFlexi.



Powered by: https://github.com/VitexSoftware/php-vitexsoftware-rbczpremiumapi

MultiFlexi
----------

AbraFlexi RaiffeisenBank is ready for run as [MultiFlexi](https://multiflexi.eu) application.

<img src="abraflexi-raiffeisenbank-statements.svg?raw=true" width="100" height="100"><img src="abraflexi-raiffeisenbank-transactions.svg?raw=true" width="100" height="100"><img src="abraflexi-raiffeisenbank-events.svg?raw=true" width="100" height="100">

See the full list of ready-to-run applications within the MultiFlexi platform on the [application list page](https://www.multiflexi.eu/apps.php).

[![MultiFlexi App](https://github.com/VitexSoftware/MultiFlexi/blob/main/doc/multiflexi-app.svg)](https://www.multiflexi.eu/apps.php)

Debian/Ubuntu installation
--------------------------

Please use the .deb packages. The repository is availble:

 ```shell
    echo "deb http://repo.vitexsoftware.com $(lsb_release -sc) main" | sudo tee /etc/apt/sources.list.d/vitexsoftware.list
    sudo wget -O /etc/apt/trusted.gpg.d/vitexsoftware.gpg http://repo.vitexsoftware.com/keyring.gpg
    sudo apt update
    sudo apt install abraflexi-raiffeisenbank
```
