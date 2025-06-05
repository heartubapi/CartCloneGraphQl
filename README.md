# 🛒 CartCloneGraphQl Module

![Magento 2](https://img.shields.io/badge/Magento-2.4.7--p5-FF6C37?style=for-the-badge&logo=magento&logoColor=white)
![GraphQL](https://img.shields.io/badge/GraphQL-E10098?style=for-the-badge&logo=graphql&logoColor=white)
![PHP](https://img.shields.io/badge/PHP-8.3-777BB4?style=for-the-badge&logo=php&logoColor=white)
![License](https://img.shields.io/badge/License-MIT-green?style=for-the-badge)
![Version](https://img.shields.io/badge/Version-1.0.0-blue?style=for-the-badge)

> **🚀 An innovative solution for cloning shopping carts using GraphQL in Magento 2**

The `CartCloneGraphQl` module allows you to duplicate an existing guest cart, including its products, shipping and billing addresses, selected shipping method, discount coupons, and email. Perfect for creating shareable, pre-configured carts for customers or enabling innovative use cases like AI-driven sales agents.

🚀 **Built for Magento 2.4.7-p5** and tested with sample data, this module is lightweight, developer-friendly, and ready to enhance your e-commerce workflows!

## 📋 Table of Contents

- [✨ Features](#-features)
- [🎯 Use Cases](#-use-cases)
- [🔧 Installation](#-installation)
- [📖 Usage](#-usage)
- [⚙️ Development Setup](#️-development-setup)
- [⚠️ Important Considerations](#️-important-considerations)
- [🔌 Extensibility](#-extensibility)
- [🤝 Contributing](#-contributing)
- [📄 License](#-license)

## ✨ Features

🔥 **Core Functionality:**

- 🛍️ **Clone Guest Carts via GraphQL**: Create a new guest cart by copying an existing one using a simple GraphQL mutation
- 📧 **Data Preservation**: Maintains shipping addresses, billing information
- 🎫 **Discount Coupons**: Automatically applies coupons from the original cart
- 🚚 **Shipping Methods**: Preserves configured shipping selection
- 👥 **Guest Email**: Preserves guest email
- ⚡ **Native GraphQL**: Perfect integration with Magento 2's GraphQL API
- 👥 **Guest Carts**: Works exclusively with guest shopping carts

## 🎯 Use Cases

### 🏪 **For Online Stores**
- **Sales Agents**: Enables representatives to create pre-configured carts for customers
- **Template Carts**: Reuse complex product configurations
- **Personalized Experience**: Facilitates creation of commercial proposals

### 🤖 **AI Integration**
- **Virtual Assistants**: Perfect for AI agents that generate personalized carts
- **Automation**: Streamline sales processes with predefined configurations
- **Scalability**: Handle multiple configurations simultaneously

### 🔄 **Workflows**
- **Cart Sharing**: Enable sharing configurations between users
- **Configuration Backup**: Maintain backup copies of complex carts
- **Testing**: Facilitate testing with specific configurations

## 🔧 Installation

### Prerequisites

- ![Magento](https://img.shields.io/badge/Magento-2.4.7--p5-FF6C37) **Magento 2.4.7-p5** (Tested and supported)
- ![PHP](https://img.shields.io/badge/PHP-8.3+-777BB4) **PHP 8.3+**
- ![Composer](https://img.shields.io/badge/Composer-Latest-885630) **Composer**

### Installation Steps

1. **Clone the repository**
   ```bash
   git clone https://github.com/heartubapi/CartCloneGraphQl.git
   ```
2. **Enable the module**
   ```bash
   php bin/magento module:enable Heartub_CartCloneGraphQl
   php bin/magento setup:upgrade
   php bin/magento setup:di:compile
   ```
3. **Verify Installation**
   ```bash
   php bin/magento module:status Heartub_CartCloneGraphQl
   ```

For detailed information about module installation in Magento 2, see [Enable or disable modules](https://experienceleague.adobe.com/en/docs/commerce-operations/installation-guide/tutorials/manage-modules).

## 📖 Usage

### GraphQL Mutation

The module provides a simple GraphQL mutation to clone shopping carts:

```graphql
mutation cloneCart($cart_id: String!) {
  cloneCart(cart_id: $cart_id) 
}
```

### Example Request

**Variables:**
```json
{
    "cart_id": "O1C7necid7CjHEYRFZgv77w5JZ3aI531"
}
```

**Response:**
```json
{
    "data": {
        "cloneCart": "3yOnvf1XHG0Vb7zWrJMJiXDw31h8jULT"
    }
}
```

### Cloning Process

The module follows this systematic approach:

1. 🆕 **Creates an empty guest cart**
2. 🛍️ **Manually adds products** from the original cart
3. 📍 **Copies shipping address** information (if exists)
4. 💰 **Copies billing address** information (if exists)
5. 🚚 **Applies selected shipping method** (if exists)
6. 🎫 **Applies discount coupons** (if exists)
7. 📧 **Copies email information** (if exists)

## ⚙️ Development Setup

### Docker Environment

This module was developed and tested using the Mark Shust [Docker Magento Setup](https://github.com/markshust/docker-magento).
The module has been tested with the following Docker setup:

```yaml
services:
  app:
    image: markoshust/magento-nginx:1.24-0
    ports:
      - "80:8000"
      - "443:8443"
    volumes: &appvolumes
      - ~/.composer:/var/www/.composer:cached
      - appdata:/var/www/html
      - sockdata:/sock
      - ssldata:/etc/nginx/certs

  phpfpm:
    image: markoshust/magento-php:8.3-fpm-4
    volumes: *appvolumes

  db:
    image: mariadb:10.6
    ports:
      - "3306:3306"
    volumes:
      - dbdata:/var/lib/mysql

  redis:
    image: redis:7.2-alpine
    ports:
      - "6379:6379"

  opensearch:
    image: markoshust/magento-opensearch:2.12-0
    ports:
      - "9200:9200"
      - "9300:9300"

  rabbitmq:
    image: markoshust/magento-rabbitmq:3.13-0
    ports:
      - "15672:15672"
      - "5672:5672"

  mailcatcher:
    image: sj26/mailcatcher:v0.10.0
    ports:
      - "1080:1080"
```

### Testing Environment

- **Magento Version**: 2.4.7-p5
- **Sample Data**: Installed and tested
- **Docker Stack**: Mark Shust's Magento Docker environment

## ⚠️ Important Considerations

### ⚡ Development Variations

> **Warning**: Depending on your development type, each part of cart creation and checkout flow may vary. It may be necessary to modify and add these implementations to the module for everything to work properly.

### 🔧 Customization Requirements

Different Magento installations may require:

- **Custom checkout steps**
- **Third-party payment integrations**
- **Additional product options**
- **Custom shipping methods**
- **Extended address fields**

### 🛠️ Implementation Notes

- Currently supports **guest-to-guest** cart cloning only
- Currently supports **simple and configurable** product types only
- Designed for flexibility to accommodate various checkout workflows
- May require customization for specific business requirements

## 🔌 Extensibility

Extension developers can interact with the `Heartub_CartCloneGraphQl` module:

### Plugin System
For more information about the Magento extension mechanism, see [Magento plugins](https://developer.adobe.com/commerce/php/development/components/plugins).

### Dependency Injection
[The Magento dependency injection mechanism](https://developer.adobe.com/commerce/php/development/components/dependency-injection) enables you to override the functionality of the `Heartub_CartCloneGraphQl` module.

### Extension Points

- **Pre-clone hooks**: Add custom logic before cloning
- **Post-clone hooks**: Execute actions after successful cloning
- **Product addition customization**: Modify how products are added
- **Address handling**: Customize address copying logic

## 📊 Roadmap

- [ ] Support for logged-in customer carts
- [ ] Bulk cart cloning functionality
- [ ] Advanced filtering options
- [ ] REST API endpoints
- [ ] Admin panel interface
- [ ] Cart template management

## 🐛 Issues & Support

Found a bug or need help? Please:

1. Check existing [Issues](https://github.com/heartubapi/CartCloneGraphQl/issues)
2. Create a new issue with detailed information
3. Include Magento version and module version
4. Provide steps to reproduce

## 📄 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

---

## 🌟 Show Your Support

Give a ⭐️ if this project helped you!

[![GitHub stars](https://img.shields.io/github/stars/heartubapi/CartCloneGraphQl?style=social)](https://github.com/heartubapi/CartCloneGraphQl/stargazers)
[![GitHub forks](https://img.shields.io/github/forks/heartubapi/CartCloneGraphQl?style=social)](https://github.com/heartubapi/CartCloneGraphQl/network/members)

---

## 📞 Contact

**Developer**: Heartub ```heartub.api@gmail.com```

**Repository**: [https://github.com/heartubapi/CartCloneGraphQl](https://github.com/heartubapi/CartCloneGraphQl)

---

<div align="center">

**Made with ❤️ for the Magento Community**

![Built with GraphQL](https://img.shields.io/badge/Built%20with-GraphQL-E10098?style=flat-square&logo=graphql)
![Powered by Magento](https://img.shields.io/badge/Powered%20by-Magento%202-FF6C37?style=flat-square&logo=magento)

</div>
