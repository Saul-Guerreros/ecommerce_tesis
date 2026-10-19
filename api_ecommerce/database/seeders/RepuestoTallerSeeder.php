<?php

namespace Database\Seeders;

use App\Models\Compras\CompraCab;
use App\Models\Compras\Proveedor;
use App\Models\Compras\TimbradoProveedor;
use App\Models\Compras\TipoComprobante;
use App\Models\Compras\TipoProveedor;
use App\Models\Configuracion\Ciudad;
use App\Models\Configuracion\Departamento;
use App\Models\Configuracion\Deposito;
use App\Models\Configuracion\Empresa;
use App\Models\Configuracion\Sucursal;
use App\Models\Producto;
use App\Services\Compras\CompraService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Catálogo de REPUESTOS DE TALLER (Servicio Técnico): 146 repuestos con stock 30.
 *
 * Cada repuesto entra por el circuito completo de Compras. NO se toca la tabla
 * stock directamente: se registra una factura de compra por proveedor a través
 * de CompraService::store(), igual que CatalogoSeeder y AumentarStockSeeder:
 *
 *   producto (prd_repues = 1)
 *     -> compra_cab + compra_detalle (factura confirmada, contado)
 *     -> libro_compra
 *     -> stock (30 unidades) + movimiento_stock (kardex, tipo 'E')
 *     -> costo promedio ponderado (sto_cosprm)
 *     -> Gestión de Precios: prd_pvtmin = costo x (1 + margen minorista) x IVA
 *        (PriceService, margen general de configuracion_precio)
 *
 * Los repuestos NO tienen publicación web: no aparecen en la tienda. Solo se
 * habilita la modalidad minorista (prd_habmaj = false); se puede cambiar desde
 * Gestión de Precios.
 *
 * El stock se carga en el depósito principal de la sucursal principal (Capiatá),
 * que es el que usa el taller. Para cargarlo en el depósito principal de TODAS
 * las sucursales, poné TODAS_LAS_SUCURSALES = true.
 *
 * Los precios de compra (Gs., IVA incluido) y los proveedores son DATOS DE
 * EJEMPLO: ajustalos desde el admin con los valores reales del negocio.
 *
 * Requisitos: migración add_prd_repues_to_producto_table y CatalogoSeeder
 * (empresa, sucursales y depósitos). Idempotente: un repuesto que ya tiene stock
 * en el depósito no se vuelve a comprar.
 *
 * Ejecutar:  php artisan db:seed --class=RepuestoTallerSeeder
 */
class RepuestoTallerSeeder extends Seeder
{
    private const CANTIDAD             = 30;    // stock inicial de cada repuesto
    private const STOCK_MINIMO         = 5;
    private const TIMBRADO_VENCE       = '2027-12-31';
    private const OBS_COMPRA           = 'Carga inicial de repuestos de taller - Tesis Z Informatica';
    private const TODAS_LAS_SUCURSALES = false;

    /** DATOS DE EJEMPLO: corregí RUC y datos reales desde el admin. */
    private array $proveedores = [
        0 => ['ruc' => '80101234-5', 'razon' => 'NB Parts Paraguay S.R.L.',      'fantasia' => 'NB Parts'],         // notebook y tablets
        1 => ['ruc' => '80102345-6', 'razon' => 'Hardware Import S.A.',          'fantasia' => 'Hardware Import'],  // PC, memorias, almacenamiento
        2 => ['ruc' => '80103456-7', 'razon' => 'Printer Service Paraguay S.R.L.', 'fantasia' => 'Printer Service'], // impresoras
        3 => ['ruc' => '80104567-8', 'razon' => 'Electro Insumos del Este S.A.', 'fantasia' => 'Electro Insumos'],  // monitores, cables, redes, insumos
    ];

