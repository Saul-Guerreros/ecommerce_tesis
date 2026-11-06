<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * M5 - Amplía los catálogos de servicio técnico con los casos más comunes de un taller.
 *
 * Qué hace:
 *  - Agrega tipos de equipo, fallas y trabajos que todavía no existen.
 *  - NO modifica lo ya cargado: usa insertOrIgnore por código, así que los precios y
 *    descripciones que ajustaste desde el panel se conservan aunque lo corras de nuevo.
 *
 * Depende de: TipoDispositivoSeeder, FallaSeeder, TrabajoSeeder y TipoIvaSeeder.
 * Límites de columnas respetados: codigo <= 20, descri <= 100 (tipo <= 50), trb_dias entre 1 y 60.
 *
 * Los precios (Gs.) son una SUGERENCIA de mano de obra; ajústalos desde el panel.
 *
 * Nota sobre el diagnóstico con IA: el catálogo completo viaja dentro del prompt en cada
 * petición, por eso las descripciones son cortas y dicen a qué equipo aplican.
 */
class ServicioTecnicoCasosComunesSeeder extends Seeder
{
    public function run(): void
    {
        $this->tipos();
        $this->fallas();
        $this->trabajos();
    }

    // ------------------------------------------------------------------
    // Tipos de equipo
    // ------------------------------------------------------------------
    private function tipos(): void
    {
        $tipos = [
            ['tdi_codigo' => 'CELULAR', 'tdi_descri' => 'Celular / Smartphone'],
            ['tdi_codigo' => 'CONSOLA', 'tdi_descri' => 'Consola de videojuegos'],
            ['tdi_codigo' => 'REDES',   'tdi_descri' => 'Router / Equipo de red'],
            ['tdi_codigo' => 'UPS',     'tdi_descri' => 'UPS / Estabilizador'],
        ];

        foreach ($tipos as $t) {
            DB::table('tipo_dispositivo')->insertOrIgnore($t + ['tdi_estado' => 1, 'tdi_creado' => now()]);
        }
    }

