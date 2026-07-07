# Laravel validation rules

Use these rules in the **Model rule** field, for example:

`required|string|max:255`

## Booleans

- `accepted` - Value must be accepted (yes/on/1/true).
- `accepted_if:field,value` - Must be accepted when another field matches.
- `boolean` - Value must be boolean-like (true/false/1/0).
- `declined` - Value must be declined (no/off/0/false).
- `declined_if:field,value` - Must be declined when another field matches.

## Strings

- `active_url` - Must be a URL with a valid DNS record.
- `alpha` - Letters only.
- `alpha_dash` - Letters, numbers, dashes, underscores.
- `alpha_num` - Letters and numbers only.
- `ascii` - ASCII characters only.
- `confirmed` - Must match `field_confirmation`.
- `current_password` - Must match current authenticated password.
- `different:field` - Must be different from another field.
- `doesnt_start_with:a,b` - Must not start with listed values.
- `doesnt_end_with:a,b` - Must not end with listed values.
- `email` - Must be a valid email address.
- `ends_with:a,b` - Must end with one listed value.
- `enum:EnumClass` - Must be a valid enum value.
- `hex_color` - Must be a valid hex color.
- `in:a,b,c` - Must be one of the listed values.
- `ip` - Must be a valid IP address.
- `ipv4` - Must be a valid IPv4 address.
- `ipv6` - Must be a valid IPv6 address.
- `json` - Must be valid JSON.
- `lowercase` - Must be lowercase.
- `mac_address` - Must be a valid MAC address.
- `max:n` - Maximum string length is `n`.
- `min:n` - Minimum string length is `n`.
- `not_in:a,b,c` - Must not be one of listed values.
- `regex:/.../` - Must match regex.
- `not_regex:/.../` - Must not match regex.
- `same:field` - Must be the same as another field.
- `size:n` - Must be exact length `n`.
- `starts_with:a,b` - Must start with one listed value.
- `string` - Must be a string.
- `uppercase` - Must be uppercase.
- `url` - Must be a valid URL.
- `ulid` - Must be a valid ULID.
- `uuid` - Must be a valid UUID.

## Numbers

- `between:min,max` - Numeric value between min and max.
- `decimal:min,max` - Decimal places between min and max.
- `digits:n` - Exactly `n` digits.
- `digits_between:min,max` - Digits count between min and max.
- `gt:field` - Greater than another field.
- `gte:field` - Greater than or equal to another field.
- `integer` - Must be an integer.
- `lt:field` - Less than another field.
- `lte:field` - Less than or equal to another field.
- `max:n` - Maximum numeric value is `n`.
- `max_digits:n` - Maximum `n` digits.
- `min:n` - Minimum numeric value is `n`.
- `min_digits:n` - Minimum `n` digits.
- `multiple_of:n` - Must be a multiple of `n`.
- `numeric` - Must be numeric.
- `same:field` - Must match another field.
- `size:n` - Must be exact numeric value `n`.

## Arrays

- `array` - Must be an array.
- `between:min,max` - Item count between min and max.
- `contains:a,b` - Must contain all listed values.
- `doesnt_contain:a,b` - Must not contain listed values.
- `distinct` - All values must be unique.
- `in_array:other.*` - Value must exist in another array.
- `in_array_keys:a,b` - Array must contain listed keys.
- `list` - Must be a list (0..n indexes).
- `max:n` - Maximum `n` items.
- `min:n` - Minimum `n` items.
- `size:n` - Exactly `n` items.

## Dates

- `after:date` - Must be after given date.
- `after_or_equal:date` - Must be after or equal given date.
- `before:date` - Must be before given date.
- `before_or_equal:date` - Must be before or equal given date.
- `date` - Must be a valid date.
- `date_equals:date` - Must equal given date.
- `date_format:Y-m-d` - Must match exact date format.
- `different:field` - Must differ from another field/date.
- `timezone` - Must be a valid timezone identifier.

## Files

- `between:min,max` - File size between min and max.
- `dimensions` - Image dimensions must match constraints.
- `extensions:jpg,png` - Extension must be in list.
- `file` - Must be an uploaded file.
- `image` - Must be an image file.
- `max:n` - Maximum file size is `n`.
- `mimes:jpg,png,pdf` - Allowed extensions by MIME inspection.
- `mimetypes:image/jpeg` - Allowed explicit MIME types.
- `size:n` - Exact file size is `n`.

## Database

- `exists:table,column` - Value must exist in database.
- `unique:table,column` - Value must be unique in database.

## Utilities

- `bail` - Stop validating this field after first failure.
- `exclude` - Always exclude field from validated data.
- `exclude_if:field,value` - Exclude field when condition matches.
- `exclude_unless:field,value` - Exclude unless condition matches.
- `exclude_with:field` - Exclude if another field is present.
- `exclude_without:field` - Exclude if another field is missing.
- `filled` - If field is present, it must not be empty.
- `missing` - Field must not be present.
- `missing_if:field,value` - Field must be missing when condition matches.
- `missing_unless:field,value` - Field must be missing unless condition matches.
- `missing_with:field` - Field must be missing if another exists.
- `missing_with_all:fields` - Field must be missing if all listed fields exist.
- `nullable` - Field may be null.
- `present` - Field must exist in input (may be empty).
- `present_if:field,value` - Field must be present when condition matches.
- `present_unless:field,value` - Field must be present unless condition matches.
- `present_with:field` - Field must be present if another exists.
- `present_with_all:fields` - Field must be present if all listed fields exist.
- `prohibited` - Field must be missing or empty.
- `prohibited_if:field,value` - Field is prohibited when condition matches.
- `prohibited_if_accepted:field` - Prohibited when another field is accepted.
- `prohibited_if_declined:field` - Prohibited when another field is declined.
- `prohibited_unless:field,value` - Prohibited unless condition matches.
- `prohibits:fields` - If this field is filled, listed fields must be empty.
- `required` - Field is required.
- `required_if:field,value` - Required when condition matches.
- `required_if_accepted:field` - Required when another field is accepted.
- `required_if_declined:field` - Required when another field is declined.
- `required_unless:field,value` - Required unless condition matches.
- `required_with:field` - Required when another field exists.
- `required_with_all:fields` - Required when all listed fields exist.
- `required_without:field` - Required when another field is missing.
- `required_without_all:fields` - Required when all listed fields are missing.
- `required_array_keys:a,b` - Array must include listed keys.
- `sometimes` - Validate this field only when present.
- `Rule::anyOf([...])` - Value must satisfy at least one rule-set.
