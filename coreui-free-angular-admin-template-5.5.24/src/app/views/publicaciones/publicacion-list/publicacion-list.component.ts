import { CommonModule } from '@angular/common';
import { Component, OnInit } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { RouterLink } from '@angular/router';
import {
  BadgeModule,
  ButtonModule,
  CardModule,
  FormModule,
  GridModule,
  PaginationModule,
  SpinnerModule,
  TableModule,
} from '@coreui/angular';
import { environment } from '../../../../environments/environment';
import { Categoria, Producto } from '../../../core/models/producto.model';
import { CatalogoService } from '../../../core/services/catalogo.service';
import { ProductoService } from '../../../core/services/producto.service';

/**
 * Listado de "Publicación Web": se basa en TODOS los productos (no solo
 * los que ya tienen publicación), para poder entrar a cualquiera y
 * asignarle categoría, título web e imágenes por primera vez. Reutiliza
 * GET /api/productos (que ya trae publicacion_web embebida) en vez de
 * GET /api/publicaciones (que solo lista publicaciones ya creadas).
 */
@Component({
  selector: 'app-publicacion-list',
  standalone: true,
  imports: [
    CommonModule,
    FormsModule,
    RouterLink,
    BadgeModule,
    ButtonModule,
    CardModule,
    FormModule,
    GridModule,
    PaginationModule,
    SpinnerModule,
    TableModule,
  ],
  templateUrl: './publicacion-list.component.html',
})
export class PublicacionListComponent implements OnInit {
  productos: Producto[] = [];
  categorias: Categoria[] = [];

  cargando = false;
  error: string | null = null;

  // filtros
  search = '';
  categoriaFiltro: number | null = null;
  // 'sin_publicar' | 'borrador' | 'publicado' | null (todos)
  estadoPublicacionFiltro: string | null = null;

  // paginación
  page = 1;
  perPage = 10;
  totalPaginas = 1;
  total = 0;

  private buscarTimeout?: ReturnType<typeof setTimeout>;

  constructor(
    private productoService: ProductoService,
    private catalogoService: CatalogoService
  ) {}

  ngOnInit(): void {
    this.catalogoService.categorias().subscribe((data) => (this.categorias = data));
    this.cargarProductos();
  }

  cargarProductos(): void {
    this.cargando = true;
    this.error = null;

    this.productoService
      .listar({
        search: this.search,
        categoria: this.categoriaFiltro,
        estado_publicacion: this.estadoPublicacionFiltro,
        incluir_variantes: true,
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
          this.error = 'No se pudo cargar el listado de productos.';
          this.cargando = false;
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

  cambiarPagina(nuevaPagina: number): void {
    if (nuevaPagina < 1 || nuevaPagina > this.totalPaginas) {
      return;
    }
    this.page = nuevaPagina;
    this.cargarProductos();
  }

  imagenPrincipal(producto: Producto): string | null {
    const ruta = producto.publicacion_web?.imagen_principal;
    return ruta ? `${environment.URL_BACKEND}/storage/${ruta}` : null;
  }

  badgeEstado(producto: Producto): { texto: string; color: string } {
    if (!producto.publicacion_web) {
      return { texto: 'Sin publicar', color: 'warning' };
    }

    switch (producto.publicacion_web.pub_estado) {
      case 1:
        return { texto: 'Publicado', color: 'success' };
      case 2:
        return { texto: 'Archivado', color: 'dark' };
      default:
        return { texto: 'Borrador', color: 'secondary' };
    }
  }
}
