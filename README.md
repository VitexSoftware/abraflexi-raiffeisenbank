# flexibee-tools

Tento program umožňuje importovat bankovní pohyby z __Raiffeisenbank__ do účetnictví __FlexiBee__ (a případně dalších).

Proč to? Raiffeisenbank je "Banka inspirovaná klienty", jak o sobě hrdě tvrdí a každý rok to úporně dokládá razítkem "Nejlepší banka roku", což nás klienty naprosto neuvěřitelně těší - je to skutečně úžasný, až nepopsatelný, pocit být klientem zcela nejlepší banky.

Bohužel se stalo, že nějaký klient inspiroval banku k tomu, že bankovní výpisy lze i v roce 2020 stáhnout pouze ve formátu PDF, což je sice krásné, ale strojově bohužel nezpracovatelné. Pro těch pár lidí, kteří své účetnictví zpracovávají pomocí počítačů je zde pak placená funkce (200&nbsp;Kč rok + 200&nbsp;Kč za každý měsíc, kdy je funkce použita = 2&nbsp;600&nbsp;Kč&nbsp;/&nbsp;rok - informace k 7.3.2020), která odemkne možnost stahovat výpisy i ve formátech __XML, ABO či Gemini__ - což jsou formáty, které se dají načíst do různých účetních programů, jako je třeba i FlexiBee.

Pro větší firmy to zajisté není problém, nicméně pokud vám přijde divné, jako mě, platit 2&nbsp;600&nbsp;Kč ročně za čudlík "Download", tak můžete použít tento program. Jak na to?

* Zalogujte se do Raiffeisenbank a stáhněte si ne bankovní výpis (ten lze pouze v PDF) ale __historii pohybů__ a to ve formátu __CSV__, samozřejmě za požadované časové období.
* Stažený soubor přejmenujte na __import.csv__ a přesuňte do složky s tímto programem
* V příkazové řádce spusťte tento program, typicky to bude:
  * Mac: __./rb2fb.php__
  * Win: __php b2fb.php__ (je potřeba mít nainstalováno PHP)
* Pokud vše proběhne úspěšně, vytvoří se soubor __output.xml__
* Nyní si ve FlexiBee nastavíte pro daný bankovní účet elektronické bankovnictví:
  * Peníze / Seznam bankovních účtů / (vybrat požadovaný účet) + rozkliknout
  * Elektronická banka
    * Formát: Gemini XML
    * Název klienta: RB2FB (třeba :)
    * Název protistrany v textu příkazu: zaškrtnout
    * Popis dokladu v textu příkazu: zaškrtnout
    * Výpisy: vybrat složku, kam budete dávat výpisy, přípona: xml 
    * Příkazy: nechat prázdné, přípona: nechat prázdné
    * Kontrolovat duplicity výpisů: Ignorovat již načtené položky
    * Uložit a zavřít
  * Přesunout soubor __output.xml__ vygenerovaný aplikací do složky výpisů, kterou jsme si nastavili ve FlexiBee
  * Přejít do modulu __Peníze / Banka__
  * Služby / __Načíst výpisy__
  * Služby / __Automatická tvorba spojení úhrady__, párovat dle vs a částky
  * Návod na párování plateb zde: https://www.flexibee.eu/podpora/akademie/automaticke-parovani-rucni-parovani-sparovani-jedne-platby-vice-fakturam/
  * A mělo by to být :)

Hodně štěstí  
Maxim Krušina  
maxim@mfcc.cz
