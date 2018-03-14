# How can I use zend-form view helpers?

If you've selected zend-view as your preferred template renderer, you'll likely
want to use the various view helpers available in other components, such as:

- zend-form
- zend-i18n
- zend-navigation

By default, only the view helpers directly available in zend-view are available;
how can you add the others?

## ConfigProvider

When you install zend-form, Composer should prompt you if you want to inject one
or more `ConfigProvider` classes, including those from zend-hydrator,
zend-inputfilter, and several others. Always answer "yes" to these; when you do,
a Composer plugin will add entries for their `ConfigProvider` classes to your
`config/config.php` file.

If for some reason you are not prompted, or chose "no" when answering the
prompts, you can add them manually. Add the following entries in the array used
to create your `ConfigAggregator` instance within `config/config.php`:

```php
    \Zend\Form\ConfigProvider::class,
    \Zend\InputFilter\ConfigProvider::class,
    \Zend\Filter\ConfigProvider::class,
    \Zend\Validator\ConfigProvider::class,
    \Zend\Hydrator\ConfigProvider::class,
```

If you installed Expressive via the skeleton, the service
`Zend\View\HelperPluginManager` is registered for you, and represents the helper
plugin manager injected into the `PhpRenderer` instance. This instance gets its
helper configuration from the `view_helpers` top-level configuration key &mdash;
which the zend-form `ConfigProvider` helps to populate!

At this point, all view helpers provided by zend-form are registered and ready
to use.
