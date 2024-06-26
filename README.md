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
    composer install
    php artisan key:generate
    php artisan migrate
    cd v2bridge && go build  # Remember to sync the submodule first
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
