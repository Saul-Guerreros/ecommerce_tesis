<?php

namespace Database\Seeders;

use App\Models\Categoria;
use App\Models\Producto;
use App\Models\PublicacionWeb;
use App\Models\VitrinaProducto;
use App\Services\Precio\PriceService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Carga el catálogo inicial de la tienda (sin usuarios):
 *   departamento/ciudad -> empresa -> sucursal -> depósito
 *   marcas -> productos -> stock inicial (+ movimiento) -> precios
 *   publicación web -> secciones de la vitrina
 *
 * Requiere haber corrido antes DatabaseSeeder (categorías, IVA,
 * unidades de medida y configuración de precios).
 *
 * Es idempotente: se puede ejecutar varias veces sin duplicar datos
 * (busca por código de producto, nombre de marca, etc.).
 *
 * Uso: php artisan db:seed --class=CatalogoTiendaSeeder --force
 *      php artisan busqueda:reindexar
 */
class CatalogoTiendaSeeder extends Seeder
{
    /** Unidades con las que arranca cada producto (entrada de stock inicial). */
    private const STOCK_INICIAL = 10;

    /** Cuántos productos se muestran en cada sección del Home. */
    private const POR_SECCION = 8;

    /** Categorías que usa este catálogo y que CategoriaSeeder no crea: hija => padre. */
    private const CATEGORIAS_EXTRA = [
        'Tarjetas de video' => 'Computadoras de Escritorio',
        'Webcams'           => 'Periféricos & Monitores',
    ];

