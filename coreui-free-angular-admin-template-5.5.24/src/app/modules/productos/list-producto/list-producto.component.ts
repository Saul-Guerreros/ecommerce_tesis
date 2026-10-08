import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ToastrService } from 'ngx-toastr';
import { Subject, debounceTime, distinctUntilChanged } from 'rxjs';
import { BadgeModule, ButtonModule, CardModule, FormModule, PaginationModule, SpinnerModule, TableModule } from '@coreui/angular';
import { Producto, VarianteResumen, MarcaOption } from '../models/producto.model';
import { ProductosService } from '../service/productos.service';
import { FormProductoComponent } from '../form-producto/form-producto.component';
import { DeleteProductoComponent } from '../delete-producto/delete-producto.component';

// Una fila de acción puede ser el producto completo (padre) o la versión
// liviana de una variante ya expandida (VarianteResumen) -- ambas traen
// prd_id/prd_codinv/prd_descri/prd_estado/usos_count, que es todo lo que
// Ver/Editar/Desactivar/Eliminar necesitan.
type FilaProducto = Producto | VarianteResumen;

@Component({
  selector: 'app-list-producto',
  standalone: true,
  imports: [CommonModule, FormsModule, CardModule, TableModule, ButtonModule, BadgeModule, SpinnerModule, FormModule,
    PaginationModule,   // ← sin esto, el paginador no toma estilos
    FormProductoComponent, DeleteProductoComponent],
  templateUrl: './list-producto.component.html',
})
export class ListProductoComponent implements OnInit {
  productos: Producto[] = [];
  marcas: MarcaOption[] = [];
  cargando = false;

  // Paginación
  currentPage = 1;
  lastPage = 1;
  total = 0;
  perPage = 10;
  desde = 0;
  hasta = 0;

  search = '';
  filtroMarca: number | null = null;
  filtroEstado = '';

  private searchSubject = new Subject<string>();

  formModalVisible = false;
  soloLectura = false;
  deleteModalVisible = false;
  // Ver/Editar siempre cargan el detalle completo antes de abrir (ver
  // abrirConDetalle) -- por eso este es Producto, no FilaProducto.
  productoSeleccionado: Producto | null = null;
  // Eliminar, en cambio, puede dispararse desde una fila de variante ya
  // expandida (VarianteResumen liviano) sin pedir el detalle completo.
  productoAEliminar: FilaProducto | null = null;

  // Listado colapsado: por fila de padre, si está expandida y sus variantes
  // (cacheadas la primera vez que se expande, para no repetir el pedido).
  filasExpandidas = new Set<number>();
  variantesPorFila = new Map<number, VarianteResumen[]>();
  cargandoVariantes = new Set<number>();

  constructor(private service: ProductosService, private toastr: ToastrService) {}

  ngOnInit(): void {
    this.cargar();
   
    this.service.getConfig().subscribe({
      next: (res) => { this.marcas = res.marcas; },
      error: () => {},
    });

    this.searchSubject.pipe(debounceTime(400), distinctUntilChanged()).subscribe(() => this.cargar(1));
  }

  onSearchChange(): void {
    this.searchSubject.next(this.search);
  }

  cargar(page: number = this.currentPage): void {
    this.cargando = true;
    this.filasExpandidas.clear();
    this.variantesPorFila.clear();

    this.service.getAll({ search: this.search, marca: this.filtroMarca, estado: this.filtroEstado }, page, this.perPage)
     .subscribe({
      next: (res) => {
        // Si se eliminó el último registro de la última página, retrocede una.
        if (res.data.length === 0 && res.meta.current_page > 1) {
          this.cargar(res.meta.current_page - 1);
          return;
        }
        this.productos   = res.data;
        this.currentPage = res.meta.current_page;
        this.lastPage    = res.meta.last_page;
        this.total       = res.meta.total;
        this.desde       = res.meta.from ?? 0;
        this.hasta       = res.meta.to ?? 0;
        this.cargando    = false;
      },
      error: () => { this.toastr.error('No se pudieron cargar los productos', 'Error'); this.cargando = false; },
    });
  }

  // Filtros y búsqueda siempre vuelven a la página 1
  filtrar(): void {
   this.cargar(1);
  }

 limpiarFiltros(): void {
  this.search = '';
  this.filtroMarca = null;
  this.filtroEstado = '';
  this.cargar(1);
 }

 cambiarPagina(page: number): void {
  if (page < 1 || page > this.lastPage || page === this.currentPage) return;
  this.cargar(page);
 }

 cambiarPorPagina(): void {
  this.cargar(1);
 }



  // Expandir/colapsar variantes de una fila (Producto padre). Se pide una
  // sola vez por fila y se cachea -- si se agregan variantes nuevas desde
  // el form, cargar() ya limpia el caché al recargar todo el listado.
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
    this.service.getOne(id).subscribe({
      next: (producto) => {
        this.variantesPorFila.set(id, producto.variantes ?? []);
        this.cargandoVariantes.delete(id);
      },
      error: () => {
        this.toastr.error('No se pudieron cargar las variantes', 'Error');
        this.cargandoVariantes.delete(id);
        this.filasExpandidas.delete(id);
      },
    });
  }

  abrirNuevo(): void {
    this.productoSeleccionado = null;
    this.soloLectura = false;
    this.formModalVisible = true;
  }

  // Ver/Editar piden el detalle completo (no solo la fila del listado o de
  // una variante expandida): ahí es donde vienen "padre" y "variantes".
  abrirVer(p: FilaProducto): void {
    this.soloLectura = true;
    this.abrirConDetalle(p.prd_id!);
  }

  abrirEditar(p: FilaProducto): void {
    this.soloLectura = false;
    this.abrirConDetalle(p.prd_id!);
  }

  // Ventana de máximo 5 páginas alrededor de la actual (evita 50 botones)
 get paginasVisibles(): number[] {
  const ventana = 5;
  let inicio = Math.max(1, this.currentPage - Math.floor(ventana / 2));
  const fin = Math.min(this.lastPage, inicio + ventana - 1);
  inicio = Math.max(1, fin - ventana + 1);
  return Array.from({ length: fin - inicio + 1 }, (_, i) => inicio + i);
 }

  private abrirConDetalle(id: number): void {
    this.service.getOne(id).subscribe({
      next: (producto) => {
        this.productoSeleccionado = producto;
        this.formModalVisible = true;
      },
      error: () => this.toastr.error('No se pudo cargar el producto', 'Error'),
    });
  }

  abrirEliminar(p: FilaProducto): void {
    this.productoAEliminar = p;
    this.deleteModalVisible = true;
  }

  confirmarEliminar(): void {
    if (!this.productoAEliminar?.prd_id) return;
    this.service.delete(this.productoAEliminar.prd_id).subscribe({
      next: (res) => { this.toastr.success(res?.message ?? 'Producto eliminado', 'Éxito'); this.deleteModalVisible = false; this.cargar(); },
      error: (err) => { this.toastr.error(err?.error?.message ?? 'No se pudo eliminar', 'Error'); this.deleteModalVisible = false; },
    });
  }

  cambiarEstado(p: FilaProducto): void {
    this.service.toggleEstado(p.prd_id!).subscribe({
      next: (res) => { this.toastr.success(res?.message ?? 'Estado actualizado', 'Éxito'); this.cargar(); },
      error: (err) => this.toastr.error(err?.error?.message ?? 'No se pudo cambiar el estado', 'Error'),
    });
  }
}
