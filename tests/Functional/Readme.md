# Functional Tests

Functional tests in this project use **[Testcontainers](https://testcontainers.com/)** to ensure reliability and environment parity.

## What is Testcontainers?

Testcontainers is a library that allows you to spin up real instances of databases, message brokers, or any other service in Docker containers during test execution. 

Instead of mocking external dependencies or relying on a manually pre-configured environment, the project automatically:
1. Starts the required Docker containers (e.g., YouTrack).
2. Runs tests against these live instances.
3. Cleans up (stops and removes) the containers after tests complete.

## How to Work with Them

- **Requirements**: You must have a Docker-compatible container runtime installed and running (Docker Desktop, Colima, etc.).
- **Execution**: Functional tests are usually part of the full test suite or can be run specifically via PHPUnit:
  ```bash
  vendor/bin/phpunit tests/Functional
  ```

## Further Reading

- [Official Testcontainers Documentation](https://testcontainers.com/getting-started/)
- [Testcontainers for PHP](https://github.com/testcontainers/testcontainers-php)
