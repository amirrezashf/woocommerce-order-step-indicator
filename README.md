# WooCommerce Order Step Indicator

Step-by-step visual order progress indicator for WooCommerce orders with AJAX admin controls, manual change history, and WooCommerce orders list status column.

## Description

WooCommerce Order Step Indicator adds a visual progress bar to WooCommerce order pages and gives admins a lightweight way to manage a symbolic order progress step from the order edit screen.

This plugin is useful when the default WooCommerce order status is not enough to clearly show the operational progress of an order to the customer.

## Features

- Step-by-step progress bar on the thank you page
- Step-by-step progress bar on the customer order view page
- Admin metabox for managing the symbolic order step
- AJAX saving without page reload
- Manual change history inside the order edit screen
- Small status column in the WooCommerce orders list
- Inline step editing from the orders list
- Support for classic WooCommerce orders list
- Support for HPOS orders list
- Automatic completed step for final order statuses
- Persian front-end labels
- Persian date formatting for change history
- Lightweight and configuration-free

## Order Steps

The plugin displays the order journey as these steps:

```text
Product Page
Cart
Checkout and Payment
Initial Processing Queue
In Progress
Order Completed
```

Persian labels used in the front-end bar:

```text
صفحه محصول
سبد خرید
تسویه حساب و پرداخت
در صف پردازش اولیه
در حال انجام
تکمیل سفارش
```

## Editable Statuses

The symbolic step can be manually edited only when the WooCommerce order status is:

```text
processing
mohem
```

For other statuses, the admin control is not editable.

## Automatically Completed Statuses

The plugin automatically shows the order as completed for these statuses:

```text
completed
delivery-by-motor
delivery-by-post
payment-done
```

## Admin Controls

Admins can manage the order step from:

- Order edit screen metabox
- WooCommerce orders list column

Available editable values:

```text
queue_init
queue_run
done
```

## Stored Meta

The plugin stores data in WooCommerce order meta:

```php
_wosi_step
_wosi_step_log
```

### `_wosi_step`

Stores the current symbolic step.

Possible values:

```text
queue_init
queue_run
done
```

### `_wosi_step_log`

Stores the manual change history.

Each log item includes:

- Timestamp
- Previous step
- New step
- User ID
- Context

## Development Notes

### Main Class

```php
WOSI_Order_Steps
```

### Main Prefix

```text
wosi_
```

### AJAX Action

```php
wosi_update_step
```

### Admin Column Key

```php
wosi_order_step
```

### Front-End Hooks

```php
woocommerce_before_thankyou
woocommerce_view_order
```

### Admin Hooks

```php
add_meta_boxes
admin_enqueue_scripts
wp_ajax_wosi_update_step
manage_edit-shop_order_columns
manage_shop_order_posts_custom_column
manage_woocommerce_page_wc-orders_columns
manage_woocommerce_page_wc-orders_custom_column
```

### Status Hooks

```php
woocommerce_order_status_changed
woocommerce_order_status_completed
```

## Customization Notes

### Editing Allowed Statuses

Editable statuses are defined in:

```php
private static $editable_statuses
```

Default values:

```php
processing
mohem
```

### Editing Force Completed Statuses

Statuses that force the step to `done` are defined in:

```php
private static $force_done_statuses
```

Default values:

```php
completed
delivery-by-motor
delivery-by-post
payment-done
```

### Editing Step Labels

Step labels are defined in:

```php
private static $steps
```

Default steps:

```php
product
cart
checkout
queue_init
queue_run
done
```

## Requirements

- WordPress
- WooCommerce
- PHP 7.4+

## Changelog

### 1.0.0

- Initial release
- Added front-end order step indicator
- Added AJAX admin step control
- Added manual step change history
- Added WooCommerce orders list status column
- Added inline step editing in orders list
- Added classic orders list support
- Added HPOS orders list support
- Added automatic completed step handling
- Added Persian labels and date formatting

## Author

Amirreza Shayesteh Far  
https://amirrezaa.ir/

---

# WooCommerce Order Step Indicator

افزونه نمایش مرحله‌ای روند سفارش برای ووکامرس، همراه با کنترل AJAX در پنل مدیریت، تاریخچه تغییرات دستی و ستون استاتوس در لیست سفارشات.

## توضیحات

افزونه WooCommerce Order Step Indicator یک نوار مرحله‌ای به صفحات سفارش ووکامرس اضافه می‌کند و به مدیران سایت امکان می‌دهد یک وضعیت نمادین برای روند سفارش مدیریت کنند.

این افزونه زمانی کاربرد دارد که وضعیت اصلی سفارش در ووکامرس برای نمایش روند عملیاتی سفارش به مشتری کافی نیست و نیاز دارید مراحل سفارش را واضح‌تر و قابل فهم‌تر نمایش دهید.

## امکانات

