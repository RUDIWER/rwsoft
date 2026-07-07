# Regles de validation Laravel

Utilisez ces regles dans le champ **Model rule**, par exemple:

`required|string|max:255`

## Booleans

- `accepted` - La valeur doit etre acceptee (yes/on/1/true).
- `accepted_if:champ,valeur` - Doit etre acceptee si un autre champ correspond.
- `boolean` - La valeur doit etre booleenne (true/false/1/0).
- `declined` - La valeur doit etre refusee (no/off/0/false).
- `declined_if:champ,valeur` - Doit etre refusee si un autre champ correspond.

## Strings

- `active_url` - URL avec enregistrement DNS valide.
- `alpha` - Lettres uniquement.
- `alpha_dash` - Lettres, chiffres, tirets, underscores.
- `alpha_num` - Lettres et chiffres uniquement.
- `ascii` - Caracteres ASCII uniquement.
- `confirmed` - Doit correspondre a `champ_confirmation`.
- `current_password` - Doit correspondre au mot de passe actuel.
- `different:champ` - Doit etre different d'un autre champ.
- `doesnt_start_with:a,b` - Ne peut pas commencer par ces valeurs.
- `doesnt_end_with:a,b` - Ne peut pas finir par ces valeurs.
- `email` - Adresse e-mail valide.
- `ends_with:a,b` - Doit finir par une valeur de la liste.
- `enum:EnumClass` - Valeur enum valide.
- `hex_color` - Couleur hex valide.
- `in:a,b,c` - Valeur dans la liste.
- `ip` - Adresse IP valide.
- `ipv4` - Adresse IPv4 valide.
- `ipv6` - Adresse IPv6 valide.
- `json` - JSON valide.
- `lowercase` - Tout en minuscules.
- `mac_address` - Adresse MAC valide.
- `max:n` - Longueur maximale `n`.
- `min:n` - Longueur minimale `n`.
- `not_in:a,b,c` - Valeur absente de la liste.
- `regex:/.../` - Doit correspondre a la regex.
- `not_regex:/.../` - Ne doit pas correspondre a la regex.
- `same:champ` - Doit etre identique a un autre champ.
- `size:n` - Longueur exacte `n`.
- `starts_with:a,b` - Doit commencer par une valeur de la liste.
- `string` - Doit etre une chaine.
- `uppercase` - Tout en majuscules.
- `url` - URL valide.
- `ulid` - ULID valide.
- `uuid` - UUID valide.

## Numbers

- `between:min,max` - Valeur numerique entre min et max.
- `decimal:min,max` - Nombre de decimales entre min et max.
- `digits:n` - Exactement `n` chiffres.
- `digits_between:min,max` - Nombre de chiffres entre min et max.
- `gt:champ` - Plus grand qu'un autre champ.
- `gte:champ` - Plus grand ou egal a un autre champ.
- `integer` - Entier valide.
- `lt:champ` - Plus petit qu'un autre champ.
- `lte:champ` - Plus petit ou egal a un autre champ.
- `max:n` - Valeur numerique max `n`.
- `max_digits:n` - Maximum `n` chiffres.
- `min:n` - Valeur numerique min `n`.
- `min_digits:n` - Minimum `n` chiffres.
- `multiple_of:n` - Multiple de `n`.
- `numeric` - Valeur numerique.
- `same:champ` - Identique a un autre champ.
- `size:n` - Valeur numerique exacte `n`.

## Arrays

- `array` - Doit etre un tableau.
- `between:min,max` - Nombre d'elements entre min et max.
- `contains:a,b` - Doit contenir toutes les valeurs indiquees.
- `doesnt_contain:a,b` - Ne doit pas contenir les valeurs indiquees.
- `distinct` - Valeurs uniques.
- `in_array:other.*` - Valeur presente dans un autre tableau.
- `in_array_keys:a,b` - Doit contenir les cles indiquees.
- `list` - Liste avec index 0..n.
- `max:n` - Maximum `n` elements.
- `min:n` - Minimum `n` elements.
- `size:n` - Exactement `n` elements.

