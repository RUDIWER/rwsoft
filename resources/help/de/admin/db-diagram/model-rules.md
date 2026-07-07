# Laravel Validierungsregeln

Verwenden Sie diese Regeln im Feld **Model rule**, zum Beispiel:

`required|string|max:255`

## Booleans

- `accepted` - Wert muss akzeptiert sein (yes/on/1/true).
- `accepted_if:feld,wert` - Muss akzeptiert sein, wenn anderes Feld passt.
- `boolean` - Wert muss boolesch sein (true/false/1/0).
- `declined` - Wert muss abgelehnt sein (no/off/0/false).
- `declined_if:feld,wert` - Muss abgelehnt sein, wenn anderes Feld passt.

## Strings

- `active_url` - URL mit gueltigem DNS Eintrag.
- `alpha` - Nur Buchstaben.
- `alpha_dash` - Buchstaben, Zahlen, Bindestrich, Unterstrich.
- `alpha_num` - Nur Buchstaben und Zahlen.
- `ascii` - Nur ASCII Zeichen.
- `confirmed` - Muss mit `feld_confirmation` uebereinstimmen.
- `current_password` - Muss zum aktuellen Passwort passen.
- `different:feld` - Muss sich von anderem Feld unterscheiden.
- `doesnt_start_with:a,b` - Darf nicht mit diesen Werten beginnen.
- `doesnt_end_with:a,b` - Darf nicht mit diesen Werten enden.
- `email` - Gueltige E-Mail-Adresse.
- `ends_with:a,b` - Muss mit einem Wert aus der Liste enden.
- `enum:EnumClass` - Gueltiger Enum Wert.
- `hex_color` - Gueltiger Hex Farbwert.
- `in:a,b,c` - Muss in der Liste enthalten sein.
- `ip` - Gueltige IP Adresse.
- `ipv4` - Gueltige IPv4 Adresse.
- `ipv6` - Gueltige IPv6 Adresse.
- `json` - Gueltiges JSON.
- `lowercase` - Muss komplett klein geschrieben sein.
- `mac_address` - Gueltige MAC Adresse.
- `max:n` - Maximale Laenge `n`.
- `min:n` - Minimale Laenge `n`.
- `not_in:a,b,c` - Darf nicht in der Liste enthalten sein.
- `regex:/.../` - Muss Regex entsprechen.
- `not_regex:/.../` - Darf Regex nicht entsprechen.
- `same:feld` - Muss gleich wie anderes Feld sein.
- `size:n` - Muss exakt Laenge `n` haben.
- `starts_with:a,b` - Muss mit einem Listenwert starten.
- `string` - Muss ein String sein.
- `uppercase` - Muss komplett gross geschrieben sein.
- `url` - Gueltige URL.
- `ulid` - Gueltige ULID.
- `uuid` - Gueltige UUID.

## Numbers

- `between:min,max` - Numerischer Wert zwischen min und max.
- `decimal:min,max` - Anzahl Dezimalstellen zwischen min und max.
- `digits:n` - Genau `n` Ziffern.
- `digits_between:min,max` - Ziffernanzahl zwischen min und max.
- `gt:feld` - Groesser als anderes Feld.
- `gte:feld` - Groesser oder gleich anderes Feld.
- `integer` - Muss ganze Zahl sein.
- `lt:feld` - Kleiner als anderes Feld.
- `lte:feld` - Kleiner oder gleich anderes Feld.
- `max:n` - Maximaler numerischer Wert `n`.
- `max_digits:n` - Maximal `n` Ziffern.
- `min:n` - Minimaler numerischer Wert `n`.
- `min_digits:n` - Mindestens `n` Ziffern.
- `multiple_of:n` - Muss ein Vielfaches von `n` sein.
- `numeric` - Muss numerisch sein.
- `same:feld` - Muss mit anderem Feld uebereinstimmen.
- `size:n` - Muss exakt numerischer Wert `n` sein.

## Arrays

- `array` - Muss ein Array sein.
- `between:min,max` - Anzahl Elemente zwischen min und max.
- `contains:a,b` - Muss alle angegebenen Werte enthalten.
- `doesnt_contain:a,b` - Darf angegebene Werte nicht enthalten.
- `distinct` - Alle Werte muessen eindeutig sein.
- `in_array:other.*` - Wert muss in anderem Array vorkommen.
- `in_array_keys:a,b` - Array muss angegebene Schluessel enthalten.
- `list` - Muss Liste mit Indizes 0..n sein.
- `max:n` - Maximal `n` Elemente.
- `min:n` - Mindestens `n` Elemente.
- `size:n` - Genau `n` Elemente.