    // ------------------------------------------------------------------
    // Fallas  (tipo = null => genérica, aplica a cualquier equipo)
    // ------------------------------------------------------------------
    private function fallas(): void
    {
        $fallas = [
            // ---- Genéricas ----
            ['RUIDO_ANORMAL', 'Ruidos extraños (ventilador, golpeteo, zumbido)',      null],
            ['GOLPE_CAIDA',   'Daño por golpe o caída',                                null],
            ['PUERTO_USB',    'Puertos USB o entradas que no funcionan',               null],
            ['SIN_SONIDO',    'Sin sonido o audio distorsionado',                      null],
            ['OLOR_QUEMADO',  'Olor a quemado, humo o chispazo',                       null],
            ['SIN_RED',       'No se conecta a internet o a la red WiFi',              null],
            ['INTERMITENTE',  'Falla intermitente (a veces funciona, a veces no)',     null],

            // ---- Notebook ----
            ['NB_CARGA',      'No carga o no reconoce el cargador',                    'NOTEBOOK'],
            ['NB_TOUCHPAD',   'Touchpad o mouse integrado no responde',                'NOTEBOOK'],
            ['NB_DISCO',      'Disco no detectado o con errores',                      'NOTEBOOK'],
            ['NB_AZUL',       'Pantalla azul o errores de memoria RAM',                'NOTEBOOK'],
            ['NB_WIFI',       'WiFi o Bluetooth no funciona',                          'NOTEBOOK'],
            ['NB_CAMARA',     'Cámara o micrófono no funciona',                        'NOTEBOOK'],
            ['NB_CARCASA',    'Carcasa rota o tornillos sueltos',                      'NOTEBOOK'],

            // ---- PC de escritorio ----
            ['PC_FUENTE',     'Fuente de poder dañada (no arranca o se apaga)',        'PC'],
            ['PC_PITIDOS',    'Emite pitidos al encender y no inicia',                 'PC'],
            ['PC_RAM',        'Pantalla azul o fallas de memoria RAM',                 'PC'],
            ['PC_DISCO',      'Disco duro o SSD no detectado o con errores',           'PC'],
            ['PC_VIDEO',      'Placa de video con fallas (artefactos o sin imagen)',   'PC'],
            ['PC_COOLER',     'Ventilador o cooler ruidoso o detenido',                'PC'],
            ['PC_RED',        'Placa de red o WiFi no funciona',                       'PC'],

            // ---- All in One ----
            ['AIO_PANTALLA',  'Pantalla con líneas, manchas o táctil sin respuesta',   'ALLINONE'],
            ['AIO_FUENTE',    'Fuente interna o adaptador dañado',                     'ALLINONE'],
            ['AIO_DISCO',     'Disco no detectado o con errores',                      'ALLINONE'],
            ['AIO_RAM',       'Pantalla azul o fallas de memoria RAM',                 'ALLINONE'],

            // ---- Impresora ----
            ['IMP_NO_IMPRIME','No imprime (cola detenida o error)',                    'IMPRESORA'],
            ['IMP_CABEZAL',   'Cabezal de impresión obstruido o dañado',               'IMPRESORA'],
            ['IMP_CARTUCHO',  'Cartucho o tóner no reconocido',                        'IMPRESORA'],
            ['IMP_ALMOHAD',   'Error de almohadillas de tinta llenas',                 'IMPRESORA'],
            ['IMP_NO_TOMA',   'No toma el papel (rodillos gastados)',                  'IMPRESORA'],
            ['IMP_CONEXION',  'La PC no la reconoce o no conecta por WiFi/red',        'IMPRESORA'],
            ['IMP_ESCANER',   'Escáner o copiadora no funciona',                       'IMPRESORA'],

            // ---- Monitor ----
            ['MON_LINEAS',    'Líneas, manchas o pantalla rota',                       'MONITOR'],
            ['MON_PARPADEO',  'Parpadea o la imagen es inestable',                     'MONITOR'],
            ['MON_OSCURO',    'Imagen muy oscura o sin retroiluminación',              'MONITOR'],
            ['MON_COLORES',   'Colores distorsionados o con tinte',                    'MONITOR'],
            ['MON_ENTRADA',   'Entrada HDMI, VGA o DisplayPort no funciona',           'MONITOR'],

            // ---- Tablet ----
            ['TAB_PANTALLA',  'Pantalla rota o táctil sin respuesta',                  'TABLET'],
            ['TAB_BATERIA',   'Batería no carga o dura poco',                          'TABLET'],
            ['TAB_PUERTO',    'Puerto de carga flojo o dañado',                        'TABLET'],
            ['TAB_BLOQUEO',   'Bloqueada por contraseña, patrón o cuenta',             'TABLET'],

            // ---- Celular ----
            ['CEL_PANTALLA',  'Pantalla rota o táctil sin respuesta',                  'CELULAR'],
            ['CEL_BATERIA',   'Batería se agota rápido o está hinchada',               'CELULAR'],
            ['CEL_CARGA',     'Puerto de carga dañado o no carga',                     'CELULAR'],
            ['CEL_TRABA',     'Se traba, reinicia solo o se queda en el logo',         'CELULAR'],
            ['CEL_BLOQUEO',   'Bloqueo por contraseña, patrón o cuenta',               'CELULAR'],
            ['CEL_CAMARA',    'Cámara con fallas o imagen borrosa',                    'CELULAR'],
            ['CEL_AUDIO',     'Parlante, micrófono o auricular sin sonido',            'CELULAR'],
            ['CEL_SENAL',     'Sin señal o no reconoce el chip',                       'CELULAR'],

            // ---- Consola ----
            ['CON_JOYSTICK',  'Joystick con deriva o botones que fallan',              'CONSOLA'],
            ['CON_HDMI',      'Puerto HDMI dañado o sin imagen en el TV',              'CONSOLA'],
            ['CON_LECTORA',   'La lectora no lee discos o juegos',                     'CONSOLA'],
            ['CON_SISTEMA',   'Error de sistema o actualización fallida',              'CONSOLA'],

            // ---- Router / Redes ----
            ['RED_SENAL',     'Sin señal WiFi o alcance reducido',                     'REDES'],
            ['RED_INTERNET',  'Sin internet (configuración o equipo)',                 'REDES'],
            ['RED_PUERTO',    'Puertos LAN que no funcionan',                          'REDES'],
            ['RED_CONFIG',    'Necesita configuración o reseteo de fábrica',           'REDES'],

            // ---- UPS / Estabilizador ----
            ['UPS_BATERIA',   'Batería agotada (no da autonomía)',                     'UPS'],
            ['UPS_ALARMA',    'Alarma o pitido continuo',                              'UPS'],
            ['UPS_SALIDA',    'No entrega energía en las salidas',                     'UPS'],
            ['UPS_SOBRECARGA','Se apaga por sobrecarga con poca carga conectada',      'UPS'],
        ];

        $tipos = DB::table('tipo_dispositivo')->pluck('tdi_id', 'tdi_codigo');

        foreach ($fallas as [$codigo, $descri, $tipo]) {
            if ($tipo !== null && !isset($tipos[$tipo])) {
                $this->command?->warn("ServicioTecnicoCasosComunesSeeder: falla {$codigo} omitida, no existe el tipo {$tipo}.");
                continue;
            }

            DB::table('falla')->insertOrIgnore([
                'fll_codigo' => $codigo,
                'fll_descri' => $descri,
                'fll_tdiid'  => $tipo !== null ? $tipos[$tipo] : null,
                'fll_estado' => 1,
                'fll_creado' => now(),
            ]);
        }
    }

