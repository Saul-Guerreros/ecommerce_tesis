import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Subject, debounceTime } from 'rxjs';
import {
  ButtonModule,
  CardModule,
  FormModule,
  SpinnerModule,
  TableModule,
} from '@coreui/angular';
import { ToastrService } from 'ngx-toastr';
import { ProductoCuota } from '../models/configuracion-cuota.model';
import { GestionCuotasService } from '../service/gestion-cuotas.service';
import { FormCuotasComponent } from '../form-cuotas/form-cuotas.component';

@Component({
  selector: 'app-list-producto-cuota',
  standalone: true,
  imports: [
    CommonModule,
    FormsModule,
    ButtonModule,
    CardModule,
    FormModule,
    SpinnerModule,
    TableModule,
    FormCuotasComponent,
  ],
  templateUrl: './list-producto-cuota.component.html',
})
export class ListProductoCuotaComponent implements OnInit {
  productos: ProductoCuota[] = [];
  cargando = false;

  search = '';
  soloConCuotas = false;

  formModalVisible = false;
  productoSeleccionado: ProductoCuota | null = null;

  private buscar$ = new Subject<void>();

  constructor(
    private service: GestionCuotasService,
    private toastr: ToastrService
  ) {
    this.buscar$.pipe(debounceTime(400)).subscribe(() => this.cargar());
  }

  ngOnInit(): void {
    this.cargar();
  }

  onSearchChange(): void {
    this.buscar$.next();
  }

  cargar(): void {
    this.cargando = true;

    this.service
      .getProductos({ search: this.search, soloConCuotas: this.soloConCuotas })
      .subscribe({
        next: (res) => {
          this.productos = res;
          this.cargando = false;
        },
        error: () => {
          this.toastr.error('No se pudieron cargar los productos', 'Error');
          this.cargando = false;
        },
      });
  }

  limpiarFiltros(): void {
    this.search = '';
    this.soloConCuotas = false;
    this.cargar();
  }

  /** Sin precio de venta no hay nada que financiar. */
  puedeConfigurar(producto: ProductoCuota): boolean {
    return producto.precio_contado !== null && producto.precio_contado > 0;
  }

  abrirConfiguracion(producto: ProductoCuota): void {
    if (!this.puedeConfigurar(producto)) {
      return;
    }

    this.productoSeleccionado = producto;
    this.formModalVisible = true;
  }
}