    /**
     * 'precio' = COSTO del producto. El precio de venta lo calcula
     * PriceService: costo + margen (configuración de precios) + IVA.
     */
    private array $productos = [
        // >>> PEGÁ ACÁ el contenido de tu array $productos (las filas ['cod' => ..., ...],) <<<
		
            ['cod' => 'PER-HAV-001', 'nombre' => 'Mouse Gamer Havit MS967WB RGB Inalámbrico - Negro', 'marca' => 'Havit', 'precio' => 127692, 'cat' => 'Mouses', 'desc' => 'El Mouse Gamer Havit MS967WB RGB Inalámbrico - Negro es la elección perfecta para los gamers que buscan precisión, estilo y comodidad. Con un diseño ergonómico y 10 botones programables, este mouse está diseñado para mejorar tu experiencia de juego. La retroiluminación RGB personalizable añade un toque visual impresionante, permitiéndote adaptar el aspecto del mouse a tu setup de gaming. Equipado con el sensor óptico PAW3311, el Havit MS967WB ofrece una resolución ajustable de 800 a 22,000 DPI, lo que garantiza movimientos precisos y rápidos en cualquier situación de juego. Su conectividad trimodal, que incluye USB-C, 2.4 GHz y Bluetooth 5.2, te proporciona la flexibilidad de usar el mouse en diferentes dispositivos y en diferentes modos de conectividad, asegurando una experiencia sin interrupciones. Ideal para sesiones de juego prolongadas, este mouse gamer inalámbrico combina funcionalidad y diseño, proporcionando la máxima comodidad con un estilo único. El Havit MS967WB no solo es un dispositivo de juego, sino una herramienta que te ofrece una ventaja competitiva, mejorando tu rendimiento y satisfacción en cada partida. ¡Eleva tu juego al siguiente nivel con este increíble mouse gamer de Havit!'],
            ['cod' => 'PER-LOG-002', 'nombre' => 'Mouse Inalámbrico Logitech M650 hasta 4000 DPI con Bluetooth - Gris', 'marca' => 'Logitech', 'precio' => 140676, 'cat' => 'Mouses', 'desc' => 'El mouse inalámbrico Logitech Signature M650 combina precisión, comodidad y productividad. Con resolución ajustable de 400 a 4.000 DPI, conectividad Bluetooth y receptor USB, es ideal para cualquier entorno. Compatible con Windows, macOS, Android, Linux, ChromeOS e iPadOS. Sus 5 botones permiten un control eficiente, y funciona con una sola pila AA. Un ratón ergonómico y versátil, perfecto para usuarios exigentes.'],
            ['cod' => 'PER-RED-003', 'nombre' => 'Mouse Gamer Redragon Woki M994WBR Superlight - Blanco/Azul', 'marca' => 'Redragon', 'precio' => 240000, 'cat' => 'Mouses', 'desc' => 'El mouse Gamer Redragon Woki M994WBR Superlight inalámbrico es la herramienta definitiva para los gamers que buscan precisión y velocidad sin precedentes. Equipado con tecnología de conexión de 3 modos, incluyendo Bluetooth y un receptor nano de 2.4 Ghz, ofrece una experiencia de juego sin cables que maximiza la libertad de movimiento y mejora el rendimiento en juegos FPS y MMO. Con un tiempo de respuesta de solo 1 ms y un sensor óptico Pixart PAW3395, garantiza una respuesta inmediata y precisa. El M994WBR cuenta con 5 niveles de DPI ajustables, que van desde 1000 hasta 26000, personalizables a través del software Redragon Pro, lo que permite cambios rápidos y precisos en el juego. Sus 6 botones programables, incluyendo dos botones macro laterales, son ideales para crear atajos personalizados en cualquier juego o aplicación. Además, su diseño ergonómico y ligero asegura largas horas de uso sin fatiga. El modo de suspensión automática y la conectividad USB Tipo-C completan este potente mouse gamer, haciendo del Redragon Woki M994WBR la elección perfecta para cualquier entusiasta del gaming.'],
            ['cod' => 'MON-SAM-001', 'nombre' => 'Monitor Gamer Samsung Odyssey G4 LS27HG400ENXGO IPS 27" Full HD 240 Hz / 1 ms - Black', 'marca' => 'Samsung', 'precio' => 1396154, 'cat' => 'Monitores', 'desc' => 'Monitor Gamer Samsung Odyssey G4 LS27HG400ENXGO IPS de 27", diseñado para los gamers más exigentes, este monitor ofrece una experiencia visual impresionante con su pantalla IPS Full HD de 1920 x 1080 píxeles, que garantiza colores nítidos y un amplio ángulo de visión de 178°. La frecuencia de actualización de 240 Hz y el tiempo de respuesta de 1 ms (GTG) te permiten reaccionar en tiempo real, eliminando el desenfoque de movimiento y proporcionando una acción ultrafluida. Compatible con Nvidia G-Sync y AMD FreeSync Premium, el Odyssey G4 asegura que cada escena se vea clara y sin interrupciones, eliminando el tearing y el stuttering de la pantalla. Su soporte ergonómico ajustable te permite personalizar la altura, inclinación y rotación para una experiencia de juego cómoda y adaptada a tus necesidades. La función Auto Source Switch+ detecta automáticamente los dispositivos conectados, facilitando el cambio entre señales de entrada sin esfuerzo. Además, la Vista de juego ultraancha de 21:9 revela áreas ocultas en los juegos, dándote ventaja sobre tus oponentes. Este monitor es ideal para cualquier tipo de juego, desde FPS hasta simuladores de carreras, proporcionando una ventaja competitiva significativa y una experiencia de juego verdaderamente envolvente.'],
            ['cod' => 'MON-XIA-002', 'nombre' => 'Monitor Gamer Xiaomi Gaming G24i 2026 IPS 24" Full HD 200 Hz / 1 ms - Negro', 'marca' => 'Xiaomi', 'precio' => 956154, 'cat' => 'Monitores', 'desc' => 'El Monitor Gamer Xiaomi Gaming G24i 2026 IPS 23.8" Full HD 200 Hz / 1 ms - Negro es la opción ideal para los jugadores que buscan una experiencia visual inmersiva y fluida. Con una resolución de 1920 x 1080 en una pantalla de 23.8 pulgadas, este monitor ofrece imágenes nítidas y colores vivos gracias a su profundidad de color de 16.7 millones y un brillo máximo de 400 nits. Su relación de aspecto 16:9 es perfecta para disfrutar de juegos y contenido multimedia en alta definición. La característica más destacada de este monitor es su frecuencia de actualización de 200 Hz y un tiempo de respuesta ultra rápido de 1 ms, lo que permite una transición suave entre imágenes, eliminando el desenfoque y el lag, crucial para videojuegos de ritmo rápido. Además, su relación de contraste de 1000:1 asegura negros profundos y blancos brillantes, mejorando la percepción visual en escenas oscuras. Este modelo incluye opciones de conectividad versátiles con puertos DP, HDMI y de audio, lo que facilita la integración con múltiples dispositivos. Incluye un completo paquete con cables HDMI, adaptador de corriente, base de montaje y manual de usuario para una instalación sencilla. Este monitor es perfecto para gamers que buscan rendimiento, calidad de imagen y comodidad en su experiencia de juego.'],
            ['cod' => 'MON-ASU-003', 'nombre' => 'Monitor Gamer ASUS ROG Strix XG27AQDMES 26.5" QHD 240 Hz / 0.3 ms - Negro', 'marca' => 'ASUS', 'precio' => 4462308, 'cat' => 'Monitores', 'desc' => 'El Monitor Gamer ASUS ROG Strix XG27AQDMES de 26.5 pulgadas está diseñado para ofrecer una experiencia de juego excepcional. Con un panel OLED QHD de 26.5 pulgadas, este monitor proporciona una resolución de 2560 x 1440, una frecuencia de actualización de 240 Hz y un tiempo de respuesta de 0.3 ms, ideal para los gamers que buscan velocidad y claridad en cada movimiento. La tecnología de visualización QD-OLED garantiza colores vibrantes y una experiencia visual fluida. Además, su compatibilidad con G-SYNC y FreeSync Premium asegura un rendimiento óptimo en juegos de ritmo rápido.'],
            ['cod' => 'CMP-KIN-001', 'nombre' => 'Memoria RAM DDR4 Kingston Fury Beast 8 GB 3200 MHz RGB KF432C16BB2A/8 - Negro', 'marca' => 'Kingston', 'precio' => 570000, 'cat' => 'Memorias RAM', 'desc' => 'Las memorias Kingston FURY™ Beast DDR4 RGB elevan la estética y el rendimiento de cualquier sistema. Con llamativa iluminación RGB que realza el elegante disipador térmico negro, alcanzan velocidades impresionantes de hasta 3.200 MT/s. Ofrecen perfiles certificados Intel® XMP, compatibilidad con AMD Ryzen y la facilidad de la sobreaceleración automática Plug N Play2 a 2.666 MT/s. Prepárese para la adrenalina necesaria tanto para trabajar como para jugar.'],
            ['cod' => 'NTB-MIC-001', 'nombre' => 'Notebook Microsoft Surface Pro EP2-33426 13" Táctil Snapdragon X Plus 16 GB 512 GB - Violet', 'marca' => 'Microsoft', 'precio' => 6069231, 'cat' => 'Notebooks', 'desc' => 'Equipo 2 en 1 que funciona como tablet y como notebook, con pantalla táctil de 13 pulgadas. Su procesador Qualcomm Snapdragon X Plus ofrece gran autonomía de batería y buen rendimiento para tareas de oficina, estudio y navegación. Incluye 16 GB de memoria LPDDR5x y 512 GB de almacenamiento, ideal para usuarios que buscan portabilidad sin resignar productividad.'],
            ['cod' => 'NTB-DEL-002', 'nombre' => 'Notebook Dell LDC15250-5315 15.6" Táctil Intel Core i5-1334U 8 GB 512 GB SSD - Carbon Black', 'marca' => 'Dell', 'precio' => 3461538, 'cat' => 'Notebooks', 'desc' => 'Notebook de 15.6 pulgadas con pantalla táctil, pensada para el trabajo diario y el estudio. Equipa un procesador Intel Core i5-1334U de bajo consumo, 8 GB de memoria DDR4 y un SSD de 512 GB que acelera el arranque del sistema y la apertura de programas. Una opción equilibrada entre precio y rendimiento para tareas de oficina y multimedia.'],
            ['cod' => 'NTB-HPI-003', 'nombre' => 'Notebook HP OmniBook X 16-CP1013DX 16" Snapdragon X2 Elite 24 GB 512 GB SSD - Deep Espresso', 'marca' => 'HP', 'precio' => 8243846, 'cat' => 'Notebooks', 'desc' => 'Notebook de 16 pulgadas con procesador Qualcomm Snapdragon X2 Elite, orientada a profesionales que necesitan potencia y una batería de larga duración. Sus 24 GB de memoria permiten trabajar con muchas aplicaciones abiertas, y el SSD PCI Express 4.0 de 512 GB ofrece transferencias de datos muy rápidas. Diseño elegante en color Deep Espresso.'],
            ['cod' => 'NTB-ASU-004', 'nombre' => 'Notebook ASUS Vivobook 16 X1605VA-MB2774W 16" Intel Core i9-270H 16 GB DDR5 1 TB SSD - Plata', 'marca' => 'ASUS', 'precio' => 5437692, 'cat' => 'Notebooks', 'desc' => 'Notebook de 16 pulgadas con procesador Intel Core i9-270H de alto rendimiento, ideal para programación, edición de documentos pesados y multitarea exigente. Cuenta con 16 GB de memoria DDR5 y un amplio SSD M.2 de 1 TB para almacenar proyectos, archivos y aplicaciones. Su pantalla grande facilita el trabajo con varias ventanas a la vez.'],
            ['cod' => 'NTB-LEN-005', 'nombre' => 'Notebook Lenovo IdeaPad 1i 83B40008US 15.6" Intel Core i5-1335U 16 GB 256 GB SSD - Grey', 'marca' => 'Lenovo', 'precio' => 4778462, 'cat' => 'Notebooks', 'desc' => 'Notebook de 15.6 pulgadas de la línea IdeaPad, pensada para estudiantes y uso hogareño. Su procesador Intel Core i5-1335U junto a 16 GB de memoria DDR4 permite trabajar con fluidez en ofimática, clases virtuales y navegación. Incluye un SSD de 256 GB para un arranque rápido del sistema.'],
            ['cod' => 'NTB-ACE-006', 'nombre' => 'Notebook Acer Aspire 14 A14-52MT-94H5 14" Táctil Intel Core Ultra 9 288V 32 GB 1 TB SSD - Steel Gray', 'marca' => 'Acer', 'precio' => 5846154, 'cat' => 'Notebooks', 'desc' => 'Notebook compacta de 14 pulgadas con pantalla táctil y procesador Intel Core Ultra 9 288V, que incorpora una unidad de procesamiento neuronal (NPU) para funciones de inteligencia artificial. Sus 32 GB de memoria LPDDR5X y 1 TB de SSD la convierten en una herramienta potente y portátil para profesionales y desarrolladores.'],
            ['cod' => 'NTB-SAM-007', 'nombre' => 'Notebook Samsung Galaxy Book5 Pro NP960XHA AMOLED 16" Táctil Intel Core Ultra 7 256V 32 GB 1 TB SSD - Gray', 'marca' => 'Samsung', 'precio' => 12156154, 'cat' => 'Notebooks', 'desc' => 'Notebook premium con pantalla AMOLED táctil de 16 pulgadas, que ofrece colores intensos y negros profundos para diseño, edición de fotos y consumo multimedia. Equipa un procesador Intel Core Ultra 7 256V, 32 GB de memoria y 1 TB de SSD. Su chasis delgado y liviano la hace ideal para profesionales que se desplazan con frecuencia.'],
            ['cod' => 'NTB-SAM-008', 'nombre' => 'Notebook Samsung Galaxy Book4 Edge NP750XQB-KA2US 15.6" Snapdragon X 16 GB 512 GB - Sapphire Blue', 'marca' => 'Samsung', 'precio' => 5114615, 'cat' => 'Notebooks', 'desc' => 'Notebook de 15.6 pulgadas con procesador Qualcomm Snapdragon X, diseñada para ofrecer una batería de larga duración y funciones de inteligencia artificial integradas. Incluye 16 GB de memoria LPDDR5x y 512 GB de almacenamiento eUFS. Una buena alternativa para estudiantes y usuarios que trabajan fuera de la oficina.'],
            ['cod' => 'NTB-HPI-009', 'nombre' => 'Notebook HP 15-FD0133WM 15.6" Intel Core i3-N305 8 GB DDR4 256 GB SSD - Silver', 'marca' => 'HP', 'precio' => 2692308, 'cat' => 'Notebooks', 'desc' => 'Notebook de entrada de 15.6 pulgadas, ideal para tareas básicas como ofimática, navegación, videollamadas y clases en línea. Cuenta con procesador Intel Core i3-N305, 8 GB de memoria DDR4 y SSD de 256 GB. Es la opción más accesible del catálogo para estudiantes y uso hogareño.'],
            ['cod' => 'NTB-ASU-010', 'nombre' => 'Notebook ASUS Zenbook 14 UX3405CA-QL214W OLED 14" Intel Core Ultra 9 285H 32 GB 1 TB SSD - Deep Blue', 'marca' => 'ASUS', 'precio' => 8580000, 'cat' => 'Notebooks', 'desc' => 'Ultrabook de 14 pulgadas con pantalla OLED de alta calidad y procesador Intel Core Ultra 9 285H, orientada a profesionales que buscan máximo rendimiento en un equipo liviano. Sus 32 GB de memoria LPDDR5x y 1 TB de SSD permiten ejecutar entornos de desarrollo, máquinas virtuales y software de diseño con fluidez.'],
            ['cod' => 'CMP-AMD-002', 'nombre' => 'Procesador AMD Ryzen 5 5600 3.5 GHz 6 Núcleos AM4', 'marca' => 'AMD', 'precio' => 730769, 'cat' => 'Procesadores', 'desc' => 'Procesador de 6 núcleos y 12 hilos para socket AM4, con frecuencia base de 3.5 GHz y turbo de hasta 4.4 GHz. Ofrece una excelente relación precio-rendimiento para gaming y multitarea, e incluye el disipador Wraith Stealth. No posee gráficos integrados, por lo que requiere una tarjeta de video dedicada.'],
            ['cod' => 'CMP-INT-003', 'nombre' => 'Procesador Intel Core i5-12400F 2.5 GHz 6 Núcleos LGA1700', 'marca' => 'Intel', 'precio' => 807692, 'cat' => 'Procesadores', 'desc' => 'Procesador Intel de 12.ª generación con 6 núcleos y 12 hilos, frecuencia turbo de hasta 4.4 GHz y socket LGA1700. Es una opción muy eficiente para equipos de gaming y trabajo de nivel medio. La versión "F" no incluye gráficos integrados, por lo que necesita una tarjeta de video dedicada.'],
            ['cod' => 'CMP-KIN-004', 'nombre' => 'Memoria RAM DDR5 Kingston Fury Beast 16 GB 5600 MHz KF556C40BB-16 - Negro', 'marca' => 'Kingston', 'precio' => 400000, 'cat' => 'Memorias RAM', 'desc' => 'Módulo de memoria DDR5 de 16 GB a 5600 MT/s, ideal para plataformas modernas Intel y AMD. Soporta perfiles Intel XMP 3.0 y AMD EXPO para un ajuste sencillo de la velocidad, y cuenta con disipador de bajo perfil compatible con la mayoría de los coolers.'],
            ['cod' => 'CMP-KIN-005', 'nombre' => 'SSD Kingston NV2 1 TB M.2 2280 NVMe PCIe 4.0 SNV2S/1000G', 'marca' => 'Kingston', 'precio' => 430769, 'cat' => 'Discos y SSD', 'desc' => 'Unidad de estado sólido en formato M.2 con interfaz NVMe PCIe 4.0, con velocidades de lectura de hasta 3.500 MB/s. Reduce de forma notable los tiempos de arranque y de carga de programas. Su formato compacto la hace compatible con notebooks y PCs de escritorio que tengan ranura M.2.'],
            ['cod' => 'CMP-WDC-006', 'nombre' => 'Disco Duro Interno Western Digital Ultrastar DC HC555 SATA 16 TB WUH722016CLE6L4', 'marca' => 'Western Digital', 'precio' => 2423077, 'cat' => 'Discos y SSD', 'desc' => 'Disco duro de 3.5 pulgadas y 16 TB de capacidad diseñado para servidores, centros de datos y sistemas NAS. Está pensado para funcionar de forma continua las 24 horas, con alta confiabilidad para el almacenamiento de grandes volúmenes de información y copias de seguridad.'],
            ['cod' => 'CMP-MSI-007', 'nombre' => 'Placa Madre MSI B550M PRO-VDH WIFI AM4 Micro-ATX DDR4', 'marca' => 'MSI', 'precio' => 684615, 'cat' => 'Placas madre', 'desc' => 'Placa madre Micro-ATX con chipset AMD B550 para procesadores Ryzen de socket AM4. Incluye Wi-Fi integrado, soporte para memoria DDR4, ranura M.2 compatible con PCIe 4.0 y salidas de video VGA, DVI y HDMI. Una base sólida para armar equipos de oficina o gaming de gama media.'],
            ['cod' => 'CMP-COR-008', 'nombre' => 'Fuente de Alimentación Corsair CV650 650W 80 Plus Bronze', 'marca' => 'Corsair', 'precio' => 400000, 'cat' => 'Fuentes de poder', 'desc' => 'Fuente de poder de 650 W con certificación 80 Plus Bronze, que garantiza una entrega de energía eficiente y estable. Su ventilador de 120 mm funciona de forma silenciosa, y su potencia es suficiente para equipos con tarjetas de video de gama media.'],
            ['cod' => 'CMP-GIG-009', 'nombre' => 'Tarjeta Gráfica Gigabyte GeForce RTX 4060 WINDFORCE OC 8 GB GDDR6', 'marca' => 'Gigabyte', 'precio' => 1884615, 'cat' => 'Tarjetas de video', 'desc' => 'Tarjeta de video con GPU NVIDIA GeForce RTX 4060 y 8 GB de memoria GDDR6, pensada para jugar en resolución Full HD con altas configuraciones. Soporta trazado de rayos (ray tracing) y la tecnología DLSS 3, y su sistema de doble ventilador mantiene temperaturas bajas con poco ruido.'],
            ['cod' => 'IMP-EPS-001', 'nombre' => 'Impresora Multifuncional Epson EcoTank L3250 Wi-Fi - Negro', 'marca' => 'Epson', 'precio' => 1069231, 'cat' => 'Impresoras', 'desc' => 'Impresora multifuncional que imprime, copia y escanea, con sistema de tanques de tinta recargables que reduce mucho el costo por página. Su conexión Wi-Fi permite imprimir desde computadoras y celulares sin cables. Ideal para el hogar, estudiantes y pequeñas oficinas con alto volumen de impresión.'],
            ['cod' => 'IMP-HPI-002', 'nombre' => 'Impresora Multifuncional HP Smart Tank 580 Wi-Fi - Blanco', 'marca' => 'HP', 'precio' => 961538, 'cat' => 'Impresoras', 'desc' => 'Equipo multifuncional con tanques de tinta de gran capacidad, conexión Wi-Fi y gestión desde la aplicación HP Smart. Permite imprimir, copiar y escanear a bajo costo, con un rendimiento de miles de páginas por cada juego de botellas de tinta.'],
            ['cod' => 'IMP-BRO-003', 'nombre' => 'Impresora Láser Monocromática Brother HL-1212W Wi-Fi', 'marca' => 'Brother', 'precio' => 753846, 'cat' => 'Impresoras', 'desc' => 'Impresora láser compacta en blanco y negro, ideal para imprimir documentos de texto con rapidez y nitidez. Su conectividad inalámbrica facilita compartirla en la red de la oficina o del hogar. Una opción económica y confiable para quienes imprimen principalmente documentos.'],
            ['cod' => 'IMP-CAN-004', 'nombre' => 'Impresora Multifuncional Canon PIXMA G3110 Wi-Fi - Negro', 'marca' => 'Canon', 'precio' => 992308, 'cat' => 'Impresoras', 'desc' => 'Multifuncional de tinta continua que imprime, copia y escanea, con conexión Wi-Fi para impresión desde dispositivos móviles. Sus tanques recargables integrados ofrecen un alto rendimiento por botella, lo que la hace adecuada para hogares y pequeños negocios.'],
            ['cod' => 'RED-TPL-001', 'nombre' => 'Router Wi-Fi TP-Link Archer C6 AC1200 Doble Banda', 'marca' => 'TP-Link', 'precio' => 223077, 'cat' => 'Routers', 'desc' => 'Router de doble banda (2.4 GHz y 5 GHz) con velocidades combinadas de hasta 1200 Mbps y tecnología MU-MIMO para atender varios dispositivos a la vez. Sus cuatro antenas externas mejoran la cobertura en el hogar u oficina pequeña. Incluye puertos Gigabit para conexiones cableadas rápidas.'],
            ['cod' => 'RED-TPL-002', 'nombre' => 'Sistema Wi-Fi Mesh TP-Link Deco M4 AC1200 - Pack x2', 'marca' => 'TP-Link', 'precio' => 607692, 'cat' => 'Routers', 'desc' => 'Sistema de red en malla compuesto por dos unidades que trabajan juntas para cubrir toda la casa con una única red Wi-Fi, sin zonas muertas. Se configura de forma sencilla desde la aplicación Deco e incluye controles parentales y priorización de dispositivos.'],
            ['cod' => 'RED-TPL-003', 'nombre' => 'Repetidor Wi-Fi TP-Link TL-WA850RE N300', 'marca' => 'TP-Link', 'precio' => 115385, 'cat' => 'Routers', 'desc' => 'Extensor de señal inalámbrica de hasta 300 Mbps que se conecta directamente a un tomacorriente. Amplía la cobertura de la red existente hacia habitaciones alejadas del router, y su indicador luminoso ayuda a encontrar la mejor ubicación.'],
            ['cod' => 'RED-TPL-004', 'nombre' => 'Switch de Escritorio TP-Link TL-SG108 8 Puertos Gigabit', 'marca' => 'TP-Link', 'precio' => 146154, 'cat' => 'Switches', 'desc' => 'Switch no administrable de 8 puertos Gigabit con carcasa metálica, ideal para ampliar la red cableada de oficinas y hogares. Funciona sin configuración (plug and play) y reduce el consumo de energía de forma automática según el uso de cada puerto.'],
            ['cod' => 'RED-TPL-005', 'nombre' => 'Adaptador Wi-Fi USB TP-Link Archer T3U AC1300 Doble Banda', 'marca' => 'TP-Link', 'precio' => 84615, 'cat' => 'Adaptadores Wi-Fi', 'desc' => 'Adaptador inalámbrico USB 3.0 que agrega o mejora la conexión Wi-Fi de computadoras de escritorio y notebooks. Trabaja en doble banda con velocidades de hasta 1300 Mbps y su tamaño compacto permite dejarlo conectado de forma permanente.'],
            ['cod' => 'RED-CUD-006', 'nombre' => 'Router Wi-Fi 6 Cudy WR3000 AX3000 Doble Banda', 'marca' => 'Cudy', 'precio' => 300000, 'cat' => 'Routers', 'desc' => 'Router con tecnología Wi-Fi 6 que alcanza velocidades combinadas de hasta 3000 Mbps, ofreciendo menor latencia y mejor rendimiento con muchos dispositivos conectados. Cuenta con puertos Gigabit, soporte para VPN y una interfaz de configuración sencilla.'],
            ['cod' => 'PER-LOG-004', 'nombre' => 'Kit Teclado y Mouse Inalámbrico Logitech MK270 Español - Negro', 'marca' => 'Logitech', 'precio' => 123077, 'cat' => 'Mouses', 'desc' => 'Combo de teclado y mouse inalámbricos que se conectan mediante un único receptor USB. El teclado en español incluye teclas multimedia de acceso rápido, y ambos dispositivos ofrecen una larga duración de baterías. Ideal para oficinas y puestos de trabajo.'],
            ['cod' => 'PER-RED-005', 'nombre' => 'Teclado Mecánico Gamer Redragon Kumara K552 RGB Switch Blue - Negro', 'marca' => 'Redragon', 'precio' => 223077, 'cat' => 'Teclados', 'desc' => 'Teclado mecánico compacto sin bloque numérico (TKL), con switches de respuesta táctil y retroiluminación RGB. Su estructura de metal y ABS le brinda gran durabilidad, y su tamaño reducido deja más espacio para mover el mouse en sesiones de juego.'],
            ['cod' => 'PER-LOG-006', 'nombre' => 'Mouse Gamer Logitech G502 HERO 25600 DPI RGB - Negro', 'marca' => 'Logitech', 'precio' => 276923, 'cat' => 'Mouses', 'desc' => 'Mouse gamer con sensor HERO de hasta 25.600 DPI y 11 botones programables. Incluye pesas ajustables para personalizar el peso y la sensación de uso, además de iluminación RGB configurable desde el software Logitech G HUB.'],
            ['cod' => 'PER-HYP-007', 'nombre' => 'Auriculares Gamer HyperX Cloud II 7.1 con Micrófono - Rojo', 'marca' => 'HyperX', 'precio' => 453846, 'cat' => 'Auriculares y parlantes', 'desc' => 'Auriculares gamer con sonido envolvente virtual 7.1, almohadillas de espuma viscoelástica y estructura de aluminio. Su micrófono desmontable con cancelación de ruido facilita la comunicación en partidas y videollamadas. Se conectan por USB o mediante jack de 3.5 mm.'],
            ['cod' => 'PER-LOG-008', 'nombre' => 'Webcam Logitech C920 HD Pro Full HD 1080p', 'marca' => 'Logitech', 'precio' => 400000, 'cat' => 'Webcams', 'desc' => 'Cámara web con resolución Full HD 1080p a 30 cuadros por segundo, enfoque automático y corrección de luz. Sus dos micrófonos captan audio estéreo, por lo que es ideal para videoconferencias, clases virtuales y transmisiones en vivo.'],
            ['cod' => 'PER-RAZ-009', 'nombre' => 'Mouse Gamer Razer DeathAdder Essential 6400 DPI - Negro', 'marca' => 'Razer', 'precio' => 176923, 'cat' => 'Mouses', 'desc' => 'Mouse gamer de diseño ergonómico para la mano derecha, con sensor óptico de 6.400 DPI y 5 botones programables. Sus interruptores mecánicos están pensados para una larga vida útil. Una opción accesible para iniciarse en el gaming.'],
            ['cod' => 'PER-LOG-010', 'nombre' => 'Teclado Inalámbrico Logitech K380 Multidispositivo Bluetooth - Gris', 'marca' => 'Logitech', 'precio' => 192308, 'cat' => 'Teclados', 'desc' => 'Teclado compacto y liviano que se conecta por Bluetooth hasta a tres dispositivos y permite alternar entre ellos con una sola tecla. Es compatible con Windows, macOS, Android e iOS, y resulta ideal para usar con notebooks, tablets y celulares.'],
            ['cod' => 'PER-RED-011', 'nombre' => 'Auriculares Gamer Redragon Zeus H510 7.1 USB RGB - Negro', 'marca' => 'Redragon', 'precio' => 300000, 'cat' => 'Auriculares y parlantes', 'desc' => 'Auriculares con sonido envolvente virtual 7.1, drivers de 53 mm e iluminación RGB. Incluyen micrófono desmontable y almohadillas acolchadas para sesiones largas, con conexión USB y opción de jack de 3.5 mm.'],
            ['cod' => 'PER-HYP-012', 'nombre' => 'Teclado Mecánico Gamer HyperX Alloy Origins Core TKL RGB - Negro', 'marca' => 'HyperX', 'precio' => 476923, 'cat' => 'Teclados', 'desc' => 'Teclado mecánico compacto con cuerpo de aluminio y switches HyperX de respuesta rápida. Su iluminación RGB por tecla y los perfiles guardados en memoria interna se configuran desde el software HyperX NGENUITY. Incluye cable USB-C desmontable.'],
            ['cod' => 'PER-LOG-013', 'nombre' => 'Mouse Gamer Logitech G203 LIGHTSYNC RGB 8000 DPI - Negro', 'marca' => 'Logitech', 'precio' => 115385, 'cat' => 'Mouses', 'desc' => 'Mouse gamer liviano con sensor de hasta 8.000 DPI, 6 botones programables e iluminación RGB LIGHTSYNC. Su diseño clásico de tres botones es cómodo para cualquier tipo de agarre, y es una de las opciones más accesibles de la línea Logitech G.'],
            ['cod' => 'ACC-SAN-001', 'nombre' => 'Memoria Micro SD SanDisk Ultra 64 GB 100 MB/s C10 SDSQUNR-064G-GN3MA', 'marca' => 'SanDisk', 'precio' => 86923, 'cat' => 'Pendrives y discos externos', 'desc' => 'Tarjeta de memoria microSD de 64 GB con velocidades de lectura de hasta 100 MB/s y clase 10, adecuada para celulares, tablets y cámaras. Permite ampliar el almacenamiento para fotos, videos y aplicaciones de forma rápida y confiable.'],
            ['cod' => 'ACC-LEX-002', 'nombre' => 'Lector de Tarjetas Lexar USB 2 en 1 LRW450UB - Negro', 'marca' => 'Lexar', 'precio' => 73077, 'cat' => 'Accesorios para notebook', 'desc' => 'Lector de tarjetas compacto compatible con memorias SD y microSD, con conexión USB 3.2 para transferencias rápidas. Facilita copiar fotos y archivos desde cámaras y celulares a la computadora.'],
            ['cod' => 'ACC-CAS-003', 'nombre' => 'Mochila para Notebook Case Logic DLBP-116 hasta 15.6" - Negro', 'marca' => 'Case Logic', 'precio' => 323077, 'cat' => 'Mochilas y fundas', 'desc' => 'Mochila con compartimento acolchado para notebooks de hasta 15.6 pulgadas y bolsillo para tablet. Sus bolsillos organizadores permiten guardar cargadores, mouse y accesorios, y sus correas acolchadas ofrecen comodidad en traslados diarios.'],
            ['cod' => 'ACC-HAV-004', 'nombre' => 'Base Refrigerante para Notebook Havit F2069 hasta 17" con 5 Ventiladores - Negro', 'marca' => 'Havit', 'precio' => 115385, 'cat' => 'Accesorios para notebook', 'desc' => 'Base de enfriamiento con cinco ventiladores y altura ajustable, compatible con notebooks de hasta 17 pulgadas. Ayuda a reducir la temperatura del equipo durante largas jornadas de trabajo o juego y mejora la postura al elevar la pantalla.'],
            ['cod' => 'ACC-SAN-005', 'nombre' => 'Pendrive SanDisk Ultra Flair 128 GB USB 3.0 - Plata', 'marca' => 'SanDisk', 'precio' => 92308, 'cat' => 'Pendrives y discos externos', 'desc' => 'Memoria USB de 128 GB con carcasa metálica resistente y velocidades de lectura de hasta 150 MB/s mediante USB 3.0. Ideal para transportar documentos, presentaciones y copias de seguridad de forma práctica.'],
    ];