    // ------------------------------------------------------------------
    // Trabajos (mano de obra). No tienen tipo de equipo, por eso el equipo
    // va en la descripción cuando el trabajo es específico de uno.
    // [codigo, descripción, precio Gs., días hábiles]
    // ------------------------------------------------------------------
    private function trabajos(): void
    {
        $iva10 = DB::table('tipo_iva')->where('tiv_tasa', 10)->value('tiv_id');

        if (!$iva10) {
            $this->command?->warn('ServicioTecnicoCasosComunesSeeder: no existe tipo_iva 10%. Corré TipoIvaSeeder primero.');
            return;
        }

        $trabajos = [
            // Software y datos
            ['OPTIMIZAR',      'Optimización y mantenimiento de software',                70000,  1],
            ['RESPALDO',       'Respaldo de información',                                 70000,  1],
            ['REC_DATOS',      'Recuperación de datos de disco dañado',                   250000, 5],
            ['CONFIG_RED',     'Configuración de red, WiFi, router o impresora',          60000,  1],
            ['RESET_ACCESO',   'Restablecer acceso al equipo (contraseña o patrón)',      60000,  1],
            ['REINST_MOVIL',   'Reinstalación de software de celular o tablet',           80000,  1],

            // Hardware general (notebook, PC, all in one)
            ['CAMB_RAM',       'Instalación o cambio de memoria RAM',                     50000,  1],
            ['CAMB_FUENTE',    'Cambio de fuente de poder (mano de obra)',                60000,  1],
            ['CAMB_COOLER',    'Cambio de ventilador o cooler (mano de obra)',            70000,  2],
            ['CAMB_VIDEO',     'Cambio de placa de video (mano de obra)',                 60000,  1],
            ['CAMB_WIFI',      'Cambio de tarjeta WiFi o Bluetooth',                      60000,  1],
            ['CAMB_BATERIA',   'Cambio de batería (notebook, tablet o celular)',          60000,  1],
            ['REP_CARGA',      'Reparación o cambio de conector de carga',                90000,  3],
            ['REP_PUERTOS',    'Reparación o cambio de puertos USB o HDMI',               90000,  3],
            ['CAMB_BISAGRA',   'Reparación o cambio de bisagras de notebook',             110000, 3],
            ['LIMP_LIQUIDO',   'Limpieza interna por daño de líquido',                    150000, 4],

            // Pantallas
            ['CAMB_PANT_CEL',  'Cambio de pantalla de celular o tablet (mano de obra)',   90000,  2],
            ['REP_BACKLIGHT',  'Reparación de retroiluminación de monitor o TV',          120000, 4],
            ['REP_FUENTE_MON', 'Reparación de fuente o placa de poder de monitor',        100000, 3],

            // Impresoras
            ['LIMP_CABEZAL',   'Limpieza o destape de cabezal de impresora',              90000,  2],
            ['CAMB_CABEZAL',   'Cambio de cabezal de impresora (mano de obra)',           150000, 3],
            ['RESET_ALMOHAD',  'Reseteo de almohadillas y mantenimiento de impresora',    100000, 1],
            ['MANT_IMPRESORA', 'Mantenimiento de impresora (limpieza y rodillos)',        90000,  2],
            ['CAMB_RODILLO',   'Cambio de rodillos de arrastre de papel',                 80000,  2],
            ['TINTA_CONT',     'Instalación de sistema de tinta continua',                120000, 2],
            ['REP_ESCANER',    'Reparación de escáner de impresora multifunción',         100000, 3],

            // Consolas, redes y energía
            ['REP_JOYSTICK',   'Reparación o cambio de palancas de joystick',             70000,  2],
            ['CAMB_LECTORA',   'Cambio o reparación de lectora de discos',                100000, 3],
            ['REP_ROUTER',     'Reparación de router o equipo de red (fuente, puertos)',  70000,  2],
            ['CAMB_BAT_UPS',   'Cambio de batería de UPS (mano de obra)',                 50000,  1],
            ['REP_UPS',        'Reparación de UPS o estabilizador (placa, transformador)', 120000, 4],
        ];

        foreach ($trabajos as [$codigo, $descri, $precio, $dias]) {
            DB::table('trabajo')->insertOrIgnore([
                'trb_codigo' => $codigo,
                'trb_descri' => $descri,
                'trb_precio' => $precio,
                'trb_dias'   => $dias,
                'trb_tivid'  => $iva10,
                'trb_estado' => 1,
                'trb_creado' => now(),
            ]);
        }
    }
}
