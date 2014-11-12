# Usage

As the Ivory http adapter library is a technical library, the only purpose of this bundle is to expose service to you
via the container. The bundle is highly configurable and you should be able to archive all use cases (I hope).

## Adapters

By default, if you don't provide any configuration, the bundle will automatically create an adapter named `default`
using the PHP socket and used as default http adapter. That means the service `ivory.http_adapter.default` can be used
and it is aliased to `ivory.http_adapter` as it is the default http adapter.

Then, if you want to create your own http adapter services, you can define as much as you want.

``` yaml
ivory_http_adapter:
    default: my_curl_adapter
    adapters:
        my_socket_adapter:
            type: socket
        my_curl_adapter:
            type: curl
```

Here, three services will be available: `ivory.http_adapter.my_socket_adapter`, `ivory.http_adapter.my_curl_adapter`
and `ivory.http_adapter` (alias of `ivory.http_adapter.my_curl_adapter`). Be aware, if you don't provide default
adapter, the first one in the list will be used.

Additionally, the type can be either: `buzz`, `curl`, `file_get_contents`, `fopen`, `guzzle`, `guzzle_http`, `httpful`,
`socket`, `zend1` or `zend2`.

Finally, when you are in debug mode, the stopwatch http adapter and the stop watch subscriber are used in order to time
your requests for all adapters.

## Configuration

Each http adapters can be configured globally (for all http adapters) or locally (for a specific http adapter only).
The configuration documentation is available
[here](https://github.com/egeloen/ivory-http-adapter/blob/master/doc/configuration.md).

### Global configuration

``` yaml
ivory_http_adapter:
    configs:
        protocol_version: 1.1
        keep_alive: false
        encoding_type: application/json
        boundary: abcefghijklmnopqrstuvwxyz
        timeout: 10
        user_agent: Ivory Http Adapter
```

### Local configuration

``` yaml
ivory_http_adapter:
    adapters:
        my_adapter:
            type: socket
            configs:
                protocol_version: 1.1
                keep_alive: false
                encoding_type: application/json
                boundary: abcefghijklmnopqrstuvwxyz
                timeout: 10
                user_agent: Ivory Http Adapter
```

## Subscribers

For each http adapters, you can register subscribers globally (for all http adapters) or locally (for a specific http
adapter only). By default, there is no subscribers registered. The subscriber documentation is available
[here](https://github.com/egeloen/ivory-http-adapter/blob/master/doc/events.md#available-subscribers).

### Global subscribers

``` yaml
ivory_http_adapter:
    subscribers:
        basic_auth:
            username: egeloen
            password: pass
        cookie: ~
        history: ~
        logger: ~
        redirect: ~
        retry: ~
        status_code: ~
        stopwatch: ~
```

### Local subscribers

``` yaml
ivory_http_adapter:
    adapters:
        my_adapter:
            type: socket
            subscribers:
                basic_auth:
                    username: egeloen
                    password: pass
                cookie: ~
                history: ~
                logger: ~
                redirect: ~
                retry: ~
                status_code: ~
                stopwatch: ~
```

### Basic auth subscriber

The basic auth `username` and `password` values are mandatory but you can provide optionally a domain as matcher via
the matcher option:

``` yaml
# ...
basic_auth:
    username: egeloen
    password: pass
    matcher: my-domain.com
```

### Cookie subscriber

The cookie subscriber can use different jar implementation according to your needs. To use the file one:

``` yaml
# ...
cookie: file
```

The available cookie jars are:

 * `default`: An in-memory cookie jar implementation (the default one used).
 * `file`: A file cookie jar implementation.
 * `session`: A session cookie jar implementation.
 * `your_service_name`: Your own cookie jar implementation.

### History subscriber

The history subscriber can use different journal implementation according to your needs. For now, there is only one
implementation, but you can use your own via its service name.

``` yaml
# ...
history: your_service_name
```

### Logger subscriber

The logger subscriber can use different psr logger implementation according to your needs. By default, it uses
monolog as default logger via the service `logger` but you can use your own via its service name:

``` yaml
# ...
logger: your_service_name
```

### Redirect subscriber

The redirect subscriber can use different strategies according to your needs:

``` yaml
# ...
redirect:
    max: 5
    strict: false
    throw_exception: true
```

### Stopwatch subscriber

The stopwatch subscriber allows to monitor the adapter via the Symfony2 stopwatch component. In debug mode, this
subscriber is automatically registered on all adapters in order to monitor them via the timeline. Additionally, you can
register an other subscriber using your own stopwatch service with:

``` yaml
stopwatch: my_service_name
```

### Custom subscribers

Everything explain still here does not allow you to register your own subscribers. Basically, everything is managed via
service tags. You can either register listeners or subscribers using respectively `ivory.http_adapter.listener` and
`ivory.http_adapter.subscriber` tags:

``` yaml
services:
    my_listener:
        class: My\Listener
        tags:
                name: ivory.http_adapter.listener
                event: ivory.http_adapter.post_send
                method: onPostSend
                priority: -10
                adapter: my_adapter

    my_subscriber:
        class: My\Subscriber
        tags:
            -
                name: ivory.http_adapter.subscriber
                adapter: my_adapter
```

Obviously, it is much more simple to configure a subscriber than a listener. Anyway, you can choose what you prefer.
Additionally, the `adapter` node is optional, so, if you don't provide it, the listener/subscriber will be registered
on all adapters.
