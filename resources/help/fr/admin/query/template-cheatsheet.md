# Aide template

Utilisez ces placeholders dans les templates xlsx / ods / docx / odt.

## Placeholders de base

```text
{{ first.name }}
{{ first.email }}
{{ first.total_amount }}
```

## Repeter des lignes

```text
{{ rows:data }}
{{ row.student_name }}
{{ row.points }}
{{ /rows }}
```

## Groupement et sous-total

```text
{{ rows:data by=class_name groupLabel=class_name subtotalColumn=points subtotalLabel=Sous-total }}
{{ row.student_name }}
{{ row.points }}
{{ /rows }}
```

## Image dans cellule ou document

```text
{{ image:first.photo_path width=180 height=120 }}
```

## Grille d'images

```text
{{ imageGrid:data.photos columns=3 width=180 height=120 caption=title }}
```

## Directives de feuille (xlsx / ods)

```text
{{ sheetClone:data.properties name=sheet.property_name }}
{{ sheetHideIfEmpty:data.photos }}
{{ sheetOrder:10 }}
{{ sheetToc numbered=true }}
```

Conseil: utilisez uniquement la notation pointee dans les placeholders.
