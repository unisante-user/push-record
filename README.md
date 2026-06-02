# Push Record — REDCap External Module

Automatically copies selected fields from a record in the **source project** into a new record in a **destination project**, triggered either when a record is saved or when a survey is completed.

---

## Requirements

- REDCap with External Modules support
- The module must be enabled on the **source** project
- The API / internal save access to the **destination** project must be available

---

## Installation

1. Place the `push-record_v0.0.1` folder inside your REDCap `modules/` directory.
2. In REDCap, go to **Control Center → External Modules** and enable **Push Record**.
3. Enable the module on the desired source project via **Project Home → External Modules**.

---

## Configuration

All settings below are configured per-project under **External Modules → Push Record → Configure**.

### 1. Error notification email
**Setting:** `Email address for synchronization errors`  
Enter an email address that will receive a notification if the push fails (e.g. a field mapping error or save failure in the destination project).

---

### 2. Trigger method
**Setting:** `Which hook should trigger the action?`

| Option | When it fires |
|---|---|
| **Save record** | Every time a record is saved in the source project (data entry or API). Only pushes when a chosen field has a truthy value. |
| **Survey complete** | When a participant completes a specific survey. |

---

### 3a. Triggering variable *(Save record only)*
**Setting:** `Triggering variable when value is trueish`  
Choose a field in the source project. The push only happens when this field has a non-empty / truthy value (e.g. `1`, `yes`). This prevents pushing on every single save.

### 3b. Survey instrument *(Survey complete only)*
**Setting:** `Which survey should trigger the push`  
Select the survey instrument whose completion will trigger the push.

---

### 4. Target project
**Setting:** `Target project`  
Select the destination REDCap project by its Project ID. The module's REDCap service account must have write access to this project.

---

### 5. Record ID field name in destination project
**Setting:** `Record ID field name in destination project`  
Enter the **variable name** of the record ID field in the destination project (e.g. `record_id`). This is the field that will receive the computed record ID value.

---

### 6. Record ID strategy
**Setting:** `Do you want to push the same record id or another variable?`

#### Option A — Same record ID (with optional prefix/suffix)
The destination record ID is built from the source record ID:

```
[prefix] + <source_record_id> + [suffix]
```

Examples:
- Prefix `copy_`, no suffix → source `42` becomes `copy_42`
- No prefix, suffix `_dest` → source `42` becomes `42_dest`

**Settings:**
- `Prefix for the record ID value` — text prepended to the record ID (optional)
- `Suffix for the record ID value` — text appended to the record ID (optional)

#### Option B — Use another variable
The destination record ID is taken from the **value** of a field in the source record.

**Setting:** `Variable to use as record id value` — choose the source field whose value will become the destination record ID.

---

### 7. Synchronized fields
**Setting:** `Synchronized fields` *(repeatable)*

Add one row per field you want to copy. Each row has:

| Sub-setting | Description |
|---|---|
| **Field source** | Variable name in the **source** project |
| **Field destination** | Variable name in the **destination** project |
| **This field is a checkbox** | Check this if the field is a REDCap checkbox. The module will map all checkbox sub-values (e.g. `field___1`, `field___2`) automatically. |

> **Tip:** If a source field has no value, the destination field will be set to `UNKNOWN`. Make sure all mandatory mappings have data before the trigger fires.

---

## How it works (summary)

```
Source project record saved / survey completed
        │
        ▼
Trigger condition met? (truthy field or correct survey)
        │
        ▼
Build destination record:
  • Record ID  → prefix + source ID + suffix  OR  value of chosen field
  • Fields     → mapped one-to-one (checkboxes expanded automatically)
        │
        ▼
REDCap::saveData() → destination project
        │
        ▼
On error → send email to configured address
```

---

## Troubleshooting

| Symptom | Check |
|---|---|
| Push never fires (save record mode) | Is the triggering variable actually set to a truthy value before saving? |
| Push never fires (survey mode) | Is the correct instrument selected in the module settings? |
| Destination record not created | Verify the target project ID and that the record ID field name is correct. |
| Email about errors received | Check the error message — it usually indicates a field name mismatch or a required field missing in the destination project. |
| Checkbox values not copied | Ensure **This field is a checkbox** is ticked for that field mapping row. |

