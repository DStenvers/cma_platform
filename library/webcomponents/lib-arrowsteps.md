# lib-arrowsteps

Horizontal **arrow / chevron step indicator** for multi-step flows (checkout,
wizards). Each step is *past* (done — clickable link), *current* (highlighted)
or *future* (inert). Self-contained via Shadow DOM, so it ships its own styling
and works on any page (CMA or webshop) without extra CSS.

## Usage

Declarative, with links:

```html
<lib-arrowsteps current="2"
    steps='[{"label":"Winkelwagen","url":"/winkelwagen"},
            {"label":"Verzendkosten","url":"/verzendkosten"},
            {"label":"Afrekenen","url":"/afrekenen"},
            {"label":"Betaling","url":"/betaling"}]'></lib-arrowsteps>
```

Shorthand (labels only, no links):

```html
<lib-arrowsteps current="1" labels="Winkelwagen|Verzendkosten|Afrekenen|Betaling"></lib-arrowsteps>
```

Script (loaded minified in production):

```html
<script src="/library/webcomponents/lib-arrowsteps.min.js?v=ASSETS_VERSION"></script>
```

## Attributes

| Attribute  | Description |
|------------|-------------|
| `current`  | 1-based index of the active step (default `1`). |
| `steps`    | JSON array of `{label, url}` objects. |
| `labels`   | Pipe-separated labels, alternative to `steps` (no urls). |
| `linkable` | `"future"` to also allow clicking steps ahead of current. Default: only past/done steps link. |

## Properties

```js
const el = document.querySelector('lib-arrowsteps');
el.current = 3;                       // re-renders
el.steps = [{label:'A', url:'/a'}];   // re-renders
```

## Events

- `step-click` — fired when a step link is activated.
  `detail: { step: <1-based>, label, url }`

## States & styling

Steps get the classes `.step.done`, `.step.current`, `.step.future` inside the
shadow root. Colors are exposed as CSS custom properties on `:host` and can be
overridden from the host page:

```css
lib-arrowsteps {
    --as-done: #0872c9;        /* past step background */
    --as-current: #236ab4;     /* current step background */
    --as-future: #e4e9f0;      /* future step background */
    --as-future-text: #8a93a3; /* future step text */
    --as-text: #ffffff;        /* done/current text */
}
```

## Replaces

The Karaat webshop's `ShoppingCart::WriteArrowSteps($current)`
(`module/shoppingcart/class_shoppingcart.inc`), which echoed a fixed
`<div class="arrow-steps"><div class="step done|current">…` for the four
checkout steps. To migrate, replace the call with:

```php
echo '<lib-arrowsteps current="' . (int)$iCurrentStep . '" steps=\''
   . htmlspecialchars(json_encode($steps), ENT_QUOTES) . '\'></lib-arrowsteps>';
```

(and load `lib-arrowsteps.min.js`). The component keeps the same `done` /
`current` semantics: steps before `current` link to their url, the current
step is highlighted, later steps are inert.
