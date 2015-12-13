# README

Before starting, be aware **this package is deprecated in favor of [php-http](https://github.com/php-http)**. 
That means bugfixes would be accepted but new features will not (except maybe for already opened PRs).

[![Build Status](https://secure.travis-ci.org/egeloen/IvoryHttpAdapterBundle.png?branch=master)](http://travis-ci.org/egeloen/IvoryHttpAdapterBundle)
[![Coverage Status](https://coveralls.io/repos/egeloen/IvoryHttpAdapterBundle/badge.png?branch=master)](https://coveralls.io/r/egeloen/IvoryHttpAdapterBundle?branch=master)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/egeloen/IvoryHttpAdapterBundle/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/egeloen/IvoryHttpAdapterBundle/?branch=master)
[![Dependency Status](http://www.versioneye.com/php/egeloen:http-adapter-bundle/badge.svg)](http://www.versioneye.com/php/egeloen:http-adapter-bundle)

[![Latest Stable Version](https://poser.pugx.org/egeloen/http-adapter-bundle/v/stable.svg)](https://packagist.org/packages/egeloen/http-adapter-bundle)
[![Latest Unstable Version](https://poser.pugx.org/egeloen/http-adapter-bundle/v/unstable.svg)](https://packagist.org/packages/egeloen/http-adapter-bundle)
[![Total Downloads](https://poser.pugx.org/egeloen/http-adapter-bundle/downloads.svg)](https://packagist.org/packages/egeloen/http-adapter-bundle)
[![License](https://poser.pugx.org/egeloen/http-adapter-bundle/license.svg)](https://packagist.org/packages/egeloen/http-adapter-bundle)

The bundle integrates the Ivory http adapter [library](https://github.com/egeloen/ivory-http-adapter) into Symfony2.
Basically, this library allows to issue HTTP requests with PHP 5.4.8+ and so, this bundle exposes a configuration able
to create as many adapters you want with global or local configurations for all of them.

## Documentation

 1. [Installation](/Resources/doc/installation.md)
 2. [Usage](/Resources/doc/usage.md)

## Testing

The bundle is fully unit tested by [PHPUnit](http://www.phpunit.de/) with a code coverage close to **100%**. To
execute the test suite, check the travis [configuration](/.travis.yml).

## Contribute

We love contributors! Ivory is an open source project. If you'd like to contribute, feel free to propose a PR!.

## License

The Ivory Http Adapter Bundle is under the MIT license. For the full copyright and license information, please read the
[LICENSE](/LICENSE) file that was distributed with this source code.
