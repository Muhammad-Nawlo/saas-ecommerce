# REPOSITORY RULES

---

## Rule R-1 — Domain Repositories Only

Domain:
Domain/Repositories/UserRepository.php

Infrastructure:
Infrastructure/Persistence/Repositories/EloquentUserRepository.php

Application must depend on:
Domain Repository Interface

Application must NOT depend on:
Eloquent models
