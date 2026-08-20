# MP-IA Tamaulipas — Entorno base de desarrollo

Infraestructura mínima para arrancar el desarrollo del MVP: base de datos,
búsqueda semántica, caché, almacenamiento de archivos y modelo de lenguaje
local. Todo corre on-premise, sin depender de servicios de IA en la nube.

## Requisitos

- Docker y Docker Compose instalados
- ~10 GB de espacio libre (modelos de Ollama pesan)
- Puertos libres: 5432, 6333, 6334, 6379, 9000, 9001, 11434

## Arranque rápido

```bash
cp .env.example .env
# edita .env y cambia las contraseñas por defecto

docker compose up -d
```

Verifica que todo esté arriba:

```bash
docker compose ps
```

## Servicios disponibles

| Servicio | Puerto | Uso |
|---|---|---|
| PostgreSQL | 5432 | Base de datos relacional (LegalCore, expedientes ficticios) |
| Qdrant | 6333 / 6334 | RAG jurídico y RAG de hechos |
| Redis | 6379 | Caché |
| MinIO | 9000 (API) / 9001 (consola) | Almacenamiento de evidencia/documentos |
| Ollama | 11434 | Modelo de lenguaje local |
| ai-service (FastAPI) | 8001 | RAG jurídico/de hechos, orquestación del LLM |

Consola de MinIO: http://localhost:9001 (usuario/contraseña de `.env`)

## Cómo se comunican las piezas

El navegador nunca le habla directo a FastAPI. Laravel (con Breeze +
Inertia + Vue, todo en un solo proyecto) es el único punto de entrada;
internamente hace peticiones HTTP servidor-a-servidor al `ai-service`:

```
Navegador → Laravel (puerto 8000, incluye el Vue de Inertia)
                ↓ HTTP interno
            ai-service / FastAPI (puerto 8001)
                ↓
            Qdrant, Ollama, Postgres
```

Por eso NO existe una carpeta `frontend/` separada: el Vue vive dentro
de `backend/resources/js/` una vez que corras Breeze.

## Probar el ai-service

Con `docker compose up -d`, verifica que responda:

```bash
curl http://localhost:8001/health
```

Documentación automática de la API (Swagger): http://localhost:8001/docs

## Descargar el modelo de lenguaje

Una vez que el contenedor de Ollama esté arriba:

```bash
docker exec -it mpia_ollama ollama pull qwen2.5:14b
```

Ajusta el modelo según el hardware disponible (modelos más chicos si no hay GPU).

## Siguientes pasos

1. Instalar el instalador de Laravel (una sola vez, si no lo tienes):
   ```bash
   composer global require laravel/installer
   ```
   Crea el proyecto con el **Vue Starter Kit** oficial (Laravel 13, Vue 3,
   TypeScript, Inertia 3, Tailwind, shadcn-vue):
   ```bash
   laravel new backend --vue
   ```
   Te preguntará el proveedor de autenticación — elige **"Laravel's
   built-in authentication"** (no necesitas WorkOS para este proyecto).

   Corre el backend en modo desarrollo (Laravel + Vite con hot-reload,
   fuera de Docker, mientras la infraestructura sigue en `docker compose`):
   ```bash
   cd backend
   cp .env.example .env
   php artisan key:generate
   # Ajusta DB_* en backend/.env para apuntar al Postgres del docker-compose
   # (host: 127.0.0.1, puerto: 5432, credenciales de tu .env raíz)
   composer install
   npm install
   composer run dev   # levanta artisan serve + vite juntos
   ```
2. Diseñar el esquema de PostgreSQL para LegalCore (ver sección 7.1 del
   anteproyecto) y correr las primeras migraciones desde Laravel.
3. Cargar el corpus jurídico Nivel 1 (Constitución, CNPP, Código Penal
   Tamaulipas, Ley Orgánica FGJ) — aunque sea manualmente al inicio.
4. Generar embeddings del corpus desde `ai-service` y subirlos a Qdrant.
5. Cuando el backend esté estable, dockerízalo con el `backend/Dockerfile`
   incluido y descomenta el servicio `backend` en `docker-compose.yml`
   para tener todo el stack unificado.

### ¿Por qué el backend no corre en Docker desde el día uno?

Vite necesita hot-module-reload (HMR) para que el desarrollo con Vue sea
ágil, y eso se complica dentro de contenedores (puertos, WebSockets,
polling de archivos). Lo más práctico en desarrollo es correr Laravel de
forma nativa con `composer run dev` mientras Postgres, Qdrant, Redis,
MinIO, Ollama y `ai-service` siguen en Docker. El `Dockerfile` de
`backend/` ya queda listo para cuando muevas todo a producción.

## Estructura de carpetas

```
mp-ia-tamaulipas/
├── docker-compose.yml
├── .env.example
├── backend/            # Laravel 13 + Vue Starter Kit (Inertia 3 + TS + Tailwind)
│   ├── resources/js/   # componentes Vue (creados automáticamente por el starter kit)
│   └── Dockerfile      # listo para cuando lo muevas a Docker
├── ai-service/         # Python + FastAPI — RAG, LLM, motor de reglas
│   ├── app/main.py
│   ├── Dockerfile
│   └── requirements.txt
└── legalcore-data/     # Corpus normativo fuente (textos, metadatos)
```
