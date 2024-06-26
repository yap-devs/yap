# Yap

Yet Another Panel for network services.

## Features

- [x] Github OAuth
- [x] User side web interface
- [x] Subscription management
- [x] Paid services for Sponsor
- [ ] Admin side web interface

## Installation

1. Clone the repository
2. Install dependencies
    ```bash
    # Backend
    composer install
    php artisan key:generate
    php artisan migrate

    # V2Bridge
    git submodule update --init
    cd v2bridge && go build
    ```
3. Frontend
    ```bash
    npm install
    npm run build
    ```
4. Permission
    ```bash
    chown -R www-data:www-data storage
    chown -R www-data:www-data bootstrap/cache
    ```

## Community

Telegram: [yap_devs](https://t.me/yap_devs)
