# Form URL Test Cases

Test URLs for the CMA form system. Base URL: `http://172.29.208.1/cma/`

## Basic Form Views

### Standard List View (tree or table based on preference)
- [form.php?form=opleidingen](http://172.29.208.1/cma/form.php?form=opleidingen)
- [form.php?form=deelnemers](http://172.29.208.1/cma/form.php?form=deelnemers)
- [form.php?form=contactpersonen](http://172.29.208.1/cma/form.php?form=contactpersonen)
- [form.php?form=documenten](http://172.29.208.1/cma/form.php?form=documenten)
- [form.php?form=toetsing](http://172.29.208.1/cma/form.php?form=toetsing)

### List with Table View Forced
- [form.php?form=opleidingen&view=table](http://172.29.208.1/cma/form.php?form=opleidingen&view=table)
- [form.php?form=deelnemers&view=table](http://172.29.208.1/cma/form.php?form=deelnemers&view=table)

### List with Tree View Forced
- [form.php?form=opleidingen&view=tree](http://172.29.208.1/cma/form.php?form=opleidingen&view=tree)
- [form.php?form=deelnemers&view=tree](http://172.29.208.1/cma/form.php?form=deelnemers&view=tree)

## Direct Record Mode (Detail Only - No List Panel)

### Edit Existing Record
- [form.php?form=opleidingen&ID=12](http://172.29.208.1/cma/form.php?form=opleidingen&ID=12)
- [form.php?form=opleidingen&ID=112](http://172.29.208.1/cma/form.php?form=opleidingen&ID=112)
- [form.php?form=deelnemers&ID=155](http://172.29.208.1/cma/form.php?form=deelnemers&ID=155)

### New Record Mode
- [form.php?form=opleidingen&New=Y](http://172.29.208.1/cma/form.php?form=opleidingen&New=Y)
- [form.php?form=opleidingen&ID=0](http://172.29.208.1/cma/form.php?form=opleidingen&ID=0)
- [form.php?form=deelnemers&New=Y](http://172.29.208.1/cma/form.php?form=deelnemers&New=Y)

### Copy Record Mode
- [form.php?form=opleidingen&ID=12&copy=Y](http://172.29.208.1/cma/form.php?form=opleidingen&ID=12&copy=Y)
- [form.php?form=opleidingen&ID=112&copy=Y](http://172.29.208.1/cma/form.php?form=opleidingen&ID=112&copy=Y)

## Subform Mode (Parent Context)

### Subform with Parent Filter (should hide parentField in detail form)
- [form.php?form=opleidingen_deelnemers&parentID=12&parentField=fkOpleiding](http://172.29.208.1/cma/form.php?form=opleidingen_deelnemers&parentID=12&parentField=fkOpleiding)
- [form.php?form=opleidingen_deelnemers&parentID=12&parentField=fkOpleiding&view=table](http://172.29.208.1/cma/form.php?form=opleidingen_deelnemers&parentID=12&parentField=fkOpleiding&view=table)

### Subform Edit Specific Record
- [form.php?form=opleidingen_deelnemers&ID=155&parentID=12&parentField=fkOpleiding](http://172.29.208.1/cma/form.php?form=opleidingen_deelnemers&ID=155&parentID=12&parentField=fkOpleiding)

### Subform New Record
- [form.php?form=opleidingen_deelnemers&New=Y&parentID=12&parentField=fkOpleiding](http://172.29.208.1/cma/form.php?form=opleidingen_deelnemers&New=Y&parentID=12&parentField=fkOpleiding)

## System Forms (Users/Groups)

### Users
- [form.php?form=users](http://172.29.208.1/cma/form.php?form=users)
- [form.php?form=users&New=Y](http://172.29.208.1/cma/form.php?form=users&New=Y)

### Groups
- [form.php?form=groups](http://172.29.208.1/cma/form.php?form=groups)
- [form.php?form=groups&New=Y](http://172.29.208.1/cma/form.php?form=groups&New=Y)

## Search

### With Search Term
- [form.php?form=opleidingen&search=test](http://172.29.208.1/cma/form.php?form=opleidingen&search=test)
- [form.php?form=deelnemers&search=jan](http://172.29.208.1/cma/form.php?form=deelnemers&search=jan)

## Expected Behaviors

| URL Pattern | Expected Behavior |
|-------------|-------------------|
| `form=X` | Shows list (tree/table per preference) + detail panel |
| `form=X&ID=N` | Shows detail form only (no list panel), edit mode |
| `form=X&ID=0` or `&New=Y` | Shows detail form only (no list panel), add mode |
| `form=X&ID=N&copy=Y` | Shows detail form only (no list panel), copy mode |
| `form=X&parentID=N&parentField=F` | Shows filtered list + detail panel, parentField hidden |
| `form=X&view=table` | Forces table view (saves preference) |
| `form=X&view=tree` | Forces tree view (saves preference) |

## Notes

- Display mode (tree/table) is saved per form in localStorage (`cma_listMode_{formName}`)
- Parent field (e.g., fkOpleiding) is automatically hidden when form is opened as subform
- JSON forms default to table view, legacy forms default to tree view
- Mobile viewport always uses table view (tree not suited for small screens)