## Dates

- `after:datum` - Muss nach angegebenem Datum liegen.
- `after_or_equal:datum` - Muss nach oder gleich angegebenem Datum sein.
- `before:datum` - Muss vor angegebenem Datum liegen.
- `before_or_equal:datum` - Muss vor oder gleich angegebenem Datum sein.
- `date` - Gueltiges Datum.
- `date_equals:datum` - Muss exakt gleich angegebenem Datum sein.
- `date_format:Y-m-d` - Muss exakt dem Datumsformat entsprechen.
- `different:feld` - Muss sich von anderem Feld/Datum unterscheiden.
- `timezone` - Gueltige Zeitzonen Kennung.

## Files

- `between:min,max` - Dateigroesse zwischen min und max.
- `dimensions` - Bildabmessungen muessen passen.
- `extensions:jpg,png` - Dateiendung muss in Liste sein.
- `file` - Muss eine hochgeladene Datei sein.
- `image` - Muss Bilddatei sein.
- `max:n` - Maximale Dateigroesse `n`.
- `mimes:jpg,png,pdf` - Erlaubte Endungen per MIME Pruefung.
- `mimetypes:image/jpeg` - Erlaubte explizite MIME Typen.
- `size:n` - Exakte Dateigroesse `n`.

## Database

- `exists:table,column` - Wert muss in der Datenbank existieren.
- `unique:table,column` - Wert muss in der Datenbank eindeutig sein.

## Utilities

- `bail` - Validierung fuer dieses Feld nach erstem Fehler stoppen.
- `exclude` - Feld immer aus validierten Daten ausschliessen.
- `exclude_if:feld,wert` - Feld ausschliessen, wenn Bedingung passt.
- `exclude_unless:feld,wert` - Feld ausschliessen, ausser Bedingung passt.
- `exclude_with:feld` - Feld ausschliessen, wenn anderes Feld vorhanden ist.
- `exclude_without:feld` - Feld ausschliessen, wenn anderes Feld fehlt.
- `filled` - Wenn Feld vorhanden ist, darf es nicht leer sein.
- `missing` - Feld darf nicht vorhanden sein.
- `missing_if:feld,wert` - Feld muss fehlen, wenn Bedingung passt.
- `missing_unless:feld,wert` - Feld muss fehlen, ausser Bedingung passt.
- `missing_with:feld` - Feld muss fehlen, wenn anderes Feld vorhanden ist.
- `missing_with_all:felder` - Feld muss fehlen, wenn alle Felder vorhanden sind.
- `nullable` - Feld darf null sein.
- `present` - Feld muss vorhanden sein (darf leer sein).
- `present_if:feld,wert` - Feld muss vorhanden sein, wenn Bedingung passt.
- `present_unless:feld,wert` - Feld muss vorhanden sein, ausser Bedingung passt.
- `present_with:feld` - Feld muss vorhanden sein, wenn anderes Feld existiert.
- `present_with_all:felder` - Feld muss vorhanden sein, wenn alle Felder existieren.
- `prohibited` - Feld muss fehlen oder leer sein.
- `prohibited_if:feld,wert` - Feld ist verboten, wenn Bedingung passt.
- `prohibited_if_accepted:feld` - Verboten, wenn anderes Feld akzeptiert wurde.
- `prohibited_if_declined:feld` - Verboten, wenn anderes Feld abgelehnt wurde.
- `prohibited_unless:feld,wert` - Verboten, ausser Bedingung passt.
- `prohibits:felder` - Wenn dieses Feld gefuellt ist, muessen andere leer sein.
- `required` - Feld ist Pflicht.
- `required_if:feld,wert` - Pflicht, wenn Bedingung passt.
- `required_if_accepted:feld` - Pflicht, wenn anderes Feld akzeptiert wurde.
- `required_if_declined:feld` - Pflicht, wenn anderes Feld abgelehnt wurde.
- `required_unless:feld,wert` - Pflicht, ausser Bedingung passt.
- `required_with:feld` - Pflicht, wenn anderes Feld vorhanden ist.
- `required_with_all:felder` - Pflicht, wenn alle Felder vorhanden sind.
- `required_without:feld` - Pflicht, wenn anderes Feld fehlt.
- `required_without_all:felder` - Pflicht, wenn alle anderen Felder fehlen.
- `required_array_keys:a,b` - Array muss angegebene Schluessel enthalten.
- `sometimes` - Nur validieren, wenn Feld vorhanden ist.
- `Rule::anyOf([...])` - Wert muss mindestens ein Regelset erfuellen.
