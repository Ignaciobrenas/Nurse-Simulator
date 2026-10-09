# Nurse Simulator — Comprehensive Project Documentation

> **M0616 - Intermodular Project | DAW 2 (Academic Year 2026/2027)**  
> **Institution:** Stucom  
> **Team:** Group pr03  
> **Authors:** Ignacio Breñas Muñoz, Christopher Roger Tito Farez, Zehao Yin, Gorka Ramirez  
> **Repository:** [https://github.com/Ignaciobrenas/Nurse-Simulator](https://github.com/Ignaciobrenas/Nurse-Simulator)

---

## 1. Executive Summary & Purpose

**Nurse Simulator** is a RESTful API built with **Symfony 7.4** and **PHP 8.2+** designed to simulate the management of hospital nursing staff.

The project is structured as an academic team endeavor conceived to evolve incrementally across progressive development phases. In this initial phase, the API exposes essential web services for querying, searching, and authenticating nurses, utilizing a file-based storage architecture decoupled from traditional relational database systems.

---

## 2. Data Architecture & Storage

In the current development phase, the application does not rely on a relational database (such as MySQL or PostgreSQL). Instead, all data is read and parsed from a structured JSON file:

* **File Location:** [`data/nurses.json`](file:///C:/Users/Ignacio/Desktop/Nurse-Simulator/data/nurses.json)
* **Dataset Volume:** Contains 1,000 mock records generated to evaluate the API's behavior and performance under realistic load conditions.

### Nurse Entity Schema

Each record in [`data/nurses.json`](file:///C:/Users/Ignacio/Desktop/Nurse-Simulator/data/nurses.json) contains the following fields:

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
  2. Iterates over the records, comparing names case-insensitively (`strcasecmp`).
  3. Returns HTTP 200 with the matching nurse object if found.
  4. Returns HTTP 404 with `{"error": "Nurse not found"}` if no matching record exists.
  5. Expanded the mock dataset to 1,000 nurses in this phase to stress-test retrieval.

---

## 5. API Specification & Endpoints

### Endpoint Summary

| Method | Endpoint | Parameters | Expected Responses |
|---|---|---|---|
| `GET` | `/nurse/index` | None | `200 OK`: Array of all nurses<br>`500 Internal Server Error`: Missing file or corrupt JSON |
| `GET` | `/nurse/name/{name}` | Path param: `name` (string) | `200 OK`: Nurse object<br>`404 Not Found`: `{ "error": "Nurse not found" }` |
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

## 6. Architecture & Sequence Diagrams

### 6.1. Sequence Diagram: Search Nurse by Name

```mermaid
sequenceDiagram
    actor Client as HTTP Client
    participant Kernel as Symfony Kernel
    participant Controller as NurseController
    participant JSON as data/nurses.json

    Client->>Kernel: GET /nurse/name/{name}
    Kernel->>Controller: findByName(name)
    Controller->>JSON: file_get_contents + json_decode
    JSON-->>Controller: Nurses list
    loop Search match
        Controller->>Controller: strcasecmp(nurse.name, name)
    end
    alt Nurse found
        Controller-->>Client: 200 OK (Nurse JSON object)
    else Not found
        Controller-->>Client: 404 Not Found {"error": "Nurse not found"}
    end
```

### 6.2. Sequence Diagram: Nurse Login

```mermaid
sequenceDiagram
    actor Client as HTTP Client
    participant Kernel as Symfony Kernel
    participant Controller as NurseController
    participant JSON as data/nurses.json

    Client->>Kernel: POST /nurse/login (username, password)
    Kernel->>Controller: login(Request)
    alt Missing username or password
        Controller-->>Client: 400 Bad Request
    else Payload valid
        Controller->>JSON: Read file contents
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

### 6.3. Sequence Diagram: Read All Nurses

```mermaid
sequenceDiagram
    actor Client as HTTP Client
    participant Controller as NurseController
    participant JSON as data/nurses.json

    Client->>Controller: GET /nurse/index
    Controller->>JSON: is_file(data/nurses.json)?
    alt File missing
        Controller-->>Client: 500 Internal Server Error {"error": "nurses.json not found"}
    else File exists
        Controller->>JSON: json_decode(file_get_contents())
        alt JSON parsing error
            Controller-->>Client: 500 Internal Server Error {"error": "nurses.json is not valid"}
        else Valid JSON
            Controller-->>Client: 200 OK (Nurses Array)
        end
    end
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
* **PHP** >= 8.2 (with `mbstring`, `json`, `ctype`, etc.)
* **Composer** >= 2.0
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
4. **Relational Database:** Migrate storage from JSON to SQL using **Doctrine ORM** (MySQL / PostgreSQL / SQLite) with automated migrations.
5. **Automated Testing:** Integrate unit and end-to-end integration test suites with PHPUnit and Symfony's `WebTestCase`.
