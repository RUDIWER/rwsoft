# Template cheatsheet

Use these placeholders in xlsx / ods / docx / odt templates.

## Basic placeholders

```text
{{ first.name }}
{{ first.email }}
{{ first.total_amount }}
```

## Repeat rows

```text
{{ rows:data }}
{{ row.student_name }}
{{ row.points }}
{{ /rows }}
```

## Grouping and subtotal

```text
{{ rows:data by=class_name groupLabel=class_name subtotalColumn=points subtotalLabel=Subtotal }}
{{ row.student_name }}
{{ row.points }}
{{ /rows }}
```

## Image in cell or document

```text
{{ image:first.photo_path width=180 height=120 }}
```

## Image grid

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

Tip: use dot notation only inside placeholders.
