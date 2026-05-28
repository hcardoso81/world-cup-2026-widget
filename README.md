# World Cup 2026 Widget

Plugin WordPress para renderizar cards elegantes de partidos del Mundial por fecha usando API-Football.

![WordPress](https://img.shields.io/badge/WordPress-Plugin-21759B)
![PHP](https://img.shields.io/badge/PHP-7.4%2B-777BB4)
![API--Football](https://img.shields.io/badge/API--Football-v3-0F7F6D)
![Shortcode](https://img.shields.io/badge/Shortcode-ready-172026)
![Cache](https://img.shields.io/badge/Cache-Transients-64717B)

El plugin agrega un shortcode propio que consulta el endpoint `/fixtures` de API-Football desde WordPress y muestra un carrusel horizontal de partidos con equipos, logos, marcador, estado, fase, estadio y penales cuando correspondan.

## Requisitos

- WordPress 6.0 o superior.
- PHP 7.4 o superior.
- Una API key de API-Football / API-SPORTS.

## Instalacion

1. Copiar la carpeta `world-cup-2026-widget` dentro de `wp-content/plugins/`.
2. Activar el plugin desde WordPress.
3. Ir a `Ajustes > World Cup 2026`.
4. Pegar la API key.
5. Elegir la fecha de prueba para el render propio.
6. Copiar alguno de los shortcodes generados.

## Configuracion

Valores por defecto:

- League ID: `1`
- Season: `2026` para el Mundial 2026, o `2022` para probar con Qatar 2022 en planes Free.
- Match date: `2022-11-20`

La API key se guarda en la opcion `wc26_widget_settings`.

## Query API-Football

Para traer todos los partidos de un dia:

```text
GET https://v3.football.api-sports.io/fixtures?league=1&season=2026&date=2026-06-11
```

Para probar con el Mundial 2022 en plan Free:

```text
GET https://v3.football.api-sports.io/fixtures?league=1&season=2022&date=2022-11-20
```

Header obligatorio:

```text
x-apisports-key: TU_API_KEY
```

La misma query devuelve los partidos pendientes, en vivo o finalizados segun el momento en que se consulte. El campo clave es:

```text
fixture.status.short
```

Estados frecuentes:

- `NS`: no iniciado.
- `1H`: primer tiempo.
- `HT`: entretiempo.
- `2H`: segundo tiempo.
- `FT`: finalizado.
- `AET`: finalizado luego de prorroga.
- `PEN`: finalizado por penales.

El shortcode propio cachea asi:

- Si hay un partido en vivo: 60 segundos.
- Si no hay partidos en vivo: 15 minutos.

## Shortcodes

Tarjetas propias con los partidos de la fecha configurada en admin:

```text
[world_cup_2026_matches]
```

Tarjetas propias con fecha explicita:

```text
[world_cup_2026_matches date="2026-06-11"]
```

Ejemplo para Qatar 2022:

```text
[world_cup_2026_matches date="2022-11-20"]
```

El shortcode legacy `[world_cup_2026]` tambien renderiza los partidos por fecha.

## Seguridad de la API key

El logger no guarda API keys, tokens ni secretos.

## Modo mock

Para probar el render sin llamar a API-Football, guardar esta API key en el admin:

```text
mock
```

Con ese valor, `[world_cup_2026_matches]` devuelve partidos falsos con estructura compatible con API-Football:

```text
fixture, league, teams, goals, score
```

El render propio normaliza cada fixture a los atributos que usa la tarjeta:

```text
id, date, stage, stadium, homeTeam, homeLogo, homeScore,
awayTeam, awayLogo, awayScore, status, statusLabel, penalties
```

Esto permite revisar resultados, estados en vivo, logos, fase, estadio y penales sin llamar a API-Football.

## Logs

El plugin guarda errores y advertencias en:

```text
world-cup-2026-widget/logs/plugin.log
```

Tambien muestra las ultimas lineas del log en `Ajustes > World Cup 2026`.

## Estructura tecnica

- `src/Api/ApiFootballClient.php`: cliente HTTP para API-Football v3.
- `src/Admin/SettingsPage.php`: admin page, API key, fecha de prueba y listado de shortcodes.
- `src/Frontend/Shortcode.php`: shortcode y render propio de partidos.
- `src/Support/Settings.php`: sanitizacion y defaults.
- `assets/css/public.css`: estilos publicos.

## IDE

Si VS Code muestra funciones WordPress como indefinidas, ejecutar:

```text
Intelephense: Clear Cache
Developer: Reload Window
```

El archivo `stubs/wordpress-plugin-functions.php` existe solo para analisis estatico del IDE. El plugin no lo carga en WordPress.