    /**
     * [código, nombre, marca, precio de COMPRA con IVA incluido (Gs.), proveedor].
     * El precio de venta lo calcula PriceService (margen general sobre el costo).
     */
    private array $repuestos = [
        // ── Notebook / Laptop (pantallas, teclados, baterías, cargadores, piezas) ──
        ['REP-NTB-001', 'Pantalla LED 14.0" HD 30 pines eDP para notebook', 'Genérico', 290000, 0],
        ['REP-NTB-002', 'Pantalla LED 14.0" Full HD IPS 30 pines eDP para notebook', 'Genérico', 385000, 0],
        ['REP-NTB-003', 'Pantalla LED 15.6" HD 30 pines eDP para notebook', 'Genérico', 320000, 0],
        ['REP-NTB-004', 'Pantalla LED 15.6" Full HD IPS 30 pines eDP para notebook', 'Genérico', 420000, 0],
        ['REP-NTB-005', 'Pantalla LED 13.3" Full HD IPS 30 pines eDP para notebook', 'Genérico', 380000, 0],
        ['REP-NTB-006', 'Pantalla LED 17.3" HD+ 30 pines eDP para notebook', 'Genérico', 460000, 0],
        ['REP-NTB-007', 'Cable flex de video LCD 30 pines para notebook (universal)', 'Genérico', 48000, 0],
        ['REP-NTB-008', 'Teclado para notebook Dell Inspiron 15 - español (compatible)', 'Genérico', 135000, 0],
        ['REP-NTB-009', 'Teclado para notebook HP 15 - español (compatible)', 'Genérico', 130000, 0],
        ['REP-NTB-010', 'Teclado para notebook Lenovo IdeaPad 3 15 - español (compatible)', 'Genérico', 135000, 0],
        ['REP-NTB-011', 'Teclado para notebook Acer Aspire 5 - español (compatible)', 'Genérico', 130000, 0],
        ['REP-NTB-012', 'Teclado para notebook ASUS Vivobook 15 - español (compatible)', 'Genérico', 135000, 0],
        ['REP-NTB-013', 'Teclado universal para notebook 14" / 15.6" - español, negro', 'Genérico', 110000, 0],
        ['REP-NTB-014', 'Batería para notebook Dell Inspiron 15 - 11.1V 4400 mAh (compatible)', 'Genérico', 210000, 0],
        ['REP-NTB-015', 'Batería para notebook HP 15 - 11.1V 4200 mAh (compatible)', 'Genérico', 205000, 0],
        ['REP-NTB-016', 'Batería para notebook Lenovo IdeaPad 3 - 11.25V 4000 mAh (compatible)', 'Genérico', 215000, 0],
        ['REP-NTB-017', 'Batería para notebook Acer Aspire - 11.1V 4400 mAh (compatible)', 'Genérico', 200000, 0],
        ['REP-NTB-018', 'Batería para notebook ASUS Vivobook - 11.4V 4200 mAh (compatible)', 'Genérico', 210000, 0],
        ['REP-NTB-019', 'Cargador para notebook Dell 65W 19.5V - punta 4.5 x 3.0 mm', 'Genérico', 140000, 0],
        ['REP-NTB-020', 'Cargador para notebook HP 65W 19.5V - punta azul 4.5 x 3.0 mm', 'Genérico', 135000, 0],
        ['REP-NTB-021', 'Cargador para notebook Lenovo 65W 20V - punta rectangular', 'Genérico', 140000, 0],
        ['REP-NTB-022', 'Cargador para notebook Lenovo 65W USB-C Power Delivery', 'Genérico', 150000, 0],
        ['REP-NTB-023', 'Cargador para notebook ASUS 65W 19V - punta 4.0 x 1.35 mm', 'Genérico', 130000, 0],
        ['REP-NTB-024', 'Cargador para notebook Acer 65W 19V - punta 5.5 x 1.7 mm', 'Genérico', 130000, 0],
        ['REP-NTB-025', 'Cargador universal para notebook 90W con 8 puntas intercambiables', 'Genérico', 170000, 0],
        ['REP-NTB-026', 'Cargador universal USB-C Power Delivery 65W para notebook', 'Genérico', 160000, 0],
        ['REP-NTB-027', 'Conector de carga (DC Jack) con cable para notebook - universal', 'Genérico', 38000, 0],
        ['REP-NTB-028', 'Juego de bisagras para notebook 15.6" (izquierda y derecha)', 'Genérico', 85000, 0],
        ['REP-NTB-029', 'Cooler (ventilador) de CPU para notebook 5V 0.5A 4 pines', 'Genérico', 95000, 0],
        ['REP-NTB-030', 'Parlantes internos para notebook (par) - universal', 'Genérico', 45000, 0],
        ['REP-NTB-031', 'Cámara web interna para notebook - módulo USB universal', 'Genérico', 55000, 0],
        ['REP-NTB-032', 'Tarjeta WiFi 6 + Bluetooth 5.2 M.2 2230 para notebook', 'Intel', 190000, 0],
        ['REP-NTB-033', 'Batería CMOS / BIOS con cable para notebook (CR2032 con conector)', 'Genérico', 22000, 0],
        ['REP-NTB-034', 'Kit de tornillos surtidos para notebook (100 piezas)', 'Genérico', 40000, 0],
        ['REP-NTB-035', 'Juego de pies de goma para notebook (4 unidades)', 'Genérico', 18000, 0],
        ['REP-NTB-036', 'Caddy para segundo disco en bahía óptica de notebook - SATA 9.5 mm', 'Genérico', 62000, 0],
        // ── Memorias RAM ──
        ['REP-MEM-001', 'Memoria RAM SODIMM DDR3L 4 GB 1600 MHz para notebook', 'Kingston', 200000, 1],
        ['REP-MEM-002', 'Memoria RAM SODIMM DDR3L 8 GB 1600 MHz para notebook', 'Kingston', 340000, 1],
        ['REP-MEM-003', 'Memoria RAM SODIMM DDR4 8 GB 3200 MHz para notebook', 'Kingston', 420000, 1],
        ['REP-MEM-004', 'Memoria RAM SODIMM DDR4 16 GB 3200 MHz para notebook', 'Kingston', 760000, 1],
        ['REP-MEM-005', 'Memoria RAM SODIMM DDR5 16 GB 4800 MHz para notebook', 'Crucial', 850000, 1],
        ['REP-MEM-006', 'Memoria RAM DIMM DDR3 4 GB 1600 MHz para PC de escritorio', 'Kingston', 190000, 1],
        ['REP-MEM-007', 'Memoria RAM DIMM DDR3 8 GB 1600 MHz para PC de escritorio', 'Kingston', 320000, 1],
        ['REP-MEM-008', 'Memoria RAM DIMM DDR4 8 GB 3200 MHz para PC de escritorio', 'Kingston', 400000, 1],
        ['REP-MEM-009', 'Memoria RAM DIMM DDR4 16 GB 3200 MHz para PC de escritorio', 'Kingston', 740000, 1],
        ['REP-MEM-010', 'Memoria RAM DIMM DDR5 16 GB 5600 MHz para PC de escritorio', 'Crucial', 820000, 1],
        // ── Almacenamiento (SSD, discos y accesorios) ──
        ['REP-ALM-001', 'Disco sólido SSD SATA 2.5" 240 GB', 'Kingston', 240000, 1],
        ['REP-ALM-002', 'Disco sólido SSD SATA 2.5" 480 GB', 'Kingston', 380000, 1],
        ['REP-ALM-003', 'Disco sólido SSD SATA 2.5" 960 GB', 'Crucial', 690000, 1],
        ['REP-ALM-004', 'Disco sólido SSD M.2 NVMe 256 GB', 'Kingston', 260000, 1],
        ['REP-ALM-005', 'Disco sólido SSD M.2 NVMe 512 GB', 'Kingston', 420000, 1],
        ['REP-ALM-006', 'Disco sólido SSD M.2 NVMe 1 TB', 'Crucial', 720000, 1],
        ['REP-ALM-007', 'Disco duro 2.5" 1 TB 5400 rpm SATA para notebook', 'Western Digital', 380000, 1],
        ['REP-ALM-008', 'Disco duro 3.5" 1 TB 7200 rpm SATA para PC', 'Seagate', 420000, 1],
        ['REP-ALM-009', 'Disco duro 3.5" 2 TB 7200 rpm SATA para PC', 'Seagate', 620000, 1],
        ['REP-ALM-010', 'Adaptador M.2 NVMe a PCIe x4 para PC', 'Genérico', 95000, 1],
        ['REP-ALM-011', 'Marco adaptador de SSD 2.5" a bahía 3.5"', 'Genérico', 28000, 1],
        ['REP-ALM-012', 'Cable adaptador SATA a USB 3.0 para discos 2.5" / 3.5" con fuente', 'Genérico', 85000, 1],
        ['REP-ALM-013', 'Carcasa externa USB 3.0 para disco / SSD SATA 2.5"', 'Genérico', 70000, 1],
        ['REP-ALM-014', 'Carcasa externa USB-C para SSD M.2 NVMe', 'Genérico', 140000, 1],
        // ── PC de escritorio / All in One (fuentes, coolers, placas) ──
        ['REP-PCD-001', 'Fuente de poder ATX 500W', 'Genérico', 190000, 1],
        ['REP-PCD-002', 'Fuente de poder ATX 650W 80 Plus Bronze', 'Corsair', 480000, 1],
        ['REP-PCD-003', 'Fuente de poder ATX 750W 80 Plus Bronze', 'Corsair', 620000, 1],
        ['REP-PCD-004', 'Cooler de CPU universal Intel / AMD - disipador de torre', 'Genérico', 120000, 1],
        ['REP-PCD-005', 'Cooler de CPU Intel LGA 1700 con base (compatible)', 'Genérico', 85000, 1],
        ['REP-PCD-006', 'Cooler de CPU AMD AM4 / AM5 con base (compatible)', 'Genérico', 85000, 1],
        ['REP-PCD-007', 'Ventilador de gabinete 120 mm 12V', 'Genérico', 35000, 1],
        ['REP-PCD-008', 'Placa madre Intel H610M LGA 1700 DDR4 micro-ATX', 'ASUS', 780000, 1],
        ['REP-PCD-009', 'Placa madre Intel B760M LGA 1700 DDR4 micro-ATX', 'Gigabyte', 1150000, 1],
        ['REP-PCD-010', 'Placa madre AMD A520M AM4 DDR4 micro-ATX', 'MSI', 650000, 1],
        ['REP-PCD-011', 'Placa madre AMD B550M AM4 DDR4 micro-ATX', 'Gigabyte', 900000, 1],
        ['REP-PCD-012', 'Placa de red PCIe Gigabit Ethernet', 'Genérico', 65000, 1],
        ['REP-PCD-013', 'Tarjeta WiFi PCIe con Bluetooth para PC', 'Genérico', 160000, 1],
        ['REP-PCD-014', 'Lectora / grabadora de DVD SATA interna', 'Genérico', 140000, 1],
        // ── Periféricos de reposición ──
        ['REP-PER-001', 'Teclado USB Logitech K120 - español', 'Logitech', 85000, 1],
        ['REP-PER-002', 'Mouse USB óptico Logitech B100', 'Logitech', 45000, 1],
        ['REP-PER-003', 'Mouse USB óptico 1000 DPI', 'Genérico', 22000, 1],
        ['REP-PER-004', 'Combo teclado y mouse inalámbrico Logitech MK270', 'Logitech', 190000, 1],
        ['REP-PER-005', 'Hub USB 3.0 de 4 puertos', 'Genérico', 60000, 1],
        ['REP-PER-006', 'Cámara web USB Full HD 1080p', 'Genérico', 160000, 1],
        // ── Monitores ──
        ['REP-MON-001', 'Adaptador de corriente para monitor 12V 3A (punta 5.5 x 2.1 mm)', 'Genérico', 55000, 3],
        ['REP-MON-002', 'Adaptador de corriente para monitor 19V 2.1A (punta 6.5 x 4.4 mm)', 'Genérico', 75000, 3],
        ['REP-MON-003', 'Placa fuente / inversor universal para monitor LCD 19" - 24"', 'Genérico', 120000, 3],
        ['REP-MON-004', 'Kit de tiras LED backlight para monitor 19" / 20"', 'Genérico', 70000, 3],
        ['REP-MON-005', 'Kit de tiras LED backlight para monitor 24"', 'Genérico', 85000, 3],
        ['REP-MON-006', 'Kit de tiras LED backlight para monitor 27"', 'Genérico', 110000, 3],
        ['REP-MON-007', 'Placa controladora universal LCD con entradas VGA / HDMI / DVI', 'Genérico', 150000, 3],
        ['REP-MON-008', 'Base / pedestal universal para monitor', 'Genérico', 60000, 3],
        ['REP-MON-009', 'Soporte de pared VESA para monitor de 17" a 32"', 'Genérico', 80000, 3],
        ['REP-MON-010', 'Kit de capacitores electrolíticos surtidos (6.3V a 25V) para fuentes y placas', 'Genérico', 45000, 3],
        // ── Impresoras ──
        ['REP-IMP-001', 'Botella de tinta Epson T544 negro 65 ml', 'Epson', 78000, 2],
        ['REP-IMP-002', 'Botella de tinta Epson T544 cian 65 ml', 'Epson', 65000, 2],
        ['REP-IMP-003', 'Botella de tinta Epson T544 magenta 65 ml', 'Epson', 65000, 2],
        ['REP-IMP-004', 'Botella de tinta Epson T544 amarillo 65 ml', 'Epson', 65000, 2],
        ['REP-IMP-005', 'Cartucho de tinta HP 664 negro', 'HP', 105000, 2],
        ['REP-IMP-006', 'Cartucho de tinta HP 664 tricolor', 'HP', 125000, 2],
        ['REP-IMP-007', 'Cartucho de tóner negro HP 85A (CE285A) - compatible', 'Genérico', 130000, 2],
        ['REP-IMP-008', 'Cartucho de tóner negro Brother TN-1060 - compatible', 'Genérico', 115000, 2],
        ['REP-IMP-009', 'Unidad de imagen (tambor) Brother DR-1060 - compatible', 'Genérico', 180000, 2],
        ['REP-IMP-010', 'Cabezal de impresión Epson L3110 / L3150 / L3250 - compatible', 'Genérico', 420000, 2],
        ['REP-IMP-011', 'Kit de rodillos de alimentación de papel para Epson serie L', 'Genérico', 95000, 2],
        ['REP-IMP-012', 'Rodillo de arrastre (pickup) para impresora láser HP M402 / M426', 'Genérico', 75000, 2],
        ['REP-IMP-013', 'Rodillo separador de papel (separation pad) para impresora láser', 'Genérico', 55000, 2],
        ['REP-IMP-014', 'Unidad fusora para impresora láser HP LaserJet Pro M402 / M426 - compatible', 'Genérico', 780000, 2],
        ['REP-IMP-015', 'Almohadilla de residuos de tinta (waste ink pad) Epson L3110 / L3150', 'Genérico', 35000, 2],
        ['REP-IMP-016', 'Kit de tubos de tinta para sistema continuo (4 colores)', 'Genérico', 90000, 2],
        // ── Tablets ──
        ['REP-TAB-001', 'Batería para tablet universal Li-Po 3.7V 4000 mAh', 'Genérico', 85000, 0],
        ['REP-TAB-002', 'Batería para tablet universal Li-Po 3.7V 7000 mAh', 'Genérico', 110000, 0],
        ['REP-TAB-003', 'Pantalla táctil (digitalizador) universal para tablet 7"', 'Genérico', 90000, 0],
        ['REP-TAB-004', 'Pantalla táctil (digitalizador) universal para tablet 10.1"', 'Genérico', 140000, 0],
        ['REP-TAB-005', 'Puerto de carga USB-C hembra para tablet / celular (unidad)', 'Genérico', 18000, 0],
        ['REP-TAB-006', 'Puerto de carga micro USB hembra para tablet (unidad)', 'Genérico', 15000, 0],
        // ── Cables, adaptadores y energía ──
        ['REP-CAB-001', 'Cable de poder para PC 220V 1.8 m (3 pines)', 'Genérico', 18000, 3],
        ['REP-CAB-002', 'Cable de poder tipo 8 (trébol) 1.5 m', 'Genérico', 15000, 3],
        ['REP-CAB-003', 'Cable de video VGA macho-macho 1.5 m', 'Genérico', 18000, 3],
        ['REP-CAB-004', 'Cable HDMI 2.0 de 1.8 m', 'Genérico', 28000, 3],
        ['REP-CAB-005', 'Cable HDMI 2.0 de 3 m', 'Genérico', 45000, 3],
        ['REP-CAB-006', 'Cable DisplayPort 1.4 de 1.8 m', 'Genérico', 45000, 3],
        ['REP-CAB-007', 'Adaptador HDMI a VGA', 'Genérico', 55000, 3],
        ['REP-CAB-008', 'Adaptador DisplayPort a HDMI', 'Genérico', 55000, 3],
        ['REP-CAB-009', 'Cable USB A-B para impresora 1.8 m', 'Genérico', 25000, 3],
        ['REP-CAB-010', 'Cable USB-C a USB-A de 1 m (carga y datos)', 'Genérico', 20000, 3],
        ['REP-CAB-011', 'Cable micro USB de 1 m (carga y datos)', 'Genérico', 15000, 3],
        ['REP-CAB-012', 'Cable SATA III de datos 50 cm', 'Genérico', 12000, 3],
        ['REP-CAB-013', 'Cable adaptador de alimentación Molex a SATA', 'Genérico', 12000, 3],
        ['REP-CAB-014', 'Adaptador de corriente para All in One 19V 4.74A (90W)', 'Genérico', 190000, 3],
        ['REP-CAB-015', 'Fuente de alimentación universal 12V 2A', 'Genérico', 45000, 3],
        ['REP-CAB-016', 'Batería para UPS 12V 7Ah', 'Genérico', 120000, 3],
        // ── Redes ──
        ['REP-RED-001', 'Cable de red UTP Cat6 patch cord 2 m', 'Genérico', 15000, 3],
        ['REP-RED-002', 'Conectores RJ45 Cat6 (bolsa de 100 unidades)', 'Genérico', 70000, 3],
        ['REP-RED-003', 'Adaptador USB a Ethernet Gigabit', 'Genérico', 85000, 3],
        ['REP-RED-004', 'Adaptador WiFi USB doble banda AC1200', 'Genérico', 95000, 3],
        ['REP-RED-005', 'Fuente de alimentación 12V 1A para router / módem', 'Genérico', 30000, 3],
        // ── Insumos de taller ──
        ['REP-INS-001', 'Pasta térmica Arctic MX-4 de 4 g', 'Arctic', 75000, 3],
        ['REP-INS-002', 'Pasta térmica en jeringa de 3 g', 'Genérico', 25000, 3],
        ['REP-INS-003', 'Thermal pad 0.5 mm lámina 100 x 100 mm', 'Genérico', 55000, 3],
        ['REP-INS-004', 'Thermal pad 1.0 mm lámina 100 x 100 mm', 'Genérico', 60000, 3],
        ['REP-INS-005', 'Alcohol isopropílico 99% de 500 ml', 'Genérico', 38000, 3],
        ['REP-INS-006', 'Limpiacontactos electrónico en spray 250 ml', 'Genérico', 42000, 3],
        ['REP-INS-007', 'Aire comprimido en lata 400 ml', 'Genérico', 40000, 3],
        ['REP-INS-008', 'Estaño para soldar 0.8 mm de 100 g', 'Genérico', 45000, 3],
        ['REP-INS-009', 'Pasta de soldar (flux) en jeringa', 'Genérico', 30000, 3],
        ['REP-INS-010', 'Malla desoldante 2 mm', 'Genérico', 15000, 3],
        ['REP-INS-011', 'Cinta Kapton 10 mm x 33 m', 'Genérico', 20000, 3],
        ['REP-INS-012', 'Bridas plásticas 20 cm (bolsa de 100 unidades)', 'Genérico', 14000, 3],
        ['REP-INS-013', 'Pila CMOS CR2032 3V (unidad)', 'Genérico', 6000, 3],
    ];

