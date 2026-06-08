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

El endpoint debe respetar los settings actuales: si `simulation_mock_enabled` esta activo junto con `simulation_enabled`, responde datos mock; si no, responde datos reales desde API-Football usando PHP como proxy server-side. Nunca exponer la API key al navegador.

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
- `penalties`

La grilla de cards usa CSS Grid y una variable CSS `--wc26-matches-per-line`. Debe poder mostrar 4 partidos en una linea en desktop, con UI compacta para que entren los 4 partidos y sus 8 logos/banderas. En mobile debe priorizar legibilidad y puede caer a 1 columna.

No debe haber logica especial de "current partido" que agrande o destaque una card rompiendo la grilla. Los estados en vivo pueden tener color/borde, pero no cambiar el ancho.

La navegacion por dias debe funcionar como carrusel con botones anterior/siguiente. La API no debe llamarse al avanzar o retroceder dias; todo debe salir de los fixtures ya renderizados.

Los botones del carrusel deben estar en la misma linea que la grilla de partidos, alineados verticalmente al centro. La fecha visible debe mostrarse debajo de los partidos, mas chica y alineada a la derecha.

Cuando el carrusel esta en el primer dia no debe mostrarse tooltip de dia anterior. Cuando esta en el ultimo dia no debe mostrarse tooltip de dia siguiente.

La meta de cada card debe mostrar ronda/partido y estadio en dos lineas separadas, no concatenadas en una sola linea.

Los tooltips deben existir en botones del carrusel, dia visible y card. La card debe tener un solo tooltip global con el detalle del partido; no agregar tooltips por palabras internas como resultado, estado, equipos, ronda o estadio.

El tooltip debe usar fondo main `--c-main: #604c8d` con texto blanco. Los tooltips de navegacion anterior/siguiente deben usar fuente significativamente mas chica que el tooltip de card.

La fecha visible del carrusel debe usar color main y ser un poco mas grande que el texto meta de las cards.

Los tooltips, fuentes y colores del shortcode deben heredar del theme tanto como sea posible; evitar colores hardcodeados en la UI publica.

La visibilidad publica se controla con el setting `frontend_visible`. Si esta apagado, `[world_cup_2026_matches]` devuelve string vacio.

## Simulacion y Mockup

La simulacion vive en el tab `Backend`, dentro de un desplegable llamado `Simulacion`.

Opciones:

- `simulation_enabled`: habilita el uso de un `current day` simulado.
- `match_date`: actua como `Current day simulado`; solo se usa cuando `simulation_enabled` esta activo.
- `simulation_mock_enabled`: cuando esta activo junto con `simulation_enabled`, el shortcode usa datos hardcodeados y no llama a API-Football.

Con simulacion apagada, el shortcode siempre usa la fecha actual del sistema en zona horaria de Argentina (`America/Argentina/Buenos_Aires`).

El mock hardcodeado debe:

- Respetar la estructura cruda de API-Football (`fixture`, `league`, `teams`, `goals`, `score`).
- Ordenar los partidos por fecha ascendente.
- Cubrir desde `2026-06-08` hasta `2026-06-13`.
- Simular 4 partidos por dia.
- Usar paises y estadios variados.
- Incluir estados mezclados por dia: finalizados, en vivo y pendientes.
- Los partidos posteriores al `2026-06-10` deben estar no iniciados (`NS`) y sin goles.
- Los partidos no iniciados deben mostrarse con marcador `-`, no `VS` ni `0 - 0`.
- No depender de API key ni de transients previos cuando `simulation_mock_enabled` esta activo.

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

`ApiFootballClient::fixturesByDate()` cachea con transients:

- 60 segundos si algun partido esta en vivo (`1H`, `HT`, `2H`, `ET`, `BT`, `P`, `SUSP`, `INT`, `LIVE`).
- 15 minutos si no hay partidos en vivo.

`ApiFootballClient::fixturesForSeason()` usa la misma politica de cache para la temporada completa.

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
php -l src/Admin/SettingsPage.php
php -l src/Frontend/Shortcode.php
```
