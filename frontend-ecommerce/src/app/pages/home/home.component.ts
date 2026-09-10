import { CommonModule, CurrencyPipe } from '@angular/common';
import { Component, OnInit, afterNextRender } from '@angular/core';
import { DomSanitizer, SafeHtml, SafeStyle } from '@angular/platform-browser';
import { Producto } from '../../core/models/producto.model';
import { ProductoPublicoService } from '../../core/services/producto-publico.service';
import { ProductoVitrina } from '../../core/models/vitrina.model';
import { VitrinaHomeService } from '../../core/services/vitrina-home.service';
import { URL_BACKEND } from '../../config/config';
import { SliderVitrina } from '../../core/models/slider.model';
import { SliderPublicoService } from '../../core/services/slider-publico.service';

declare const UIkit: any;


@Component({
  selector: 'app-home',
  standalone: true,
  imports: [CommonModule, CurrencyPipe],
  templateUrl: './home.component.html',
  styleUrl: './home.component.css'
})
export class HomeComponent implements OnInit {

  productosDestacados: Producto[] = [];
  cargandoProductos = false;
  

  // Secciones manuales de la vitrina del home (GET /api/tienda/home).
  destacados: ProductoVitrina[] = [];
  trending: ProductoVitrina[] = [];
  masVendidos: ProductoVitrina[] = [];
  ofertaDelDia: ProductoVitrina[] = [];
  nuevos: ProductoVitrina[] = [];
  enOferta: ProductoVitrina[] = [];
	  // Banners del home (GET /api/tienda/sliders): hero (tipo 1) y promo (tipo 2).
  slidersPrincipales: SliderVitrina[] = [];
  slidersSecundariosIzquierda: SliderVitrina[] = [];
  slidersSecundariosDerecha: SliderVitrina[] = [];
  slidersTerciarios: SliderVitrina[] = [];

  // Se usa cuando el producto publicado todavía no tiene imagen cargada.
  readonly imagenFallback = 'data:image/svg+xml;charset=UTF-8,%3Csvg%20xmlns%3D%22http%3A//www.w3.org/2000/svg%22%20width%3D%22300%22%20height%3D%22225%22%20viewBox%3D%220%200%20300%20225%22%3E%3Crect%20width%3D%22300%22%20height%3D%22225%22%20fill%3D%22%23e9e9e9%22/%3E%3Ctext%20x%3D%22150%22%20y%3D%22117%22%20font-family%3D%22Arial%2C%20sans-serif%22%20font-size%3D%2216%22%20fill%3D%22%23999%22%20text-anchor%3D%22middle%22%20dominant-baseline%3D%22middle%22%3ESin%20imagen%3C/text%3E%3C/svg%3E';

	private heroInicializado = false;
	
	constructor(
		private productoPublicoService: ProductoPublicoService,
		private vitrinaHomeService: VitrinaHomeService,
		private sliderPublicoService: SliderPublicoService,
		private sanitizer: DomSanitizer
  ) {
    // afterNextRender solo corre en el navegador (nunca en SSR) y después
    // de que el DOM quedó pintado/hidratado: es el punto seguro para
    // inicializar librerías que tocan el DOM directamente, como UIkit.
    afterNextRender(() => this.inicializarHero());
	}

  ngOnInit(): void {
    this.cargarProductosPublicados();
    this.cargarVitrinaHome();
    this.cargarSliders();
  }

cargarVitrinaHome(): void {
  this.vitrinaHomeService.obtener(12).subscribe({
    next: (res) => {
      this.nuevos = res.data.nuevos;
      this.destacados = res.data.destacados;
      this.trending = res.data.trending;
      this.masVendidos = res.data.mas_vendidos;
      this.enOferta = res.data.en_oferta;
      this.ofertaDelDia = res.data.oferta_del_dia;
    },
    error: () => {
      this.nuevos = [];
      this.destacados = [];
      this.trending = [];
      this.masVendidos = [];
      this.enOferta = [];
      this.ofertaDelDia = [];
    },
  });
}

