# Installation

Require the bundle in your composer.json file:

```
$ composer require egeloen/http-adapter-bundle --no-update
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
$ composer update egeloen/http-adapter-bundle
```
