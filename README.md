<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework.

In addition, [Laracasts](https://laracasts.com) contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

You can also watch bite-sized lessons with real-world projects on [Laravel Learn](https://laravel.com/learn), where you will be guided through building a Laravel application from scratch while learning PHP fundamentals.

## Agentic Development

Laravel's predictable structure and conventions make it ideal for AI coding agents like Claude Code, Cursor, and GitHub Copilot. Install [Laravel Boost](https://laravel.com/docs/ai) to supercharge your AI workflow:

```bash
composer require laravel/boost --dev

php artisan boost:install
```

Boost provides your agent 15+ tools and skills that help agents build Laravel applications while following best practices.

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).

## Rw Form/Dialog Standard

Binnen deze low-code/admin omgeving geldt een vaste UI-standaard voor forms en dialogs.

### Basisopbouw

Elke nieuwe form of dialog volgt dezelfde structuur:

1. Titel linksboven
2. Optionele subtitel direct onder de titel
3. Subtitel kleiner dan de titel en in een lichtere grijstint
4. Horizontale lijn
5. Actiebar onder de lijn
6. Flashmessage-zone onder de actiebar
7. Content/body onder de flashmessage-zone

Er worden geen actieknoppen onderaan forms of dialogs geplaatst.

### Dialog hoogte en scroll (verplicht)

- Dialogs mogen nooit buiten de viewport groeien zonder scroll.
- Gebruik een maximale dialoghoogte gebaseerd op de viewport (bijvoorbeeld `max-h-[calc(100vh-1.5rem)]`).
- Houd header en actiebar zichtbaar, en laat alleen de contentzone verticaal scrollen (`overflow-y-auto`).
- Deze regel geldt als algemene standaard voor alle create/edit/confirm dialogs in admin en low-code tooling.

### Actiebar

- Links staat altijd `Terug` met icoon `mdi mdi-arrow-left-circle`
- Rechts staan de overige acties
- `Bewaren` staat rechtstreeks zichtbaar rechts
- Destructieve acties zoals `Verwijder` zitten rechts achter een drie-punten knop (`mdi mdi-dots-vertical`)

### Buttons

- Gebruik `RwActionButton` voor actieknoppen in forms/dialogs
- Gebruik geen text buttons voor deze flows
- Icon + label zijn standaard zichtbaar
- Op kleine schermen mag alleen het icoon zichtbaar blijven
- Kleurstandaard voor primaire actieknoppen:
    - `Terug`: zwart
    - `Nieuw`: blauw
    - `Bewaren`: donkergroen
    - `Verwijder`: rood
- Deze kleurrol slaat op de **tekst en iconen** van de knop, niet op een volledig ingekleurde knopachtergrond
- De knop zelf blijft visueel neutraal, met een subtiele hover/background state
- Standaard iconen voor hoofdacties:
    - `Terug`: `mdi mdi-arrow-left-circle`
        - `Nieuw`: `mdi mdi-plus-circle`
        - `Bewaren`: `mdi mdi-content-save`
        - `Verwijder`: `mdi mdi-delete`

### Flashmessages

### Flashmessages

Gebruik `RwFlashMessage` voor uniforme feedback.

Types:

- `success`
- `danger` / `error`
- `alert` / `warning`
- `info`

Gedrag:

- Elke flashmessage heeft rechts een sluit-icoon
- `success` verdwijnt automatisch na 3 seconden
- Andere types blijven zichtbaar tot de gebruiker ze sluit

### Input- en selectstijl (verplicht)

Voor forms/dialogs in admin en low-code tooling gelden deze visuele veldregels:

- Verplichte velden (`required`) krijgen een **lichtblauwe achtergrond** zodat validatievereisten direct zichtbaar zijn.
- Labels/prompts behouden hun **standaard tekstkleur** (geen aparte required-kleur op labels).
- Niet-bewerkbare velden (`disabled`) krijgen een **lichtgrijze achtergrond**.
- Voor create/edit applicatie-dialogen zijn minstens de verplichte `Naam` en `Slug` volgens deze regel opgemaakt.
- **Alle velden zijn flat**: geen schaduw op inputs, selects, autocomplete en checkboxes (`shadow-none`).

Standaardcomponenten die dit afdwingen:

- `resources/js/components/ui/input/Input.vue`
- `resources/js/components/ui/select/SelectTrigger.vue`
- `resources/js/Components/RwAutoCompleteInput.vue`
- `resources/js/Components/TextInput.vue`
- `resources/js/Components/Checkbox.vue`

### Select component standaard

- Gebruik voor selecties voortaan `RwAutoCompleteInput` als standaard component.
- Gebruik geen gewone HTML `<select>` meer in nieuwe of aangepaste admin forms/dialogs, behalve wanneer een technische beperking dit expliciet vereist.
- In de huidige admin screens is deze migratie al doorgevoerd; nieuwe schermen moeten dit patroon behouden.

### Knopconsistentie

- Actieknoppen binnen admin en builder screens gebruiken consequent dezelfde hoogte en fontgrootte.
- Gebruik voor zulke knoppen bij voorkeur `RwActionButton` zodat `Canvas instellingen`, `Form instellingen`, `Terug`, `Nieuw`, `Bewaren` en gelijkaardige acties visueel identiek blijven.
- Inline toevoegknoppen in form-secties (`+ Veld`, `+ Index`, `+ Foreign key`, ... ) volgen ook de `Nieuw` kleurrol: blauwe tekst + blauw icoon op neutrale knop.
- Vermijd kleinere one-off contentknoppen voor beheeracties wanneer er al een gelijkwaardige actieknopstijl bestaat.
- Alle knoppen moeten bij hover een zichtbare achtergrondwijziging hebben.
- Neutrale en outline-achtige knoppen gebruiken standaard een subtiele grijze hover (`hover:bg-slate-100`) en actieve toestand (`active:bg-slate-200`).
- Configuratieknoppen krijgen altijd een subtiele oranje hoverachtergrond.

### Flat card stijl (Screen Builder en DB Diagram)

Voor overzichtspagina's met statistiekcards en zoekblokken (zoals Screen Builder en DB Diagram) gebruiken we standaard een vlakke, lichtgrijze kaartstijl.

Centrale utility classes (in `resources/css/app.css`):

- `rw-flat-card-muted` => `rounded-md border border-slate-200 bg-slate-50 shadow-none`
- `rw-flat-card-clear` => `rounded-md border border-slate-200 bg-white shadow-none`
- `rw-flat-search-input` => `h-9 border-slate-300 bg-white text-sm`

Gebruik in componenten bij voorkeur deze centrale classes i.p.v. losse one-off combinaties.
Deze classes overschrijven bewust de standaard `Card` defaults (zoals `rounded-xl`, `bg-card`, `shadow`) zodat de flat-variant visueel altijd correct blijft.

Toepassing:

- Statistiekcards (`Screens`, `Bindingen`, `Inzendingen`, enz.)
- Zoek/filterkaart boven een overzicht

DB Diagram specifieke regel:

- Overzichts- en zoekcards: **flat + lichtgrijs** (`rw-flat-card-muted`)
- Database-inhoudskaarten (tabelcards, list sidebar, list content): **flat + wit/neutraal** (`rw-flat-card-clear`)

Niet toepassen op:

- Inhoudskaarten met effectieve data (bv. tabelcards met databasevelden/relaties/indexen in DB Diagram) krijgen geen lichtgrijze achtergrond
- Deze blijven wel flat (`rounded-md border border-slate-200 shadow-none`) maar met witte/neutrale achtergrond voor betere leesbaarheid

Doel:

- visuele consistentie tussen builders
- rustige, platte UI zonder zware card-schaduwen
- lichtgrijze accenten voor admin-overzichtsschermen

### Builder objectfilosofie

- In de Screen Builder krijgt elk zichtbaar configureerbaar object een logisch instellingenpunt op het canvas zelf.
- Screen-acties worden behandeld als losse knopobjecten; detailconfiguratie gebeurt per knop via een tandwiel en niet primair gegroepeerd in een algemene dialog.
- Formvelden worden per veld via een eigen tandwiel en veld-dialog bewerkt.
- Forms en tables volgen dezelfde canvasfilosofie:
    - bovenaan een preview van de screen-acties,
    - rechtsboven 1 hoofdknop voor block-instellingen,
    - inhoud daaronder als realistische preview van runtime.
- De screeninstellingen-dialog dient voor algemene screeninstellingen en overzicht, niet als primaire plek om individuele knopdetails te beheren.

### Flashpositie

- Flashmessages op paginaforms en builder-schermen verschijnen altijd onder de actiebar/knoppenrij en boven de inhoud.
- Plaats succes-, warning- en foutmeldingen dus niet midden in de content als het om feedback op `Bewaren`, `Publiceren` of vergelijkbare hoofdacties gaat.
- Gebruik hiervoor de `flash` zone van `RwFormTemplate` of een gelijkwaardig vast patroon.

### Configuratiekleur

- Oranje is binnen het platform de vaste kleur voor configuratie-acties en instellingen.
- Configuratieknoppen gebruiken waar mogelijk een tandwiel-icoon.
- Voorbeelden: `Canvas instellingen`, `Form instellingen`, `Table instellingen`, tandwielen op screen-acties en tandwielen op formvelden.
- Gebruik deze kleur semantisch voor instellingen/configuratie, niet voor primaire bewaar- of navigatieacties.
- De knop om in een action bar een nieuwe actie toe te voegen gebruikt ook deze configuratiekleur.
- In preview action bars staat deze toevoegknop altijd uiterst rechts en toont ze alleen een plus-icoon.

### Template keuze

Gebruik onderstaande componenten als standaard:

- `RwFormTemplate`
    - voor paginaforms
    - voor create/edit pagina's
    - voor builder pagina's met vaste header + actiebar

- `RwDialogTemplate`
    - voor dialogs
    - voor create/edit dialogs
    - voor delete confirm dialogs
    - voor kleine beheerflows vanuit dropdowns/topbar/contextmenu

- `RwFlashMessage`
    - voor lokale save/delete feedback
    - voor paginaflashmessages

- `RwActionButton`
    - voor `Terug`, `Bewaren` en andere actiebaracties

### Create/Edit/Delete patroon

- Toevoegen en wijzigen gebruiken dezelfde controller/formcomponent waar mogelijk
- Toevoegen mag via `id = 0` of gelijkaardige consistente flow
- Verwijderen gebeurt nooit direct, maar altijd via een confirm dialog volgens dezelfde layout
- De menuoptie `Verwijder` in een drie-punten dropdown gebruikt rode tekst en een rood `mdi mdi-delete` icoon

### Toepassing

Deze standaard geldt voor:

- application dialogs
- menu builder dialogs
- screen builder dialogs
- toekomstige form/query/menu builders
- create/edit/delete flows in admin en low-code tooling

## Backend Routing Standard

Voor de backendstructuur van de low-code/admin omgeving geldt onderstaande standaard.

### Basisroutes

- `/admin`
    - slimme redirect op basis van de actieve werkmode
- `/admin/dev`
    - ontwikkelaarsomgeving
    - toont dashboard en builders
- `/admin/run`
    - runtime/gebruiker omgeving
    - toont de actieve applicatie of een waarschuwing indien nog geen basisroute gedefinieerd is

### Dev routes

Alle builders en beheerflows horen onder `/admin/dev/...`.

Voorbeelden:

- `/admin/dev`
- `/admin/dev/menu-builder`
- `/admin/dev/screen-builder`
- `/admin/dev/applications/...`

### Run routes

Alle runtime/gebruiker functies horen onder `/admin/run/...`.

Voorbeelden:

- `/admin/run`
- `/admin/run/screens/page/{slug}`

### Controller namespaces

Nieuwe controllers worden opgesplitst volgens werkcontext:

- `App\Http\Controllers\Admin\Dev\...`
    - voor builders, dashboards en beheerflows in ontwikkelaarsmodus
- `App\Http\Controllers\Admin\Run\...`
    - voor runtime/gebruiker functionaliteit

### Per onderdeel eigen map

Elke builder of functioneel onderdeel krijgt bij voorkeur een eigen map.

Voorbeelden:

- `App\Http\Controllers\Admin\Dev\MenuBuilder\MenuBuilderController`
- `App\Http\Controllers\Admin\Dev\Application\ApplicationController`
- `App\Http\Controllers\Admin\Run\DashboardController`

### Naamgeving en URL-richtlijn

- Gebruik `dev` en `run` expliciet in zichtbare admin URL's
- Gebruik geen `/lowcode/...` in publieke adminpaden
- Splits builderlogica en runtimelogica ook op controllerniveau

### Toekomstige middleware

Deze structuur moet compatibel blijven met latere aparte middleware voor:

- dev access
- run access

Nieuwe backendontwikkeling moet daarom de scheiding tussen `/admin/dev` en `/admin/run` consequent respecteren.

### Kan je boven iedere functie of method dat je schrijft in php of javascript in commentaar een korte engelstalige toelichting schrijven over wat het doel is van de functie en wat de eventuel input variabelen betekenen. de commentar moet steeds beginnen met RW:

## Apps Architecture Standard

Deze applicatie gebruikt een modulaire monolith-structuur met een centrale `Apps` map voor app-specifieke code.

### Kernprincipes

- App-specifieke code staat onder `app/Apps/{PrefixStudly}/...`
- Gedeelde/core code blijft in de standaard Laravel mappen (`app/Models`, `app/Actions`, `app/Http/Controllers`, ...)
- De bestaande `Admin/Dev` en `Admin/Run` routing/controller scheiding blijft leidend
- Geen backward compatibility voor oude generated paden in deze opbouwfase

### Directory structuur

- `app/Apps/{PrefixStudly}/Models/{Model}.php`
- `app/Apps/{PrefixStudly}/Models/Generated/{Model}Generated.php`
- `app/Apps/{PrefixStudly}/Actions/...`
- `app/Apps/{PrefixStudly}/Policies/...`
- `database/migrations/apps/{prefix}/*.php`

Voorbeeld (prefix `rwt`):

- `app/Apps/Rwt/Models/Blog.php`
- `app/Apps/Rwt/Models/Generated/BlogGenerated.php`
- `database/migrations/apps/rwt/2026_..._create_rwt_blogs_table.php`

### Naming regels

- Prefix map in code: `PrefixStudly` (bv. `rwt` -> `Rwt`)
- Prefix map voor migraties: lowercase (bv. `rwt`)
- Model class naam bevat geen prefix (bv. `Blog`, niet `RwtBlog`)
- Tabellen blijven prefix-gebaseerd (`rwt_blogs`)

### Namespaces

- Models: `App\\Apps\\{PrefixStudly}\\Models\\...`
- Generated traits: `App\\Apps\\{PrefixStudly}\\Models\\Generated\\...`

### Migrations

- Builder-generated migraties staan onder `database/migrations/apps/{prefix}`
- `php artisan migrate` moet alle submappen onder `database/migrations/apps/*` automatisch meenemen
- `runSingleMigration` blijft toegestaan via `--path` voor 1 specifieke migration

### Database wijzigingen: migration-first (verplicht)

Alle databasewijzigingen moeten via migraties gebeuren. Rechtstreekse schema-aanpassingen buiten migraties zijn niet toegelaten.

#### Niet toegelaten

- `ALTER TABLE`, `DROP COLUMN`, `ADD COLUMN`, `CREATE INDEX`, `DROP INDEX`, `DROP TABLE`, ... rechtstreeks uitvoeren vanuit controllers, jobs of services
- runtime schema writes via `Schema::table(...)` buiten een migration-bestand
- handmatige SQL patches op productie zonder bijhorende migration in de codebase

#### Wel toegelaten

- schema-aanpassingen in migration-bestanden onder `database/migrations/apps/{prefix}`
- voor gedeelde tabellen: migration in een gedeelde map (bij voorkeur `database/migrations/apps/shared`)
- uitvoeren van exact 1 migration via `php artisan migrate --path=...` (single-change flow)

#### Standaard werkwijze

1. Bepaal eerst scope en prefix (`{prefix}` of `shared`).
2. Genereer een migration in de juiste map.
3. Plaats de schemawijziging in `up()` en een veilige rollback in `down()`.
4. Laat destructieve wijzigingen (drop kolommen/indexen/foreign keys) altijd expliciet en traceerbaar in migration-code staan.
5. Voer lokaal uit via `php artisan migrate --path=...` of volledige `php artisan migrate`.
6. Verifieer resultaat en commit migration samen met bijhorende codewijzigingen.

#### Command voorbeelden

Nieuwe migration in app-prefix map:

```bash
php artisan make:migration add_status_to_crm_orders_table --path=database/migrations/apps/crm
```

Specifieke migration uitvoeren:

```bash
php artisan migrate --path=database/migrations/apps/crm/2026_03_28_120000_add_status_to_crm_orders_table.php --force
```

#### Richtlijnen voor destructieve wijzigingen

- Drop van kolommen of tabellen alleen via migration-bestanden
- Eerst afhankelijke foreign keys/indexes expliciet verwijderen in dezelfde migration
- `down()` moet herstelbaar blijven waar technisch haalbaar
- Bij risico op dataverlies: vooraf backup en expliciete review

#### Table Builder policy

- Table Builder schrijft schemawijzigingen als migration-bestand
- "Run migration" mag uitvoeren, maar de bron van waarheid blijft de migration in de repository
- Veldverwijdering op bestaande tabellen verloopt via migration-first flow (geen directe schema write)

#### Review checklist (verplicht)

- Staat de migration in de juiste prefix-map?
- Is de wijziging beperkt tot 1 duidelijke verantwoordelijkheid?
- Zijn foreign keys/indexes correct mee aangepast?
- Is `down()` correct en veilig?
- Is de wijziging getest op de beoogde flow?

### Audit Logger Standard (verplicht)

Alle belangrijke mutaties, beheeracties en security-relevante events moeten centraal gelogd worden via de `AuditLogger`.

#### Centrale componenten

- Service: `App\Support\Audit\AuditLogger`
- Model: `App\Models\AuditLog`
- Tabel: `audit_logs`
- Middleware context: `App\Http\Middleware\AttachAuditContext`
- File channel: `audit` in `config/logging.php` (bestand: `storage/logs/audit.log`)

#### Verplichte velden per audit event

- `module` (bv. `db_diagram`, `query_builder`, `screen_builder`, `lowcode`)
- `action` (dot-notatie, bv. `db.table.create`, `query.update`, `application.delete.denied`)
- `subject_type` en `subject_key`
- `success` en gepaste `severity` (`info`, `warning`, `error`)
- `meta` met relevante context (zonder secrets)

#### Execution mode (dev/run)

- Kolom: `audit_logs.execution_mode`
- Bron: session `lowcode_mode` via `AttachAuditContext`
- Waarden: `dev`, `run`
- Als geen mode-context beschikbaar is: `NULL` (bewust gekozen)

#### Gebruikspatroon

Gebruik enkel de centrale methods:

- `$auditLogger->success(...)`
- `$auditLogger->failure(...)`
- `$auditLogger->denied(...)`

Gebruik **geen** losse `AuditLog::create(...)` calls in controllers/services.

#### Security & privacy regels

- Nooit wachtwoorden/tokens/secrets loggen
- Gevoelige keys worden centraal geredact in `AuditLogger`
- Audit logging mag business flows niet blokkeren (logging-fouten mogen geen request doen falen)

#### Rollout plan: alle bestaande acties aansluiten

Fase 1 (afgerond):

- Centrale audit infrastructuur (model, migraties, middleware context, file channel)
- Eerste integraties in o.a.:
    - `ApplicationController`
    - `RwDbSqlController`
    - `RwDbTableBuilderController`
    - `RwDbTableViewController`
    - `RwDbDiagramController` (shared access updates)
    - `QueryController` (create/update)

Fase 2 (volgende stap):

- `ScreenController`: create/update/delete/publish/submission acties
- `MenuBuilderController`: draft create/update/delete/reorder/publish
- Overige query-flows: run/export/report legacy endpoints

Fase 3 (stabilisatie):

- Volledige audit-actiecatalogus per module finaliseren
- `DatabaseEditorLog` gebruik afbouwen (compatibel houden tijdens transitie)
- Audit-overzicht in admin met filtering op user/app/module/action/execution_mode

#### Implementatiecheck bij nieuwe features

- Is er een `success` log bij geslaagde mutatie?
- Is er een `failure`/`denied` log bij blokkades of fouten?
- Is `module` + `action` volgens naming standaard?
- Is `execution_mode` automatisch aanwezig via context (of `NULL` indien niet beschikbaar)?
- Bevat `meta` geen gevoelige data?

### Dev/Run compatibiliteit

- `App\\Http\\Controllers\\Admin\\Dev\\...` blijft voor builder/beheer
- `App\\Http\\Controllers\\Admin\\Run\\...` blijft voor runtime
- Dev/Run controllers mogen app-specifieke services/models uit `app/Apps/{Prefix}/...` aanroepen

### Table Builder output contract

Nieuwe table-builder output moet altijd:

- migration schrijven naar `database/migrations/apps/{prefix}/...`
- model schrijven naar `app/Apps/{PrefixStudly}/Models/...`
- generated trait schrijven naar `app/Apps/{PrefixStudly}/Models/Generated/...`

### Relaties in generated code

Bij FK-relaties:

- Als referenced table dezelfde prefix heeft: resolve naar `App\\Apps\\{PrefixStudly}\\Models\\...`
- Anders: resolve naar gedeeld model in `App\\Models\\...` indien beschikbaar

### Doel

Deze standaard zorgt voor:

- duidelijke scheiding per app
- betere transporteerbaarheid
- consistente schaalbaarheid zonder Laravel-conventies te breken

## Validatie Standaard (Client + Server)

Deze sectie beschrijft de actuele afspraken voor validatie in forms, zowel in builder/dev flows als runtime/run flows.

### Doel

- 1 centrale validatieaanpak voor alle formvelden
- client feedback zo vroeg mogelijk (blur/change)
- submit blokkeren bij client errors
- server blijft bron van waarheid voor serverregels
- duidelijke vertaalbare foutmeldingen per veld en in samenvatting

### Bronnen van validatie

Voor een veld kunnen regels uit meerdere bronnen komen:

- `validationRules`
    - primair veldniveau rule-string
    - mag server- en clientregels bevatten
- `clientValidationRules`
    - extra client-only regels
- fallback `required`
    - als er geen regels zijn maar veld verplicht is

Legacy aliases worden nog gelezen en genormaliseerd:

- `validation_rules`
- `modelRule` / `model_rule`
- `client_validation_rules`

Na save worden deze legacy keys omgezet naar:

- `validationRules`
- `clientValidationRules`

### Client-side validatie

Client validatie draait in beide formtypes:

- Screen Runtime forms (`resources/js/Pages/Admin/ScreenRuntime/Show.vue`)
- DB Diagram form (`resources/js/Pages/Admin/RwDbDiagram/RwDbTableForm.vue`)

Gedrag:

- valideren op `change` en `blur`
- fouttekst onder het veld
- visuele error-state op veld
- bij submit:
    - alle velden opnieuw valideren
    - submit stoppen als er fouten zijn
    - globale warning met samenvatting van veldfouten

### Server-side validatie

Server validatie blijft actief op submit.

- expliciete veldregels (`validationRules`) worden server-side toegepast
- select/radio/autocomplete whitelisting (`in(...)`) blijft afdwingen op server
- voor `autocomplete` met `multiple=true` blijft `array` op hoofdveld verplicht

Belangrijk:

- client custom rule tokens (`custom:*` of `x:*`) worden server-side automatisch gestript
- daardoor kan je client custom regels gebruiken zonder server validator errors

### Custom client regels (globaal)

Projectbrede custom rules staan in:

- `resources/js/validation/extended_rules.js`

Deze file wordt gecombineerd met de package rules via:

- `resources/js/validation/validate_with_extended_rules.js`

Gebruik in rule strings:

- `custom:rule_key`
- `custom:rule_key,param1,param2`
- alias: `x:rule_key`

Voorbeelden:

- `required|string|max:255|custom:iban_be`
- `nullable|custom:min_words,3`
- `custom:rrn_be`

Actuele voorbeeldregels in `extended_rules.js`:

- `iban_be`
- `rrn_be`
- `phone_be`
- `postcode_be`
- `enterprise_be`
- `min_words`

### Client Rules IDE met versiebeheer

Er is een aparte dev editor met versies:

- route: `admin.client-rules.index`
- pagina: `resources/js/Pages/Admin/Validation/ClientRulesEditor.vue`

Flow:

- `Opslaan`
    - maakt een nieuwe draft versie
    - voert syntax-check en (optioneel) build uit
- `Publiceer`
    - zet een bewaarde versie live
    - vorige published versie wordt terug draft

Knopafspraak:

- `Publiceer` mag enkel actief zijn als de geselecteerde versie eerst bewaard is
- bij onbewaarde editorwijzigingen blijft `Publiceer` disabled

Buildgedrag wordt gestuurd door:

- `config/client_validation_rules.php`
    - `run_build_on_save`
    - `run_build_on_publish`
    - `run_syntax_check`

### Vertalingen

Validatieboodschappen lopen via rwtable vertalingen:

- `lang/vendor/rwtable/nl/rwtable.php`
- `lang/vendor/rwtable/en/rwtable.php`
- `lang/vendor/rwtable/fr/rwtable.php`
- `lang/vendor/rwtable/de/rwtable.php`

Nieuwe custom rule messages horen onder:

- `vue.validation.custom.<rule_key>`

Generieke custom runtime keys:

- `validation.custom_failed`
- `validation.custom_unknown_rule`
- `validation.custom_runtime_error`

### Wat kan wel en niet (huidige fase)

Wel:

- sterke client UX met vroege feedback en submit-block
- globale custom client rules met versiebeheer
- per veld mix van standaardregels en `custom:*` tokens

Nog niet (bewust, huidige fase):

- automatische server-equivalent voor elke custom client rule

Gevolg:

- custom client rules zijn UX- en flow-validatie in de frontend
- server-side hard enforcement voor custom rules volgt in latere fase

### Migratie en onderhoud

Voor bestaande schema's met oude rule keys:

```bash
php artisan screen-builder:normalize-schema-validation-rules --dry-run
php artisan screen-builder:normalize-schema-validation-rules
```

Gebruik eerst `--dry-run` om impact te controleren.

## Richtlijn Vertalingen (Prompt + UI)

Deze afspraken zijn verplicht voor nieuwe prompts, labels, placeholders en helpteksten.

### Kernprincipes

- Gebruik altijd **key + fallback tekst**.
- Fallback tekst blijft in DB/schema voor backward compatibility.
- Vertaalbestanden zijn de bron voor meertaligheid.
- Bronlocale voor nieuwe keys is `nl`.
- Nieuwe schermen en nieuwe UI-teksten moeten bij oplevering meteen in **alle bestaande talen** staan (`config/app.php -> available_locales`).
- Een scherm is pas af wanneer de gebruikte keys minstens in `nl`, `en`, `fr` en `de` zijn toegevoegd (of in alle actieve locales).

### Dynamic prompt vertalingen

Bestanden:

- `lang/nl/dynamic_prompts.php`
- `lang/en/dynamic_prompts.php`
- `lang/fr/dynamic_prompts.php`
- `lang/de/dynamic_prompts.php`

Inertia shared props leveren deze automatisch via `app.translations.dynamic_prompts`.

Frontend helper:

- `resources/js/composables/useDynamicTranslations.js`

### Formvelden (Screen Builder)

Ondersteunde sleutelvelden per veld:

- `labelKey` (`label_key` legacy input)
- `placeholderKey` (`placeholder_key` legacy input)
- `helpKey` (`help_key` legacy input)

Resolutie in runtime:

1. Probeer key in `dynamic_prompts`
2. Val terug op veldtekst (`label`, `placeholder`, `help`)

### Query bindings

Ondersteunde sleutelvelden per binding row:

- `title_key`
- `prompt_key`

Ook hier geldt key eerst, fallback tekst tweede.

### Naming conventie keys

- Screens: `screens.{screen_key}.blocks.{block_id}.fields.{field_key}.{type}`
- Query bindings: `queries.{query_key}.bindings.{parameter}.{type}`

Waar `{type}` in praktijk is: `label`, `placeholder`, `help`, `title`, `prompt`.

### Commands voor onderhoud

Backfill keys uit bestaande DB data:

```bash
php artisan translations:dynamic-prompts-backfill --dry-run
php artisan translations:dynamic-prompts-backfill
```

Sync ontbrekende keys naar doeltalen:

```bash
php artisan translations:dynamic-prompts-sync --source=nl
php artisan translations:dynamic-prompts-sync --source=nl --targets=en fr de
```

Nieuwe taal toevoegen:

```bash
php artisan translations:add-locale es
```

### Do's en don'ts

Do:

- voeg nieuwe key eerst toe in `nl`
- sync daarna naar andere talen
- werk nieuwe schermen direct meertalig uit en voeg de vertalingen in dezelfde wijziging toe
- hou fallback tekst zinvol en functioneel

Don't:

- geen nieuwe hardcoded runtime promptteksten zonder key
- geen handmatige key-namen met spaties of speciale tekens
