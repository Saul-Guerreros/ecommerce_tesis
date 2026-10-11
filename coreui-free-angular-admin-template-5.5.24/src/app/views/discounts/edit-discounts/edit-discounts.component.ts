import { CommonModule } from '@angular/common';
import { Component, OnInit } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { ActivatedRoute, Router, RouterLink } from '@angular/router';
import { ToastrService } from 'ngx-toastr';
import { ButtonModule, CardModule, FormModule, SpinnerModule, TableModule } from '@coreui/angular';
import { DiscountsService } from '../service/discounts.service';
import { CreateDiscountsComponent, fechaLocal } from '../create-discounts/create-discounts.component';

@Component({
  selector: 'app-edit-discounts',
  standalone: true,
  imports: [CommonModule, FormsModule, RouterLink, CardModule, ButtonModule, FormModule, TableModule, SpinnerModule],
  templateUrl: '../create-discounts/create-discounts.component.html',
})
export class EditDiscountsComponent extends CreateDiscountsComponent implements OnInit {
  override readonly esEdicion: boolean = true;
  DISCOUNT_ID!: number;

  constructor(
    discountService: DiscountsService,
    router: Router,
    toastr: ToastrService,
    private activedRoute: ActivatedRoute
  ) {
    super(discountService, router, toastr);
  }

  override ngOnInit(): void {
    super.ngOnInit();

    this.DISCOUNT_ID = Number(this.activedRoute.snapshot.params['id']);

    this.discountService.showDiscount(this.DISCOUNT_ID).subscribe({
      next: (resp: any) => {
        const d = resp.discount;
        this.type_discount = Number(d.type_discount);
        this.discount_type = Number(d.discount_type);
        this.discount = Number(d.discount);
        this.type_campaing = Number(d.type_campaing);
        this.state = Number(d.state);
        // Solo la parte de fecha por si el backend envía "YYYY-MM-DD HH:mm:ss"
        this.start_date = String(d.start_date ?? '').substring(0, 10);
        this.end_date = String(d.end_date ?? '').substring(0, 10);
        this.categories_add = d.categories ?? [];
        this.products_add = d.products ?? [];
        this.brands_add = d.brands ?? [];

        // Una campaña ya iniciada debe poder editarse sin mover su fecha de inicio
        const hoy = fechaLocal();
        this.minInicio = this.start_date && this.start_date < hoy ? this.start_date : hoy;
      },
      error: () => {
        this.toastr.error('No se pudo cargar la campaña', 'Error');
        this.router.navigate(['/discounts/list']);
      },
    });
  }

  override save(): void {
    const error = this.validar();
    if (error) {
      this.toastr.warning(error, 'Atención');
      return;
    }

    this.guardando = true;
    this.discountService.updateDiscounts(this.DISCOUNT_ID, { ...this.armarDatos(), state: this.state }).subscribe({
      next: (resp: any) => this.manejarRespuesta(resp, 'Campaña actualizada correctamente'),
      error: (err) => this.manejarError(err, 'No se pudo actualizar la campaña'),
    });
  }
}