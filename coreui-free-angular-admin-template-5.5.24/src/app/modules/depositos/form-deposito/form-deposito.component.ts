import { Component, EventEmitter, Input, Output, OnInit, OnChanges, SimpleChanges } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ReactiveFormsModule, FormBuilder, FormGroup, Validators } from '@angular/forms';
import { ToastrService } from 'ngx-toastr';
import { ButtonModule, FormModule, ModalModule, SpinnerModule } from '@coreui/angular';
import { Deposito, SucursalOption } from '../models/deposito.model';
import { DepositosService } from '../service/depositos.service';

@Component({
  selector: 'app-form-deposito',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule, ButtonModule, FormModule, ModalModule, SpinnerModule],
  templateUrl: './form-deposito.component.html',
})
export class FormDepositoComponent implements OnInit, OnChanges {
  @Input() visible = false;
  @Input() deposito: Deposito | null = null;

  @Output() visibleChange = new EventEmitter<boolean>();
  @Output() guardado = new EventEmitter<void>();

  form: FormGroup;
  guardando = false;
  sucursales: SucursalOption[] = [];

  constructor(
    private fb: FormBuilder,
    private depositosService: DepositosService,
    private toastr: ToastrService
  ) {
    this.form = this.fb.group({
      dpo_sucid:  [null, [Validators.required]],
      dpo_codigo: ['', [Validators.required, Validators.maxLength(10)]],
      dpo_nombre: ['', [Validators.required, Validators.maxLength(45)]],
      dpo_descri: ['', [Validators.maxLength(45)]],
      dpo_princi: [0],
      dpo_estado: [1],
    });
  }

  ngOnInit(): void {
    this.depositosService.getSucursales().subscribe({
      next: (res) => (this.sucursales = res),
      error: () => this.toastr.error('No se pudieron cargar las sucursales', 'Error'),
    });
  }

  get esEdicion(): boolean {
    return !!this.deposito?.dpo_id;
  }

  // El principal actual no puede desmarcarse desde acá: hay que designar otro.
  get bloquearPrincipal(): boolean {
    return this.esEdicion && this.deposito?.dpo_princi === 1;
  }

  ngOnChanges(changes: SimpleChanges): void {
    if (changes['visible'] && this.visible) {
      this.form.reset({
        dpo_sucid:  this.deposito?.dpo_sucid ?? (this.sucursales.length === 1 ? this.sucursales[0].suc_id : null),
        dpo_codigo: this.deposito?.dpo_codigo ?? '',
        dpo_nombre: this.deposito?.dpo_nombre ?? '',
        dpo_descri: this.deposito?.dpo_descri ?? '',
        dpo_princi: this.deposito?.dpo_princi ?? 0,
        dpo_estado: this.deposito?.dpo_estado ?? 1,
      });
      this.guardando = false;
    }
  }

  campoInvalido(campo: string): boolean {
    const c = this.form.get(campo);
    return !!c && c.invalid && (c.touched || c.dirty);
  }

  errorBackend(campo: string): string | null {
    return this.form.get(campo)?.errors?.['backend'] ?? null;
  }

  guardar(): void {
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }

    this.guardando = true;

    const datos: Partial<Deposito> = {
      ...this.form.value,
      dpo_descri: this.form.value.dpo_descri || null,
    };

    const peticion = this.esEdicion
      ? this.depositosService.update(this.deposito!.dpo_id!, datos)
      : this.depositosService.create(datos);

    peticion.subscribe({
      next: (res) => {
        this.toastr.success(res?.message ?? 'Operación realizada', 'Éxito');
        this.guardando = false;
        this.guardado.emit();
        this.cerrar();
      },
      error: (err) => {
        this.guardando = false;

        if (err.status === 422 && err.error?.errors) {
          Object.keys(err.error.errors).forEach((campo) => {
            this.form.get(campo)?.setErrors({ backend: err.error.errors[campo][0] });
            this.form.get(campo)?.markAsTouched();
          });
          this.toastr.error('Revise los datos ingresados', 'Validación');
          return;
        }

        this.toastr.error(err?.error?.message ?? 'No se pudo guardar el depósito', 'Error');
      },
    });
  }

  cerrar(): void {
    this.visible = false;
    this.visibleChange.emit(false);
  }
}