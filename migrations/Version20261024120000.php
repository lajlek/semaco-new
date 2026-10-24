<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261024120000 extends AbstractMigration
{
    private const CONTENT = <<<'HTML'
        <p>Společnost SEMACO tools and software, s.r.o., IČO 64619338, se sídlem B. Němcové 787, 790 01 Jeseník, provozuje e-shop eshop.semaco.cz a chce, aby ho mohli pohodlně používat všichni zákazníci včetně lidí se zdravotním postižením. Tyto informace zveřejňujeme podle zákona č. 424/2023 Sb., o požadavcích na přístupnost výrobků a služeb.</p>
        <h3>Popis služby</h3>
        <p>V e-shopu můžete vyhledávat a prohlížet nástroje a příslušenství, porovnávat jejich technické parametry, vložit zboží do košíku, objednat ho a zaplatit, sledovat objednávky v zákaznickém účtu, poslat dotaz nebo poptávku kontaktním formulářem a online odstoupit od smlouvy.</p>
        <h3>Jak e-shop splňuje požadavky na přístupnost</h3>
        <ul>
            <li>Při vývoji vycházíme z doporučení WCAG 2.1 na úrovni AA (norma EN 301 549). Stránky kontrolujeme automatickými nástroji i ručně.</li>
            <li>Web lze ovládat klávesnicí. Na začátku každé stránky je odkaz „Přeskočit na obsah“ a prvek, na kterém je právě ovládání, je zřetelně zvýrazněný.</li>
            <li>Stránky mají určený jazyk, nadpisy a hlavní oblast obsahu, takže je lze procházet čtečkou obrazovky.</li>
            <li>Formulářová pole mají popisky. Chyby ve formuláři se zobrazují textem u příslušného pole, ne jen barvou.</li>
            <li>Obrázky produktů mají textový popis a ovládací prvky zobrazené jen ikonou mají textový název.</li>
            <li>Kontrast textu vůči pozadí je nejméně 4,5 : 1.</li>
            <li>Obsah lze zvětšit až na 200 % a web se přizpůsobí šířce obrazovky počítače, tabletu i mobilu.</li>
        </ul>
        <h3>Známá omezení</h3>
        <ul>
            <li>Technické výkresy a katalogové listy výrobců ve formátu PDF nemusí být plně přístupné. Na požádání vám informace z nich pošleme v jiné podobě.</li>
            <li>Fotografie výrobků nemají podrobný popis nad rámec názvu produktu; technické údaje najdete v parametrech produktu.</li>
        </ul>
        <h3>Zpětná vazba a kontakt</h3>
        <p>Pokud narazíte na problém s přístupností nebo potřebujete informace v jiné podobě, napište nám na <a href="mailto:info@semaco.cz">info@semaco.cz</a> nebo zavolejte na +420 737 283 750. Ozveme se vám bez zbytečného odkladu.</p>
        <h3>Dozor</h3>
        <p>Dozor nad dodržováním požadavků na přístupnost vykonává Česká obchodní inspekce (<a href="https://www.coi.cz">www.coi.cz</a>), u které můžete podat podnět.</p>
        <p>Informace byly připraveny 24. 10. 2026.</p>
        HTML;

    public function getDescription(): string
    {
        return 'Accessibility information page';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            'INSERT INTO app_cms_page (code, slug, title, meta_title, meta_description, content, position, enabled, updated_at)
             SELECT :code, :slug, :title, NULL, :description, :content, 3, 1, NOW() FROM DUAL
             WHERE NOT EXISTS (SELECT 1 FROM app_cms_page WHERE code = :code OR slug = :slug)',
            [
                'code' => 'accessibility',
                'slug' => 'informace-o-pristupnosti',
                'title' => 'Informace o přístupnosti',
                'description' => 'Jak e-shop SEMACO splňuje požadavky na přístupnost a kde nahlásit problém.',
                'content' => self::CONTENT,
            ],
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DELETE FROM app_cms_page WHERE code = 'accessibility'");
    }
}
