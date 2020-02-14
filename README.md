Immutable objects in PHP
-----------------
This library provides native immutable objects for PHP>=7.4.2

[![Build Status](https://img.shields.io/travis/com/lisachenko/immutable-object/master)](https://travis-ci.org/lisachenko/immutable-object)
[![GitHub release](https://img.shields.io/github/release/lisachenko/immutable-object.svg)](https://github.com/lisachenko/immutable-object/releases/latest)
[![Minimum PHP Version](http://img.shields.io/badge/php-%3E%3D%207.4-8892BF.svg)](https://php.net/)
[![License](https://img.shields.io/packagist/l/lisachenko/immutable-object.svg)](https://packagist.org/packages/lisachenko/immutable-object)

Rationale
------------
How many times have you thought it would be nice to have immutable objects in PHP? How many errors could be avoided if
the objects could warn about attempts to change them outside the constructor? Unfortunately, 
[Immutability RFC](https://wiki.php.net/rfc/immutability) has never been implemented.

What to do? 
Of course, there is [psalm-immutable](https://psalm.dev/docs/annotating_code/supported_annotations/#psalm-immutable) 
annotation which can help us find errors when running static analysis. But during the development of the code itself, 
we will not see any errors when trying to change a property in such an object.

However, with the advent of [FFI](https://www.php.net/manual/en/book.ffi.php) and the 
[Z-Engine](https://github.com/lisachenko/z-engine) library, it became possible to use PHP to expand the capabilities
of the PHP itself.

Pre-requisites and initialization
--------------
As this library depends on `FFI`, it requires PHP>=7.4 and `FFI` extension to be enabled.

To install this library, simply add it via `composer`:
```bash
composer require lisachenko/immutable-object
```
To enable immutability, you should activate `FFI` bindings for PHP first by initializing the `Z-Engine` library with
short call to the `Core::init()`. And you also need to activate immutability handler for development mode (or do not
call it for production mode to follow Design-by-Contract logic and optimize performance and stability of application)

```php
use Immutable\ImmutableHandler;
use ZEngine\Core;

include __DIR__.'/vendor/autoload.php';

Core::init();
ImmutableHandler::install();
```

Probably, `Z-Engine` will provide an automatic self-registration later, but for now it's ok to perform initialization
manually.

Applying immutability
--------
In order to make your object immutable, you just need to implement the `ImmutableInterface` interface marker in your
class and this library automatically convert this class to immutable. Please note, that this interface should be added
to every class (it isn't guaranteed that it will work child with parent classes that was declared as immutable)

Now you can test it with following example:
```php
<?php
declare(strict_types=1);

use Immutable\ImmutableInterface;
use Immutable\ImmutableHandler;
use ZEngine\Core;

include __DIR__.'/vendor/autoload.php';

Core::init();
ImmutableHandler::install();

final class MyImmutableObject implements ImmutableInterface
{
    public $value;

    public function __construct($value)
    {
        $this->value = $value;
    }
}

$object = new MyImmutableObject(100);
echo $object->value;  // OK, 100
$object->value = 200; // FAIL: LogicException: Immutable object could be modified only in constructor or static methods
```

Low-level details (for geeks)
--------------
Every PHP class is represented by `zend_class_entry` structure in the engine:

```text
struct _zend_class_entry {
    char type;
    zend_string *name;
    /* class_entry or string depending on ZEND_ACC_LINKED */
    union {
        zend_class_entry *parent;
        zend_string *parent_name;
    };
    int refcount;
    uint32_t ce_flags;

    int default_properties_count;
    int default_static_members_count;
    zval *default_properties_table;
    zval *default_static_members_table;
    zval ** static_members_table;
    HashTable function_table;
    HashTable properties_info;
    HashTable constants_table;

    struct _zend_property_info **properties_info_table;

    zend_function *constructor;
    zend_function *destructor;
    zend_function *clone;
    zend_function *__get;
    zend_function *__set;
    zend_function *__unset;
    zend_function *__isset;
    zend_function *__call;
    zend_function *__callstatic;
    zend_function *__tostring;
    zend_function *__debugInfo;
    zend_function *serialize_func;
    zend_function *unserialize_func;

    /* allocated only if class implements Iterator or IteratorAggregate interface */
    zend_class_iterator_funcs *iterator_funcs_ptr;

    /* handlers */
    union {
        zend_object* (*create_object)(zend_class_entry *class_type);
        int (*interface_gets_implemented)(zend_class_entry *iface, zend_class_entry *class_type); /* a class implements this interface */
    };
    zend_object_iterator *(*get_iterator)(zend_class_entry *ce, zval *object, int by_ref);
    zend_function *(*get_static_method)(zend_class_entry *ce, zend_string* method);

    /* serializer callbacks */
    int (*serialize)(zval *object, unsigned char **buffer, size_t *buf_len, zend_serialize_data *data);
    int (*unserialize)(zval *object, zend_class_entry *ce, const unsigned char *buf, size_t buf_len, zend_unserialize_data *data);

    uint32_t num_interfaces;
    uint32_t num_traits;

    /* class_entry or string(s) depending on ZEND_ACC_LINKED */
    union {
        zend_class_entry **interfaces;
        zend_class_name *interface_names;
    };

    zend_class_name *trait_names;
    zend_trait_alias **trait_aliases;
    zend_trait_precedence **trait_precedences;

    union {
        struct {
            zend_string *filename;
            uint32_t line_start;
            uint32_t line_end;
            zend_string *doc_comment;
        } user;
        struct {
            const struct _zend_function_entry *builtin_functions;
            struct _zend_module_entry *module;
        } internal;
    } info;
};
```
You can notice that this structure is pretty big and contains a lot of interesting information. But we are interested in
the `interface_gets_implemented` callback which is called when some class trying to implement concrete interface. Do you
remember about `Throwable` class that throws an error when you are trying to add this interface to your class? This is
because `Throwable` class has such handler installed that prevents implementation of this interface in user-land.

We are going to use this hook for our `ImmutableInterface` interface to adjust original class behaviour. `Z-Engine` 
provides a method called `ReflectionClass->setInterfaceGetsImplementedHandler()` that is used for installing custom 
`interface_gets_implemented` callback.

But how we will make existing class and objects immutable? Ok, let's have a look at one more structure, called
`zend_object`. This structure represents an object in PHP.

```text
struct _zend_object {
    zend_refcounted_h gc;
    uint32_t          handle;
    zend_class_entry *ce;
    const zend_object_handlers *handlers;
    HashTable        *properties;
    zval              properties_table[1];
};
``` 
You can see that there is handle of object (almost not used), there is a link to class entry (`zend_class_entry *ce`),
properties table and strange `const zend_object_handlers *handlers` field. This `handlers` field points to the list of
object handlers hooks that can be used for object casting, operator overloading and much more:

```text
struct _zend_object_handlers {
    /* offset of real object header (usually zero) */
    int                                      offset;
    /* object handlers */
    zend_object_free_obj_t                  free_obj;             /* required */
    zend_object_dtor_obj_t                  dtor_obj;             /* required */
    zend_object_clone_obj_t                 clone_obj;            /* optional */
    zend_object_read_property_t             read_property;        /* required */
    zend_object_write_property_t            write_property;       /* required */
    zend_object_read_dimension_t            read_dimension;       /* required */
    zend_object_write_dimension_t           write_dimension;      /* required */
    zend_object_get_property_ptr_ptr_t      get_property_ptr_ptr; /* required */
    zend_object_get_t                       get;                  /* optional */
    zend_object_set_t                       set;                  /* optional */
    zend_object_has_property_t              has_property;         /* required */
    zend_object_unset_property_t            unset_property;       /* required */
    zend_object_has_dimension_t             has_dimension;        /* required */
    zend_object_unset_dimension_t           unset_dimension;      /* required */
    zend_object_get_properties_t            get_properties;       /* required */
    zend_object_get_method_t                get_method;           /* required */
    zend_object_call_method_t               call_method;          /* optional */
    zend_object_get_constructor_t           get_constructor;      /* required */
    zend_object_get_class_name_t            get_class_name;       /* required */
    zend_object_compare_t                   compare_objects;      /* optional */
    zend_object_cast_t                      cast_object;          /* optional */
    zend_object_count_elements_t            count_elements;       /* optional */
    zend_object_get_debug_info_t            get_debug_info;       /* optional */
    zend_object_get_closure_t               get_closure;          /* optional */
    zend_object_get_gc_t                    get_gc;               /* required */
    zend_object_do_operation_t              do_operation;         /* optional */
    zend_object_compare_zvals_t             compare;              /* optional */
    zend_object_get_properties_for_t        get_properties_for;   /* optional */
};
```
But there is one important fact. This field is declared as `const`, this means that it cannot be changed in runtime, we
need to initialize it only once during object creation. We can not hook into default object creation process without 
writing a C extension, but we have an access to the `zend_class_entry->create_object` callback. We can replace it with
our own implementation that could allocate our custom object handlers list for this class and save a pointer to it in
memory, providing API to modify object handlers in runtime, as they will point to one single place.

We will override low-level `write_property` handler to prevent changes of properties for every instance of class. But 
we should preserve original logic in order to allow initialization in class constructor, otherwise properties will be 
immutable from the beginning. Also we should throw an exception for attempts to unset property in `unset_property` hook.
And we don't want to allow getting a reference to properties to prevent indirect modification like `$obj->field++` or
`$byRef = &$obj->field; $byRef++;`.

This is how immutable objects in PHP are implemented. Hope that this information will give you some food for thoughts )

Code of Conduct
--------------

This project adheres to the Contributor Covenant [code of conduct](CODE_OF_CONDUCT.md).
By participating, you are expected to uphold this code.
Please report any unacceptable behavior.

License
-------
This library is distributed under [MIT-license](LICENSE) and it uses `Z-Engine` library distributed under 
**RPL-1.5** with additional [premium license](ZENGINE_LICENSE_PREMIUM).
