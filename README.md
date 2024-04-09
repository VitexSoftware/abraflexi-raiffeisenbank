
![Logo](abraflexi-raiffeisenbank.svg?raw=true)

Debian/Ubuntu installation
--------------------------

Please use the .deb packages. The repository is availble:

 ```shell
    echo "deb http://repo.vitexsoftware.com $(lsb_release -sc) main" | sudo tee /etc/apt/sources.list.d/vitexsoftware.list
    sudo wget -O /etc/apt/trusted.gpg.d/vitexsoftware.gpg http://repo.vitexsoftware.com/keyring.gpg
    sudo apt update
    sudo apt install abraflexi-raiffeisenbank
```

Po instalaci balíku jsou v systému k dispozici tyto nové příkazy:

  * **abraflexi-raiffeisenbank-setup**        - check and/or prepare Bank account setup in AbraFlexi
  * **abraflexi-raiffeisenbank-transactions** - Import transactions. From latest imported or within the given scope
  * **abraflexi-raiffeisenbank-statements**   - Import transactions from Account Statements.


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

  * today 
  * yesterday
  * current_month
  * last_month
  * last_two_months
  * previous_month
  * two_months_ago
  * this_year
  * January
  * February
  * March
  * April
  * May
  * June
  * July
  * August
  * September
  * October
  * November
  * December
  * auto


Powered by: https://github.com/VitexSoftware/php-vitexsoftware-rbczpremiumapi

MultiFlexi
----------

AbraFlexi RaiffeisenBank is ready for run as [MultiFlexi](https://multiflexi.eu) application.
See the full list of ready-to-run applications within the MultiFlexi platform on the [application list page](https://www.multiflexi.eu/apps.php).

[![MultiFlexi App](https://github.com/VitexSoftware/MultiFlexi/blob/main/doc/multiflexi-app.svg)](https://www.multiflexi.eu/apps.php)
