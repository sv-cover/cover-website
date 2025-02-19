# Legacy Components

For the time being, the website still relies on some old components from before the migration to Symfony. These components are labeled as “legacy” in the codebase. Ultimately, all legacy components and everything depending on them should be replaced by a Symfony-native equivalent.

Unfortunately, these components contain little inline documentation. This documentation aims to explain the basic concepts and developers are encouraged to look at the implementation for more information.

## Database
The database component provides a PHP API for database interactions and PHP representation for data stored in the database. This component provides similar functionality as Doctrine does in Symfony.

### DataIter
An instance of DataIter corresponds with a row in a database table. For example, a DataIterMember instance contains data for a specific member. This makes DataIter roughly equivalent to Doctrine’s `Entity` and to ease future migration to Doctrine, DataIters are not Symfony Services.

Data stored in DataIters can be accessed through getters, but array syntax is also available. This means data can be accessed and adjusted in the following ways:

```php
$iter->get('field');
$iter['field'];

$iter->set('field', $value);
$iter['field'] = $value;
```

A DataIter can defined any function of the format `get_field` and `set_field` to customize this behaviour. These can be called through the same syntax. Of course, they can also be called directly (`$iter->get_field()` and `$iter->set_field()`), however this will bypass the getter cache. Therefore calling getters this way might have a performance penalty, and calling setters this way might lead to unexpected side effects (i.e. data seemingly not being updated after calling a setter).

Please refer to the DataIter source code for more.

### DataModel
DataModels provide a PHP API for interacting with the database, with the main goal of obtaining DataIters or writing them to the database. This makes DataModels roughly equivalent to Doctrine’s `EntityManager`. DataModels are shared Symfony services, meaning they have only one instance and can be autowired.

DataModels have methods for standard database operations such as the following:

- `insert(DataIter $iter)`
- `update(DataIter $iter)`
- `delete(DataIter $iter)`
- `find(array|string $conditions): Array<DataIter>`
- `find_one(array|string $conditions): DataIter`
- `get_iter($id): DataIter`

In the find functions, conditions can be provided as a SQL string (e.g. `"id = 1 AND name LIKE 'cover'"`) or as an array (e.g. `['id' => 1, 'name__contains' => 'cover']`). The array syntax is generally preferred, as this takes care of escaping.

Additionally, DataModel provides a function called `new_iter`, which returns a new DataIter instance for this model.

Many models provide more functions for custom behaviour or advanced querying.

Please refer to the DataModel source code for more.

## Authentication
As the name implies, the authentication component provides the logic for user authentication. The component is always accessed through the `App\Legacy\Authentication\Authentication` service, which can be autowired.

This service provides the following API:

- `getAuth()` returns the current SessionProvider. A SessionProvider allows access to the current session through its `get_session()` method. It also allows testing if a user is currently logged in through the `logged_in()` method.
- `getIdentity()` returns the current IdentityProvider. If a user is logged in, this allows access to the current member through its `member()` function. Additionally, the IdentityProvider has some shorthand functions to get the current member’s properties (e.g. `get($key, $default)`, `is-member()`, or `member_in_committee($committee)`).

Additionally, the Authentication service provides some short hand syntax:

- `Authentication->auth` is short for `Authentication->getAuth()`.
- `Authentication->identityh` is short for `Authentication->getIdentityh()`.
- `Authentication->loggedIn` is short for `Authentication->getAuth()->logged_in()`.

The Authentication service is available in templates under the name `auth`.

## Policy
The Policy component provides access control. It’s used to determine whether the current member can perform a certain action for a DataIter. This makes policies roughly equivalent to voters in Symfony’s Security component.

All policies implement at least the following functions, but might implement more:

- `userCanCreate(DataIter $iter): bool`
- `userCanRead(DataIter $iter): bool`
- `userCanUpdate(DataIter $iter): bool`
- `userCanDelete(DataIter $iter): bool`

All policies are services and can be autowired. Alternatively, all policies can be accessed through the `App\Legacy\Policy\Policy` service, which has a `get` function to retrieve the policy for a DataIter or DataModel. Additionally, it provides a shorthand syntax to check a policy for an iter: `Policy->userCanRead($iter)` is equivalent to `Policy->get('DataModelX')->userCanRead($iter)` and to `XPolicy->userCanRead($iter)`.

The policy service is available in templates under the name `policy`.

Please refer to the Policy source code for more.

## Translations (i18n)
The translation component (I18n) provides translation features. While the languages other than English have been deprecated since 2018, most of the website still implements translation functionality. This component implements the required functions for backwards compatibility.

In PHP, the following functions are available globally:

- `__($message)` to translate a message string. Most strings are wrapped in this function.
- `__N($singular, $plural, $number)` to translate and pluralize a message based on a given number.

In Twig, these functions are also available. Here, the `__N` has an additional parameter for a display version of the number.

A few more functions are available in Twig, but those are rarely used. Please refer to the LegacyExtension source code for more.
