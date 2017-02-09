# Should You Choose zend-expressive Over zend-mvc?

After creating several projects with zend-expressive, we recommend that you
choose Expressive for any new project &mdash; _if the choice is yours to make_.

## We Recommend Expressive

[zend-mvc](https://github.com/zendframework/zend-mvc) has many preconceptions
about how things work, yet they're very broad and unspecific.  What’s more, it
also has several pre-wired structures in place.

As a result, you are required to know a lot of what those things are &mdash; *if* you
want to use it optimally.  And to acquire that depth of knowledge, you’re going
to need to spend a lot of time, digging deep into zend-mvc’s internals, before
you begin to get the most out of it.

To quote Zend Framework project lead, [Matthew Weier O’Phinney](https://mwop.net):

> The problem is that zend-mvc is anything but beginner-friendly at this point.
> You're required to deep dive into the event manager, service manager, and
> module system &mdash; right from the outset; And to do this you need more than a
> passing understanding of object-oriented programming and a range of design
> patterns.

Expressive (specifically applications based on
[the Expressive Skeleton Installer](https://docs.zendframework.com/zend-expressive/getting-started/skeleton/))
on the other hand, comes with barely any of these assumptions and requirements.

It provides a very minimalist structure. Essentially all you have to become
familiar with are five core components. These are:

- A DI container
- A router
- An error handler for development
- A template engine (if you’re not creating an API)
- PSR-7 messages and http-interop (future PSR-15) middleware

Given that, you can quickly get up to speed with the framework and begin
creating the application that you need.  We believe that this approach &mdash; in
contrast to the zend-mvc approach &mdash; is more flexible and accommodating.

What’s more, you can mix and match the types of applications that you create.

- Do you just need an API? Great; you can do that quite quickly.
- Do you want an HTML-based front-end? That’s available too.

When building applications with Expressive, you can make use of any other
Zend library, or non-Zend library.  You can pick and choose what you need, as
and when you need it.  You’re not bound by many, if any, constraints and design
decisions.

## In Conclusion

For what it’s worth, we’re **not** saying that zend-mvc is a poor choice!  What
we are saying is:

1. The learning curve, from getting started to building the first application,
   is _significantly_ lower with Expressive
2. The ways in which you can create applications, whether through multiple
   pieces of middleware or by combining multiple Expressive apps, into one
   larger one, is a much more efficient and fluid way to work

Ultimately, the choice is always up to you, your team, and your project’s needs.
We just want to ensure that you’ve got all the information you need, to make an
informed decision.
