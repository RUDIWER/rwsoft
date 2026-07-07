# Template cheatsheet

Gebruik deze placeholders in xlsx / ods / docx / odt templates.

## Basis placeholders

```text
{{ first.name }}
{{ first.email }}
{{ first.total_amount }}
```

## Herhaal rijen

```text
{{ rows:data }}
{{ row.student_name }}
{{ row.points }}
{{ /rows }}
```

## Groeperen en subtotaal

```text
{{ rows:data by=class_name groupLabel=class_name subtotalColumn=points subtotalLabel=Subtotaal }}
{{ row.student_name }}
{{ row.points }}
{{ /rows }}
```

## Afbeelding in cel of document

```text
{{ image:first.photo_path width=180 height=120 }}
```

## Afbeeldingsgrid

```text
{{ imageGrid:data.photos columns=3 width=180 height=120 caption=title }}
```

## Sheet directives (xlsx / ods)

```text
{{ sheetClone:data.properties name=sheet.property_name }}
{{ sheetHideIfEmpty:data.photos }}
{{ sheetOrder:10 }}
{{ sheetToc numbered=true }}
```

Tip: gebruik enkel dot-notatie binnen placeholders.
