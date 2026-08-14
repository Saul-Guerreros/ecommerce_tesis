import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ReactiveFormsModule, FormBuilder, FormGroup, Validators } from '@angular/forms';
import { ActivatedRoute, Router } from '@angular/router';
import { ToastrService } from 'ngx-toastr';
import { ButtonModule, CardModule, FormModule } from '@coreui/angular';
import { BrandsService } from '../service/brands.service';

@Component({
  selector: 'app-edit-brand',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule, ButtonModule, CardModule, FormModule],
  templateUrl: './edit-brand.component.html',
  styleUrls: ['./edit-brand.component.scss'],
})
export class EditBrandComponent implements OnInit {
  form: FormGroup;
  marcaId!: number;
  guardando = false;
  cargando = true;

  constructor(
    private fb: FormBuilder,
    private brandsService: BrandsService,
    private toastr: ToastrService,
    private route: ActivatedRoute,
    private router: Router
  ) {
    this.form = this.fb.group({
      mar_descri: ['', [Validators.required, Validators.maxLength(50)]],
    });
  }

  ngOnInit(): void {
    this.marcaId = Number(this.route.snapshot.paramMap.get('id'));
    this.cargarMarca();
  }

  cargarMarca(): void {
    this.brandsService.getById(this.marcaId).subscribe({
      next: (res) => {
        this.form.patchValue({
          mar_descri: res.mar_descri,
        });
        this.cargando = false;
      },
      error: () => {
        this.toastr.error('No se pudo cargar la marca', 'Error');
        this.router.navigate(['/brands']);
      },
    });
  }

  guardar(): void {
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }

    this.guardando = true;
    this.brandsService.update(this.marcaId, this.form.value).subscribe({
      next: () => {
        this.toastr.success('Marca actualizada correctamente', 'Éxito');
        this.router.navigate(['/brands']);
      },
      error: (err) => {
        const msg = err?.error?.message || 'No se pudo actualizar la marca';
        this.toastr.error(msg, 'Error');
        this.guardando = false;
      },
    });
  }

  cancelar(): void {
    this.router.navigate(['/brands']);
  }
}