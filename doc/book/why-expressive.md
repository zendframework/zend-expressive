# Should You Choose Zend\Expressive Over Zend\Mvc?

After creating several projects with Zend\Expressive, we recommend that you choose Expressive for any new project — _if the choice is yours to make_.

## We Recommend Zend\Expressive

[Zend\MVC](https://github.com/zendframework/zend-mvc) has many preconceptions about how things work, yet they're very broad and unspecific.
What’s more, it also has several pre-wired structures in place.

As a result, you are required to know a lot of what those things are — *if* you want to use it optimally.
And to acquire that depth of knowledge, you’re going to need to spend a lot of time, digging deep into Zend\Mvc’s internals, before you begin to get the most out of it.

To quote Zend Framework project lead, [Matthew Weier O’Phinney](https://twitter.com/mwop):

> The problem is that Zend\Mvc is anything but beginner-friendly at this point. You're required to deep dive into the event manager, service manager, and module system — right from the outset; And to do this you need more than a passing understanding of object-oriented programming and a range of design patterns.

Zend\Expressive (specifically applications based on [the Expressive Skeleton Installer](https://zendframework.github.io/zend-expressive/)) on the other hand, comes with barely any of these assumptions and requirements.

It provides a very minimalist structure. Essentially all you have to become familiar with are five core components. These are:

- A DI container
- A Router
- An Error handler for development
- A Template engine (if you’re not creating an API)
- PSR-7 Middleware

Given that, you can quickly get up to speed with the framework and begin creating the application that you need.
We believe that this approach — in contrast to the Zend\Mvc approach — is more flexible and accommodating.

What’s more, you can mix and match the types of applications that you create.
Do you just need an API? Great; you can do that quite quickly.
Do you want an HTML-based front-end? That’s available too.

When building applications with Zend\Expressive, you can make use of any other Zend library, or non-Zend library.
You can pick and choose what you need, as and when you need it.
You’re not bound by many, if any, constraints and design decisions.

## In Conclusion

For what it’s worth, we’re **not** saying that Zend\Mvc is a poor choice!
What we are saying is:

1. The learning curve, from getting started to building the first application, is _significantly_ lower with Zend\Expressive
2. The ways in which you can create applications, whether through multiple pieces of middleware or by combining multiple Expressive apps, into one larger one, is a much more efficient and fluid way to work

Ultimately, the choice is always up to you, your team, and your project’s needs.
We just want to ensure that you’ve got all the information you need, to make an informed decision.
