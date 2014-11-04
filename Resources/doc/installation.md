# Installation

Require the bundle in your composer.json file:

```
{
    "require": {
        "egeloen/http-adapter-bundle": "~0.1",
    }
}
```

Register the bundle:

``` php
// app/AppKernel.php

public function registerBundles()
{
    return array(
        new Ivory\HttpAdapterBundle\IvoryHttpAdapterBundle(),
        // ...
    );
}
```

Install the bundle:

```
$ composer update
```
