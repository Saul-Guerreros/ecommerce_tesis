import { Component } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ReactiveFormsModule, FormBuilder, FormGroup, Validators } from '@angular/forms';
import { Router } from '@angular/router';
import { ToastrService } from 'ngx-toastr';
import { ButtonModule, CardModule, FormModule } from '@coreui/angular';
import { BrandsService } from '../service/brands.service';

@Component({
  selector: 'app-create-brand',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule, ButtonModule, CardModule, FormModule],
  templateUrl: './create-brand.component.html',
  styleUrls: ['./create-brand.component.scss'],
})
export class CreateBrandComponent {
  form: FormGroup;
  guardando = false;

  constructor(
    private fb: FormBuilder,
    private brandsService: BrandsService,
    private toastr: ToastrService,
    private router: Router
  ) {
    this.form = this.fb.group({
      mar_descri: ['', [Validators.required, Validators.maxLength(50)]],
    });
  }

  guardar(): void {
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }

    this.guardando = true;
    this.brandsService.create(this.form.value).subscribe({
      next: () => {
        this.toastr.success('Marca creada correctamente', 'Éxito');
        this.router.navigate(['/brands']);
      },
      error: (err) => {
        const msg = err?.error?.message || 'No se pudo crear la marca';
        this.toastr.error(msg, 'Error');
        this.guardando = false;
      },
    });
  }

  cancelar(): void {
    this.router.navigate(['/brands']);
  }
}