## Dates

- `after:date` - Date apres la date donnee.
- `after_or_equal:date` - Date apres ou egale.
- `before:date` - Date avant la date donnee.
- `before_or_equal:date` - Date avant ou egale.
- `date` - Date valide.
- `date_equals:date` - Date exactement egale.
- `date_format:Y-m-d` - Respecte exactement le format.
- `different:champ` - Doit differer d'un autre champ/date.
- `timezone` - Fuseau horaire valide.

## Files

- `between:min,max` - Taille de fichier entre min et max.
- `dimensions` - Dimensions image conformes.
- `extensions:jpg,png` - Extension autorisee.
- `file` - Fichier upload valide.
- `image` - Fichier image.
- `max:n` - Taille max `n`.
- `mimes:jpg,png,pdf` - Extensions autorisees via inspection MIME.
- `mimetypes:image/jpeg` - Types MIME explicites autorises.
- `size:n` - Taille exacte `n`.

## Database

- `exists:table,column` - Valeur doit exister en base.
- `unique:table,column` - Valeur doit etre unique en base.

## Utilities

- `bail` - Arreter la validation de ce champ apres la premiere erreur.
- `exclude` - Exclure toujours le champ des donnees validees.
- `exclude_if:champ,valeur` - Exclure si condition vraie.
- `exclude_unless:champ,valeur` - Exclure sauf si condition vraie.
- `exclude_with:champ` - Exclure si un autre champ est present.
- `exclude_without:champ` - Exclure si un autre champ est absent.
- `filled` - Si present, le champ ne peut pas etre vide.
- `missing` - Le champ ne doit pas etre present.
- `missing_if:champ,valeur` - Le champ doit etre absent si condition vraie.
- `missing_unless:champ,valeur` - Le champ doit etre absent sauf si condition vraie.
- `missing_with:champ` - Le champ doit etre absent si un autre champ est present.
- `missing_with_all:champs` - Le champ doit etre absent si tous les champs sont presents.
- `nullable` - Valeur null autorisee.
- `present` - Le champ doit etre present (peut etre vide).
- `present_if:champ,valeur` - Doit etre present si condition vraie.
- `present_unless:champ,valeur` - Doit etre present sauf si condition vraie.
- `present_with:champ` - Doit etre present si un autre champ existe.
- `present_with_all:champs` - Doit etre present si tous les champs existent.
- `prohibited` - Le champ doit etre absent ou vide.
- `prohibited_if:champ,valeur` - Interdit si condition vraie.
- `prohibited_if_accepted:champ` - Interdit si un autre champ est accepte.
- `prohibited_if_declined:champ` - Interdit si un autre champ est refuse.
- `prohibited_unless:champ,valeur` - Interdit sauf si condition vraie.
- `prohibits:champs` - Si ce champ est rempli, les autres doivent etre vides.
- `required` - Champ obligatoire.
- `required_if:champ,valeur` - Obligatoire si condition vraie.
- `required_if_accepted:champ` - Obligatoire si un autre champ est accepte.
- `required_if_declined:champ` - Obligatoire si un autre champ est refuse.
- `required_unless:champ,valeur` - Obligatoire sauf si condition vraie.
- `required_with:champ` - Obligatoire si un autre champ est present.
- `required_with_all:champs` - Obligatoire si tous les champs sont presents.
- `required_without:champ` - Obligatoire si un autre champ est absent.
- `required_without_all:champs` - Obligatoire si tous les autres champs sont absents.
- `required_array_keys:a,b` - Le tableau doit contenir les cles indiquees.
- `sometimes` - Valider seulement si le champ est present.
- `Rule::anyOf([...])` - La valeur doit satisfaire au moins un jeu de regles.