    public function run(PriceService $precios): void
    {
        if (empty($this->productos)) {
            throw new RuntimeException('CatalogoTiendaSeeder: el array $productos está vacío.');
        }

        DB::transaction(function () use ($precios) {
            $depositoId = $this->depositoPrincipal();
            $ivaId      = $this->idOrFail('tipo_iva', 'tiv_id', 'tiv_tasa', 10, 'IVA 10%');
            $unidadId   = DB::table('unidad_medida')->whereIn('umd_codigo', ['UNI', 'UN'])->value('umd_id')
                ?? throw new RuntimeException('Falta la unidad de medida "Unidad". Corré primero DatabaseSeeder.');

            $this->crearCategoriasExtra();
            $categorias = $this->mapaCategorias();
            $slugsUsados = DB::table('publicacion_web')->pluck('pub_slug')->flip()->all();

            foreach ($this->productos as $i => $p) {
                $marcaId = $this->marca($p['marca']);
                $catId   = $categorias[$p['cat']]
                    ?? throw new RuntimeException("Categoría inexistente: {$p['cat']} (producto {$p['cod']})");

                // 1) Producto (clave natural: código de inventario)
                $producto = Producto::firstOrNew(['prd_codinv' => $p['cod']]);
                $esNuevo  = ! $producto->exists;
                $producto->fill([
                    'prd_marid'  => $marcaId,
                    'prd_tivid'  => $ivaId,
                    'prd_umdid'  => $unidadId,
                    'prd_descri' => Str::limit($p['nombre'], 147),
                    'prd_stkmin' => 1,
                    'prd_afestk' => 1,
                    'prd_estado' => 1,
                    'prd_habmin' => true,
                    'prd_habmaj' => true,
                ]);
                if ($esNuevo) {
                    $producto->prd_creado = now();
                }
                $producto->save();

                // 2) Stock inicial: solo si el producto todavía no tiene stock en el depósito
                $this->stockInicial($producto->prd_id, $depositoId, (float) $p['precio']);

                // 3) Precio de venta a partir del costo (misma fórmula que el sistema)
                $precios->aplicarPrecios($producto->load('tipoIva'), (float) $p['precio']);

                // 4) Publicación web (lo que ve la tienda)
                $pub = PublicacionWeb::firstOrNew(['pub_prdid' => $producto->prd_id]);
                if (! $pub->exists) {
                    $pub->pub_slug = $this->slugUnico($p['nombre'], $slugsUsados);
                }
                $pub->fill([
                    'pub_catid'            => $catId,
                    'pub_titulo'           => Str::limit($p['nombre'], 147),
                    'pub_descwb'           => $p['desc'],
                    'pub_meta_titulo'      => Str::limit($p['nombre'], 157),
                    'pub_meta_descripcion' => Str::limit($p['desc'], 317),
                    'pub_estado'           => PublicacionWeb::ESTADO_PUBLICADO,
                ]);
                $pub->save();

                // 5) Secciones del Home: destacados, tendencia y más vendidos
                $seccion = VitrinaProducto::SECCIONES[intdiv($i, self::POR_SECCION)] ?? null;
                if ($seccion) {
                    DB::table('vitrina_producto')->updateOrInsert(
                        ['vip_pubid' => $pub->pub_id, 'vip_seccion' => $seccion],
                        ['vip_orden' => $i % self::POR_SECCION + 1]
                    );
                }
            }
        });

        $this->command?->info(count($this->productos) . ' productos cargados. Ejecutá: php artisan busqueda:reindexar');
    }

