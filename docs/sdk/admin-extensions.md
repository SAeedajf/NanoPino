# Admin Extensions

Admin extensions register components, routes, menu items, widgets or panels through the NanoPino admin registries. Module loading is constrained to the active app mount and same-origin boundary.

A missing admin component must render an explicit unavailable state rather than a blank page. Admin copy should use the public i18n contract rather than embedding Persian/English strings directly.

Admin extensions must respect capabilities supplied by the server manifest. Hiding a control in Vue is not an authorization boundary.
