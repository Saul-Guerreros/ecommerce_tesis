import { CommonModule } from '@angular/common';
import { Component, OnInit } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { ActivatedRoute, Router, RouterLink } from '@angular/router';
import { ToastrService } from 'ngx-toastr';
import { ButtonModule, CardModule, FormModule, SpinnerModule, TableModule } from '@coreui/angular';
import { CuponesService } from '../service/cupones.service';
import { CreateCuponesComponent } from '../create-cupones/create-cupones.component';

@Component({
  selector: 'app-edit-cupones',
  standalone: true,
  imports: [CommonModule, FormsModule, RouterLink, CardModule, ButtonModule, FormModule, TableModule, SpinnerModule],
  // Mismo template que create: el título y el campo Estado cambian según esEdicion
  templateUrl: '../create-cupones/create-cupones.component.html',
})
export class EditCuponesComponent extends CreateCuponesComponent implements OnInit {
  override readonly esEdicion = true;
  CUPONE_ID!: number;
  
  constructor(
    cuponesService: CuponesService,
    private routerEdit: Router,
    private toastrEdit: ToastrService,
    private activedRoute: ActivatedRoute
  ) {
    super(cuponesService, routerEdit, toastrEdit);
  }

  override ngOnInit(): void {
    super.ngOnInit(); // carga combos (productos, categorías, marcas)

    this.CUPONE_ID = Number(this.activedRoute.snapshot.params['id']);

    this.cuponesService.showCupone(this.CUPONE_ID).subscribe({
      next: (resp: any) => {
        const c = resp.cupone;
        this.code = c.code;
        this.type_discount = Number(c.type_discount);
        this.discount = Number(c.discount);
        this.type_count = Number(c.type_count);
        this.num_use = c.num_use;
        this.type_cupone = Number(c.type_cupone);
        this.state = Number(c.state);
        this.categories_add = c.categories ?? [];
        this.products_add = c.products ?? [];
        this.brands_add = c.brands ?? [];
      },
      error: () => {
        this.toastrEdit.error('No se pudo cargar el cupón', 'Error');
        this.routerEdit.navigate(['/cupones/list']);
      },
    });
  }

  override save(): void {
    if (!this.code?.trim() || !this.discount) {
      this.toastrEdit.warning('Completa el código y el descuento', 'Atención');
      return;
    }
    if (this.type_discount == 1 && this.discount > 100) {
      this.toastrEdit.warning('El porcentaje no puede superar 100', 'Atención');
      return;
    }
    if (this.type_count == 2 && !this.num_use) {
      this.toastrEdit.warning('Indica la cantidad de usos del cupón', 'Atención');
      return;
    }
    if (this.type_cupone == 1 && this.products_add.length == 0) {
      this.toastrEdit.warning('Agrega uno o varios productos', 'Atención');
      return;
    }
    if (this.type_cupone == 2 && this.categories_add.length == 0) {
      this.toastrEdit.warning('Agrega una o varias categorías', 'Atención');
      return;
    }
    if (this.type_cupone == 3 && this.brands_add.length == 0) {
      this.toastrEdit.warning('Agrega una o varias marcas', 'Atención');
      return;
    }

    const data = {
      type_discount: this.type_discount,
      type_count: this.type_count,
      type_cupone: this.type_cupone,
      num_use: this.type_count == 2 ? this.num_use : 0,
      discount: this.discount,
      code: this.code.trim(),
      product_selected: this.products_add,
      categorie_selected: this.categories_add,
      brand_selected: this.brands_add,
      state: this.state,
    };

    this.guardando = true;
    this.cuponesService.updateCupones(this.CUPONE_ID, data).subscribe({
      next: (resp: any) => {
        this.guardando = false;
        if (resp?.message == 403) {
          this.toastrEdit.error(resp.message_text ?? 'No se pudo actualizar el cupón', 'Error');
          return;
        }
        this.toastrEdit.success('Cupón actualizado correctamente', 'Éxito');
        this.routerEdit.navigate(['/cupones/list']); // ← faltaba la redirección
      },
      error: (err) => {
        this.guardando = false;
        const msg = err?.error?.message_text ?? err?.error?.message ?? 'No se pudo actualizar el cupón';
        this.toastrEdit.error(msg, 'Error');
      },
    });
  }
}