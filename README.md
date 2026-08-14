# Task Management System

This is my submission for the technical assignment. It's a decoupled Task Management System built with Laravel 11 for the backend and AngularJS for the frontend.

## The Approach & Key Decisions
The core of this assignment was the **Dynamic Rule-Based Assignment Engine**. Rather than doing everything synchronously when a task is created (which could block the API), I decided to move the assignment logic to a background queue (`EvaluateTaskEligibility`). 

I separated the business logic into a dedicated `RuleEngineService`. This ensures that the queue jobs are only responsible for dispatching, while the service handles the actual database interactions and tie-breakers. 

To prevent race conditions (e.g. if two workers try to assign the same task simultaneously), I wrapped the assignment logic in a database transaction with pessimistic locking (`lockForUpdate`).

### Database & Performance Strategy
The assignment asked to consider a system with 100k+ users. While I haven't load-tested this specific docker container with 100k users, I designed the database schema to theoretically support it:
- I created a composite index on `(department, active_tasks_count, years_of_experience)`. This helps the database quickly filter and sort users without scanning the entire table.
- For API performance, the dashboard route is cached using Redis. This prevents constant database queries on every page load. The cache is immediately invalidated whenever a user's task status changes to ensure data consistency.

### What I'd Improve with More Time
- **Frontend Stack**: While AngularJS works well for this SPA, in a real-world scenario I would transition to React or Vue with a proper build step (Vite/Webpack) for better modularity.
- **CI/CD**: I would add automated GitHub Action pipelines to run the PHPUnit tests on every pull request.
- **Queue Workers**: In this Docker setup, the queue is processed synchronously for simplicity. In production, I would use Laravel Horizon and Supervisor to manage Redis queue workers.

## Setup Instructions

Make sure you have Docker installed on your machine.

1. Clone the repository:
```bash
git clone <repo-url>
cd technical-assignment
```

2. Setup environment variables:
Create a `.env` file in the root directory if you want to override the default database credentials. (By default, the `docker-compose.yml` falls back to default test credentials).

3. Start the Docker containers:
```bash
docker compose up -d --build
```
*Note: The `app` service has a `depends_on` condition that waits for MySQL and Redis to pass their healthchecks before booting.*

4. Run the migrations and insert the test data:
```bash
docker compose exec app php artisan migrate --seed
```

5. Access the app:
- UI: `http://localhost:8080`
- API: `http://localhost:8000`

### Test Accounts (Password: `password123`)
- `admin@example.com` (Admin in IT)
- `hr@example.com` (Manager in HR)
- `finance@example.com` (Normal user in Finance)

## Testing
I've included a PHPUnit test suite that covers the Rule Engine edge cases (including tie-breakers, failure scenarios, and reassignments) as well as the HTTP endpoints. 

Run the tests inside the container:
```bash
docker compose exec app php artisan test
```

## Assumptions & Limitations
- **Active Tasks**: I defined "active tasks" as tasks that are not marked as "Completed" or "Done". 
- **Reassignment**: If a user updates their profile (e.g. gets a promotion or changes department), an Observer triggers a background re-evaluation of all incomplete tasks to see if they are a better fit than the currently assigned user.
