import { CommonModule } from '@angular/common';
import { Component, OnInit } from '@angular/core';
import { FormsModule } from '@angular/forms';
import {
  ButtonModule,
  BadgeModule,
  CardModule,
  FormModule,
  GridModule,
  PaginationModule,
  SpinnerModule,
  TableModule,
  ModalModule,
} from '@coreui/angular';
import { IconModule } from '@coreui/icons-angular';
import { CatalogoService } from '../../../core/services/catalogo.service';
import { ProductoService } from '../../../core/services/producto.service';
import { Categoria, Marca, Producto, TipoIva, UnidadMedida } from '../../../core/models/producto.model';
import { ProductoFormComponent } from '../producto-form/producto-form.component';
import { RouterLink } from '@angular/router';

@Component({
  selector: 'app-producto-list',
  standalone: true,
  imports: [
    CommonModule,
    FormsModule,
    ButtonModule,
    BadgeModule,
    CardModule,
    FormModule,
    GridModule,
    PaginationModule,
    SpinnerModule,
    TableModule,
    ModalModule,
    IconModule,
    ProductoFormComponent,
    RouterLink,
  ],
  templateUrl: './producto-list.component.html',
})
export class ProductoListComponent implements OnInit {
  productos: Producto[] = [];
  categorias: Categoria[] = [];
  marcas: Marca[] = [];
  tiposIva: TipoIva[] = [];
  unidadesMedida: UnidadMedida[] = [];

  cargando = false;
  error: string | null = null;

  // filtros
  search = '';
  categoriaFiltro: number | null = null;
  marcaFiltro: number | null = null;
  estadoFiltro: number | null = null;

  // paginación
  page = 1;
  perPage = 10;
  totalPaginas = 1;
  total = 0;

  // modal formulario
  mostrarFormulario = false;
  productoSeleccionado: Producto | null = null;

  // modal confirmación eliminar
  mostrarConfirmacion = false;
  productoAEliminar: Producto | null = null;

  private buscarTimeout?: ReturnType<typeof setTimeout>;

  constructor(
    private productoService: ProductoService,
    private catalogoService: CatalogoService
  ) {}

  ngOnInit(): void {
    this.catalogoService.categorias().subscribe((data) => (this.categorias = data));
    this.catalogoService.marcas().subscribe((data) => (this.marcas = data));
    this.catalogoService.tiposIva().subscribe((data) => (this.tiposIva = data));
    this.catalogoService.unidadesMedida().subscribe((data) => (this.unidadesMedida = data));
    this.cargarProductos();
  }

  cargarProductos(): void {
    this.cargando = true;
    this.error = null;

    this.productoService
      .listar({
        search: this.search,
        categoria: this.categoriaFiltro,
        marca: this.marcaFiltro,
        estado: this.estadoFiltro,
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

  abrirCrear(): void {
    this.productoSeleccionado = null;
    this.mostrarFormulario = true;
  }

  abrirEditar(producto: Producto): void {
    this.productoSeleccionado = producto;
    this.mostrarFormulario = true;
  }

  onGuardado(): void {
    this.mostrarFormulario = false;
    this.cargarProductos();
  }

  confirmarEliminar(producto: Producto): void {
    this.productoAEliminar = producto;
    this.mostrarConfirmacion = true;
  }

  eliminar(): void {
    if (!this.productoAEliminar?.prd_id) {
      return;
    }

    this.productoService.eliminar(this.productoAEliminar.prd_id).subscribe({
      next: () => {
        this.mostrarConfirmacion = false;
        this.productoAEliminar = null;
        this.cargarProductos();
      },
      error: () => {
        this.error = 'No se pudo eliminar el producto.';
        this.mostrarConfirmacion = false;
      },
    });
  }
  
	duplicarComoVariante(producto: Producto): void {
		this.productoSeleccionado = {...producto,prd_padre_id: producto.prd_padre_id ?? producto.prd_id,  prd_id: undefined,};
	this.mostrarFormulario = true;
	}

  /** Texto + color del badge de estado de PUBLICACIÓN (distinto del estado de inventario). */
  estadoPublicacion(producto: Producto): { texto: string; color: string } {
    if (!producto.publicacion_web) {
      return { texto: 'Sin publicar', color: 'secondary' };
    }

    switch (producto.publicacion_web.pub_estado) {
      case 1:
        return { texto: 'Publicado', color: 'success' };
      case 2:
        return { texto: 'Archivado', color: 'dark' };
      default:
        return { texto: 'Borrador', color: 'warning' };
    }
  }
}