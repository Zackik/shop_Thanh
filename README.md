# 🛒 Thanh Buy - E-Commerce Website

A complete PHP & MySQL E-Commerce website with User, Admin Dashboard, Shopping Cart, Order Management and REST API.

![PHP](https://img.shields.io/badge/PHP-8.x-blue)
![MySQL](https://img.shields.io/badge/MySQL-Database-orange)
![TailwindCSS](https://img.shields.io/badge/TailwindCSS-Frontend-38BDF8)
![REST API](https://img.shields.io/badge/API-REST-green)
![License](https://img.shields.io/badge/License-MIT-red)

---

# 📖 Project Overview

Thanh Buy is an online shopping system developed using **PHP**, **MySQL**, and **TailwindCSS**.

The system supports two roles:

- 👤 Customer
- 👨‍💼 Administrator

Customers can browse products, add items to the shopping cart, place orders, manage their profile, and cancel pending orders.

Administrators can manage products, monitor orders, update order status, receive cancellation notifications, and manage the store.

---

# ✨ Features

## Customer

- User Registration
- User Login
- JWT/Session Authentication
- View Products
- Product Details
- Shopping Cart
- Checkout
- Purchase History
- Cancel Pending Orders
- Edit Profile
- REST API Support

---

## Administrator

- Admin Login
- Dashboard
- Upload Products
- Edit Products
- Delete Products
- View All Orders
- Update Order Status
- Delete Orders
- View Sales Chart
- Notification Center
- Receive Order Cancellation Notifications

---

# 🛠 Technologies

Backend

- PHP
- MySQL
- REST API
- JSON

Frontend

- HTML5
- TailwindCSS
- JavaScript

Libraries

- Chart.js

Server

- Apache
- XAMPP

---

# 📂 Project Structure

```
shop_Thanh/

│
├── api/
│   ├── login_api.php
│   ├── register_api.php
│   ├── products_api.php
│   ├── orders_api.php
│   ├── cart_api.php
│   ├── profile_api.php
│
├── product_images/
│
├── admin_dashboard.php
├── user_dashboard.php
├── view_orders.php
├── cart.php
├── checkout.php
├── profile.php
├── login.php
├── register.php
├── db_config.php
└── index.php
```

---

# 🗄 Database

Main Tables

- users
- products
- cart
- orders
- order_items
- notifications

Relationships

```
Users
   │
   ├──────── Orders
   │            │
   │            └────── Order Items
   │
   └──────── Cart

Products
    │
    ├──── Cart
    └──── Order Items

Notifications
      │
      └──── Order Cancellation
```

---

# 🔥 REST API

## Login

```
POST /api/login_api.php
```

Body

```json
{
    "email":"admin@gmail.com",
    "password":"123456"
}
```

Response

```json
{
    "status":"success",
    "message":"Login successful",
    "user":{
        "id":1,
        "name":"Admin",
        "email":"admin@gmail.com",
        "role":"admin"
    }
}
```

---

## Register

```
POST /api/register_api.php
```

---

## Products

```
GET /api/products_api.php
```

Returns all products.

---

## Orders

```
GET /api/orders_api.php
```

Returns all orders.

---

## Cart

```
GET /api/cart_api.php
```

Returns user shopping cart.

---

## Profile

```
GET /api/profile_api.php?id=1
```

Returns user profile.

---

# 📊 Admin Dashboard

The dashboard includes

- Product Management
- Order Management
- Sales Statistics
- Chart.js Reports
- Notifications
- Product Editing
- Product Upload

---

# 🔔 Notification System

Whenever a customer cancels an order:

- Order status becomes **Canceled**
- Notification is automatically inserted into

```
notifications
```

Admin Dashboard displays

```
🔔 Notifications (3)
```

Clicking Notifications marks them as read.

---

# 🛒 Shopping Flow

```
Register
      │
      ▼
Login
      │
      ▼
Browse Products
      │
      ▼
Add to Cart
      │
      ▼
Checkout
      │
      ▼
Create Order
      │
      ▼
Purchase History
      │
      ▼
Cancel Order (Pending)
      │
      ▼
Admin Notification
```

---

# 🚀 Installation

Clone repository

```bash
git clone https://github.com/Zackik/shop_Thanh.git
```

Move project to

```
htdocs/
```

Start

- Apache
- MySQL

Import database

```
shop.sql
```

Update

```
db_config.php
```

Open browser

```
http://localhost/shop_Thanh
```

---

# 📸 Screenshots

Recommended screenshots

- Home Page
- Login
- Register
- Shopping Cart
- Checkout
- Purchase History
- Admin Dashboard
- Product Management
- Order Management
- Notifications
- REST API (Postman)

---

# 👨‍💻 Author

**Thanh Bui**

Software Engineering Student

GitHub

https://github.com/Zackik

---

# 📄 License

This project is developed for educational purposes.

Feel free to use and modify it.