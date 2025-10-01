# 🌐 URL CORRECTE DU SITE

## ✅ URL à Utiliser

```
http://localhost/COURSIER_LOCAL/
```

**IMPORTANT** : Ne PAS utiliser `index.php` à la fin !

---

## ❌ URLs Incorrectes

- ❌ AUCUNE URL avec `/index.php` ne doit être utilisée !
- ❌ Apache gère automatiquement le routage vers index.php

---

## 📋 Configuration Apache

Le fichier `.htaccess` à la racine gère automatiquement la redirection vers `index.php`.

```apache
# .htaccess
DirectoryIndex index.php

# Redirection automatique
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ index.php [L]
```

---

## 🔗 URLs de l'Application

### Index (Site Client)
```
http://localhost/COURSIER_LOCAL/
```

### Admin Dashboard
```
http://localhost/COURSIER_LOCAL/admin.php
```

### Coursier Dashboard  
```
http://localhost/COURSIER_LOCAL/coursier.php
```

### API Endpoints
```
http://localhost/COURSIER_LOCAL/api/submit_order.php
http://localhost/COURSIER_LOCAL/api/initiate_payment_only.php
http://localhost/COURSIER_LOCAL/api/create_order_after_payment.php
http://localhost/COURSIER_LOCAL/api/get_coursier_data.php
```

---

## 🔧 Configuration CinetPay

Les URLs de callback/return sont configurées automatiquement dans `cinetpay/config.php` :

```php
define('CINETPAY_NOTIFY_URL', appUrl('cinetpay/payment_notify.php'));
define('CINETPAY_RETURN_URL', appUrl('cinetpay/payment_return.php'));
```

La fonction `appUrl()` génère automatiquement l'URL correcte basée sur l'environnement.

---

**Dernière mise à jour** : 1er Octobre 2025
