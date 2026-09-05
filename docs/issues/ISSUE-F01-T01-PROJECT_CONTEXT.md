# Contexto de Proyecto: jg_dashboard

> **Fecha de Actualización:** 5 de septiembre de 2026  
> **Estado Global del Proyecto:** Fase 01 iniciada (`ISSUE-F01-T01` completado)  
> **Próxima Tarea Inmediata:** `ISSUE-F01-T02` (Dockerfile y Docker Compose para jg_dashboard y PostgreSQL propio)  

---

## 1. Visión y Propósito

`jg_dashboard` es el **Control Plane** (plano de control, administración y gobernanza) del ecosistema jG. Es el **único componente expuesto hacia el exterior**, actuando como escudo de seguridad para los agentes de ejecución (`jg_agente`) que operan aislados en redes internas privadas.

### Principios Troncales:
- **Único Punto de Exposición Externa:** Todo acceso de usuarios, administradores o clientes externos pasa por el Dashboard.
- **Aislamiento Total de Persistencia:** El Dashboard cuenta con su propia base de datos PostgreSQL independiente. **Nunca se conecta a la base de datos de los agentes**.
- **Gobernanza Human-In-The-Loop (HITL):** Consola reactiva para auditar, autorizar o rechazar ejecuciones que alcancen un nivel de riesgo crítico (`RiskLevel >= HIGH`).
- **Desacoplamiento de Protocolos:** Adaptadores de transporte normalizan estímulos en `AgentInput` y respuestas en `AgentOutput`.
- **Aprovisionamiento Zero-Trust:** Gestión de ciclo de vida con `bootstrap_tokens` de un solo uso (24h) y credenciales permanentes revocables.

---

## 2. Estado Actual del Repositorio

### 2.1 Stack Tecnológico y Calidad
- **Framework Base:** Laravel 11.x
- **Panel Administrativo:** Filament PHP v3.2 + Livewire 3 + Alpine.js
- **Estilos & UI:** Tailwind CSS (Dark Mode)
- **Testing:** Pest PHP v3 (25 tests pasando)
- **Script de Verificación:** `./scripts/check.sh`

### 2.2 Estructura del Proyecto
```text
jg_dashboard/
├── composer.json                   # Dependencias Laravel 11, Filament v3 y Pest
├── composer.lock                   # Lockfile de dependencias PHP
├── phpunit.xml                     # Configuración de pruebas Pest/PHPUnit
├── Dockerfile                      # Contenedor PHP 8.3-FPM + Nginx
├── app/
│   ├── Filament/                   # Panel Filament y recursos reactivos
│   │   ├── Pages/                  # Vistas personalizadas y mapa de topología
│   │   ├── Resources/              # AgentTaskResource, CircuitBreakerResource, etc.
│   │   └── Widgets/                # Widgets de métricas y estados
│   ├── Models/                     # Modelos Eloquent ORM (AgentTask, User, etc.)
│   ├── Providers/                  # Service Providers
│   └── Services/                   # DevOps60kApiClient / AgentClient (REST hacia el agente)
├── bootstrap/                      # Bootstrap de Laravel
├── config/                         # Configuraciones (app, database, devops60k/agent)
├── database/                       # Migraciones, factories y seeders
├── docker/                         # Configuraciones y entrypoint Docker
├── public/                         # Assets públicos y punto de entrada index.php
├── resources/                      # Vistas Blade y recursos estáticos
├── routes/                         # Rutas web y de consola
├── scripts/
│   └── check.sh                    # Script de verificación integral (Pest)
├── tests/                          # Suite integral de pruebas (Unit + Feature)
│   ├── Feature/
│   └── Unit/
└── docs/
    └── issues/
        ├── ISSUE-F01-T01-analysis.md
        ├── ISSUE-F01-T01-walkthrough.md
        ├── ISSUE-F01-T01-PROJECT_CONTEXT.md
        └── ISSUE-F01-T01-closing-comment.md
```

---

## 3. Estado de la Fase Actual (Fase 01)

| Issue ID | Título | Estado |
| :--- | :--- | :--- |
| `ISSUE-F01-T01` | Estructura base del repositorio jg_dashboard y configuración de entorno | **COMPLETADO** |
| `ISSUE-F01-T02` | Dockerfile y Docker Compose para jg_dashboard y PostgreSQL propio | Pendiente |
| `ISSUE-F01-T03` | Pipeline de CI/CD para jg_dashboard | Pendiente |
