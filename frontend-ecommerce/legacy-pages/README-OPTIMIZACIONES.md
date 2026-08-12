# Z Informática / UIkit E-commerce Template — Versión optimizada (HTML/CSS/JS)

Este proyecto es la conversión a **HTML + CSS + JS puro** (sin Pug, sin Gulp, sin
dependencias de build) de la plantilla original, con optimizaciones de
rendimiento aplicadas a las 21 páginas.

## Qué se hizo

### 1. Conversión de Pug a HTML
El repositorio ya traía una carpeta `dest/` compilada por Gulp con el HTML final.
Se tomó esa salida como base (en vez de recompilar desde los `.pug`) y se le
aplicaron todas las optimizaciones abajo. **No se necesita Node/Gulp/Pug para
usar este proyecto: solo un servidor estático.**

### 2. Imágenes → WebP (–57%)
- 41 imágenes raster (jpg/png) convertidas a WebP (calidad 82).
- El `src` de cada `<img>` apunta directo al `.webp` (WebP tiene soporte
  universal en navegadores modernos, así que no hace falta `<picture>` +
  fallback).
  **Nota técnica:** en una primera versión envolví las imágenes en
  `<picture><source>`, pero eso rompía selectores CSS de "hijo directo"
  de UIkit (p. ej. `.tm-media-box-wrap>*`) y el cálculo de tamaño del
  componente Cover (usado en el carrusel), porque el `<img>` dejaba de
  ser hijo directo de su contenedor. Por eso el carrusel se veía negro y
  algunas tarjetas de producto se aplastaban. Se corrigió quitando el
  wrapper y usando el `.webp` directo en `src`.
- Peso real transferido por el navegador: **2.5 MB → 1.1 MB**.
- Los `.jpg`/`.png` originales ya no se usan en ninguna página (ni como
  `src` ni como enlace de zoom/lightbox, que también se actualizó a
  `.webp`), así que se eliminaron del proyecto para no duplicar peso.

### 3. Carga diferida de imágenes (lazy loading)
- Todas las imágenes fuera del "above the fold" llevan `loading="lazy"
  decoding="async"`.
- La imagen del hero/slideshow y el logo llevan `loading="eager"` (y la del
  hero además `fetchpriority="high"`) para no perjudicar el LCP.

### 4. CSS purgado y minificado (–27%)
- Se depuraron del CSS original solo las reglas verdaderamente no usadas
  (componentes de UIkit que este template nunca invoca: Lightbox-zoom,
  Sortable, algunos tooltips direccionales, etc.), preservando el **orden
  exacto** de las reglas restantes.
- **Dos rondas de corrección importantes** (documentadas por transparencia):
  1. Un primer intento con PurgeCSS no reconocía el carácter `@` de los
     breakpoints de UIkit (`uk-hidden@s`, `uk-child-width-1-4@m`, etc.),
     eliminando por error TODAS las reglas responsivas (menú siempre en
     versión escritorio, grids apilados en una columna).
  2. Un segundo intento corrigió eso, pero PurgeCSS seguía eliminando mal
     los selectores de atributo tipo `[class*="uk-child-width"]`, que son
     la base del sistema de grid de UIkit (dan `box-sizing:border-box` a
     las columnas). Sin ellos, el padding se sumaba de más al ancho de
     cada columna y el conteo de columnas por fila salía mal (p. ej. la
     grilla de categorías mostraba 5 en vez de 6 por fila).
  La solución final fue reemplazar PurgeCSS por un script propio
  (`postcss` + `postcss-selector-parser`) que recorre el CSS original regla
  por regla, en su orden original, y solo elimina una regla si NINGUNA de
  sus clases aparece en el HTML ni en la lista de clases que UIkit agrega
  dinámicamente por JavaScript. Los selectores de atributo `[class*=...]`
  y los selectores sin clase (`body`, `a`, `*`, etc.) se conservan siempre.
  Verificado con un navegador real (Playwright) en `index`, `subcategory`,
  `product`, `cart`, `checkout` y `login`, en desktop y mobile.
- CSS final minificado con `csso --no-restructure` (evita que el
  minificador reordene reglas y rompa la cascada): **124 KB → 91 KB**.

### 5. JS minificado y no bloqueante (–23%)
- `uikit.js`, `uikit-icons.js` y `script.js` minificados con `terser`.
- Los `<script>` de UIkit en el `<head>` ahora llevan `defer` (antes bloqueaban
  el parseo del HTML).