    /** Usa el depósito principal existente o crea la estructura mínima. */
    private function depositoPrincipal(): int
    {
        $existente = DB::table('deposito')->where('dpo_princi', 1)->where('dpo_estado', 1)->value('dpo_id');
        if ($existente) {
            return $existente;
        }

        DB::table('departamento')->updateOrInsert(['dep_descri' => 'Central'], ['dep_estado' => 1]);
        $depId = DB::table('departamento')->where('dep_descri', 'Central')->value('dep_id');

        DB::table('ciudad')->updateOrInsert(['ciu_depid' => $depId, 'ciu_descri' => 'Capiatá'], ['ciu_estado' => 1]);
        $ciuId = DB::table('ciudad')->where(['ciu_depid' => $depId, 'ciu_descri' => 'Capiatá'])->value('ciu_id');

        // DATOS DE EJEMPLO: corregilos luego desde el admin (Configuración > Empresa).
        $empId = DB::table('empresa')->value('emp_id') ?? DB::table('empresa')->insertGetId([
            'emp_razonsocial' => 'Z Informática',
            'emp_ruc'         => '80000000-0',
            'emp_actividad'   => 'Venta de equipos informáticos',
            'emp_estado'      => 1,
        ], 'emp_id');

        $sucId = DB::table('sucursal')->where('suc_empid', $empId)->where('suc_princi', 1)->value('suc_id')
            ?? DB::table('sucursal')->insertGetId([
                'suc_empid'  => $empId,
                'suc_ciuid'  => $ciuId,
                'suc_nombre' => 'Casa Central',
                'suc_princi' => 1,
                'suc_establ' => '001',
                'suc_estado' => 1,
                'suc_retiro' => true,
            ], 'suc_id');

        return DB::table('deposito')->insertGetId([
            'dpo_sucid'  => $sucId,
            'dpo_codigo' => 'DEP01',
            'dpo_nombre' => 'Depósito principal',
            'dpo_princi' => 1,
            'dpo_estado' => 1,
        ], 'dpo_id');
    }

