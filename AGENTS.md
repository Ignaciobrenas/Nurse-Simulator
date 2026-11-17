# AGENTS.md — Normas del Proyecto Nurse Simulator para Agentes de IA

> Este documento define las reglas obligatorias de contexto, arquitectura, flujo de trabajo, nomenclatura de commits, calidad y seguridad que debe respetar cualquier agente de IA que trabaje en el proyecto **Nurse Simulator** (DAW 2 · STUCOM Pelai, curso 2026/27).
>
> El archivo es compartido de forma idéntica entre los dos repositorios del proyecto. Antes de realizar cualquier cambio o ejecutar comandos, verifica en qué repositorio te encuentras (`git remote -v`).

---

## 1. Contexto del Proyecto (Ecosistema Multi-Repositorio)

**Nurse Simulator** es una aplicación de simulación y gestión hospitalaria de personal de enfermería dividida en **dos repositorios independientes** coordinados bajo un mismo tablero de GitHub Projects (**[Nurse Simulator´s Project (#8)](https://github.com/users/Ignaciobrenas/projects/8)**):

| Repositorio | Rol | URL |
| :--- | :--- | :--- |
| **`Nurse-Simulator`** | API REST Backend | `https://github.com/Ignaciobrenas/Nurse-Simulator` |
| **`Nurse-Simulator-Frontend`** | Aplicación Web Frontend | `https://github.com/Ignaciobrenas/Nurse-Simulator-Frontend` |

### Equipo y Prefijos Oficiales

| Miembro | GitHub | Prefijo de Commit | Rol principal |
| :--- | :--- | :--- | :--- |
| **Ignacio Breñas** | `@Ignaciobrenas` | `ibrenas` | Project Manager & Tech Lead |
| **Christopher Roger Tito** | `@RogerTito455` | `rtito` | Desarrollador Full-Stack |
| **Gorka Ramirez** | `@gorkarmz` | `gramirez` | Desarrollador Full-Stack |
| **Zehao Yin** | `@zyin-08` | `zyin-08` | Desarrollador Full-Stack |

### Regla de Coordinación entre Repositorios

- Si una tarea en **Frontend** afecta o depende de contratos del **Backend** (rutas, campos JSON de `Nurse`, códigos HTTP o validaciones), respeta el contrato vigente de `Nurse-Simulator` y advierte al usuario si hace falta alinear ambos repositorios.

---

## 2. Stack Tecnológico y Arquitectura por Repositorio

### 2.1. Backend — `Ignaciobrenas/Nurse-Simulator`

- **Lenguaje y Framework:** PHP `>=8.2`, Symfony `7.4.*`, Composer `2.x`
- **ORM y Base de Datos:** Doctrine ORM `^3.7` (`doctrine/doctrine-bundle`, `doctrine/doctrine-migrations-bundle`), PostgreSQL `15` (Supabase en entorno remoto / PostgreSQL 15 container en CI) + dataset de respaldo en `data/nurses.json`
- **Testing y QA:** PHPUnit `11` (`tests/`), Newman / Postman (`docs/postman/nurse-api.postman_collection.json`)
- **Calidad y Estilo:** PHP-CS-Fixer `^3.95` con estándar `@PSR12` (`.php-cs-fixer.dist.php`)
- **Estructura principal:**
  - `src/Controller/NurseController.php` — Endpoints REST (`POST /nurse`, `GET /nurse/index`, `GET /nurse/{id}`, `GET /nurse/name/{name}`, `PUT /nurse/{id}`, `DELETE /nurse/{id}`, `POST /nurse/login`)
  - `src/Entity/Nurse.php` y `src/Repository/NurseRepository.php` — Entidad y repositorio Doctrine (contraseñas hasheadas con Bcrypt)
  - `config/`, `migrations/`, `tests/`, `docs/` (`general/`, `postman/`, `ci/`)
  - `.github/workflows/` — 4 pipelines obligatorios de CI (`1-crud-phpunit.yml`, `2-api-postman.yml`, `3-code-quality.yml`, `4-security-audit.yml`)

### 2.2. Frontend — `Ignaciobrenas/Nurse-Simulator-Frontend`

- **Lenguaje y Framework:** TypeScript (`~6.0.2`), Angular (`^22.2.0` / Angular CLI `^22.2.6`), RxJS (`~7.8.0`), Node.js (LTS v20+) y `npm`
- **Testing y Formato:** Vitest (`^5.0.0` vía `ng test` / `@angular/build:unit-test`), `jsdom` (`^30.0.0`), Prettier (`^3.8.1` en `.prettierrc`: `printWidth: 100`, `singleQuote: true`, parser `angular` para `*.html`) y `.editorconfig` (2 espacios, UTF-8)
- **Convenciones Angular (`angular.json` y `tsconfig.json`):**
  - Componentes **standalone** con plantilla `.html` propia y estilos encapsulados.
  - Schematics configurados con `addTypeToClassName: false`.
  - Uso explícito de la sintaxis moderna de control de flujo declarativo de Angular (`@if`, `@switch`, `@for` con `track`) y navegación mediante `RouterOutlet`, `routerLink` y `routerLinkActive`.
  - TypeScript estricto (`noImplicitOverride`, `noImplicitReturns`, `noFallthroughCasesInSwitch`, `strictInjectionParameters`, `strictInputAccessModifiers`).
- **Estructura principal:**
  - `src/app/components/` (`home/`, `login/`, `nurse-list/`, `nurse-search/`)
  - `src/app/models/` (`nurse.model.ts`)
  - `src/app/mocks/` (`nurses.mock.ts`)
  - `src/app/app.routes.ts`, `src/app/app.config.ts`, `src/app/app.component.*`, `public/`

---

## 3. Flujo de Trabajo en Git y GitHub (Git Flow)

El proyecto sigue un flujo controlado basado en **Issues -> Rama de trabajo -> Pull Request -> `staging` -> `main`**:

1. **Ramas principales protegidas:**
   - `main`: código estable y releases publicados (ej. `v0.3.0`, `v0.4.0`). **Prohibido** hacer commits o pushes directos sobre `main`.
   - `staging`: rama de integración continua del equipo. **Prohibido** hacer commits o pushes directos sobre `staging`; todo cambio entra mediante Pull Request revisada.
2. **Creación de ramas de trabajo:**
   - Toda rama nueva debe nacer actualizada desde **`staging`** (salvo indicación explícita del Tech Lead).
   - **Convención de nombres de ramas:**
     - **Tareas vinculadas a un issue del repositorio:** `<numero-issue>-<slug-descriptivo>` en minúsculas y kebab-case.
       - *Ejemplo:* `58-setup-github-actions-ci-for-crud-phpunit-tests` o `1-initial-angular-setup-base-layout-and-navbar-with-routeroutlet`.
     - **Tareas transversales sin número de issue en el repo (p. ej. Draft Issues de proyecto, docs globales o simulaciones):** `<tipo>/<slug-descriptivo>` en minúsculas y kebab-case.
       - *Ejemplo:* `docs/define-agents-md` o `test/ci-failure-58-phpunit`.
3. **Pull Requests y Tablero (`Nurse Simulator´s Project` #8):**
   - Toda PR debe apuntar a `staging` (solo el Tech Lead integra `staging` -> `main` para releases).
   - Utilizar la plantilla `.github/pull_request_template.md` cuando exista en el repositorio y enlazar el issue correspondiente (`Closes #<numero>`).
   - No fusionar ni dar por lista una PR si existe algún workflow de CI en rojo o checks fallidos.
   - Mantener sincronizado el estado de la tarjeta en el tablero (`Backlog` -> `In progress` -> `In review` -> `Done`).

---

## 4. Nomenclatura Obligatoria de Commits

Salvo bootstrap inicial explícito del repositorio, todos los commits deben seguir el formato del equipo:

```text
<usuario>/<Tipo>: <descripción concisa en inglés> #<issue>
```

> Si el commit corresponde a un **Draft Issue** del tablero sin número de issue en el repositorio (como este `AGENTS.md`), omitir `#<issue>`:
> `<usuario>/<Tipo>: <descripción concisa en inglés>`

### Valores permitidos

- **`<usuario>`:** `ibrenas` | `rtito` | `gramirez` | `zyin-08`
  *(El agente debe usar el prefijo del desarrollador que solicita la tarea; si no está claro, debe preguntar antes de commitear).*
- **`<Tipo>`:**
  - `Feat` — Nueva funcionalidad, componente o endpoint
  - `Fix` — Corrección de bug, configuración o fallo de CI
  - `Docs` — Documentación técnica, `README.md`, evidencias o `AGENTS.md`
  - `Test` — Tests unitarios o E2E (PHPUnit, Vitest, Newman)
  - `CI` — Workflows de GitHub Actions y pipelines
  - `Style` — Ajustes de formato (PSR-12, Prettier) sin cambio funcional
  - `Refactor` — Refactorización de código sin alterar comportamiento
  - `Chore` — Mantenimiento, dependencias o configuración base
  - `Revert` — Reversión de cambios previos

### Ejemplos válidos

- `ibrenas/Feat: implement delete nurse endpoint DELETE /nurse/{id} #57`
- `rtito/CI: add 3-code-quality workflow with linting and PSR-12 check #60`
- `gramirez/Test: add PHPUnit tests for POST /nurse responses #54`
- `ibrenas/Docs: add shared AGENTS.md guidelines for backend and frontend`

### Restricciones de historial Git

- Realizar commits atómicos, claros y en **inglés** (modo imperativo).
- **Prohibido** ejecutar `git commit --amend`, `git rebase` o `git push --force` sobre ramas compartidas o historial ya publicado sin autorización explícita del usuario.

---

## 5. Verificación y Estándares de Calidad antes de Entregar

Antes de finalizar cualquier tarea de código, el agente debe ejecutar las comprobaciones del repositorio modificado:

### En `Nurse-Simulator` (Backend)

```bash
# 1. Sintaxis PHP
find src tests config -name '*.php' -print0 | xargs -0 -n1 php -l

# 2. Linting de Symfony (YAML y contenedor DI)
php bin/console lint:yaml config/
php bin/console lint:container

# 3. Estándar de estilo PSR-12 (dry-run)
vendor/bin/php-cs-fixer fix --dry-run --diff --using-cache=no

# 4. Suite de tests unitarios y funcionales
php bin/phpunit

# 5. Consistencia y auditoría de dependencias
composer validate --strict --no-check-publish
composer audit --locked --no-interaction
```

### En `Nurse-Simulator-Frontend` (Frontend)

```bash
# 1. Compilación de la aplicación Angular
npm run build

# 2. Ejecución de tests unitarios (Vitest)
npm test
```

Además, en cambios de UI Angular verificar:
- Cumplimiento de `.prettierrc` (`singleQuote: true`, `printWidth: 100`) y `.editorconfig`.
- Uso de `.html` dedicado por componente, `@if` / `@switch` / `@for`, iconografía/imágenes, interacción `(click)` y navegación por rutas sin errores de consola.

---

## 6. Normas de Comportamiento y Seguridad para Agentes de IA

1. **Alcance estricto:** Modifica únicamente los archivos necesarios para la tarea asignada. No refactorices, renombres ni elimines código o documentación no relacionada sin pedir confirmación.
2. **Confirmación antes de acciones de Git o cambios transversales:** No hagas `git commit`, `git push`, merges ni crees/cierres issues o PRs sin que el usuario lo haya pedido expresamente. Si falta contexto (p. ej., rama base o prefijo de usuario), pregunta primero.
3. **Protección total de secretos y credenciales:**
   - **Nunca** leas ni expongas en commits, código, logs o respuestas los valores reales de `.env`, `.env.local`, contraseñas de Supabase/PostgreSQL o tokens de GitHub.
   - Si se añade una nueva variable de entorno en Backend, declara únicamente su nombre y un valor de ejemplo ficticio en `.env.example` (verificado por `4-security-audit.yml`).
4. **Integridad documental:** Respeta comentarios existentes, docstrings y las memorias bilingües (`docs/general/DOCUMENTACION_ES.md` y `DOCUMENTATION_EN.md`) salvo que la tarea consista en actualizarlas.
5. **Veracidad absoluta al informar:**
   - Nunca inventes rutas, símbolos, endpoints o resultados de pruebas.
   - Reporta como superados únicamente los comandos y tests ejecutados **después del último cambio**, indicando exactamente su alcance o cualquier error/limitación detectada.
6. **Idioma:** Comunícate con el usuario en **español** (salvo que escriba en otro idioma), manteniendo en **inglés** los mensajes de commit, nombres técnicos de símbolos/ramas y títulos de issues/PRs según la convención del proyecto.
