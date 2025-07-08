<div align="center">

# YAP - Yet Another Panel

**A modern, feature-rich network services management panel**

[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)
[![PHP Version](https://img.shields.io/badge/PHP-8.2%2B-blue.svg)](https://php.net)
[![Laravel](https://img.shields.io/badge/Laravel-11.9%2B-red.svg)](https://laravel.com)
[![React](https://img.shields.io/badge/React-18-blue.svg)](https://reactjs.org)
[![GitHub Sponsors](https://img.shields.io/github/sponsors/hldh214?style=social)](https://github.com/sponsors/hldh214)

[Features](#features) • [Installation](#installation) • [Documentation](#documentation) • [Contributing](#contributing) • [Support](#support)

</div>

---

## 📖 Table of Contents

- [About](#about)
- [Features](#features)
- [Screenshots](#screenshots)
- [Technology Stack](#technology-stack)
- [Installation](#installation)
- [Configuration](#configuration)
- [Usage](#usage)
- [API Documentation](#api-documentation)
- [Deployment](#deployment)
- [Testing](#testing)
- [Contributing](#contributing)
- [Support](#support)
- [License](#license)
- [Acknowledgments](#acknowledgments)

## 🚀 About

YAP (Yet Another Panel) is a modern, full-featured network services management panel built with Laravel and React. It provides a comprehensive solution for managing network services, subscriptions, and user accounts with a beautiful, responsive interface.

### Why YAP?

- **Modern Tech Stack**: Built with Laravel 11, React 18, and Inertia.js
- **Admin-Friendly**: Powerful admin panel powered by Filament
- **Payment Ready**: Multiple payment gateways including Alipay and BEPUSDT
- **Monitoring**: Built-in error tracking with Sentry integration
- **Scalable**: Queue system and proper caching for high performance
- **Developer-Friendly**: Clean code, comprehensive tests, and good documentation

## ✨ Features

### Core Features
- [x] **Authentication & Authorization**
  - GitHub OAuth integration
  - Role-based access control
  - Secure session management

- [x] **User Management**
  - User registration and profile management
  - Subscription management with packages
  - Balance management and top-up system
  - User statistics and analytics dashboard

- [x] **Admin Panel**
  - Comprehensive admin interface (Filament)
  - User management and monitoring
  - System configuration and settings
  - Analytics and reporting tools

### Network Services
- [x] **Server Management**
  - Multiple server types (Relay, Vmess)
  - Server health monitoring
  - Load balancing configuration

- [x] **Configuration Generation**
  - Clash configuration auto-generation
  - QR code generation for easy setup
  - Multiple client support

### Payment & Billing
- [x] **Payment Processing**
  - Multiple payment methods (Alipay, BEPUSDT)
  - Automated billing and invoicing
  - Payment history and receipts

- [x] **Subscription Management**
  - Flexible subscription packages
  - Automatic renewal handling
  - Usage tracking and limits

### Additional Features
- [x] **Customer Support**
  - Integrated customer service system
  - Ticket management
  - Live chat support

- [x] **Monitoring & Analytics**
  - Error monitoring with Sentry
  - Performance analytics
  - Usage statistics and reporting

## 📸 Screenshots

> 🚧 Screenshots coming soon! We're working on adding visual previews of the interface.

## 🛠 Technology Stack

### Backend
| Technology | Version | Purpose |
|------------|---------|---------|
| **PHP** | 8.2+ | Server-side language |
| **Laravel** | 11.9+ | Web framework |
| **Filament** | 3.2+ | Admin panel |
| **MySQL/PostgreSQL** | Latest | Database |
| **Laravel Sanctum** | 4.0+ | API authentication |
| **Laravel Socialite** | 5.15+ | OAuth integration |

### Frontend
| Technology | Version | Purpose |
|------------|---------|---------|
| **React** | 18+ | UI framework |
| **Inertia.js** | 1.0+ | SPA integration |
| **Tailwind CSS** | 3.2+ | Styling framework |
| **Headless UI** | 2.0+ | UI components |
| **Vite** | 5.0+ | Build tool |
| **Chart.js** | 4.4+ | Data visualization |

### DevOps & Monitoring
| Technology | Purpose |
|------------|---------|
| **Sentry** | Error tracking |
| **Ansible** | Deployment automation |
| **Pest** | Testing framework |
| **Laravel Telescope** | Debugging |

## 📦 Installation

### Prerequisites

Before you begin, ensure you have the following installed:

- **PHP 8.2+** with required extensions (bcmath, yaml)
- **Composer** (latest version)
- **Node.js 18+** and npm
- **Database** (MySQL 8.0+, PostgreSQL 13+, or SQLite)
- **Web Server** (Apache 2.4+ or Nginx 1.18+)

### Quick Start

1. **Clone the repository**
   ```bash
   git clone https://github.com/hldh214/yap.git
   cd yap
   ```

2. **Install PHP dependencies**
   ```bash
   composer install --optimize-autoloader --no-dev
   ```

3. **Install Node.js dependencies**
   ```bash
   npm install
   ```

4. **Environment setup**
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

5. **Configure your environment**

   Edit `.env` file with your settings:
   ```env
   # Database Configuration
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=yap
   DB_USERNAME=your_username
   DB_PASSWORD=your_password

   # GitHub OAuth (Required)
   GITHUB_CLIENT_ID=your_github_client_id
   GITHUB_CLIENT_SECRET=your_github_client_secret
   GITHUB_REDIRECT_URI=http://localhost:8000/auth/github/callback

   # Optional: Sentry for error monitoring
   SENTRY_LARAVEL_DSN=your_sentry_dsn

   # YAP Configuration
   YAP_UNIT_PRICE=10.00
   YAP_RESET_SUBSCRIPTION_PRICE=5.00
   ```

6. **Database setup**
   ```bash
   php artisan migrate --seed
   ```

7. **Build frontend assets**
   ```bash
   npm run build
   ```

8. **Set proper permissions** (Linux/macOS)
   ```bash
   sudo chown -R www-data:www-data storage bootstrap/cache
   sudo chmod -R 775 storage bootstrap/cache
   ```

9. **Create admin user**
   ```bash
   php artisan make:filament-user
   ```

10. **Start the application**
    ```bash
    # Development
    php artisan serve
    npm run dev

    # The application will be available at http://localhost:8000
    # Admin panel: http://localhost:8000/admin
    ```

## ⚙️ Configuration

### GitHub OAuth Setup

1. Go to GitHub Settings > Developer settings > OAuth Apps
2. Create a new OAuth App with:
   - Application name: `YAP`
   - Homepage URL: `http://your-domain.com`
   - Authorization callback URL: `http://your-domain.com/auth/github/callback`
3. Copy Client ID and Client Secret to your `.env` file

### Payment Gateway Setup

#### Alipay Configuration
```env
ALIPAY_APP_ID=your_app_id
ALIPAY_PRIVATE_KEY=your_private_key
ALIPAY_PUBLIC_KEY=alipay_public_key
```

#### BEPUSDT Configuration
```env
BEPUSDT_API_KEY=your_api_key
BEPUSDT_SECRET=your_secret
```

## 📚 Usage

### For End Users
1. **Registration**: Sign up
2. **Subscription**: Choose and purchase a subscription package
3. **Configuration**: Generate and download your network configuration
4. **Management**: Monitor usage and manage your account

### For Administrators
1. **Access Admin Panel**: Visit `/admin` and login with admin credentials
2. **User Management**: View and manage user accounts
3. **System Configuration**: Configure system settings and parameters
4. **Analytics**: Monitor system performance and user statistics

## 📖 API Documentation

> 🚧 API documentation is coming soon! We're working on comprehensive API docs.

For now, you can explore the API endpoints in the `routes/api.php` file.

## 🚀 Deployment

### Manual Production Setup

1. **Web Server Configuration**
   - Point document root to `public/` directory
   - Configure SSL certificate
   - Set up proper file permissions

2. **Environment Optimization**
   ```bash
   php artisan config:cache
   php artisan route:cache
   php artisan view:cache
   php artisan optimize
   ```

3. **Queue Worker Setup**
   ```bash
   # Add to supervisor or systemd
   php artisan queue:work --daemon
   ```

## 🧪 Testing

Run the test suite:

```bash
# Run all tests
php artisan test

# Run with coverage
php artisan test --coverage

# Run specific test file
php artisan test tests/Feature/AuthTest.php
```

## 🤝 Contributing

We welcome contributions! Please see our [Contributing Guidelines](CONTRIBUTING.md) for details.

### Development Setup

1. Fork the repository
2. Create a feature branch: `git checkout -b feature/amazing-feature`
3. Make your changes and add tests
4. Run the test suite: `php artisan test`
5. Commit your changes: `git commit -m 'Add amazing feature'`
6. Push to the branch: `git push origin feature/amazing-feature`
7. Open a Pull Request

## 💬 Support

### Community Support
- **Telegram**: [yap_devs](https://t.me/yap_devs) - Join our community chat
- **GitHub Issues**: [Report bugs or request features](https://github.com/hldh214/yap/issues)
- **GitHub Discussions**: [Ask questions and share ideas](https://github.com/hldh214/yap/discussions)

### Commercial Support
For commercial support, custom development, or enterprise features, please contact us through GitHub Sponsors.

### Sponsorship
If you find YAP useful, consider sponsoring the project:

[![GitHub Sponsors](https://img.shields.io/github/sponsors/hldh214?style=for-the-badge&logo=github)](https://github.com/sponsors/hldh214)

## 📄 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

```
MIT License

Copyright (c) 2024 hldh214

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
```

## 🙏 Acknowledgments

- **Laravel Team** - For the amazing framework
- **Filament Team** - For the beautiful admin panel
- **React Team** - For the powerful frontend library
- **Inertia.js Team** - For seamless SPA integration
- **All Contributors** - Thank you for your contributions!

---

<div align="center">

**[⬆ Back to Top](#yap---yet-another-panel)**

Made with ❤️ by [hldh214](https://github.com/hldh214) and [contributors](https://github.com/hldh214/yap/contributors)

</div>