    private function crearCategoriasExtra(): void
    {
        foreach (self::CATEGORIAS_EXTRA as $hija => $padre) {
            $padreId = Categoria::where('cat_nombre', $padre)->whereNull('cat_idpadr')->value('cat_id')
                ?? throw new RuntimeException("Falta la categoría padre \"$padre\". Corré CategoriaSeeder.");

            Categoria::updateOrCreate(
                ['cat_nombre' => $hija, 'cat_idpadr' => $padreId],
                ['cat_nivel' => 2, 'cat_icono' => null]
            );
        }
    }

    /** nombre de categoría hija => cat_id */
    private function mapaCategorias(): array
    {
        return DB::table('categoria')->whereNotNull('cat_idpadr')->pluck('cat_id', 'cat_nombre')->all();
    }

    private function marca(string $nombre): int
    {
        DB::table('marca')->updateOrInsert(['mar_descri' => $nombre], ['mar_estado' => 1]);

        return DB::table('marca')->where('mar_descri', $nombre)->value('mar_id');
    }

    private function stockInicial(int $productoId, int $depositoId, float $costo): void
    {
        $existe = DB::table('stock')->where(['sto_prdid' => $productoId, 'sto_depid' => $depositoId])->exists();
        if ($existe) {
            return;
        }

        $cantidad = self::STOCK_INICIAL;

        DB::table('stock')->insert([
            'sto_prdid'  => $productoId,
            'sto_depid'  => $depositoId,
            'sto_cantid' => $cantidad,
            'sto_reserv' => 0,
            'sto_cosprm' => $costo,
        ]);

        // Trazabilidad: el kardex muestra de dónde salió el stock inicial
        DB::table('movimiento_stock')->insert([
            'mst_fecha'  => now(),
            'mst_tipo'   => 'E',
            'mst_cantid' => $cantidad,
            'mst_cosuni' => $costo,
            'mst_salcan' => $cantidad,
            'mst_salcos' => round($cantidad * $costo, 4),
            'mst_obs'    => 'Stock inicial (seeder)',
            'mst_stprid' => $productoId,
            'mst_stdeid' => $depositoId,
        ]);
    }

    private function slugUnico(string $texto, array &$usados): string
    {
        $base = Str::limit(Str::slug($texto), 170, '') ?: 'producto';
        $slug = $base;
        for ($n = 2; isset($usados[$slug]); $n++) {
            $slug = "{$base}-{$n}";
        }
        $usados[$slug] = true;

        return $slug;
    }

    private function idOrFail(string $tabla, string $pk, string $col, $valor, string $nombre): int
    {
        return DB::table($tabla)->where($col, $valor)->value($pk)
            ?? throw new RuntimeException("Falta \"$nombre\" en $tabla. Corré primero DatabaseSeeder.");
    }
}
