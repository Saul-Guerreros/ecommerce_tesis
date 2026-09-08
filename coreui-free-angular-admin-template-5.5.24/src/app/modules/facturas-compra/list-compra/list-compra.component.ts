import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ToastrService } from 'ngx-toastr';
import { Subject, debounceTime, distinctUntilChanged } from 'rxjs';
import { BadgeModule, ButtonModule, CardModule, FormModule, ModalModule, SpinnerModule, TableModule } from '@coreui/angular';
import { Compra, ProveedorOption } from '../models/compra.model';
import { ComprasService } from '../service/compras.service';
import { FormCompraComponent } from '../form-compra/form-compra.component';

@Component({
  selector: 'app-list-compra',
  standalone: true,
  imports: [
    CommonModule, FormsModule, CardModule, TableModule, ButtonModule,
    BadgeModule, SpinnerModule, FormModule, ModalModule,
    FormCompraComponent,
  ],
  templateUrl: './list-compra.component.html',
})
export class ListCompraComponent implements OnInit {
  compras: Compra[] = [];
  proveedores: ProveedorOption[] = [];
  cargando = false;

  search = '';
  filtroProveedor: number | null = null;
  filtroEstado = '';

  private searchSubject = new Subject<string>();

  formModalVisible = false;
  soloLectura = false;
  compraSeleccionada: Compra | null = null;

  // confirmacion de anulacion (modal simple aparte, no dentro del form)
  anularModalVisible = false;
  compraAAnular: Compra | null = null;

  constructor(private service: ComprasService, private toastr: ToastrService) {}

  ngOnInit(): void {
    this.cargar();

    this.service.getConfig().subscribe({
      next: (res) => (this.proveedores = res.proveedores),
      error: () => {},
    });

    this.searchSubject.pipe(debounceTime(400), distinctUntilChanged()).subscribe(() => this.cargar());
  }

  onSearchChange(): void {
    this.searchSubject.next(this.search);
  }

  cargar(): void {
    this.cargando = true;
    this.service
      .getAll({ search: this.search, proveedor: this.filtroProveedor, estado: this.filtroEstado })
      .subscribe({
        next: (res) => { this.compras = res; this.cargando = false; },
        error: () => { this.toastr.error('No se pudieron cargar las compras', 'Error'); this.cargando = false; },
      });
  }

  limpiarFiltros(): void {
    this.search = '';
    this.filtroProveedor = null;
    this.filtroEstado = '';
    this.cargar();
  }

  abrirNuevo(): void {
    this.compraSeleccionada = null;
    this.soloLectura = false;
    this.formModalVisible = true;
  }

  abrirVer(c: Compra): void {
    this.cargando = true;
    this.service.getById(c.com_id!).subscribe({
      next: (compraCompleta) => {
        this.compraSeleccionada = compraCompleta;
        this.soloLectura = true;
        this.formModalVisible = true;
        this.cargando = false;
      },
      error: () => { this.toastr.error('No se pudo cargar el detalle de la compra', 'Error'); this.cargando = false; },
    });
  }

  // Se dispara desde el listado (boton directo) o desde el footer del
  // modal Ver (evento (anular) del form-compra) — misma accion en ambos casos.
  pedirAnular(c: Compra): void {
    this.compraAAnular = c;
    this.anularModalVisible = true;
  }

  cancelarAnular(): void {
    this.anularModalVisible = false;
    this.compraAAnular = null;
  }

  confirmarAnular(): void {
    if (!this.compraAAnular?.com_id) return;

    this.service.anular(this.compraAAnular.com_id).subscribe({
      next: (res) => {
        this.toastr.success(res?.message ?? 'Compra anulada', 'Éxito');
        this.anularModalVisible = false;
        this.formModalVisible = false; // por si vino del footer del modal Ver
        this.cargar();
      },
      error: (err) => {
        this.toastr.error(err?.error?.message ?? 'No se pudo anular la compra', 'Error');
        this.anularModalVisible = false;
      },
    });
  }
}