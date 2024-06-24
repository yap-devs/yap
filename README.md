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
    ```
3. Frontend
    ```bash
    npm install
    npm run build
    ```
4. ProtoBuf(If you want to re-compile the proto file)
    ```bash
    git submodule update --init
    protoc --proto_path=v2ray-core --proto_path=protobuf/src --php_out=pb $(find v2ray-core -iname "*.proto")
    ```

## Community

Telegram: [yap_devs](https://t.me/yap_devs)
