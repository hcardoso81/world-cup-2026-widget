# World Cup 2026 - estrategia de cache y sync

## Objetivo

Maximizar el plan Pro de API-Football sin gastar requests innecesarios.

El frontend siempre debe pedir datos al endpoint local del plugin:

```text
/wp-json/wc26/v1/fixtures
```

Ese endpoint debe comportarse igual con datos mock y datos reales:

1. Leer cache local persistente.
2. Si la cache esta fresca, responder sin llamar a API-Football.
3. Si esta vencida, intentar sincronizar.
4. Si hay otro sync en curso, devolver stale data si existe.
5. Si API-Football falla, devolver stale data si existe y guardar el error.

## Fuente de verdad local

La fuente principal para el frontend debe ser la base local de WordPress, no API-Football.

- Cache persistente: `wp_options`.
- Nombre: `wc26_fixtures_cache_{league}_{season}`.
- Autoload: desactivado.
- Lock anti rafagas: transient `wc26_fixtures_sync_lock_{league}_{season}`.

Memcached u object cache puede ayudar si el hosting lo provee, pero no debe ser requisito. En GoDaddy puede variar por plan; por eso la estrategia base debe funcionar con DB.

## Comportamiento mock vs real

El mock no debe ser un camino alternativo sin cache. Debe pasar por la misma capa:

```text
Frontend
  -> /wp-json/wc26/v1/fixtures
  -> FixturesSyncService
  -> FixturesRepository
  -> ApiFootballClient
      -> mock si simulation_enabled + simulation_mock_enabled
      -> API-Football si no hay mock
```

Esto permite probar la estrategia de cache, stale data, locks y cron con mock antes de usar requests reales.

Para temporada completa, `ApiFootballClient::fixturesForSeason()` no debe decidir cache. Debe comportarse como transporte puro: devuelve mock o API real, y deja que `FixturesSyncService` aplique la misma politica de cache para ambos casos.

## Horario argentino

Argentina usa UTC-3. La mayor parte del calendario 2026 en Norteamerica se publica en horarios locales/ET. Para plan operativo:

- 12:00 ET -> 13:00 ART
- 15:00 ET -> 16:00 ART
- 18:00 ET -> 19:00 ART
- 21:00 ET -> 22:00 ART
- 22:00 ET -> 23:00 ART
- 23:00 ET -> 00:00 ART del dia siguiente

El plugin debe planificar sync usando la fecha/hora de WordPress o una zona explicita de Argentina cuando se trate de mostrar el dia activo.

## Ventanas tacticas de cron

### Fuera de torneo

Usar frecuencia baja.

- Cada 12 horas.
- Objetivo: actualizar cambios de fixture, estadio, horarios o metadata.

### Dias sin partido

Usar frecuencia baja.

- Cada 6 horas.
- Si faltan menos de 48 horas para el proximo partido, cada 1 hora.

### Dias con partidos, antes del primer partido

Usar frecuencia media.

- Desde 6 horas antes del primer partido: cada 15 minutos.
- Desde 90 minutos antes del primer partido: cada 5 minutos.

### Durante ventana de partidos

Usar frecuencia alta, pero controlada.

- Desde 2 horas antes del primer partido hasta 3 horas despues del ultimo kickoff programado: cada 60 segundos.
- Si API-Football informa algun estado live (`1H`, `HT`, `2H`, `ET`, `P`, `LIVE`, `SUSP`, `INT`), TTL de cache: 60 segundos.

La ventana amplia evita quedar corto por demoras, alargues, penales, suspensiones por clima o carga tardia de datos. Ejemplo: si el primer partido empieza a las 16:00 ART y el ultimo empieza a la 01:00 ART, se refresca cada 60 segundos desde las 14:00 ART hasta las 04:00 ART.

### Despues del ultimo partido del dia

Usar frecuencia media y luego baja.

- Primeros 90 minutos despues del ultimo partido: cada 5 minutos.
- Luego: cada 15 minutos por 3 horas.
- Despues: cada 6 horas hasta el proximo dia con partidos.

## Dias de maxima actividad esperada

La fase de grupos concentra la mayor cantidad de partidos por dia. Para el mock actual:

| Dia ART | Partidos | Ventana operativa ART |
| --- | ---: | --- |
| 2026-06-08 | 4 | 13:00 a 22:00 |
| 2026-06-09 | 4 | 13:00 a 22:00 |
| 2026-06-10 | 4 | 13:00 a 22:00 |
| 2026-06-11 | 4 | 13:00 a 22:00 |
| 2026-06-12 | 4 | 13:00 a 22:00 |
| 2026-06-13 | 4 | 13:00 a 22:00 |

Plan recomendado para estos dias:

- 07:00 a 11:30 ART: cada 15 minutos.
- 11:30 a 23:30 ART: cada 60 segundos.
- 23:30 a 02:30 ART: cada 15 minutos.
- Resto del dia: cada 6 horas.

## Estimacion de requests

Si se usa una llamada agregada a fixtures por temporada o por rango y se refresca cada minuto durante 12 horas:

```text
12 horas * 60 minutos = 720 requests/dia
```

Con plan Pro:

```text
7500 requests/dia disponibles
```

Incluso con margen extra para retries, admin, warmups o endpoints complementarios, el consumo queda por debajo del limite si el frontend nunca pega directo a API-Football.

## Reglas de implementacion

- No llamar API-Football desde cada visita.
- No llamar API-Football desde cada click del carrusel.
- No llamar API-Football desde JS publico.
- El endpoint local puede disparar sync solo si la cache esta vencida.
- El cron debe intentar mantener la cache caliente.
- El frontend debe tolerar stale data.
- El mock debe usar la misma cache que el modo real.
- Guardar errores recientes para diagnostico en admin/log.

## Proximo paso tecnico sugerido

Agregar una politica de sync por calendario:

```php
SyncPolicy::ttlForFixtures(array $fixtures, DateTimeImmutable $now): int
```

La politica debe devolver:

- `60` si hay live.
- `60` desde 2 horas antes del primer partido hasta 3 horas despues del ultimo kickoff.
- `900` desde 6 horas antes del primer partido hasta la ventana de alta frecuencia.
- `900` desde el final de la ventana de alta frecuencia hasta 6 horas despues del ultimo kickoff.
- `21600` si no hay partido cercano.

Esto reemplaza el TTL fijo simple de `60 / 15 minutos` por uno mas tactico.
