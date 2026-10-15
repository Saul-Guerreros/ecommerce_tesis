import { CommonModule, isPlatformBrowser } from '@angular/common';
import { Component, Inject, OnInit, PLATFORM_ID } from '@angular/core';
import { RouterModule } from '@angular/router';
import { ToastrService } from 'ngx-toastr';
import { EspecificacionDetalle } from '../../core/models/producto-detalle.model';
import { CuotaOpcion, ProductoVitrina } from '../../core/models/vitrina.model';
import { CarritoUiService } from '../../core/services/carrito-ui.service';
import { CarritoService } from '../../core/services/carrito.service';
import { ComparadorService } from '../../core/services/comparador.service';
import { MonedaPyPipe } from '../../shared/pipes/moneda-py.pipe';

interface ColumnaComparador {
  producto: ProductoVitrina;
  especificaciones: EspecificacionDetalle[];
  agregando: boolean;
}

interface FilaEspecificacion {
  atributo: string;
  /** Un valor por columna; '' = el producto no tiene ese dato. */
  valores: string[];
  difiere: boolean;
}

/**
 * Página /comparar-productos: tabla lado a lado.
 * Patrón Best Buy / Samsung / MediaMarkt: cabecera fija con foto, precio y
 * botón de compra; filas con diferencias resaltadas y opción de ocultar
 * las que son iguales.
 */
@Component({
  selector: 'app-comparador',
  standalone: true,
  imports: [CommonModule, RouterModule, MonedaPyPipe],
  templateUrl: './comparador.component.html',
  styleUrl: './comparador.component.css',
})
export class ComparadorComponent implements OnInit {
  readonly maximo = ComparadorService.MAXIMO;
  readonly urlImagen = ComparadorService.urlImagen;

  cargando = true;
  error = false;
  soloDiferencias = false;

  columnas: ColumnaComparador[] = [];
  filas: FilaEspecificacion[] = [];

  private readonly esNavegador: boolean;

  constructor(
    public comparador: ComparadorService,
    private carritoService: CarritoService,
    private carritoUi: CarritoUiService,
    private toastr: ToastrService,
    @Inject(PLATFORM_ID) platformId: Object,
  ) {
    this.esNavegador = isPlatformBrowser(platformId);
  }

  ngOnInit(): void {
    // El servidor no tiene la lista (vive en localStorage): renderiza el
    // estado "cargando" y el navegador completa el resto.
    if (this.esNavegador) this.cargar();
  }

  cargar(): void {
    const slugs = this.comparador.items.map((i) => i.slug);

    if (!slugs.length) {
      this.columnas = [];
      this.filas = [];
      this.cargando = false;
      return;
    }

    this.cargando = true;
    this.error = false;

    this.comparador.obtenerComparacion(slugs).subscribe({
      next: (res) => {
        this.columnas = res.data.productos.map((p) => ({
          producto: p.producto,
          especificaciones: p.especificaciones ?? [],
          agregando: false,
        }));
        this.comparador.sincronizar(this.columnas.map((c) => c.producto));
        this.armarFilas();
        this.cargando = false;
      },
      error: () => {
        this.error = true;
        this.cargando = false;
      },
    });
  }

  // ---------- Acciones ----------

  quitar(col: ColumnaComparador): void {
    if (col.producto.slug) this.comparador.quitar(col.producto.slug);
    this.columnas = this.columnas.filter((c) => c !== col);
    this.armarFilas();
  }

  vaciar(): void {
    this.comparador.vaciar();
    this.columnas = [];
    this.filas = [];
  }

