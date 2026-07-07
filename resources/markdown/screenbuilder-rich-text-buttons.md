# Rich text editor instellen

De Screen Builder gebruikt Jodit voor rich text velden. Een textarea wordt een rich text editor wanneer `Tekst editor` op `Rich text editor` staat.

## Toolbar keuzes

- `Standaard toolbar`: uitgebreide toolbar voor normale contentopmaak.
- `Basis toolbar`: compacte toolbar met alleen vet, cursief, onderlijnen, lijsten en links.
- `Eigen buttons`: handmatige lijst met Jodit button keys.

## Eigen buttons invullen

Gebruik een komma-gescheiden of regelgescheiden lijst.

Voorbeeld met komma's:

```text
bold,italic,underline,|,ul,ol,|,link,source
```

Voorbeeld met regels:

```text
bold
italic
underline
|
ul
ol
|
link
source
```

Gebruik `|` als visuele scheiding tussen groepen. Gebruik `\n` niet letterlijk; plaats elke key op een eigen regel als je regelgescheiden wil werken.

## Veelgebruikte button keys

- `bold`: vet
- `italic`: cursief
- `underline`: onderlijnen
- `strikethrough`: doorhalen
- `superscript`: superscript
- `subscript`: subscript
- `ul`: ongeordende lijst
- `ol`: genummerde lijst
- `outdent`: inspringing verkleinen
- `indent`: inspringing vergroten
- `align`: uitlijning-menu
- `paragraph`: paragraaf/koptekst-menu
- `font`: lettertype
- `fontsize`: lettergrootte
- `brush`: tekst- en achtergrondkleur
- `link`: link invoegen
- `unlink`: link verwijderen
- `image`: afbeelding invoegen
- `file`: bestand invoegen
- `video`: video invoegen
- `table`: tabel invoegen
- `hr`: horizontale lijn
- `symbols`: speciale tekens
- `undo`: ongedaan maken
- `redo`: opnieuw uitvoeren
- `cut`: knippen
- `copy`: kopieren
- `paste`: plakken
- `selectall`: alles selecteren
- `eraser`: opmaak wissen
- `copyformat`: opmaak kopieren
- `find`: zoeken
- `spellcheck`: spellcheck
- `fullsize`: editor op volledig scherm
- `preview`: voorbeeld
- `print`: afdrukken
- `source`: HTML-broncode tonen/bewerken
- `about`: Jodit informatie

## Aanbevolen presets

Compact:

```text
bold,italic,underline,|,ul,ol,|,link
```

Contentbeheer:

```text
bold,italic,underline,strikethrough,|,ul,ol,|,paragraph,fontsize,brush,|,link,table,image,hr,|,undo,redo,|,eraser
```

Technisch beheer:

```text
bold,italic,underline,|,ul,ol,|,link,table,image,|,source,fullsize
```

## Veiligheid

Rich text kan HTML bevatten, zeker wanneer `source` beschikbaar is. Render publieke output later alleen via gecontroleerde/sanitized HTML-output. Gebruik geen directe `v-html` op publieke gebruikersinput zonder filtering.
