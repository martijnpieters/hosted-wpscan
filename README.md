# Hosted WPScan

[WPScan](https://github.com/wpscanteam/wpscan) implemented with the PHP micro-framework [Silex](https://github.com/silexphp/Silex).

## Prerequisites

- This project relies on a docker installation of WPScan, see their [documentation](https://github.com/wpscanteam/wpscan#docker) for installation instructions.
- `php >= 7.0`

## Installation

Install this repository through [Composer](http://getcomposer.org/):
```
$ composer install
```

After retrieving the required packages, point your favourite webserver to the `app/` directory and start using the hosted WPScan.

## Run development server

Run PHP's built-in development server with:
```bash
$ php -S 127.0.0.1:5000 app/index.php
```

After that, point your web browser to [http://127.0.0.1:5000](http://127.0.0.1:5000) and play with it. 😄
