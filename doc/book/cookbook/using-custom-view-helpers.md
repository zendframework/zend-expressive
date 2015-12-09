# How do you register custom view helpers when using zend-view?

If you've selected zend-view as your preferred template renderer, you may want
to define and use custom view helpers. How can you use them?

Assuming you've used the Expressive skeleton to start your application, you will
already have a factory defined for `Zend\View\HelperPluginManager`, and it will
be injected into the `PhpRenderer` instance used. Since the `HelperPluginManager`
is available, we can configure it.

Open the file `config/autoload/templates.global.php`. In that file, you'll see
three top-level keys:

```php
return [
    'dependencies' => [ /* ... */ ],
    'templates' => [ /* ... */ ],
    'view_helpers' => [ /* ... */ ],
];
```

The last is the one you want. In this, you can define service mappings,
including aliases, invokables, factories, and abstract factories to define how
helpers are named and created.
[See the zend-view custom helpers documentation](http://framework.zend.com/manual/current/en/modules/zend.view.helpers.advanced-usage.html#zend-view-helpers-advanced-usage)
for information on how to populate this configuration.
