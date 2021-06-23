<p align="center">
    <a href="https://www.supportpal.com" target="_blank"><img src="https://www.supportpal.com/assets/img/logo_blue_small.png" /></a>
    <br>
    <strong>Pollcast.</strong> A Laravel broadcast driver using short polling.
</p>

<p align="center">
<a href="https://github.com/supportpal/pollcast/actions"><img src="https://img.shields.io/github/workflow/status/supportpal/pollcast/ci" alt="Build Status"></a>
<a href="https://packagist.org/packages/supportpal/pollcast"><img src="https://img.shields.io/packagist/dt/supportpal/pollcast" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/supportpal/pollcast"><img src="https://img.shields.io/packagist/v/supportpal/pollcast" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/supportpal/pollcast"><img src="https://img.shields.io/packagist/l/supportpal/pollcast" alt="License"></a>
</p>

# Pollcast

"Pollcast" is short for XHR polling using Laravel Broadcasting.

## Motivation

Laravel supports several broadcast drivers but all of these either require integration
with a third party service such as Pusher, or installation of additional software. The
motivation behind this package is to provide a broadcast driver which works in all
environments without additional configuration and is compatible with Laravel Echo. 

In most cases, where you have control over the environment, you'll want to use web sockets.

## Installation

Require this package with composer:

```
composer require supportpal/pollcast
```

Add the ServiceProvider class to the `providers` array in `config/app.php`:

```
\SupportPal\Pollcast\ServiceProvider::class,
```

Add the `pollcast` driver to `config/broadcasting.php`:

```
'pollcast' => [
    'driver' => 'pollcast',
]
```

Change the default broadcast driver to in your `.env` file:

```
BROADCAST_DRIVER=pollcast
```

Add the database tables:

```
php artisan migrate --path=vendor/supportpal/pollcast/database/migrations
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
  }
});
```
