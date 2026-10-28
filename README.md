# 🛒 API de E-commerce Segura con Swagger

API RESTful para un e-commerce básico construida con **Laravel 12**, **MySQL**, autenticación por tokens con **Laravel Sanctum**, pagos con **Stripe** (`stripe/stripe-php`) y documentación **OpenAPI 3 / Swagger UI** (`darkaonline/l5-swagger`).

Cubre el flujo completo de una tienda en línea: **registro → login → catálogo → orden de compra → pago con Stripe → historial de compras**.

---

## 📋 Tabla de contenidos

1. [Tecnologías](#-tecnologías)
2. [Requisitos](#-requisitos)
3. [Instalación](#-instalación)
4. [Variables de entorno](#-variables-de-entorno)
5. [Documentación Swagger](#-documentación-swagger)
6. [Endpoints](#-endpoints)
7. [Flujo de compra paso a paso](#-flujo-de-compra-paso-a-paso)
8. [Pagos con Stripe](#-pagos-con-stripe)
9. [Formato de respuestas y errores](#-formato-de-respuestas-y-errores)
10. [Seguridad implementada](#-seguridad-implementada)
11. [Base de datos](#-base-de-datos)
12. [Tests](#-tests)
13. [Estructura del proyecto](#-estructura-del-proyecto)

---

## 🧰 Tecnologías

| Componente        | Tecnología                                  |
|-------------------|---------------------------------------------|
| Framework         | Laravel 12 (PHP ≥ 8.2)                      |
| Base de datos     | MySQL 8 / MariaDB 10.4+                     |
| Autenticación     | Laravel Sanctum (tokens Bearer con expiración) |
| Pasarela de pago  | Stripe — `stripe/stripe-php` (PaymentIntents + Webhooks) |
| Documentación     | Swagger / OpenAPI 3 — `darkaonline/l5-swagger` (atributos PHP) |
| Tests             | PHPUnit (SQLite en memoria + doble de Stripe) |

## ✅ Requisitos

- PHP **8.2** o superior con extensiones `pdo_mysql`, `openssl`, `mbstring`, `curl`, `fileinfo`
- Composer 2
- MySQL 8 o MariaDB (por ejemplo, el incluido en XAMPP)
- Cuenta de Stripe en **modo test** (gratis): <https://dashboard.stripe.com/register>
- *(Opcional)* [Stripe CLI](https://docs.stripe.com/stripe-cli) para probar webhooks en local

## 🚀 Instalación

```bash
# 1. Clonar el repositorio
git clone https://github.com/<tu-usuario>/ecommerce-api-segura.git
cd ecommerce-api-segura

# 2. Instalar dependencias
composer install

# 3. Crear el archivo de entorno y la clave de la aplicación
cp .env.example .env
php artisan key:generate
```

4. **Crear la base de datos** en MySQL:

```sql
CREATE DATABASE ecommerce_api CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

5. **Configurar `.env`**: credenciales de MySQL (`DB_*`) y claves de Stripe (`STRIPE_*`). Ver [Variables de entorno](#-variables-de-entorno).

```bash
# 6. Ejecutar migraciones y seeders (crea tablas, 10 productos y 2 usuarios demo)
php artisan migrate --seed

# 7. Generar la documentación Swagger
php artisan l5-swagger:generate

# 8. Levantar el servidor
php artisan serve
```

La API queda disponible en `http://localhost:8000/api` y Swagger UI en **<http://localhost:8000/api/documentation>**.

### Usuarios de prueba (seeders)

| Rol      | Email                    | Contraseña    |
|----------|--------------------------|---------------|
| Admin    | `admin@ecommerce.test`   | `Admin123!`   |
| Cliente  | `cliente@ecommerce.test` | `Cliente123!` |

> Se pueden cambiar con las variables `SEED_*` del `.env`. Cámbialas en cualquier entorno que no sea local.

## 🔐 Variables de entorno

Todas están documentadas en [`.env.example`](.env.example). Las específicas del proyecto:

| Variable | Descripción | Ejemplo |
|----------|-------------|---------|
| `DB_CONNECTION` | Driver de BD | `mysql` |
| `DB_HOST` / `DB_PORT` | Servidor MySQL | `127.0.0.1` / `3306` |
| `DB_DATABASE` | Nombre de la base | `ecommerce_api` |
| `DB_USERNAME` / `DB_PASSWORD` | Credenciales MySQL | `root` / *(tu contraseña)* |
| `SANCTUM_TOKEN_EXPIRATION` | Minutos de vida de cada token | `1440` (24 h) |
| `STRIPE_KEY` | Clave **publicable** (se entrega al frontend) | `pk_test_...` |
| `STRIPE_SECRET` | Clave **secreta** (solo servidor, nunca en git) | `sk_test_...` |
| `STRIPE_WEBHOOK_SECRET` | Secreto de firma de webhooks | `whsec_...` |
| `STRIPE_CURRENCY` | Moneda ISO en minúsculas | `usd` |
| `SHOP_TAX_RATE` | Impuesto sobre el subtotal | `0.19` |
| `L5_SWAGGER_CONST_HOST` | URL base que muestra Swagger | `${APP_URL}` |
| `L5_SWAGGER_GENERATE_ALWAYS` | Regenerar docs en cada request (solo dev) | `true` |
| `SEED_*` | Credenciales de los usuarios demo | — |

Las claves de Stripe se obtienen en **Dashboard → Developers → API keys** (asegúrate de estar en *Test mode*).

## 📖 Documentación Swagger

- **Swagger UI:** `GET /api/documentation`
- **Especificación JSON (OpenAPI 3):** `GET /docs` (archivo `storage/api-docs/api-docs.json`)

La especificación se genera a partir de **atributos PHP** (`#[OA\...]`) ubicados en:

- `app/OpenApi/OpenApiSpec.php` → info general, servidor, esquema de seguridad, tags, esquemas y respuestas de error reutilizables.
- `app/Http/Controllers/Api/*` → cada endpoint (parámetros, body, respuestas 2xx/4xx/5xx).
- `app/Http/Requests/**` → esquemas de los cuerpos de petición.
- `app/Http/Resources/*` → esquemas de las respuestas (`User`, `Product`, `Order`, `OrderItem`, `Payment`).

**Probar endpoints protegidos desde Swagger:**
1. Ejecuta `POST /api/auth/login` con un usuario demo.
2. Copia el valor de `data.token`.
3. Presiona **Authorize 🔒**, pega el token (sin `Bearer`) y confirma. La autorización persiste al recargar la página.

## 🔗 Endpoints

| Método | Ruta | Auth | Descripción |
|--------|------|------|-------------|
| POST | `/api/auth/register` | — | Registro de cliente (devuelve token) |
| POST | `/api/auth/login` | — | Login (devuelve token) |
| GET | `/api/auth/me` | Bearer | Perfil del usuario autenticado |
| POST | `/api/auth/logout` | Bearer | Revoca el token actual |
| POST | `/api/auth/logout-all` | Bearer | Revoca todos los tokens del usuario |
| GET | `/api/products` | — | Listado público (filtros, orden, paginación) |
| GET | `/api/products/{id}` | — | Detalle de producto |
| POST | `/api/products` | Bearer **admin** | Crear producto |
| PUT/PATCH | `/api/products/{id}` | Bearer **admin** | Editar producto |
| DELETE | `/api/products/{id}` | Bearer **admin** | Eliminar producto (soft delete) |
| GET | `/api/orders` | Bearer | **Historial de compras** del usuario |
| POST | `/api/orders` | Bearer | Crear orden de compra |
| GET | `/api/orders/{id}` | Bearer (dueño/admin) | Detalle de orden |
| POST | `/api/orders/{id}/cancel` | Bearer (dueño) | Cancelar orden no pagada (devuelve stock) |
| POST | `/api/orders/{id}/pay` | Bearer (dueño) | Pagar orden con Stripe |
| GET | `/api/payments` | Bearer | Historial de pagos |
| GET | `/api/payments/{id}` | Bearer (dueño/admin) | Consultar pago (sincroniza con Stripe) |
| POST | `/api/stripe/webhook` | Firma Stripe | Recepción de eventos de Stripe |

**Filtros de `/api/products`:** `search`, `category`, `min_price`, `max_price`, `in_stock`, `sort` (`name`, `-name`, `price`, `-price`, `created_at`, `-created_at`), `per_page` (1–100), `page`.

## 🛍️ Flujo de compra paso a paso

```bash
# 1) Login
curl -X POST http://localhost:8000/api/auth/login \
  -H "Accept: application/json" -H "Content-Type: application/json" \
  -d '{"email":"cliente@ecommerce.test","password":"Cliente123!"}'
# → copia data.token en la variable TOKEN

# 2) Ver catálogo
curl "http://localhost:8000/api/products?category=Electrónica&sort=-price"

# 3) Crear la orden (precios y stock se calculan en el servidor)
curl -X POST http://localhost:8000/api/orders \
  -H "Authorization: Bearer $TOKEN" -H "Accept: application/json" -H "Content-Type: application/json" \
  -d '{"items":[{"product_id":1,"quantity":2},{"product_id":3,"quantity":1}],"shipping_address":"Av. Siempre Viva 742"}'

# 4) Pagar con una tarjeta de prueba de Stripe
curl -X POST http://localhost:8000/api/orders/1/pay \
  -H "Authorization: Bearer $TOKEN" -H "Accept: application/json" -H "Content-Type: application/json" \
  -d '{"payment_method":"pm_card_visa"}'

# 5) Historial de compras
curl http://localhost:8000/api/orders -H "Authorization: Bearer $TOKEN" -H "Accept: application/json"
```

Estados de una orden: `pending` → `paid` | `failed` (se puede reintentar el pago) | `cancelled`.

## 💳 Pagos con Stripe

La integración usa **PaymentIntents** del SDK oficial `stripe/stripe-php`, encapsulado en `App\Services\StripeGateway` (implementa la interfaz `App\Contracts\PaymentGateway`).

`POST /api/orders/{id}/pay` admite dos modos:

1. **Confirmación en el servidor** — enviando `payment_method`. Útil para pruebas desde Swagger/Postman. PaymentMethods de prueba de Stripe:

   | `payment_method` | Resultado |
   |------------------|-----------|
   | `pm_card_visa` | ✅ Pago aprobado → orden `paid` |
   | `pm_card_mastercard` | ✅ Pago aprobado |
   | `pm_card_chargeDeclined` | ❌ Rechazado → HTTP `402`, orden `failed` |
   | `pm_card_visa_chargeDeclinedInsufficientFunds` | ❌ Fondos insuficientes → HTTP `402` |
   | `pm_inexistente` (cualquier ID que no exista) | ⚠️ HTTP `422` `VALIDATION_ERROR` |
   | `pm_card_authenticationRequired` | 🔐 Requiere 3D Secure (`requires_action: true`) |

2. **Confirmación en el frontend** — sin `payment_method`. La API devuelve `client_secret` y `publishable_key`; el frontend confirma con Stripe.js (`stripe.confirmCardPayment(client_secret, ...)`). El estado final se actualiza por **webhook** o consultando `GET /api/payments/{id}`.

**Buenas prácticas aplicadas:**
- El monto se calcula **siempre en el servidor** a partir de la orden (en centavos), nunca desde el cliente.
- **Claves de idempotencia** por intento de pago para evitar cobros duplicados.
- Al recibir `succeeded` se verifica que **monto y moneda coincidan** con la orden.
- Cada transacción queda registrada en la tabla `payments` (ID del PaymentIntent, estado, método, error).
- El webhook valida la cabecera `Stripe-Signature` con `STRIPE_WEBHOOK_SECRET`.

**Probar webhooks en local** con Stripe CLI:

```bash
stripe login
stripe listen --forward-to localhost:8000/api/stripe/webhook
# Copia el "whsec_..." que imprime en STRIPE_WEBHOOK_SECRET y reinicia el servidor
stripe trigger payment_intent.succeeded
```

## 📦 Formato de respuestas y errores

**Éxito:**

```json
{
  "success": true,
  "message": "Orden creada correctamente.",
  "data": { "...": "..." },
  "meta": { "current_page": 1, "last_page": 1, "per_page": 15, "total": 10 }
}
```

**Error** (mismo formato para todos los casos, gestionado en `App\Exceptions\ApiExceptionRenderer`):

```json
{
  "success": false,
  "message": "Los datos enviados no son válidos.",
  "error_code": "VALIDATION_ERROR",
  "errors": { "email": ["El campo correo electrónico es obligatorio."] }
}
```

| HTTP | `error_code` | Cuándo |
|------|--------------|--------|
| 400 | `INVALID_SIGNATURE` | Firma de webhook inválida |
| 401 | `UNAUTHENTICATED` | Token ausente, inválido o expirado |
| 402 | `CARD_DECLINED` | Stripe rechazó la tarjeta |
| 403 | `FORBIDDEN` | Sin permisos (no admin / orden ajena) |
| 404 | `NOT_FOUND` | Recurso o ruta inexistente |
| 405 | `METHOD_NOT_ALLOWED` | Método HTTP incorrecto |
| 409 / 422 | `BUSINESS_RULE_VIOLATION` | Stock insuficiente, orden ya pagada, etc. |
| 422 | `VALIDATION_ERROR` | Falla de validación de un Form Request |
| 429 | `TOO_MANY_REQUESTS` | Límite de solicitudes excedido |
| 502 | `PAYMENT_GATEWAY_ERROR` | Error de comunicación con Stripe |
| 500 | `SERVER_ERROR` | Error inesperado (sin detalles si `APP_DEBUG=false`) |

## 🛡️ Seguridad implementada

- **Tokens Sanctum** con expiración configurable; logout revoca el token actual o todos.
- **Contraseñas** con hash bcrypt y política fuerte (mín. 8, mayúsculas, minúsculas, números y símbolos).
- **Roles** (`customer` / `admin`): el campo `role` no es asignable masivamente, por lo que no se puede escalar privilegios desde el registro.
- **Autorización** con middleware `admin` para el CRUD de productos y **Policies** (`OrderPolicy`, `PaymentPolicy`) para que cada cliente solo vea y pague sus propias órdenes.
- **Rate limiting:** 5 intentos/min de login-registro por email+IP, 60 req/min generales, 10 pagos/min.
- **Mensaje genérico** en login fallido (evita enumeración de usuarios).
- **Validación** de toda entrada con **Form Requests**.
- **Precios calculados en el servidor** y stock reservado con **transacciones y bloqueo de filas** (`lockForUpdate`) para evitar sobreventa.
- **Webhooks verificados** por firma; secretos solo en `.env` (excluido de git).
- **Soft delete** de productos para preservar el historial de órdenes.

## 🗄️ Base de datos

```
users ──< orders ──< order_items >── products
  │          │
  └──< payments >──┘
```

| Tabla | Campos principales |
|-------|--------------------|
| `users` | name, email (único), password, phone, role |
| `products` | sku (único), name, slug (único), description, price, stock, category, image_url, is_active, deleted_at |
| `orders` | order_number (único), user_id, status, subtotal, tax, total, currency, shipping_address, notes, paid_at |
| `order_items` | order_id, product_id, product_name, unit_price, quantity, subtotal *(copia del precio al momento de la compra)* |
| `payments` | order_id, user_id, provider, stripe_payment_intent_id (único), amount, currency, status, payment_method, failure_message, metadata, paid_at |

Seeders: `UserSeeder` (admin + cliente) y `ProductSeeder` (10 productos en 4 categorías, uno sin stock para probar validaciones).

```bash
php artisan migrate:fresh --seed   # reinicia la BD con datos de ejemplo
```

## 🧪 Tests

Los tests usan SQLite en memoria y un doble de Stripe (`tests/Fakes/FakePaymentGateway.php`), por lo que **no requieren MySQL ni claves reales**:

```bash
php artisan test
```

Cubren: registro/login/logout, rate limiting, CRUD y permisos de productos, filtros del catálogo, creación de órdenes con validación de stock, pago aprobado, pago rechazado y reintento, flujo con `client_secret`, webhook con firma válida/inválida, cancelación con devolución de stock y aislamiento entre usuarios.

## 📁 Estructura del proyecto

```
app/
├── Contracts/PaymentGateway.php           # Interfaz de la pasarela
├── Exceptions/
│   ├── ApiExceptionRenderer.php           # Errores JSON consistentes
│   └── BusinessException.php
├── Http/
│   ├── Controllers/Api/                   # Auth, Product, Order, Payment, StripeWebhook
│   ├── Middleware/EnsureUserIsAdmin.php
│   ├── Requests/{Auth,Product,Order}/     # Form Requests (validación)
│   └── Resources/                         # Transformación de respuestas
├── Models/                                # User, Product, Order, OrderItem, Payment
├── OpenApi/OpenApiSpec.php                # Definición global de Swagger
├── Policies/                              # OrderPolicy, PaymentPolicy
└── Services/                              # OrderService, PaymentService, StripeGateway
database/
├── migrations/
└── seeders/                               # UserSeeder, ProductSeeder
routes/api.php
tests/Feature/                             # AuthTest, ProductTest, OrderPaymentTest
```
