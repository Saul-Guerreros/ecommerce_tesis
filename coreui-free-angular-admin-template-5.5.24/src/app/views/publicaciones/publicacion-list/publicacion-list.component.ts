import { CommonModule } from '@angular/common';
import { Component, OnInit } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { RouterLink } from '@angular/router';
import { ToastrService } from 'ngx-toastr';
import { BadgeModule, ButtonModule, CardModule, FormModule, GridModule, SpinnerModule, TableModule } from '@coreui/angular';
import { environment } from '../../../../environments/environment';
import { Categoria, Producto } from '../../../core/models/producto.model';
import { CatalogoService } from '../../../core/services/catalogo.service';
import { ProductoService } from '../../../core/services/producto.service';

@Component({
  selector: 'app-publicacion-list',
  standalone: true,
  imports: [CommonModule, FormsModule, RouterLink, BadgeModule, ButtonModule, CardModule, FormModule, GridModule, SpinnerModule, TableModule],
  templateUrl: './publicacion-list.component.html',
})
export class PublicacionListComponent implements OnInit {
  productos: Producto[] = [];
  categorias: Categoria[] = [];
  cargando = false;

  // filtros
  search = '';
  categoriaFiltro: number | null = null;
  estadoPublicacionFiltro: string | null = null;

  // paginación
  page = 1;
  perPage = 10;
  totalPaginas = 1;
  total = 0;

  // Variantes desplegables (mismo patrón que list-producto): se piden
  // una sola vez por padre y se cachean hasta el próximo cargarProductos().
  filasExpandidas = new Set<number>();
  variantesPorFila = new Map<number, Producto[]>();
  cargandoVariantes = new Set<number>();

  private buscarTimeout?: ReturnType<typeof setTimeout>;

  constructor(
    private productoService: ProductoService,
    private catalogoService: CatalogoService,
    private toastr: ToastrService
  ) {}

  ngOnInit(): void {
    this.catalogoService.categorias().subscribe((data) => (this.categorias = data));
    this.cargarProductos();
  }

  /**
   * Con búsqueda o filtros activos se usa la lista PLANA: si una variante
   * coincide pero su padre no, en modo agrupado quedaría oculta.
   */
  get modoPlano(): boolean {
    return !!this.search.trim() || this.categoriaFiltro !== null || this.estadoPublicacionFiltro !== null;
  }

  cargarProductos(): void {
    this.cargando = true;
    this.filasExpandidas.clear();
    this.variantesPorFila.clear();

    this.productoService
      .listar({
        search: this.search,
        categoria: this.categoriaFiltro,
        estado_publicacion: this.estadoPublicacionFiltro,
        incluir_variantes: this.modoPlano,
        repuesto: 0, // los repuestos de taller no se publican en la tienda
        page: this.page,
        per_page: this.perPage,
      })
      .subscribe({
        next: (res) => {
          this.productos = res.data;
          this.totalPaginas = res.meta.last_page;
          this.total = res.meta.total;
          this.cargando = false;
        },
        error: () => {
          this.toastr.error('No se pudo cargar el listado de productos', 'Error');
          this.cargando = false;
        },
      });
  }

  toggleExpandir(p: Producto): void {
    const id = p.prd_id!;
    if (this.filasExpandidas.has(id)) {
      this.filasExpandidas.delete(id);
      return;
    }

    this.filasExpandidas.add(id);
    if (this.variantesPorFila.has(id)) {
      return;
    }

    this.cargandoVariantes.add(id);
    this.productoService.listar({ padre: id, per_page: 100 }).subscribe({
      next: (res) => {
        this.variantesPorFila.set(id, res.data);
        this.cargandoVariantes.delete(id);
      },
      error: () => {
        this.toastr.error('No se pudieron cargar las variantes', 'Error');
        this.cargandoVariantes.delete(id);
        this.filasExpandidas.delete(id);
      },
    });
  }

  onBuscarCambio(): void {
    clearTimeout(this.buscarTimeout);
    this.buscarTimeout = setTimeout(() => {
      this.page = 1;
      this.cargarProductos();
    }, 400);
  }

  onFiltroCambio(): void {
    this.page = 1;
    this.cargarProductos();
  }

  get paginasVisibles(): number[] {
    const ventana = 5;
    let inicio = Math.max(1, this.page - Math.floor(ventana / 2));
    const fin = Math.min(this.totalPaginas, inicio + ventana - 1);
    inicio = Math.max(1, fin - ventana + 1);
    return Array.from({ length: fin - inicio + 1 }, (_, i) => inicio + i);
  }

  cambiarPorPagina(): void {
    this.page = 1;
    this.cargarProductos();
  }

  cambiarPagina(nuevaPagina: number): void {
    if (nuevaPagina < 1 || nuevaPagina > this.totalPaginas) return;
    this.page = nuevaPagina;
    this.cargarProductos();
  }

  imagenPrincipal(producto: Producto): string | null {
    const ruta = producto.publicacion_web?.imagen_principal;
    return ruta ? `${environment.URL_BACKEND}/storage/${ruta}` : null;
  }

  badgeEstado(producto: Producto): { texto: string; color: string } {
    if (!producto.publicacion_web) return { texto: 'Sin publicar', color: 'warning' };
    switch (producto.publicacion_web.pub_estado) {
      case 1: return { texto: 'Publicado', color: 'success' };
      case 2: return { texto: 'Archivado', color: 'dark' };
      default: return { texto: 'Borrador', color: 'secondary' };
    }
  }
}