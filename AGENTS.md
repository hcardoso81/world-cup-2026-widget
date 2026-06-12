# World Cup 2026 Widget

## Proyecto

- Plugin: World Cup 2026 Widget
- Entrada principal: `world-cup-2026-widget.php`
- Namespace: `HernanCardoso\WorldCup2026Widget`
- Text domain: `world-cup-2026-widget`
- Version: `1.0.0`
- Objetivo: Generar cards propias, elegantes y livianas de partidos por fecha para el Mundial usando API-Football.
- Shortcode principal: `world_cup_2026_matches`.
- Shortcodes heredados/deshabilitados para widgets oficiales: `world_cup_2026_page`, `world_cup_2026_league`, `world_cup_2026_standings`, `world_cup_2026_game`, `world_cup_2026_player`, `world_cup_2026_team`.
- Opciones: `wc26_widget_settings`
- Integraciones: endpoint API-Football v3 `/fixtures`.
- Scripts externos: ninguno para el shortcode propio de cards; puede usar JS local en `assets/js/public.js`.
- Dependencias: WordPress Plugin API. Sin Composer.

## Criterios de desarrollo

- Mantener modulos y metodos chicos, con responsabilidades claras.
- Reutilizar helpers existentes antes de crear logica duplicada.
- Evitar archivos o clases gigantes; si una funcion crece demasiado, extraer partes con nombres claros.
- Usar CSS para layout y estados visuales, evitando estilos inline salvo variables dinamicas pequeñas y controladas.
- Priorizar clean code: nombres descriptivos, validacion cerca de la entrada, sanitizacion explicita y bajo acoplamiento.
- No hacer refactors amplios si no son necesarios para el cambio pedido.
- Al finalizar cualquier cambio, incluir un mensaje de commit sugerido.

## Arquitectura

El archivo principal solo define constantes, carga autoload, registra hooks de activacion y delega el arranque.

- `src/Bootstrap`: registra hooks.
- `src/Admin`: pantalla de ajustes y listado de shortcodes.
- `src/Api`: cliente HTTP propio para API-Football y endpoint REST propio.
- `src/Frontend`: shortcode y render propio de partidos.
- `src/Support`: settings y logger.

La pantalla admin debe estar como item principal de menu `World Cup 2026`, con icono de copa del mundo, no dentro de `Ajustes`.

La pantalla admin usa tabs:

- `Backend`: API key, league, season, logs y herramientas de simulacion.
- `Front end`: visibilidad del shortcode y cantidad de partidos por linea.

El cliente propio consulta `https://v3.football.api-sports.io/fixtures` con `league` y `season`, usando el header `x-apisports-key`. La llamada de temporada completa popula los resultados del shortcode y el frontend navega dias localmente sin nuevas llamadas.

`fixturesByDate()` se conserva por compatibilidad, pero el shortcode principal debe preferir `fixturesForSeason()` para poder navegar partidos anteriores y siguientes desde memoria/render inicial.

El plugin expone un endpoint tipo fetch en:

- REST route: `/wp-json/wc26/v1/fixtures`
- Clase: `src/Api/FixturesEndpoint.php`
- JS publico que lo consume: `assets/js/public.js`

El endpoint siempre responde datos reales desde API-Football usando PHP como proxy server-side y la cache compartida. Nunca exponer la API key al navegador.

El JS publico puede hacer polling contra este endpoint local para refrescar estados, marcador y minutos sin recargar la pagina. Ese polling debe respetar `SyncPolicy::refreshInterval()` via `window.WC26Widget.pollInterval`; API-Football sigue protegido por cache y lock server-side.

## Cache y Sincronizacion

La estrategia principal de datos debe ser server-side:

- `ApiFootballClient`: sabe como pedir datos reales a API-Football.
- `FixturesRepository`: guarda/lee fixtures en `wp_options`, con autoload desactivado.
- `FixturesSyncService`: decide cuando refrescar, aplica lock anti rafagas y conserva stale data si la API falla.
- `FixturesEndpoint`: responde al frontend desde la capa de sync/cache.

Para temporada completa, `ApiFootballClient::fixturesForSeason()` debe comportarse como transporte puro. No agregar cache interna ahi: la cache comun vive en `FixturesSyncService` + `FixturesRepository`.

El frontend nunca debe llamar API-Football directo. Siempre llama `/wp-json/wc26/v1/fixtures`.

La cache persistente vive en opciones por `league_id` y `season`, con nombres `wc26_fixtures_cache_{league}_{season}`. Los locks de sync usan transients cortos `wc26_fixtures_sync_lock_{league}_{season}`.

