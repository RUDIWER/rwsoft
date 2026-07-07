# Laravel validatieregels

Gebruik deze regels in het veld **Model rule**, bijvoorbeeld:

`required|string|max:255`

## Booleans

- `accepted` - Waarde moet geaccepteerd zijn (yes/on/1/true).
- `accepted_if:veld,waarde` - Moet geaccepteerd zijn als een ander veld overeenkomt.
- `boolean` - Waarde moet boolean-achtig zijn (true/false/1/0).
- `declined` - Waarde moet geweigerd zijn (no/off/0/false).
- `declined_if:veld,waarde` - Moet geweigerd zijn als een ander veld overeenkomt.

## Strings

- `active_url` - Moet een URL zijn met geldig DNS record.
- `alpha` - Enkel letters.
- `alpha_dash` - Letters, cijfers, streepjes en underscores.
- `alpha_num` - Enkel letters en cijfers.
- `ascii` - Enkel ASCII tekens.
- `confirmed` - Moet overeenkomen met `veld_confirmation`.
- `current_password` - Moet overeenkomen met huidig wachtwoord van gebruiker.
- `different:veld` - Moet verschillen van ander veld.
- `doesnt_start_with:a,b` - Mag niet starten met opgegeven waarden.
- `doesnt_end_with:a,b` - Mag niet eindigen met opgegeven waarden.
- `email` - Moet een geldig e-mailadres zijn.
- `ends_with:a,b` - Moet eindigen met een opgegeven waarde.
- `enum:EnumClass` - Moet een geldige enumwaarde zijn.
- `hex_color` - Moet een geldige hex-kleur zijn.
- `in:a,b,c` - Moet een van de opgegeven waarden zijn.
- `ip` - Moet een geldig IP-adres zijn.
- `ipv4` - Moet een geldig IPv4-adres zijn.
- `ipv6` - Moet een geldig IPv6-adres zijn.
- `json` - Moet geldige JSON zijn.
- `lowercase` - Moet volledig in kleine letters zijn.
- `mac_address` - Moet een geldig MAC-adres zijn.
- `max:n` - Maximale lengte is `n`.
- `min:n` - Minimale lengte is `n`.
- `not_in:a,b,c` - Mag geen van de opgegeven waarden zijn.
- `regex:/.../` - Moet overeenkomen met regex.
- `not_regex:/.../` - Mag niet overeenkomen met regex.
- `same:veld` - Moet gelijk zijn aan ander veld.
- `size:n` - Moet exact lengte `n` hebben.
- `starts_with:a,b` - Moet starten met een opgegeven waarde.
- `string` - Moet een string zijn.
- `uppercase` - Moet volledig in hoofdletters zijn.
- `url` - Moet een geldige URL zijn.
- `ulid` - Moet een geldige ULID zijn.
- `uuid` - Moet een geldige UUID zijn.

## Numbers

- `between:min,max` - Numerieke waarde tussen min en max.
- `decimal:min,max` - Aantal decimalen tussen min en max.
- `digits:n` - Exact `n` cijfers.
- `digits_between:min,max` - Aantal cijfers tussen min en max.
- `gt:veld` - Groter dan ander veld.
- `gte:veld` - Groter dan of gelijk aan ander veld.
- `integer` - Moet een geheel getal zijn.
- `lt:veld` - Kleiner dan ander veld.
- `lte:veld` - Kleiner dan of gelijk aan ander veld.
- `max:n` - Maximale numerieke waarde is `n`.
- `max_digits:n` - Maximaal `n` cijfers.
- `min:n` - Minimale numerieke waarde is `n`.
- `min_digits:n` - Minimaal `n` cijfers.
- `multiple_of:n` - Moet een veelvoud zijn van `n`.
- `numeric` - Moet numeriek zijn.
- `same:veld` - Moet gelijk zijn aan ander veld.
- `size:n` - Moet exact numerieke waarde `n` zijn.

## Arrays

- `array` - Moet een array zijn.
- `between:min,max` - Aantal items tussen min en max.
- `contains:a,b` - Moet alle opgegeven waarden bevatten.
- `doesnt_contain:a,b` - Mag opgegeven waarden niet bevatten.
- `distinct` - Alle waarden moeten uniek zijn.
- `in_array:other.*` - Waarde moet voorkomen in andere array.
- `in_array_keys:a,b` - Array moet opgegeven keys bevatten.
- `list` - Moet een lijst zijn met indexen 0..n.
- `max:n` - Maximaal `n` items.
- `min:n` - Minimaal `n` items.
- `size:n` - Exact `n` items.

