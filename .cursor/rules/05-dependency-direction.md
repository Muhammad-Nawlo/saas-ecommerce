# DEPENDENCY DIRECTION RULES

Allowed Direction:

Http → Application → Domain
Infrastructure → Domain
Application → Shared
Infrastructure → Shared

Forbidden:

Domain → Application
Domain → Infrastructure
Application → Http
Domain → Laravel
Domain → Spatie
Domain → Stancl