El cron `wc26_widget_sync_fixtures` corre con schedule `wc26_fixed_refresh_interval`, equivalente a 1 request por minuto. La cache, el lock y los transients compatibles deben usar la misma fuente de verdad: `SyncPolicy::refreshInterval()`, actualmente `MINUTE_IN_SECONDS`.

No volver a introducir umbrales dinamicos por partido en vivo, cercania al kickoff, cooldown, deltas ni ventanas de alta frecuencia. La regla operativa es simple: una consulta de temporada completa como maximo por minuto si la cache esta vencida.

Si la API falla y existe cache previa, se debe devolver stale data antes que romper el frontend. Guardar ultimos errores en la opcion de cache.

La temporada admite datos historicos desde 1930 para poder probar planes Free con `season=2022`.

## Shortcodes

- `[world_cup_2026_matches]`: tarjetas propias de partidos para la fecha actual de Argentina.
- `[world_cup_2026_matches amount_match_per_line="4"]`: renderiza hasta 4 partidos por linea en desktop.
- `[world_cup_2026_matches amount_match_per_line="3"]`: renderiza hasta 3 partidos por linea en desktop.
- `[world_cup_2026_matches amount_match_per_line="2"]`: renderiza hasta 2 partidos por linea en desktop.
- `[world_cup_2026_matches amount_match_per_line="1"]`: renderiza 1 partido por linea.
- `[world_cup_2026_matches date="2026-06-11"]`: fecha explicita solo debe afectar cuando la simulacion esta habilitada.

Tambien se acepta `matches_per_line` como alias interno/configurable, pero el shortcode generado para copiar debe usar `amount_match_per_line`.

`[world_cup_2026]` queda solo como compatibilidad hacia atras.

Los shortcodes de widgets oficiales permanecen registrados, pero responden con un aviso para administradores. La experiencia publica recomendada es solo `[world_cup_2026_matches]`.

## Render Propio

El shortcode propio renderiza cards por dia dentro de un carrusel local, sin cache visible ni URL de debug. Cada fixture de API-Football se normaliza internamente a:

- `id`
- `date`
- `stage`
- `stadium`
- `homeTeam`
- `homeLogo`
- `homeScore`
- `awayTeam`
- `awayLogo`
- `awayScore`
- `status`
- `statusLabel`
- `elapsed`
- `extra`
- `statusExtra`
- `isCurrent`
- `penalties`

La grilla de cards usa CSS Grid y una variable CSS `--wc26-matches-per-line`. Debe poder mostrar 4 partidos en una linea en desktop, con UI compacta para que entren los 4 partidos y sus 8 logos/banderas. En mobile debe priorizar legibilidad y puede caer a 1 columna.

Puede haber logica de `current match`, pero solo para destacar visualmente sin cambiar el ancho ni romper la grilla. El destaque debe ser por clase CSS (`wc26-match--current`) y debe mantener dimensiones estables.

Una card es current match si API-Football informa un estado live (`1H`, `HT`, `2H`, `ET`, `BT`, `P`, `SUSP`, `INT`, `LIVE`) o si, en modo simulacion, la fecha/hora simulada cae entre el kickoff y 150 minutos despues. Este modo de simulacion es solo para testing/demo visual y no debe modificar los datos crudos de API.

El estado visible debe localizarse al espanol. Para partidos live mostrar `En juego` salvo estados de pausa/interrupcion que tienen etiqueta propia (`HT`, `BT`, `P`, `SUSP`, `INT`). El texto extra del estado muestra hora de kickoff para `NS`/`TBD` y minutos transcurridos cuando API-Football envia `elapsed`, incluyendo partidos finalizados; en `HT` no se debe duplicar el minuto como extra.

La navegacion por dias debe funcionar como carrusel con botones anterior/siguiente. La API no debe llamarse al avanzar o retroceder dias; todo debe salir de los fixtures ya renderizados.

El polling no debe reconstruir todo el carrusel ni cambiar el dia activo. Debe actualizar in-place las cards existentes por `data-wc26-match-id`: etiqueta de estado, estado extra, marcador, clases `wc26-match--{status}` / `wc26-match--current`, kickoff y tooltip global.

Los botones del carrusel deben estar en la misma linea que la grilla de partidos, alineados verticalmente al centro. La fecha visible debe mostrarse debajo de los partidos, mas chica y alineada a la derecha.

Cuando el carrusel esta en el primer dia no debe mostrarse tooltip de dia anterior. Cuando esta en el ultimo dia no debe mostrarse tooltip de dia siguiente.

