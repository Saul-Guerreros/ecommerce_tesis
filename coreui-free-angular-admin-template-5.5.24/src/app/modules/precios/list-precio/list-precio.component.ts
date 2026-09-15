import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Subject, debounceTime } from 'rxjs';
import { ButtonModule, CardModule, FormModule, SpinnerModule, TableModule } from '@coreui/angular';
import { ToastrService } from 'ngx-toastr';
import { Precio } from '../models/precio.model';
import { PreciosService } from '../service/precios.service';
import { FormPrecioComponent } from '../form-precio/form-precio.component';

@Component({
  selector: 'app-list-precio',
  standalone: true,
  imports: [CommonModule, FormsModule, ButtonModule, CardModule, FormModule, SpinnerModule, TableModule, FormPrecioComponent],
  templateUrl: './list-precio.component.html',
})
export class ListPrecioComponent implements OnInit {
  precios: Precio[] = [];
  cargando = false;
  search = '';

  formModalVisible = false;
  precioSeleccionado: Precio | null = null;

  private buscar$ = new Subject<void>();

  constructor(private service: PreciosService, private toastr: ToastrService) {
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
    this.service.getAll({ search: this.search }).subscribe({
      next: (res) => { this.precios = res; this.cargando = false; },
      error: () => { this.toastr.error('No se pudieron cargar los precios', 'Error'); this.cargando = false; },
    });
  }

  abrirEditar(p: Precio): void {
    this.precioSeleccionado = p;
    this.formModalVisible = true;
  }
}