## Dates

- `after:datum` - Moet na opgegeven datum liggen.
- `after_or_equal:datum` - Moet na of gelijk aan opgegeven datum liggen.
- `before:datum` - Moet voor opgegeven datum liggen.
- `before_or_equal:datum` - Moet voor of gelijk aan opgegeven datum liggen.
- `date` - Moet een geldige datum zijn.
- `date_equals:datum` - Moet exact gelijk zijn aan opgegeven datum.
- `date_format:Y-m-d` - Moet exact opgegeven datumformaat volgen.
- `different:veld` - Moet verschillen van ander veld of datum.
- `timezone` - Moet een geldige timezone identifier zijn.

## Files

- `between:min,max` - Bestandsgrootte tussen min en max.
- `dimensions` - Afmetingen van afbeelding moeten voldoen.
- `extensions:jpg,png` - Extensie moet in lijst staan.
- `file` - Moet een geupload bestand zijn.
- `image` - Moet een afbeelding zijn.
- `max:n` - Maximale bestandsgrootte is `n`.
- `mimes:jpg,png,pdf` - Toegelaten extensies via MIME inspectie.
- `mimetypes:image/jpeg` - Toegelaten expliciete MIME types.
- `size:n` - Exacte bestandsgrootte is `n`.

## Database

- `exists:table,column` - Waarde moet bestaan in database.
- `unique:table,column` - Waarde moet uniek zijn in database.

## Utilities

- `bail` - Stop validatie van dit veld na eerste fout.
- `exclude` - Sluit veld altijd uit van gevalideerde data.
- `exclude_if:veld,waarde` - Sluit veld uit als conditie waar is.
- `exclude_unless:veld,waarde` - Sluit veld uit tenzij conditie waar is.
- `exclude_with:veld` - Sluit veld uit als ander veld aanwezig is.
- `exclude_without:veld` - Sluit veld uit als ander veld ontbreekt.
- `filled` - Als veld aanwezig is, mag het niet leeg zijn.
- `missing` - Veld mag niet aanwezig zijn.
- `missing_if:veld,waarde` - Veld moet ontbreken als conditie waar is.
- `missing_unless:veld,waarde` - Veld moet ontbreken tenzij conditie waar is.
- `missing_with:veld` - Veld moet ontbreken als ander veld aanwezig is.
- `missing_with_all:velden` - Veld moet ontbreken als alle opgegeven velden aanwezig zijn.
- `nullable` - Veld mag null zijn.
- `present` - Veld moet aanwezig zijn in input (mag leeg zijn).
- `present_if:veld,waarde` - Veld moet aanwezig zijn als conditie waar is.
- `present_unless:veld,waarde` - Veld moet aanwezig zijn tenzij conditie waar is.
- `present_with:veld` - Veld moet aanwezig zijn als ander veld aanwezig is.
- `present_with_all:velden` - Veld moet aanwezig zijn als alle opgegeven velden aanwezig zijn.
- `prohibited` - Veld moet ontbreken of leeg zijn.
- `prohibited_if:veld,waarde` - Veld is verboden als conditie waar is.
- `prohibited_if_accepted:veld` - Verboden als ander veld geaccepteerd is.
- `prohibited_if_declined:veld` - Verboden als ander veld geweigerd is.
- `prohibited_unless:veld,waarde` - Verboden tenzij conditie waar is.
- `prohibits:velden` - Als dit veld gevuld is, moeten andere velden leeg zijn.
- `required` - Veld is verplicht.
- `required_if:veld,waarde` - Veld is verplicht als conditie waar is.
- `required_if_accepted:veld` - Verplicht als ander veld geaccepteerd is.
- `required_if_declined:veld` - Verplicht als ander veld geweigerd is.
- `required_unless:veld,waarde` - Verplicht tenzij conditie waar is.
- `required_with:veld` - Verplicht als ander veld aanwezig is.
- `required_with_all:velden` - Verplicht als alle opgegeven velden aanwezig zijn.
- `required_without:veld` - Verplicht als ander veld ontbreekt.
- `required_without_all:velden` - Verplicht als alle opgegeven velden ontbreken.
- `required_array_keys:a,b` - Array moet opgegeven keys bevatten.
- `sometimes` - Valideer veld enkel als het aanwezig is.
- `Rule::anyOf([...])` - Waarde moet voldoen aan minstens een rule-set.