La meta de cada card debe mostrar ronda/partido y estadio en dos lineas separadas, no concatenadas en una sola linea.

Los tooltips deben existir en botones del carrusel, dia visible y card. La card debe tener un solo tooltip global con el detalle del partido; no agregar tooltips por palabras internas como resultado, estado, equipos, ronda o estadio.

Las cards no deben usar atributo `title`, para evitar el tooltip nativo del navegador. Usar solo `data-wc26-tooltip` para el tooltip custom.

El tooltip debe usar fondo main `--c-main: #604c8d` con texto blanco. Los tooltips de navegacion anterior/siguiente deben usar fuente significativamente mas chica que el tooltip de card.

La fecha visible del carrusel debe usar color main y ser un poco mas grande que el texto meta de las cards.

Los tooltips, fuentes y colores del shortcode deben heredar del theme tanto como sea posible; evitar colores hardcodeados en la UI publica. Se permiten fondos sutiles para cards y chips blancos detras de logos/banderas para que imagenes claras se distingan.

La UI publica debe localizar textos comunes al espanol cuando la API los devuelve en ingles: nombres de selecciones frecuentes, rondas como `Group Stage`, `Matchday`, `Round of 16`, `Quarter-finals`, `Semi-finals`, y estadios con patron `Stadium`.

La visibilidad publica se controla con el setting `frontend_visible`. Si esta apagado, `[world_cup_2026_matches]` devuelve string vacio.

## Simulacion

La simulacion vive en el tab `Backend`, dentro de un desplegable llamado `Simulacion`.

Opciones:

- `simulation_enabled`: habilita el uso de un `current day/time` simulado.
- `match_date`: actua como `Current day simulado`; solo se usa cuando `simulation_enabled` esta activo.
- `match_time`: actua como `Current time simulado`; solo se usa cuando `simulation_enabled` esta activo.

Con simulacion apagada, el shortcode siempre usa la fecha actual del sistema en zona horaria de Argentina (`America/Argentina/Buenos_Aires`).

No existe mock hardcodeado en esta build. No reintroducir `simulation_mock_enabled`, `api_key=mock`, datos fake ni ramas de transporte mock. La simulacion solo cambia la fecha/hora usada por el render para elegir dia activo y marcar `current match` sobre datos reales/cacheados.

## Widgets Oficiales

`Shortcode::renderConfig()` imprime el config global con:

- `data-sport="football"`
- `data-league="1"`
- `data-season="2026"`
- `data-target-game`
- `data-target-player="modal"`
- `data-target-team="modal"`

El script oficial debe imprimirse como `type="module"` mediante `filterWidgetScriptTag()`.

Advertencia: API-SPORTS requiere exponer la API key en `data-key` para los widgets oficiales. No loguear esa key y recomendar restricciones de dominio cuando existan.

Nota: los widgets oficiales estan deshabilitados en esta build; se conserva el codigo solo como referencia/compatibilidad.

## Cliente API-Football

`ApiFootballClient::fixturesByDate()` se conserva por compatibilidad y usa transients con `SyncPolicy::refreshInterval()`.

`ApiFootballClient::fixturesForSeason()` es el camino principal del shortcode/cache/endpoint y no debe guardar cache interna. Debe consultar `/fixtures` con `league`, `season` y `timezone`, y devolver el payload normalizado para que `FixturesSyncService` lo persista.

La key se envia en header `x-apisports-key`, nunca en query string.

## Logs

El logger vive en `src/Support/Logger.php` y escribe en `logs/plugin.log`.

Registra:

- Activacion del plugin.
- Errores fatales cuyo archivo pertenece al plugin.
- Excepciones durante el boot.

La pantalla `World Cup 2026` muestra las ultimas lineas. La carpeta `logs/` debe mantener `index.php` y `.htaccess`.

## IDE

`stubs/wordpress-plugin-functions.php` contiene declaraciones minimas para Intelephense cuando el plugin se abre fuera de una instalacion WordPress completa. No debe cargarse desde el plugin.

## Validacion

Validar sintaxis con:

```bash
php -l world-cup-2026-widget.php
php -l autoload.php
php -l src/Bootstrap/Plugin.php
php -l src/Support/Settings.php
php -l src/Support/Logger.php
php -l src/Api/ApiFootballClient.php
php -l src/Api/FixturesEndpoint.php
php -l src/Api/FixturesRepository.php
php -l src/Api/FixturesSyncService.php
php -l src/Api/SyncPolicy.php
php -l src/Admin/SettingsPage.php
php -l src/Frontend/Shortcode.php
```