  /** Igual que la tarjeta de producto: solo viajan id, cantidad y modalidad. */
  agregarAlCarrito(col: ColumnaComparador): void {
    const p = col.producto;
    if (col.agregando || this.sinStock(p)) return;

    col.agregando = true;
    this.carritoService
      .agregar({
        car_idpro: p.prd_id,
        car_idvarp: null,
        car_cantid: p.cantidad_minima && p.cantidad_minima > 1 ? p.cantidad_minima : 1,
        car_modpag: 'contado',
        car_numcuo: null,
      })
      .subscribe({
        next: () => {
          col.agregando = false;
          this.carritoUi.confirmarAgregado();
        },
        error: (err) => {
          col.agregando = false;
          const errores = err?.error?.errors;
          const msj = errores
            ? errores[Object.keys(errores)[0]]?.[0]
            : err?.error?.message;
          this.toastr.error(msj ?? 'Intenta nuevamente.', 'No se pudo agregar');
        },
      });
  }

  // ---------- Datos derivados para la tabla ----------

  get filasVisibles(): FilaEspecificacion[] {
    return this.soloDiferencias ? this.filas.filter((f) => f.difiere) : this.filas;
  }

    /** Cuenta TODAS las diferencias: filas generales + especificaciones. */
  get totalDiferencias(): number {
    const generales = (['disponibilidad', 'cuotas', 'marca', 'sku'] as const).filter((c) => this.difiereGeneral(c)).length;
    return generales + this.filas.filter((f) => f.difiere).length;
  }

  /** ¿Los productos tienen valores distintos en esta fila general? */
  difiereGeneral(campo: 'disponibilidad' | 'cuotas' | 'marca' | 'sku'): boolean {
    if (this.columnas.length < 2) return false;

    const valor = (p: ProductoVitrina): string => {
      switch (campo) {
        case 'marca':
          return p.marca ?? '';
        case 'sku':
          return p.sku ?? '';
        case 'cuotas': {
          const c = this.cuotaMaxima(p);
          return c ? `${c.cantidad}|${c.monto_cuota}` : '';
        }
        case 'disponibilidad':
          return this.sinStock(p) ? 'sin' : p.poco_stock ? `poco${p.stock}` : 'ok';
      }
    };

    return new Set(this.columnas.map((c) => valor(c.producto).trim().toLowerCase())).size > 1;
  }

  /** Ancho mínimo de la tabla: en móvil se desplaza en horizontal. */
  get anchoMinimo(): number {
    const columnas = this.columnas.length + (this.columnas.length < this.maximo ? 1 : 0);
    return 130 + columnas * 200;
  }

  esMejorPrecio(col: ColumnaComparador): boolean {
    const precios = this.columnas.map((c) => c.producto.precio_final).filter((p) => p > 0);
    if (new Set(precios).size < 2) return false; // todos iguales: no hay "mejor"
    return col.producto.precio_final === Math.min(...precios);
  }

  sinStock(p: ProductoVitrina): boolean {
    return p.controla_stock === true && (p.stock ?? 0) <= 0;
  }

  /** La opción con más cuotas es la que más baja la cuota mensual. */
  cuotaMaxima(p: ProductoVitrina): CuotaOpcion | null {
    const cuotas = p.cuotas ?? [];
    return cuotas.length ? cuotas.reduce((a, b) => (b.cantidad > a.cantidad ? b : a)) : null;
  }

  /**
   * Une los atributos de todos los productos (en orden de aparición) y marca
   * cuáles difieren. La comparación ignora mayúsculas y espacios.
   */
  private armarFilas(): void {
    const etiquetas = new Map<string, string>();

    const porColumna = this.columnas.map((col) => {
      const valores = new Map<string, string>();
      for (const esp of col.especificaciones) {
        const nombre = (esp.atributo ?? '').trim();
        const valor = esp.valor === null || esp.valor === undefined ? '' : String(esp.valor).trim();
        if (!nombre || !valor) continue;

        const clave = nombre.toLowerCase();
        if (!etiquetas.has(clave)) etiquetas.set(clave, nombre);
        valores.set(clave, valor);
      }
      return valores;
    });

    this.filas = [...etiquetas].map(([clave, atributo]) => {
      const valores = porColumna.map((m) => m.get(clave) ?? '');
      const distintos = new Set(valores.map((v) => v.toLowerCase()));
      return { atributo, valores, difiere: this.columnas.length > 1 && distintos.size > 1 };
    });
  }
}
