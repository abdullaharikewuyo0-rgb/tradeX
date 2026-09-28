# TradeX

A PHP-based e-commerce platform where buyers can browse products and sellers can manage their listings, orders, and earnings.

## Features

**For Buyers**
- Browse products by category
- Add items to cart and wishlist
- Checkout with order confirmation
- Track order history

**For Sellers**
- Register and log in as a seller
- Upload and manage product listings
- View incoming orders
- Track earnings and withdrawals

## Tech Stack

- **Backend:** PHP
- **Database:** MySQL
- **Frontend:** HTML, CSS, JavaScript
- **Server:** Apache (XAMPP)

## Setup Instructions

### Prerequisites
- [XAMPP](https://www.apachefriends.org/) (or any PHP + MySQL + Apache stack)
- Git

### Steps

1. **Clone the repository**


2. **Move it into your web server's root folder**

For XAMPP on Windows, move the folder into `C:\xampp\htdocs\`.

3. **Create the database**

- Start Apache and MySQL from the XAMPP Control Panel.
- Open http://localhost/phpmyadmin.
- Create a new database named `tradex`.
- Import the SQL schema if one is provided.

4. **Configure database credentials**

Copy `config.example.php` to `config.php` and fill in your own MySQL credentials.

Note: `config.php` is not tracked by Git — you must create it yourself.

5. **Run the app**

Open http://localhost/tradeX in your browser.

## Project Structure

## Author

**Abdullah Ariwkuyo**
- GitHub: [@abdullaharikewuyo0-rgb](https://github.com/abdullaharikewuyo0-rgb)

## License

This project is for educational purposes.