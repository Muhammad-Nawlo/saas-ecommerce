# MODULE BOUNDARY RULES

## Rule MB-1 — No Cross-Module Infrastructure Usage

A module may NOT import:

Modules\OtherModule\Infrastructure\*
Modules\OtherModule\Http\*

Allowed:
Modules\OtherModule\Domain\Contracts\*

Violation Example:
use Modules\Order\Infrastructure\Persistence\Eloquent\OrderModel;

Correct:
use Modules\Order\Domain\Contracts\OrderRepository;

---

## Rule MB-2 — Domain Is Pure

Files inside:
Modules/*/Domain/*

MUST NOT reference:

Illuminate\
Spatie\
Stancl\
Filament\
DB
Auth
Cache
Request
Eloquent

Domain must be framework-agnostic.

---

## Rule MB-3 — Application Cannot Use Eloquent

Files inside:
Modules/*/Application/*

MUST NOT reference:

Infrastructure\Persistence\Eloquent\
Models\*
DB::
Model::

Application layer works only with:
- Domain Entities
- Domain Contracts
- DTOs
