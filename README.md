<p align="center">
    <a href="https://www.supportpal.com" target="_blank"><img src="https://www.supportpal.com/assets/img/logo_blue_small.png" /></a>
    <br>
    <strong>Pollcast.</strong> A Laravel broadcast driver using short polling.
</p>

<p align="center">
<a href="https://github.com/supportpal/pollcast/actions"><img src="https://img.shields.io/github/workflow/status/supportpal/pollcast/test" alt="Build Status"></a>
<a href="https://codecov.io/gh/supportpal/pollcast">
  <img src="https://codecov.io/gh/supportpal/pollcast/branch/master/graph/badge.svg?token=R56Z5T3SBS"/>
</a>
<a href="https://packagist.org/packages/supportpal/pollcast"><img src="https://img.shields.io/packagist/dt/supportpal/pollcast" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/supportpal/pollcast"><img src="https://img.shields.io/packagist/v/supportpal/pollcast" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/supportpal/pollcast"><img src="https://img.shields.io/packagist/l/supportpal/pollcast" alt="License"></a>
</p>

# Pollcast

"Pollcast" is short for XHR polling using Laravel Broadcasting.

## Motivation

Laravel supports several broadcast drivers, but all of these either require integration
with a third party service such as Pusher, or installation of additional software. The
motivation behind this package is to provide a broadcast driver which works in all
environments without additional configuration and is compatible with Laravel Echo. 

In most cases, where you have control over the environment, you'll want to use web sockets.

## Installation

Require this package with composer:

```
composer require supportpal/pollcast
```

Add the ServiceProvider class to the `providers` array in `config/app.php`. In Laravel versions 5.5 and beyond, this step can be skipped if package auto-discovery is enabled.

```
\SupportPal\Pollcast\ServiceProvider::class,
```

Change the default broadcast driver to in your `.env` file:

```
BROADCAST_DRIVER=pollcast
```

Add the database tables:

```
php artisan migrate --path=vendor/supportpal/pollcast/database/migrations
```

Finally, publish the config file `config/pollcast.php` if required:

```
php artisan vendor:publish --provider="SupportPal\Pollcast\ServiceProvider"
```

## Usage

Require the [pollcast-js](https://github.com/supportpal/pollcast-js/) package:
```
npm i --save pollcast-js laravel-echo
```

Create a fresh Laravel `Echo` instance and provide the `PollcastConnector`
as the broadcaster:

```js
import Echo from 'laravel-echo';
import PollcastConnector from 'pollcast-js'

window.Echo = new Echo({
  broadcaster: PollcastConnector,
  csrfToken: "{{ csrf_token() }}",
  routes: {
    connect: "{{ route('supportpal.pollcast.connect') }}",
    receive: "{{ route('supportpal.pollcast.receive') }}",
    publish: "{{ route('supportpal.pollcast.publish') }}",
    subscribe: "{{ route('supportpal.pollcast.subscribe') }}",
    unsubscribe: "{{ route('supportpal.pollcast.unsubscribe') }}"
  },
    polling: {{ Config.get('pollcast.polling_interval', 5000) }}
});
```