- نمایش نوار مرحله‌ای در صفحه تشکر سفارش
- نمایش نوار مرحله‌ای در صفحه مشاهده سفارش مشتری
- متاباکس مدیریت مرحله سفارش در صفحه ویرایش سفارش
- ذخیره‌سازی AJAX بدون رفرش صفحه
- ثبت تاریخچه تغییرات دستی
- ستون کوچک استاتوس سفارش در لیست سفارشات ووکامرس
- ویرایش سریع مرحله سفارش از لیست سفارشات
- پشتیبانی از لیست سفارشات کلاسیک ووکامرس
- پشتیبانی از لیست سفارشات HPOS
- تکمیل خودکار مرحله سفارش برای وضعیت‌های نهایی
- برچسب‌های فارسی در فرانت‌اند
- نمایش تاریخ فارسی در تاریخچه تغییرات
- سبک و بدون نیاز به تنظیمات

## مراحل سفارش

مراحل نمایشی افزونه:

```text
صفحه محصول
سبد خرید
تسویه حساب و پرداخت
در صف پردازش اولیه
در حال انجام
تکمیل سفارش
```

## وضعیت‌های قابل ویرایش

مرحله نمادین سفارش فقط زمانی قابل ویرایش است که وضعیت اصلی سفارش یکی از موارد زیر باشد:

```text
processing
mohem
```

در سایر وضعیت‌ها، کنترل مدیریتی قابل ویرایش نیست.

## وضعیت‌های تکمیل خودکار

در وضعیت‌های زیر، سفارش به صورت خودکار در مرحله تکمیل نمایش داده می‌شود:

```text
completed
delivery-by-motor
delivery-by-post
payment-done
```

## کنترل‌های مدیریت

مدیر سایت می‌تواند مرحله سفارش را از این بخش‌ها مدیریت کند:

- متاباکس صفحه ویرایش سفارش
- ستون استاتوس در لیست سفارشات ووکامرس

مقادیر قابل انتخاب:

```text
queue_init
queue_run
done
```

## متاهای ذخیره‌شده

افزونه اطلاعات خود را در متای سفارش ذخیره می‌کند:

```php
_wosi_step
_wosi_step_log
```

### `_wosi_step`

مرحله فعلی سفارش را ذخیره می‌کند.

مقادیر ممکن:

```text
queue_init
queue_run
done
```

### `_wosi_step_log`

تاریخچه تغییرات دستی مرحله سفارش را ذخیره می‌کند.

هر آیتم شامل این موارد است:

- زمان تغییر
- مرحله قبلی
- مرحله جدید
- شناسه کاربر
- نوع تغییر

## نکات توسعه

### کلاس اصلی

```php
WOSI_Order_Steps
```

### پیشوند اصلی

```text
wosi_
```

### اکشن AJAX

```php
wosi_update_step
```

### کلید ستون مدیریت

```php
wosi_order_step
```

### هوک‌های فرانت‌اند

```php
woocommerce_before_thankyou
woocommerce_view_order
```

### هوک‌های مدیریت

```php
add_meta_boxes
admin_enqueue_scripts
wp_ajax_wosi_update_step
manage_edit-shop_order_columns
manage_shop_order_posts_custom_column
manage_woocommerce_page_wc-orders_columns
manage_woocommerce_page_wc-orders_custom_column
```

### هوک‌های وضعیت سفارش

```php
woocommerce_order_status_changed
woocommerce_order_status_completed
```

## نکات سفارشی‌سازی

### تغییر وضعیت‌های قابل ویرایش

وضعیت‌های قابل ویرایش در این بخش تعریف شده‌اند:

```php
private static $editable_statuses
```

مقادیر پیش‌فرض:

```text
processing
mohem
```

### تغییر وضعیت‌های تکمیل خودکار

وضعیت‌هایی که مرحله سفارش را به `done` تغییر می‌دهند در این بخش تعریف شده‌اند:

```php
private static $force_done_statuses
```

مقادیر پیش‌فرض:

```text
completed
delivery-by-motor
delivery-by-post
payment-done
```

### تغییر عنوان مراحل

عنوان مراحل در این بخش تعریف شده است:

```php
private static $steps
```

مراحل پیش‌فرض:

```text
product
cart
checkout
queue_init
queue_run
done
```

## پیش‌نیازها

- وردپرس
- ووکامرس
- PHP 7.4 یا بالاتر

## تغییرات

### 1.0.0

- انتشار اولیه
- افزودن نوار مرحله‌ای سفارش در فرانت‌اند
- افزودن کنترل AJAX در پنل مدیریت
- افزودن تاریخچه تغییرات دستی
- افزودن ستون استاتوس در لیست سفارشات ووکامرس
- افزودن ویرایش سریع مرحله از لیست سفارشات
- پشتیبانی از لیست سفارشات کلاسیک
- پشتیبانی از لیست سفارشات HPOS
- تکمیل خودکار مرحله برای وضعیت‌های نهایی
- افزودن برچسب‌ها و تاریخ فارسی

## نویسنده

Amirreza Shayesteh Far  
https://amirrezaa.ir/