  /**
   * Devuelve la imagen del banner como valor de background-image. Se usa
   * fondo en vez de <img> porque el diseño necesita que la foto cubra toda
   * la tarjeta (cover) y el texto se superponga encima.
   * bypassSecurityTrustStyle evita que Angular descarte la url() al
   * sanitizar el binding de estilo.
   */
  fondoSlider(slider: SliderVitrina): SafeStyle {
    return this.sanitizer.bypassSecurityTrustStyle(`url('${this.imagenSlider(slider)}')`);
  }

// La re-inicialización manual de UIkit fue eliminada a propósito.
// Todos los sliders declaran su config en el atributo (uk-slideshow / uk-slider)
// y UIkit los inicializa una sola vez cuando Angular inserta el nodo por *ngIf,
// ya con todos los <li> del *ngFor renderizados. Volver a llamar a
// UIkit.slideshow()/UIkit.slider() sobre el mismo elemento creaba una segunda
// instancia (la del atributo no se destruye con getComponent) y ambas competían
// por el slide activo.

  /**
   * Banners del home. sld_tipo=1 va en el hero (uk-slideshow) y
   * sld_tipo=2 alimenta dos carruseles independientes (izquierda/derecha)
   * según sld_posicion. Solo llegan sld_estado=1 (Activo), ya ordenados
   * por sld_orden desde el backend.
   */
   cargarSliders(): void {
    this.sliderPublicoService.obtener().subscribe({
       next: (res) => {
        // El backend puede no devolver alguna de las claves (o devolverla
        // null). Se normaliza a [] para que los *ngIf del template nunca
        // lean .length sobre undefined.
        const data = res?.data;
        this.slidersPrincipales = data?.principales ?? [];
        this.slidersSecundariosIzquierda = data?.secundarios_izquierda ?? [];
        this.slidersSecundariosDerecha = data?.secundarios_derecha ?? [];
        this.slidersTerciarios = data?.terciarios ?? [];
        this.inicializarHero();
      },
      error: () => {
        this.slidersPrincipales = [];
        this.slidersSecundariosIzquierda = [];
        this.slidersSecundariosDerecha = [];
        this.slidersTerciarios = [];
      },
    });
  }
  
     /**
   * Inicializa el hero una sola vez, recién cuando el <ul> ya contiene los
   * N <li> del *ngFor. El reintento es necesario porque el orden entre la
   * hidratación del DOM (afterNextRender) y la respuesta del endpoint de
   * sliders no está garantizado: gana el que llegue último y el otro sale
   * por el guard de heroInicializado.
   */
  private inicializarHero(intentos = 30): void {
    if (this.heroInicializado || typeof UIkit === 'undefined') {
      return;
    }

    const heroEl = document.getElementById('hero-slideshow');
    const items = heroEl?.querySelectorAll('.uk-slideshow-items > li').length ?? 0;

    // Todavía no está el DOM completo (o no llegó la data): reintentar.
    if (!heroEl || items === 0 || items !== this.slidersPrincipales.length) {
      if (intentos > 0) {
        setTimeout(() => this.inicializarHero(intentos - 1), 100);
      }
      return;
    }

    const existente = UIkit.getComponent(heroEl, 'slideshow');
    if (existente) {
      existente.$destroy();
    }

    UIkit.slideshow(heroEl, {
      autoplay: true,
      autoplayInterval: 6000,
      pauseOnHover: true,
      minHeight: 620,
      maxHeight: 720,
    });

    UIkit.update(heroEl);
    this.heroInicializado = true;
  }

  // Mismo criterio que imagenVitrina(): el backend ya manda URL absoluta,
  // pero por si APP_URL no está bien seteado, se resuelve acá también.
  imagenSlider(slider: SliderVitrina): string {
    if (!slider.imagen) {
      return this.imagenFallback;
    }
    if (slider.imagen.startsWith('http')) {
      return slider.imagen;
    }
    return `${URL_BACKEND.replace(/\/$/, '')}/${slider.imagen.replace(/^\//, '')}`;
  }

  enlaceSlider(slider: SliderVitrina): string {
    return slider.enlace || '#';
  }

