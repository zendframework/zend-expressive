# TODO

- [X] Document all use cases that were explicit to the design.
  These are primarily already done, in the [NOTES.md](NOTES.md), but they will
  need revisions, and a few will need to be added (particularly concepts of
  pipeline workflows and middleware nesting).

- [X] Error handling in `Application`
  - [X] Add a method for injecting a "final handler" to use when `$next` is
    null.
  - [X] Add a default final handler implementation. (Or will the one from
    Stratigility be sufficient?)
  - [X] Modify `__invoke()` to create the final handler implementation if `$out`
    is null.

- [X] Emitters
  - [X] Add the ability to inject an `EmitterInterface` into `Application`
  - [X] Create an `EmitterMap` or `EmitterStack` (or both?) implementation

- [X] Create a `run()` method
  This method will accept request and response objects *optionally*, creating
  them if none are passed. It will then use the final handler instance, creating
  a default instance if none is present, and pass the three to its own
  `__invoke()` method. Finally, it will pass the returned response to the
  composed emitter (using the `SapiEmitter` by default).

- [X] Decide if dispatcher *requires* a container.
  Decision: no. Path of least dependencies would allow using only callables
  and/or invokable callable classes.

- [X] Add getter for container to Application.
  - [X] Will retrieve container from dispatcher on instantiation.
  - [X] Getter **must** raise an exception if no container is present, to
    prevent "[method] on a null" errors.

- [X] Create static factory for default use case
  - [X] Decide if that factory belongs in the base Expressive package, or a
    skeleton. It will require selecting default router (and potentially
    container, if we make it required) implementations!
    - Decision: factory will be a separate class, but in the same package. This
      simplifies the story of knowing what package is the entry point.
    - Decision: the factory will also create a container.
    - Decision: Aura.Router and ZF2 SM will be used.
  - [X] Factory SHOULD allow passing router and potentially container.
