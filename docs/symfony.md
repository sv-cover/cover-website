# Getting started with Symfony

Originally a fully custom codebase, the website is currently being migrated to Symfony. The choice for Symfony was made because it’s very mature and, more importantly, also very flexible due to its modular nature, easing the migration process. Moreover, the website had already been using some Symfony components for a long time.

This documentation is meant to help developers who are new to Symfony or this website get started.

## Documentation
If you haven’t worked with Symfony before, familiarize yourself with at least the [Routing](https://symfony.com/doc/current/routing.html), [Controllers](https://symfony.com/doc/current/controller.html), and [Template](https://symfony.com/doc/current/templates.html) documentation in the “Getting Started” section of [Symfony’s documentation](https://symfony.com/doc/current/index.html). These topics will help you get familiar with Symfony functions needed to implement basic features. As many of the website’s features use forms in some shape or form, it’s also recommended to check out the [Forms documentation](/docs/forms.md). Finally, you won’t be able to get around without the website’s old legacy components, so do check out the [legacy documentation](/docs/legacy.md) as well.

If the features you’re working on get more complicated, also check out Symfony’s documentation on [Configuration](https://symfony.com/doc/current/configuration/override_dir_structure.html) and [Services](https://symfony.com/doc/current/service_container.html). The documentation on services covers Symfony’s dependency injection (DI) system, which is how Symfony provides dependencies through function arguments in class methods and constructors. In other words, this is how Symfony knows to provide the request as an argument when calling a controller with the signature `Controller::index(Request $request)`. This process is also known as autowiring and it is a very important concept when working with Symfony.

**All developers** should familiarise themselves with the [Symfony Framework best practices](https://symfony.com/doc/current/best_practices.html). Unfortunately, some of them are not adhered to for legacy reasons, but it’s a good starting point.

Symfony’s documentation generally covers common use cases, but — for better or for worse — invites developers to look at the source code for exact function/class signatures. Usually, there’s a link to the specific source code somewhere nearby in the documentation, but sometimes it takes a bit of searching to get to the right files.

Here’s a list of useful places to visit for documentation:

- [Symfony documentation](https://symfony.com/doc/7.2/index.html)
- [Legacy documentation](/docs/legacy.md)
- [Twig documentation](https://twig.symfony.com/doc/3.x/)
- [Forms documentation](/docs/forms.md) (How this project integrates the Symfony Form component.)
- [Symfony Source Code (GitHub)](https://github.com/symfony/symfony/tree/7.2)
- [Symfony Reference Documentation](https://symfony.com/doc/current/reference/index.html)

Unfortunately, I (Martijn Luinstra) sometimes struggle to find some specific documentation. Here’s a list of pages I find useful but keep losing.

- [Requests and Responses](https://symfony.com/doc/current/components/http_foundation.html). Everything you need to know about the `Request` and `Response` classes.
- [Service tags](https://symfony.com/doc/current/service_container/tags.html) A.K.A. how to autowire services based on tags.
- [Customising Error Pages](https://symfony.com/doc/current/controller/error_pages.html). This is how our error pages have been customised.
- [Console: Symfony Style](https://symfony.com/doc/current/console/style.html). This has everything you need to make console commands look good

## Overview of the codebase

The codebase mostly follows [Symfony’s default directory structure](https://symfony.com/doc/current/best_practices.html#use-the-default-directory-structure), but you will find some unfamiliar parts when navigating the codebase. This section aims to explain the following:

```
repo/
├─ data/
├─ opt/
└─ src/
    ├─ Menu.php
    ├─ Bridge/
    ├─ DataIter/
    ├─ DataModel/
    ├─ Legacy/
    ├─ Markup/
    ├─ Policy/
    ├─ SignUp/
    ├─ Twig/
    └─ Utils/
```

### repo/data/
This directory contains SQL files for initialising a database. `structure.sql` creates empty tables, whereas `webcie-minimal.sql` creates tables and populates them with some data for testing.

### repo/opt/
This directory contains the python code running our the face detection software used in the photo books. Don’t worry about it unless you wish to work on that software specifically.

### repo/src/Menu.php
This service specifies how to render the different menus.

### repo/src/Bridge
All connections with external services (e.g. Secretary, Filemanager, etc.) are defined here. Most of them use the [Symfony HTTP Client](https://symfony.com/doc/current/http_client.html) to connect to the service.

As a general rule, these classes do not make any API calls from their constructor, or any method called by their constructor. This is to fail more gracefully if something is wrong with the connection or the external service. Most of the time, these classes are autowired when used and if the constructor throws an exception (i.e. when an API call returns an error), whole parts of the website may stop functioning. If the constructors cannot throw errors, the errors can be dealt with locally, when the external service is called.

### repo/src/DataIter and repo/src/DataModel
Home of all classes representing data and managing the connection with the database. DataIter is roughly equivalent to Symfony/Doctrine’s Entity, while DataModel is roughly equivalent to Symfony/Doctrine’s EntityManager. See also the [legacy documentation](/docs/legacy.md).

### repo/src/Legacy
Home of the legacy components. See also the [legacy documentation](/docs/legacy.md).

### repo/src/Markup
This is the parser for the [Markup language](https://wiki.svcover.nl/documentation/website/markup) used throughout the website. Should you want/need to work on this code, it’s documented inline. Keep in mind that the rendered markup should always be valid HTML.

### repo/src/Policy
This directory contains the policies used for authorisation, which makes the directory more or less equivalent to the `src/security` directory in Symfony’s default directory structure. See also the [legacy documentation](/docs/legacy.md).

### repo/src/SignUp
This directory contains the field type definitions for the sign up system as well as some logic for creating and rendering forms and their entities.

### repo/src/Twig
Though part of the Symfony default directory structure, this one is worth including as it contains our own Twig extensions. The AppExtension provides functionality needed to implement certain features, the LegacyExtension makes some of the legacy components available in templates, and the MarkupExtension provides filters to deal with Markup.

Some additional functionality is provided through globals, as defined in `config/packages/twig.yaml`.

### repo/src/Utils
This directory is home to various utilities.

## Miscellaneous

### Caching data
We use Symfony’s [Cache](https://symfony.com/doc/current/cache.html) ([additional documentation](https://symfony.com/doc/current/components/cache.html)) to cache various items and data. This component supports two Caching API's: PSR-6 and contracts. The PSR-6 API provides more flexibility, but is also harder to use.

The contracts API is easier to use at the cost of flexibility. But unlike PSR-6, it supports adding tags to cached items. This allows invalidation of all cached item with a certain tag.

A good use case for this is caching profile pictures, where we need to cache several versions of a single picture but all of them are tagged with the member ID. So, when a member changes their picture we can invalidate all versions of the old profile picture by invalidating the tag.

At the moment of writing, we only use the contracts API.

### Sending emails
Emails are sent using Symfony’s [Mailer](https://symfony.com/doc/current/mailer.html) component. This is useful to know but doesn’t need any further elaboration.