- Total: **251 KB → 192.5 KB**.

### 6. Google Maps cargado bajo demanda
- Antes: el script de Google Maps (con la API key expuesta) se cargaba en
  **todas** las páginas al inicio, aunque el mapa solo se usa en un dropdown
  de "tienda" del header.
- Ahora: se carga con `IntersectionObserver` **solo cuando el mapa entra en
  pantalla** (al abrir el dropdown), ahorrando esa descarga en el 99% de las
  visitas y reduciendo la exposición innecesaria de la key.

### 7. Fuentes (Google Fonts)
- Se agregaron `<link rel="preconnect">` a `fonts.googleapis.com` y
  `fonts.gstatic.com`.
- Se agregó `&display=swap` para evitar texto invisible mientras carga la
  fuente (FOIT).

### 8. Páginas de autenticación (nuevas)
Se agregaron 4 páginas siguiendo el mismo estilo visual (UIkit, tarjetas
`uk-card`, formularios `uk-form-stacked`) y con el mismo header/footer/menú
del resto del sitio:

- **`login.html`** — Iniciar sesión (email + contraseña, "recordarme",
  enlace a "Olvidé mi contraseña" y a "Crear cuenta").
- **`register.html`** — Registro (nombre, email, contraseña, confirmar
  contraseña, checkbox de términos y condiciones).
- **`forgot-password.html`** — Solicitar código de recuperación (solo pide
  el email; el backend debería enviar el token/JWT por correo).
- **`verify-code.html`** — Aquí el usuario **pega el código/token (JWT)**
  que recibió por email para verificar su identidad, y en la misma pantalla
  define su nueva contraseña (campo de código + nueva contraseña + confirmar).

Estas 4 páginas ya están enlazadas en el menú "Pages" del header (versión
escritorio y el offcanvas móvil) en las 21 páginas existentes.

**Importante:** los formularios son solo de front-end (`action="#"`, sin
JS de envío). Falta conectarlos a tu backend/API: el submit de
`forgot-password.html` debería llamar al endpoint que genera y envía el
JWT por correo, y el submit de `verify-code.html` debería enviar el
código + nueva contraseña al endpoint que valida el JWT y actualiza la
contraseña. Si quieres, puedo agregar ese fetch/JS y el manejo de errores
(código inválido, expirado, contraseñas no coinciden, etc.).

### 9. Indentación / estructura del HTML
Los 25 archivos `.html` se reformatearon con indentación legible (antes
estaban casi todo en una sola línea, heredado del build de Gulp).

**Nota técnica:** la versión de `uikit.js` que trae este proyecto (heredada
de la plantilla original) resultó ser inusualmente sensible a los espacios
en blanco que un formateador normal inserta entre etiquetas — en varios
puntos del `<header>` (menú de cuenta, carrito) esos espacios hacían fallar
la inicialización de UIkit. Por eso se usó el modo "whitespace-sensitivity:
strict" del formateador, que es 100% seguro (nunca rompe nada) pero en
consecuencia el cierre `>` de algunas etiquetas en línea (como `<a>`,
`<span>`) queda en su propia línea en vez de pegado al texto — se ve un
poco menos prolijo en esos puntos puntuales, pero garantiza que el sitio
funcione igual que antes. Verificado sin errores de JavaScript en las 25
páginas con un navegador real (Playwright).

## Estructura

```
├── *.html              25 páginas (index, catalog, product, cart, checkout,
│                        login, register, forgot-password, verify-code, etc.)
├── styles/style.css    CSS purgado y minificado
├── scripts/
│   ├── uikit.js         (minificado)
│   ├── uikit-icons.js   (minificado)
│   └── script.js        (minificado + lazy-load de Maps)
└── images/              solo .webp (los jpg/png originales se removieron)
```

## Cómo usarlo
Es 100% estático: solo súbelo a cualquier hosting (Netlify, Vercel, un
`php artisan serve`, Apache/Nginx, etc.) o ábrelo con un servidor local:

```
npx serve .
```

## Nota de seguridad
La API key de Google Maps que traía la plantilla original es una key pública
de demostración. Si vas a usar mapas reales en producción, reemplázala por tu
propia key restringida por dominio en `scripts/script.js` (búscala como
`maps.googleapis.com/maps/api/js?key=`).
