# Nurse Simulator — Comprehensive Project Documentation

> **M0616 - Intermodular Project | DAW 2 (Academic Year 2026/2027)**  
> **Institution:** STUCOM Pelai (Barcelona)  
> **Team:** Group pr03  
> **Authors:** Ignacio Breñas Muñoz, Christopher Roger Tito Farez, Zehao Yin, Gorka Ramirez  
> **Repository:** [https://github.com/Ignaciobrenas/Nurse-Simulator](https://github.com/Ignaciobrenas/Nurse-Simulator)

---

## 1. Executive Summary & Team Roles

**Nurse Simulator** is a RESTful API built with **Symfony 7.4** and **PHP 8.2+** designed to simulate the management of hospital nursing staff.

The project is structured as an academic team endeavor conceived to evolve incrementally across progressive development phases. In this initial phase, the API exposes essential web services for querying, searching, and authenticating nurses, utilizing a file-based storage architecture decoupled from traditional relational database systems.

### Development Team & Roles

| Member | Role | Responsibilities |
|---|---|---|
| **Ignacio Breñas Muñoz** | **Project Manager & Tech Lead** | Overall team organization, feature branch creation for each developer, issue breakdown and assignment in GitHub Projects, Pull Request reviews, and project quality assurance. |
| **Christopher Roger Tito Farez** | **Backend Developer** | Implementation of `GET /nurse/index` endpoint and Postman run evidence. |
| **Zehao Yin** | **Backend Developer** | Implementation of `GET /nurse/name/{name}` endpoint and 1,000 nurses mock dataset expansion. |
| **Gorka Ramirez** | **Backend Developer** | Implementation of `POST /nurse/login` credential validation and Postman test evidence. |

---

## 2. Data Architecture & Storage

In the current development phase, the application does not rely on a relational database (such as MySQL or PostgreSQL). Instead, all data is read and parsed from a structured JSON file:

* **File Location:** [`data/nurses.json`](file:///C:/Users/Ignacio/Desktop/Nurse-Simulator/data/nurses.json)
* **Dataset Volume:** Contains 1,000 mock records generated to evaluate the API's behavior and performance under realistic load conditions.

### Nurse Entity Schema

Each row in the `nurses` table on Supabase contains the following fields:

| Field | Data Type | Description |
|---|---|---|
| `id` | `int` | Unique identifier for the nurse |
| `name` | `string` | Full name of the nurse |
| `email` | `string` | Contact email address |
| `password` | `string` | Access credential |

> [!WARNING]
> Passwords are currently stored in **plain text** solely for educational and local prototyping purposes. Future phases will introduce cryptographic hashing via `PasswordHasherInterface` and secure token/session-based authentication.

---

## 3. Methodology & Git Workflow

The team adopts an agile development methodology supported by **GitHub** collaboration tools:

```mermaid
flowchart LR
    Issue["GitHub Issue\n(#n)"] --> Branch["Feature Branch\n<n>-<description>"]
    Branch --> Commits["Conventional Commits\nuser/Type: description #issue"]
    Commits --> PR["Pull Request with review"]
    PR --> Staging["Staging Branch"]
    Staging --> Main["Main Branch (Production)"]
```

### Core Workflow Guidelines:
1. **GitHub Projects:** Tasks are tracked on a Kanban board across three columns: `Todo`, `In progress`, and `Done`.
2. **Issues & Branching:** Every feature or bugfix is linked to an *Issue*. Developers create a dedicated branch formatted as:  
   `<issue_number>-<description>`  
   *(Example: `2-implement-read-all-nurses-from-file`)*.
3. **Commit Convention:**  
   `user/Type: description #issue`  
   *(Example: `roger/Feat: implement read all nurses from file #2`)*.
4. **Pull Requests & Code Review:** All branches must go through a peer-reviewed *Pull Request* targeting `staging` before final integration into `main`.

---

## 4. Completed Work & Development Phases

### 4.1. Basic Framework Setup
* Initialized the core project structure on **Symfony 7.4**.
* Configured Composer dependencies via [`composer.json`](file:///C:/Users/Ignacio/Desktop/Nurse-Simulator/composer.json), application lifecycle in [`src/Kernel.php`](file:///C:/Users/Ignacio/Desktop/Nurse-Simulator/src/Kernel.php), and routes in [`config/routes.yaml`](file:///C:/Users/Ignacio/Desktop/Nurse-Simulator/config/routes.yaml).
* Provided a [`.env.example`](file:///C:/Users/Ignacio/Desktop/Nurse-Simulator/.env.example) environment template to protect secrets and facilitate local environment setup.

### 4.2. Read All Nurses From File
* **Branch:** `2-implement-read-all-nurses-from-file`
* **Implementation:** `GET /nurse/index` endpoint inside [`NurseController`](file:///C:/Users/Ignacio/Desktop/Nurse-Simulator/src/Controller/NurseController.php).
* **Logic:**
  1. Verifies the physical existence of [`data/nurses.json`](file:///C:/Users/Ignacio/Desktop/Nurse-Simulator/data/nurses.json). If missing, returns HTTP 500 (`nurses.json not found`).
  2. Reads and parses JSON contents. If invalid or corrupt, returns HTTP 500 (`nurses.json is not valid`).
  3. Returns the complete array of nurse records with HTTP 200 JSON response upon success.

### 4.3. Implement Nurse Login From File
* **Branch:** `3-implement-nurse-login-from-file`
* **Implementation:** `POST /nurse/login` endpoint inside [`NurseController`](file:///C:/Users/Ignacio/Desktop/Nurse-Simulator/src/Controller/NurseController.php).
* **Logic:**
  1. Accepts a JSON request body containing `username` and `password`.
  2. Validates that both fields are supplied. If either is missing, responds with HTTP 400 (`Username and password are required`).
  3. Compares credentials against records in [`data/nurses.json`](file:///C:/Users/Ignacio/Desktop/Nurse-Simulator/data/nurses.json):
     * The `username` field flexibly matches either the **`email`** or the **`name`** field using case-insensitive comparison (`strcasecmp`).
  4. Returns HTTP 200 with `{"success": true}` on matching credentials.
  5. Returns HTTP 401 Unauthorized with `{"success": false}` if credentials do not match.

### 4.4. Find Nurse By Name From File
* **Branch:** `4-implement-find-nurse-by-name-from-file`
* **Implementation:** `GET /nurse/name/{name}` endpoint inside [`NurseController`](file:///C:/Users/Ignacio/Desktop/Nurse-Simulator/src/Controller/NurseController.php).
* **Logic:**
  1. Extracts `{name}` from the route parameters.
  2. Queries the `nurses` table on Supabase via Doctrine ORM, filtering by name (case-insensitive).
  3. Returns HTTP 200 with the matching nurse object if found.
  4. Returns HTTP 404 with `{"error": "Nurse not found"}` if no matching record exists.
  5. Mock data was migrated from the JSON file into the Supabase database.

### 4.5. Read Nurse By ID
* **Branch:** `55-implement-read-nurse-by-id-endpoint-get-nurseid`
* **Implementation:** `GET /nurse/{id}` endpoint in `NurseController::getById`, using `NurseRepository::find()`.
* **Logic:**
  1. Validates that `{id}` is a positive integer (`filter_var` with `min_range: 1`); otherwise HTTP 400.
  2. Looks the record up with `NurseRepository::find($id)`; if missing, HTTP 404.
  3. Returns the nurse with HTTP 200 **excluding the `password` field**.
  4. Doctrine (DBAL/ORM) exceptions are caught and returned as a JSON HTTP 500.
* **Technical decisions:**
  * The route has no `requirements: ['id' => '\d+']`: with it, `/nurse/abc` would yield a router 404 instead of the required 400.
  * `server_version` was set in `config/packages/doctrine.yaml` so Doctrine does not connect to the DB when building the repository; a connection failure is thus caught inside the controller.
* **Tests:** PHPUnit in `tests/Controller/NurseControllerGetByIdTest.php` (200, 400, 404, 500). Screenshots in `docs/postman/readById/`.

### 4.6. Update Nurse
* **Branch:** `56-implement-update-nurse-endpoint-put-nurseid`
* **Implementation:** `PUT /nurse/{id}` endpoint in `NurseController::update`, using `NurseRepository::find()` and `EntityManagerInterface::flush()`.
* **Logic:**
  1. Validates that `{id}` is a positive integer (`filter_var` with `min_range: 1`); otherwise HTTP 400.
  2. Parses the body with `json_decode(..., JSON_THROW_ON_ERROR)`; if the JSON is malformed, empty or not an object with data, HTTP 400.
  3. Looks the nurse up with `NurseRepository::find($id)`; if missing, HTTP 404.
  4. Validates the fields: `name` and `email` are required (PUT replaces the resource), the email must be well formed and `password` is optional but, when sent, must be at least 8 characters with letters and numbers. Any failure returns HTTP 422.
  5. If the submitted data matches the stored data and no `password` is sent, returns HTTP 204 with no body and does not touch the DB.
  6. If the email belongs to another nurse, HTTP 409.
  7. Applies the changes (lowercased email, password hashed with `password_hash`), runs `flush()` and returns HTTP 200 with the nurse **excluding the `password` field**.
  8. Any Doctrine/DB failure during the lookup or the `flush()` returns a JSON HTTP 500.
* **Technical decisions:**
  * As in `GET /nurse/{id}`, the route has no `requirements: ['id' => '\d+']`: with it, `PUT /nurse/abc` would yield a router 405 instead of a 400.
  * The 409 is checked with `findOneBy(['email' => ...])` before `flush()`, and `UniqueConstraintViolationException` is also caught in case two requests arrive at the same time.
  * Same validation rules as `POST /nurse` so both endpoints stay consistent.
* **Tests:** PHPUnit in `tests/Controller/NurseControllerUpdateTest.php` (200, 204, 400, 404, 409, 422, 500). Screenshots in `docs/postman/update/`.

---

## 5. API Specification & Endpoints

### Endpoint Summary

| Method | Endpoint | Parameters | Expected Responses |
|---|---|---|---|
| `GET` | `/nurse/index` | None | `200 OK`: Array of all nurses<br>`500 Internal Server Error`: Missing file or corrupt JSON |
| `GET` | `/nurse/name/{name}` | Path param: `name` (string) | `200 OK`: Nurse object<br>`404 Not Found`: `{ "error": "Nurse not found" }` |
| `GET` | `/nurse/{id}` | Path param: `id` (integer > 0) | `200 OK`: Nurse without `password`<br>`400 Bad Request`: invalid `id`<br>`404 Not Found`: `{ "error": "Nurse not found" }`<br>`500 Internal Server Error`: DB failure |
| `PUT` | `/nurse/{id}` | Path param: `id` (integer > 0)<br>JSON Body: `name`, `email`, `password` (optional) | `200 OK`: Updated nurse without `password`<br>`204 No Content`: No changes<br>`400 Bad Request`: Invalid `id` or malformed/empty JSON<br>`404 Not Found`: `{ "error": "Nurse not found" }`<br>`409 Conflict`: Email belongs to another nurse<br>`422 Unprocessable Content`: Invalid or missing fields<br>`500 Internal Server Error`: DB failure |
| `POST` | `/nurse/login` | JSON Body: `username`, `password` | `200 OK`: `{ "success": true }`<br>`400 Bad Request`: Missing credentials<br>`401 Unauthorized`: `{ "success": false }` |

### Request and Response Examples

#### 1. Retrieve all nurses
```bash
curl -X GET http://localhost:8000/nurse/index
```
**Response (HTTP 200):**
```json
[
  {
    "id": 1,
    "name": "Ana",
    "email": "ana@hospital.com",
    "password": "ana123"
  },
  {
    "id": 2,
    "name": "Carlos",
    "email": "carlos@hospital.com",
    "password": "carlos123"
  }
]
```

#### 2. Search nurse by name
```bash
curl -X GET http://localhost:8000/nurse/name/Ana
```
**Response (HTTP 200):**
```json
{
  "id": 1,
  "name": "Ana",
  "email": "ana@hospital.com",
  "password": "ana123"
}
```
**Error Response (HTTP 404):**
```json
{
  "error": "Nurse not found"
}
```

#### 2b. Search by ID
```bash
curl -X GET http://localhost:8000/nurse/1000
```
**Response (HTTP 200):**
```json
{
  "id": 1000,
  "name": "Andrea Alvarez",
  "email": "andrea.alvarez@hospital.com"
}
```
**Errors:** `/nurse/1a` → HTTP 400 `{"error": "Invalid id: must be a positive integer"}`; `/nurse/99999` → HTTP 404 `{"error": "Nurse not found"}`.

#### 2c. Update nurse
```bash
curl -X PUT http://localhost:8000/nurse/3 \
  -H "Content-Type: application/json" \
  -d '{"name": "Lucia Updated", "email": "lucia.updated@hospital.com"}'
```
**Response (HTTP 200):**
```json
{
  "id": 3,
  "name": "Lucia Updated",
  "email": "lucia.updated@hospital.com"
}
```
**Errors:** repeating the same request → HTTP 204 with no body; `/nurse/1a` → HTTP 400 `{"error": "Invalid id: must be a positive integer"}`; body `{"name":` → HTTP 400 `{"error": "Malformed JSON"}`; `/nurse/99999` → HTTP 404 `{"error": "Nurse not found"}`; another nurse's email → HTTP 409 `{"error": "Email already registered"}`; email `no-email` → HTTP 422 `{"error": "Invalid email format"}`.

#### 3. Nurse login
```bash
curl -X POST http://localhost:8000/nurse/login \
  -H "Content-Type: application/json" \
  -d '{"username": "ana@hospital.com", "password": "ana123"}'
```
**Success Response (HTTP 200):**
```json
{
  "success": true
}
```
**Unauthorized Response (HTTP 401):**
```json
{
  "success": false
}
```
**Missing Fields Response (HTTP 400):**
```json
{
  "success": false,
  "message": "Username and password are required"
}
```

---

## 6. Architecture, UML Schemas & Flowcharts

### 6.1. Modular Integration Schema (Individual Development to Group Repository)

Based on the official intermodular assignment guidelines (`pr03_Introduccio_back_end`), each developer implemented their endpoint method individually before coordinated merging into the centralized group repository:

```mermaid
graph TD
    subgraph Individual Developments
        M1["👤 Member 1 (Gorka)<br><b>NurseController</b><br>+login(user, pw): boolean"]
        M2["👤 Member 2 (Roger)<br><b>NurseController</b><br>+getAll(): List&lt;Nurse&gt;"]
        M3["👤 Member 3 (Zehao)<br><b>NurseController</b><br>+findByName(name): Nurse"]
    end

    subgraph Centralized Group Repository
        GC["👥 Group pr03 (Ignacio, Roger, Zehao, Gorka)<br><b>NurseController</b><br>─────────────────────────────<br>+getAll(): JsonResponse<br>+findByName(name: string): JsonResponse<br>+login(request: Request): JsonResponse"]
    end

    M1 -->|Pull Request #17| GC
    M2 -->|Pull Request #16| GC
    M3 -->|Pull Request #15| GC
```

### 6.2. UML Class Diagram

Representation of the application classes and relationships built on Symfony 7.4:

```mermaid
classDiagram
    direction TB
    class Kernel {
        <<MicroKernelTrait>>
        +boot()
        +registerBundles()
    }

    class AbstractController {
        <<Symfony Framework>>
        +json(data, status, headers, context) JsonResponse
        +getParameter(name) mixed
    }

    class NurseController {
        +getAll() JsonResponse
        +findByName(name: string) JsonResponse
        +login(request: Request) JsonResponse
    }

    class JsonResponse {
        <<Symfony HttpFoundation>>
        +int statusCode
        +string content
    }

    class Request {
        <<Symfony HttpFoundation>>
        +getContent() string
    }

    class NurseEntity {
        <<Data Model>>
        +int id
        +string name
        +string email
        +string password
    }

    class NursesStorage {
        <<Supabase: nurses table>>
        +1000 items
    }

    AbstractController <|-- NurseController
    NurseController ..> Request : receives
    NurseController ..> JsonResponse : returns
    NurseController ..> NursesStorage : queries via Doctrine ORM
    NursesStorage "1" *-- "1000" NurseEntity : stores
    Kernel ..> NurseController : routes requests
```

### 6.3. Sequence Diagram: Search Nurse by Name

```mermaid
sequenceDiagram
    actor Client as HTTP Client
    participant Kernel as Symfony Kernel
    participant Controller as NurseController
    participant JSON as Supabase (nurses table)

    Client->>Kernel: GET /nurse/name/{name}
    Kernel->>Controller: findByName(name)
    Controller->>JSON: Doctrine ORM query (findByName)
    JSON-->>Controller: Nurses list
    alt Nurse found
        Controller-->>Client: 200 OK (Nurse JSON object)
    else Not found
        Controller-->>Client: 404 Not Found {"error": "Nurse not found"}
    end
```

### 6.4. Sequence Diagram: Nurse Login

```mermaid
sequenceDiagram
    actor Client as HTTP Client
    participant Kernel as Symfony Kernel
    participant Controller as NurseController
    participant JSON as Supabase (nurses table)

    Client->>Kernel: POST /nurse/login (username, password)
    Kernel->>Controller: login(Request)
    alt Missing username or password
        Controller-->>Client: 400 Bad Request
    else Payload valid
        Controller->>JSON: Doctrine ORM query (findAll)
        JSON-->>Controller: Nurses list
        loop Credential check
            Controller->>Controller: Verify email/name and password
        end
        alt Valid credentials
            Controller-->>Client: 200 OK {"success": true}
        else Invalid credentials
            Controller-->>Client: 401 Unauthorized {"success": false}
        end
    end
```

### 6.5. Sequence Diagram: Read All Nurses

```mermaid
sequenceDiagram
    actor Client as HTTP Client
    participant Controller as NurseController
    participant JSON as Supabase (nurses table)

    Client->>Controller: GET /nurse/index
    Controller->>JSON: Doctrine ORM query (findAll)
    JSON-->>Controller: Nurses array
    Controller-->>Client: 200 OK (Nurses Array)
```

### 6.6. Application Logic Flowchart

```mermaid
flowchart TD
    Start([Incoming HTTP Request]) --> RouteCheck{Registered Route?}
    RouteCheck -- No --> R404[404 Route Not Found]
    RouteCheck -- Yes --> EndpointSwitch{Endpoint Routing}

    %% Branch /nurse/index
    EndpointSwitch -- GET /nurse/index --> CheckFile1{File nurses.json exists?}
    CheckFile1 -- No --> Err500_1[500: nurses.json not found]
    CheckFile1 -- Yes --> Parse1{Valid JSON?}
    Parse1 -- No --> Err500_2[500: nurses.json is not valid]
    Parse1 -- Yes --> Resp200_All[200 OK: Total list of nurses]

    %% Branch /nurse/name/{name}
    EndpointSwitch -- GET /nurse/name/{name} --> LoadFile2[Load nurses.json]
    LoadFile2 --> LoopSearch{Match strcasecmp?}
    LoopSearch -- Yes --> Resp200_Nurse[200 OK: Nurse JSON object]
    LoopSearch -- No --> Err404_Nurse[404 Not Found: Nurse not found]

    %% Branch /nurse/login
    EndpointSwitch -- POST /nurse/login --> CheckBody{Username and password provided?}
    CheckBody -- No --> Err400[400 Bad Request: Username and password required]
    CheckBody -- Yes --> MatchCreds{Credentials match?}
    MatchCreds -- Yes --> Resp200_Log[200 OK: success true]
    MatchCreds -- No --> Err401[401 Unauthorized: success false]
```

---

## 7. Testing & Postman Collection

A dedicated **Postman** collection with verified request templates and test evidence is available in:
* [`docs/postman/nurse-api.postman_collection.json`](file:///C:/Users/Ignacio/Desktop/Nurse-Simulator/docs/postman/nurse-api.postman_collection.json)

The collection includes organized subfolders mirroring test executions:
* [`docs/postman/readAll`](file:///C:/Users/Ignacio/Desktop/Nurse-Simulator/docs/postman/readAll): Tests for `/nurse/index`.
* [`docs/postman/readByName`](file:///C:/Users/Ignacio/Desktop/Nurse-Simulator/docs/postman/readByName): Tests for `/nurse/name/{name}` covering 200 and 404 statuses.
* [`docs/postman/login`](file:///C:/Users/Ignacio/Desktop/Nurse-Simulator/docs/postman/login): Tests for `/nurse/login` covering 200, 400, and 401 statuses.

---

## 8. Project Structure

```
Nurse-Simulator/
├── bin/
│   └── console                     # Symfony CLI tool
├── config/                         # Configuration files (routes, packages, services)
│   ├── packages/
│   ├── routes.yaml
│   └── services.yaml
├── data/
│   └── nurses.json                 # Mock dataset (1,000 nurses)
├── docs/                           # Documentation directory
│   ├── general/
│   │   ├── DOCUMENTACION_ES.md     # Full Spanish documentation
│   │   ├── DOCUMENTATION_EN.md     # Full English documentation
│   │   ├── Nurse-Simulator.docx    # Project report document
│   │   └── Nurse-Simulator_...pdf  # Official academic report (PDF)
│   └── postman/                    # Postman collection and run evidence
│       ├── login/
│       ├── readAll/
│       ├── readByName/
│       └── nurse-api.postman_collection.json
├── public/
│   └── index.php                   # Web application entry point (Front Controller)
├── src/
│   ├── Controller/
│   │   └── NurseController.php     # Controller handling nurse endpoints
│   └── Kernel.php                  # Symfony application kernel
├── .env.example                    # Environment configuration template
├── composer.json                   # PHP dependencies and autoload definitions
├── README.md                       # Quick start guide
└── LICENSE                         # Project license
```

---

## 9. Installation & Local Execution

### Prerequisites
* **PHP** >= 8.2 (with `mbstring`, `json`, `ctype`, `pdo_pgsql`, etc.)
* **Composer** >= 2.0
* **A [Supabase](https://supabase.com/) account and project** with the 
urses table set up
* *(Optional)* **Symfony CLI**

### Setup Instructions

1. **Clone the repository:**
   ```bash
   git clone https://github.com/Ignaciobrenas/Nurse-Simulator.git
   cd Nurse-Simulator
   ```

2. **Install dependencies:**
   ```bash
   composer install
   ```

3. **Configure environment:**
   ```bash
   cp .env.example .env
   ```

4. **Launch the development server:**
   * Using Symfony CLI:
     ```bash
     symfony server:start
     ```
   * Or with PHP's built-in server:
     ```bash
     php -S localhost:8000 -t public
     ```

5. **Inspect registered routes:**
   ```bash
   php bin/console debug:router
   ```

---

## 10. Roadmap & Upcoming Milestones

The following architectural enhancements are scheduled for upcoming phases:

1. **Persistence & Repository Layer:** Decouple file reading from the controller into a dedicated `NurseRepository` service.
2. **Entities & DTOs:** Introduce strongly-typed models with validation constraints (Symfony Validator).
3. **Security & Hashing:** Implement secure password hashing via `PasswordHasherInterface` and modern authentication (JWT / sessions).
4. **Relational Database:** âœ… Completed â€” the API now uses **Supabase (PostgreSQL)** as a remote database, managed through **Doctrine ORM** and migrations.
5. **Automated Testing:** Integrate unit and end-to-end integration test suites with PHPUnit and Symfony's `WebTestCase`.