    public function run(CompraService $compras): void
    {
        $facturas = 0;

        DB::transaction(function () use ($compras, &$facturas) {
            $ivaId     = $this->idOrFail('tipo_iva', 'tiv_id', 'tiv_tasa', 10, 'IVA 10%');
            $unidadId  = DB::table('unidad_medida')->whereIn('umd_codigo', ['UNI', 'UN'])->value('umd_id')
                ?? throw new RuntimeException('Falta la unidad de medida "Unidad".');
            $facturaId = TipoComprobante::idFactura()
                ?? throw new RuntimeException('Falta el tipo de comprobante Factura (código 001).');

            $destinos = $this->depositosDestino();
            $provs    = $this->cargarProveedores($facturaId);

            // 1) Productos: repuestos de taller (no se publican en la tienda)
            $productos = [];
            foreach ($this->repuestos as [$cod, $nombre, $marca, $precio, $prov]) {
                $producto = Producto::firstOrNew(['prd_codinv' => $cod]);
                if (! $producto->exists) {
                    $producto->prd_creado = now();
                }
                $producto->fill([
                    'prd_marid'  => $this->marca($marca),
                    'prd_tivid'  => $ivaId,
                    'prd_umdid'  => $unidadId,
                    'prd_descri' => Str::limit($nombre, 147),
                    'prd_stkmin' => self::STOCK_MINIMO,
                    'prd_afestk' => 1,
                    'prd_repues' => 1,
                    'prd_estado' => 1,
                    'prd_habmin' => true,
                    'prd_habmaj' => false,
                ])->save();

                $productos[$cod] = $producto;
            }

            // 2) Compras: una factura por proveedor y depósito. Genera el stock,
            //    el kardex, el libro de compras y el precio de venta.
            foreach ($destinos as [$sucId, $depId, $sucNombre]) {
                $lineas = [];

                foreach ($this->repuestos as [$cod, $nombre, $marca, $precio, $prov]) {
                    $producto = $productos[$cod];

                    $tieneStock = DB::table('stock')
                        ->where(['sto_prdid' => $producto->prd_id, 'sto_depid' => $depId])
                        ->exists();
                    if ($tieneStock) {
                        continue;
                    }

                    $lineas[$prov][] = [
                        'prd_id' => $producto->prd_id,
                        'umd_id' => $unidadId,
                        'tiv_id' => $ivaId,
                        'descri' => Str::limit($nombre, 147),
                        'precun' => $precio,
                        'canfac' => self::CANTIDAD,
                        'canrec' => self::CANTIDAD,
                    ];
                }

                foreach ($lineas as $provIdx => $detalle) {
                    $p = $provs[$provIdx];

                    $compras->store([
                        'com_proid'  => $p['id'],
                        'com_proruc' => $p['ruc'],
                        'com_proraz' => $p['razon'],
                        'com_depid'  => $depId,
                        'com_sucid'  => $sucId,
                        'com_tcoid'  => $facturaId,
                        'com_tpvid'  => $p['tpv_id'],
                        'com_timbra' => $p['timbra'],
                        'com_timbvn' => self::TIMBRADO_VENCE,
                        'com_nrodoc' => $this->siguienteNroDoc($p['id']),
                        'com_fchaem' => now()->toDateString(),
                        'com_condic' => CompraCab::CONDICION_CONTADO,
                        'com_obs'    => self::OBS_COMPRA,
                    ], $detalle);

                    $facturas++;
                    $this->command?->info("[{$sucNombre}] Factura {$p['razon']}: " . count($detalle) . ' repuestos x ' . self::CANTIDAD . ' unidades');
                }
            }
        });

        $this->command?->info(count($this->repuestos) . " repuestos de taller listos ({$facturas} factura(s) nueva(s)).");
    }

