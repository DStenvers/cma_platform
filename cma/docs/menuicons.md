# Menu Icons Configuration

The sidebar menu icons in CMA are configured in `main.php`. Icons use the **Linearicons** font library (paid version).

## Icon Library

This project uses the paid/full version of Linearicons which includes 1000+ icons.

- Full icon reference: https://linearicons.com/
- Icons are referenced using CSS classes like `lnr-home`, `lnr-cog`, `lnr-user`, etc.

## Menu Group Icons

Menu groups (the collapsible sections in the sidebar) get their icons from the `$menuGroupIcons` array in `main.php` (around line 127):

```php
$menuGroupIcons = [
    'dashboard' => 'lnr-home',
    'systeem' => 'lnr-cog',
    'beheer' => 'lnr-database',
    'content' => 'lnr-file-add',
    'rapportage' => 'lnr-chart-bars',
    'rapporten' => 'lnr-chart-bars',
    'rapportages' => 'lnr-chart-bars',
    'rapport' => 'lnr-chart-bars',
    'instellingen' => 'lnr-cog',
    'tools' => 'lnr-construction',
    'utilities' => 'lnr-construction',
    'formulieren' => 'lnr-layers',
    'opleidingen' => 'lnr-graduation-hat',
    'toetsing' => 'lnr-checkmark-circle',
    'evaluaties' => 'lnr-thumbs-up',
    'rino_info' => 'lnr-file-empty',
    'marketing_url' => 'lnr-link',
    'marketing' => 'lnr-link',
    'afspraken' => 'lnr-calendar-full',
    'forums' => 'lnr-bubble',
    'literatuur' => 'lnr-book',
    'personen' => 'lnr-user',
    'cgo' => 'lnr-layers',
    'gesprekken' => 'lnr-bubble',
    'servicebureau' => 'lnr-briefcase',
    'menu' => 'lnr-menu',
    'zoektermen' => 'lnr-magnifier',
    'urls' => 'lnr-link',
    'materialen' => 'lnr-box',
    'artikelen' => 'lnr-file-empty',
    'nieuws' => 'lnr-file-empty',
    'agenda' => 'lnr-calendar-full',
    'agendareserveringen' => 'lnr-calendar-full',
    'tijdsblokken' => 'lnr-calendar-full',
    'kalender' => 'lnr-calendar-full',
    'rooster' => 'lnr-calendar-full',
    'tags' => 'lnr-tag',
    'autos' => 'lnr-car',
];
```

The **key** is the lowercase menu group name (from the database). The **value** is the Linearicons class name.

### To change a menu group icon:

1. Find the menu group name in lowercase
2. Update or add an entry in `$menuGroupIcons`
3. If the menu name isn't in the array, it defaults to `lnr-menu`

## Menu Item Icons

Individual menu items within groups get icons based on their type (around line 247 in `main.php`):

| Item Type | Icon | Detection |
|-----------|------|-----------|
| Forms (default) | `lnr-file-empty` | Default for all items |
| Reports | `lnr-chart-bars` | href contains 'report' or 'Rep' |
| Tools | `lnr-cog` | href contains 'tool' |

### To change menu item icons:

Edit the logic around line 247 in `main.php`:

```php
$itemIcon = 'lnr-file-empty';  // Default for forms
if (!empty($formId)) {
    $itemIcon = 'lnr-file-empty';
} elseif (stripos($href, 'report') !== false || stripos($href, 'Rep') !== false) {
    $itemIcon = 'lnr-chart-bars';
} elseif (stripos($href, 'tool') !== false) {
    $itemIcon = 'lnr-cog';
}
```

## Commonly Used Icons

| Icon Class | Description |
|------------|-------------|
| `lnr-home` | Home/Dashboard |
| `lnr-cog` | Settings/Configuration |
| `lnr-database` | Database/Management |
| `lnr-file-empty` | File/Form |
| `lnr-file-add` | Add file/Content |
| `lnr-chart-bars` | Charts/Reports |
| `lnr-calendar-full` | Calendar/Agenda |
| `lnr-user` | Single user |
| `lnr-users` | Multiple users/Groups |
| `lnr-book` | Book/Literature |
| `lnr-link` | Link/URL |
| `lnr-layers` | Layers/Forms |
| `lnr-graduation-hat` | Education/Training |
| `lnr-checkmark-circle` | Checkmark/Verification |
| `lnr-thumbs-up` | Thumbs up/Evaluation |
| `lnr-bubble` | Chat/Forum |
| `lnr-briefcase` | Briefcase/Business |
| `lnr-menu` | Menu (default) |
| `lnr-magnifier` | Search |
| `lnr-box` | Box/Materials |
| `lnr-tag` | Tag |
| `lnr-car` | Car/Vehicle |
| `lnr-construction` | Tools/Construction |
| `lnr-lock` | Lock/Security |
| `lnr-exit` | Exit/Logout |

## Special Menu Items

Some menu items have hardcoded icons:

- **Users** (`form.php?form=users`): `lnr-user`
- **Groups** (`form.php?form=groups`): `lnr-users`
- **Preferences**: `lnr-cog`
- **Change Password**: `lnr-lock`
- **Logout**: `lnr-exit`

These are defined in the static menu items section of `main.php`.
