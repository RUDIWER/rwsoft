# Template Hilfe

Verwenden Sie diese Platzhalter in xlsx / ods / docx / odt Templates.

## Basis Platzhalter

```text
{{ first.name }}
{{ first.email }}
{{ first.total_amount }}
```

## Zeilen wiederholen

```text
{{ rows:data }}
{{ row.student_name }}
{{ row.points }}
{{ /rows }}
```

## Gruppieren und Zwischensumme

```text
{{ rows:data by=class_name groupLabel=class_name subtotalColumn=points subtotalLabel=Zwischensumme }}
{{ row.student_name }}
{{ row.points }}
{{ /rows }}
```

## Bild in Zelle oder Dokument

```text
{{ image:first.photo_path width=180 height=120 }}
```

## Bildraster

```text
{{ imageGrid:data.photos columns=3 width=180 height=120 caption=title }}
```

## Sheet Direktiven (xlsx / ods)

```text
{{ sheetClone:data.properties name=sheet.property_name }}
{{ sheetHideIfEmpty:data.photos }}
{{ sheetOrder:10 }}
{{ sheetToc numbered=true }}
```

Tipp: verwenden Sie in Platzhaltern nur Punktnotation.