    /** @return array<int, array{0:int,1:int,2:string}> [sucursal, depósito principal, nombre de sucursal] */
    private function depositosDestino(): array
    {
        $empresa = Empresa::first()
            ?? throw new RuntimeException('No hay empresa cargada. Ejecutá CatalogoSeeder antes (php artisan db:seed --class=CatalogoSeeder).');

        $query = Sucursal::where('suc_empid', $empresa->emp_id)->where('suc_estado', 1);
        if (! self::TODAS_LAS_SUCURSALES) {
            $query->where('suc_princi', 1);
        }

        $destinos = [];
        foreach ($query->orderByDesc('suc_princi')->orderBy('suc_id')->get() as $suc) {
            $depId = Deposito::where('dpo_sucid', $suc->suc_id)
                ->where('dpo_princi', 1)->where('dpo_estado', 1)->value('dpo_id');

            if ($depId) {
                $destinos[] = [$suc->suc_id, $depId, $suc->suc_nombre];
            }
        }

        if (! $destinos) {
            throw new RuntimeException('No hay una sucursal con depósito principal. Ejecutá CatalogoSeeder antes.');
        }

        return $destinos;
    }

    /** @return array<int, array{id:int, ruc:string, razon:string, tpv_id:int, timbra:string}> */
    private function cargarProveedores(int $facturaId): array
    {
        $tipo   = TipoProveedor::firstOrCreate(['tpr_descri' => 'Importador de Repuestos'], ['tpr_estado' => 1]);
        $ciudad = $this->ciudad('Capital', 'Asunción');
        $out    = [];

        foreach ($this->proveedores as $n => $p) {
            $prov = Proveedor::firstOrCreate(
                ['pro_ruc' => $p['ruc']],
                [
                    'pro_tprid'  => $tipo->tpr_id,
                    'pro_ciuid'  => $ciudad,
                    'pro_razon'  => $p['razon'],
                    'pro_nomfan' => $p['fantasia'],
                    'pro_estado' => 1,
                ]
            );

            $timbrado = TimbradoProveedor::where('tpv_proid', $prov->pro_id)
                ->where('tpv_tcoid', $facturaId)->where('tpv_estado', 1)->first()
                ?? TimbradoProveedor::create([
                    'tpv_proid'  => $prov->pro_id,
                    'tpv_tcoid'  => $facturaId,
                    'tpv_numero' => (string) (16000001 + $n),   // 8 dígitos, fijo y reproducible
                    'tpv_fechfn' => self::TIMBRADO_VENCE,
                    'tpv_estado' => 1,
                ]);

            $out[$n] = [
                'id'     => $prov->pro_id,
                'ruc'    => $p['ruc'],
                'razon'  => $prov->pro_razon,
                'tpv_id' => $timbrado->tpv_id,
                'timbra' => $timbrado->tpv_numero,
            ];
        }

        return $out;
    }

    private function ciudad(string $departamento, string $ciudad): int
    {
        $dep = Departamento::firstOrCreate(['dep_descri' => $departamento], ['dep_estado' => 1]);

        return Ciudad::firstOrCreate(
            ['ciu_depid' => $dep->dep_id, 'ciu_descri' => $ciudad],
            ['ciu_estado' => 1]
        )->ciu_id;
    }

    private function marca(string $nombre): int
    {
        DB::table('marca')->updateOrInsert(['mar_descri' => $nombre], ['mar_estado' => 1]);

        return DB::table('marca')->where('mar_descri', $nombre)->value('mar_id');
    }

    private function siguienteNroDoc(int $proveedorId): string
    {
        $ultimo = DB::table('compra_cab')->where('com_proid', $proveedorId)->max('com_nrodoc');
        $n = $ultimo ? ((int) substr($ultimo, -7)) + 1 : 1;

        return sprintf('001-001-%07d', $n);
    }

    private function idOrFail(string $tabla, string $pk, string $col, $valor, string $nombre): int
    {
        return DB::table($tabla)->where($col, $valor)->value($pk)
            ?? throw new RuntimeException("Falta \"$nombre\" en $tabla. Revisá el orden en DatabaseSeeder.");
    }
}
