import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ToastrService } from 'ngx-toastr';
import { Subject, debounceTime, distinctUntilChanged } from 'rxjs';
import { BadgeModule, ButtonModule, CardModule, FormModule, SpinnerModule, TableModule } from '@coreui/angular';
import { Producto, MarcaOption } from '../models/producto.model';
import { ProductosService } from '../service/productos.service';
import { FormProductoComponent } from '../form-producto/form-producto.component';
import { DeleteProductoComponent } from '../delete-producto/delete-producto.component';

@Component({
  selector: 'app-list-producto',
  standalone: true,
  imports: [CommonModule, FormsModule, CardModule, TableModule, ButtonModule, BadgeModule, SpinnerModule, FormModule,
    FormProductoComponent, DeleteProductoComponent],
  templateUrl: './list-producto.component.html',
})
export class ListProductoComponent implements OnInit {
  productos: Producto[] = [];
  marcas: MarcaOption[] = [];
  cargando = false;

  search = '';
  filtroMarca: number | null = null;
  filtroEstado = '';

  private searchSubject = new Subject<string>();

  formModalVisible = false;
  soloLectura = false;
  deleteModalVisible = false;
  productoSeleccionado: Producto | null = null;

  constructor(private service: ProductosService, private toastr: ToastrService) {}

  ngOnInit(): void {
    this.cargar();

    this.service.getConfig().subscribe({
      next: (res) => { this.marcas = res.marcas; },
      error: () => {},
    });

    this.searchSubject.pipe(debounceTime(400), distinctUntilChanged()).subscribe(() => this.cargar());
  }

  onSearchChange(): void {
    this.searchSubject.next(this.search);
  }

  cargar(): void {
    this.cargando = true;
    this.service.getAll({ search: this.search, marca: this.filtroMarca, estado: this.filtroEstado }).subscribe({
      next: (res) => { this.productos = res; this.cargando = false; },
      error: () => { this.toastr.error('No se pudieron cargar los productos', 'Error'); this.cargando = false; },
    });
  }

  limpiarFiltros(): void {
    this.search = '';
    this.filtroMarca = null;
    this.filtroEstado = '';
    this.cargar();
  }

  abrirNuevo(): void {
    this.productoSeleccionado = null;
    this.soloLectura = false;
    this.formModalVisible = true;
  }

  abrirVer(p: Producto): void {
    this.productoSeleccionado = { ...p };
    this.soloLectura = true;
    this.formModalVisible = true;
  }

  abrirEditar(p: Producto): void {
    this.productoSeleccionado = { ...p };
    this.soloLectura = false;
    this.formModalVisible = true;
  }

  abrirEliminar(p: Producto): void {
    this.productoSeleccionado = p;
    this.deleteModalVisible = true;
  }

  confirmarEliminar(): void {
    if (!this.productoSeleccionado?.prd_id) return;
    this.service.delete(this.productoSeleccionado.prd_id).subscribe({
      next: (res) => { this.toastr.success(res?.message ?? 'Producto eliminado', 'Éxito'); this.deleteModalVisible = false; this.cargar(); },
      error: (err) => { this.toastr.error(err?.error?.message ?? 'No se pudo eliminar', 'Error'); this.deleteModalVisible = false; },
    });
  }

  cambiarEstado(p: Producto): void {
    this.service.toggleEstado(p.prd_id!).subscribe({
      next: (res) => { this.toastr.success(res?.message ?? 'Estado actualizado', 'Éxito'); this.cargar(); },
      error: (err) => this.toastr.error(err?.error?.message ?? 'No se pudo cambiar el estado', 'Error'),
    });
  }
}