  /**
   * Pone en negrita el precio (ej. "$274.00") dentro del texto pequeño
   * de arriba del título ("Starting at $274.00"), igual que en el diseño
   * de referencia.
   */
  resaltarPrecio(texto: string | null): SafeHtml {
    const seguro = (texto || '').replace(/[<>]/g, '');
    const html = seguro.replace(/\$[\d.,]+/, (match) => `<b>${match}</b>`);
    return this.sanitizer.bypassSecurityTrustHtml(html);
  }

    /**
   * Resalta el porcentaje de descuento (ej. "-35%") con color + subrayado
   * manuscrito. Si el subtítulo no trae un patrón "-NN%", se resalta el
   * texto completo, para que el detalle amarillo del diseño no dependa de
   * cómo se haya cargado el banner desde el panel admin.
   */
  resaltarOferta(texto: string | null): SafeHtml {
    const seguro = (texto || '').replace(/[<>]/g, '');
    const patron = /-?\d{1,3}%/;
    const html = patron.test(seguro)
      ? seguro.replace(patron, (match) => `<span class="hero-v2-discount">${match}</span>`)
      : `<span class="hero-v2-discount">${seguro}</span>`;
    return this.sanitizer.bypassSecurityTrustHtml(html);
  }

  /**
   * Separa el título del banner terciario en dos líneas: la primera
   * palabra se pinta con el color del banner y el resto en blanco y
   * mayúsculas, que es el contraste del diseño de referencia
   * ("Black" / "FRIDAY"). Si el título tiene una sola palabra, `resto`
   * queda vacío y el template no renderiza la segunda línea.
   */
  tituloSpotlight(slider: SliderVitrina): { inicio: string; resto: string } {
    const palabras = (slider.titulo || '').trim().split(/\s+/);
    return {
      inicio: palabras[0] ?? '',
      resto: palabras.slice(1).join(' '),
    };
  }

  trackBySlider(_index: number, slider: SliderVitrina): number {
    return slider.sld_id;
  }


  // El backend debería devolver la URL absoluta (asset('storage/...')),
  // pero si APP_URL no está bien seteado en el .env de Laravel puede
  // llegar como ruta relativa ("/storage/..."). Por las dudas, si no
  // viene con http ya adelante, le anteponemos el host del backend acá.
  imagenVitrina(producto: ProductoVitrina): string {
    if (!producto.imagen) {
      return this.imagenFallback;
    }
    if (producto.imagen.startsWith('http')) {
      return producto.imagen;
    }
    return `${URL_BACKEND.replace(/\/$/, '')}/${producto.imagen.replace(/^\//, '')}`;
  }

  cargarProductosPublicados(): void {
    this.cargandoProductos = true;
    this.productoPublicoService.listarPublicados(8).subscribe({
      next: (res) => {
        this.productosDestacados = res.data;
        this.cargandoProductos = false;
      },
      error: () => {
        // si falla, la sección simplemente queda vacía en vez de romper la home
        this.productosDestacados = [];
        this.cargandoProductos = false;
      }
    });
  }
  
    /**
   * Decide si el texto del banner va en blanco, calculando la luminancia
   * relativa del color de fondo cargado desde el panel admin.
   */
  esColorOscuro(color: string | null): boolean {
    const hex = (color || '').replace('#', '');
    if (hex.length !== 6) {
      return false;
    }
    const r = parseInt(hex.slice(0, 2), 16);
    const g = parseInt(hex.slice(2, 4), 16);
    const b = parseInt(hex.slice(4, 6), 16);
    return (0.299 * r + 0.587 * g + 0.114 * b) < 140;
  }

  imagenPrincipal(producto: Producto): string | null {
    return this.productoPublicoService.imagenUrl(producto.publicacion_web?.imagen_principal);
  }

  precioMostrar(producto: Producto): number {
    return producto.publicacion_web?.pub_prevta ?? producto.prd_precio;
  }

  tituloMostrar(producto: Producto): string {
    return producto.publicacion_web?.pub_titulo ?? producto.prd_descri;
  }

  trackByProducto(_index: number, producto: Producto): number {
    return producto.prd_id;
  }

  trackByVitrina(_index: number, producto: ProductoVitrina): number {
    return producto.pub_id;
  }
}