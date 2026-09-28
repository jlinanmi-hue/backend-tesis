# Comercial Valencia - Backend API & Valencia AI

Backend RESTful y Asistente Inteligente (Valencia AI) para el sistema de gestión comercial, control de inventario y medición de indicadores operacionales de **Comercial Valencia**.

---

## 🚀 Arquitectura y Tecnologías

- **Framework:** Laravel 11.x (PHP 8.2+)
- **Base de Datos:** Microsoft SQL Server (`sqlsrv`)
- **Inteligencia Artificial:** Asistente "Valencia AI" con arquitectura Function Calling de 4 capas (Gemini & OpenAI API)
- **Indicadores Clave de Desempeño (Tesis):**
  - **PODE:** Porcentaje de Órdenes Despachadas Exitosamente
  - **PEOR:** Porcentaje de Errores en Órdenes Registradas
  - **PRS:** Porcentaje de Roturas de Stock evitadas / detectadas
  - **TBPP:** Tiempo promedio por proceso de pedido (Telemetría de registro)

---

## 📦 Estructura del Proyecto

```
backend-tesis/
├── app/
│   ├── Http/Controllers/       # Controladores RESTful & AI Controller
│   ├── Http/Requests/          # Validación estricta de formularios y DTOs
│   ├── Models/                 # Modelos Eloquent con relaciones y auditoría
│   ├── Repositories/           # Patrón repositorio (Contracts & Eloquent)
│   ├── Rules/                  # Reglas de negocio (RUC SUNAT Módulo 11)
│   └── Services/               # Lógica de dominio, Kardex, IA y Dashboard
├── config/                     # Configuración de servicios y base de datos
├── database/
│   ├── migrations/             # 41 migraciones estructuradas
│   └── seeders/                # 28 seeders modulares con datos de producción/tesis
├── routes/                     # Rutas API versionadas
└── storage/                    # Almacenamiento local y logs de aplicación
```

---

## 🛠️ Instalación y Configuración

### 1. Clonar el repositorio
```bash
git clone https://github.com/jlinanmi-hue/backend-tesis.git
cd backend-tesis
```

### 2. Instalar dependencias de PHP
```bash
composer install
```

### 3. Configurar entorno (.env)
Copiar `.env.example` a `.env` y configurar las credenciales de SQL Server y los proveedores de IA:
```bash
cp .env.example .env
php artisan key:generate
```

Variables clave en `.env`:
```env
DB_CONNECTION=sqlsrv
DB_HOST=127.0.0.1
DB_PORT=1433
DB_DATABASE=bdTesis
DB_USERNAME=tu_usuario
DB_PASSWORD=tu_password

AI_PROVIDER=openai # o gemini
OPENAI_API_KEY=tu_openai_key
GEMINI_API_KEY=tu_gemini_key
```

### 4. Ejecutar Migraciones y Pobladores (Seeders)
Para crear las tablas y cargar todos los registros iniciales y datos de tesis:
```bash
php artisan migrate
php artisan db:seed
```

### 5. Iniciar el Servidor de Desarrollo
```bash
php artisan serve
```
El backend estará disponible en `http://127.0.0.1:8000`.

---

## 🧪 Pruebas y Endpoints Principales

- **Dashboard de Indicadores:** `GET /api/dashboard/indicadores?dias=7`
- **Catálogo de Productos:** `GET /api/inventario/productos`
- **Gestión de Pedidos:** `POST /api/pedidos`, `GET /api/pedidos`
- **Kárdex / Movimientos:** `GET /api/inventario/movimientos`
- **ChatBot Valencia AI:** `POST /api/ai/chat`, `GET /api/ai/tools`
