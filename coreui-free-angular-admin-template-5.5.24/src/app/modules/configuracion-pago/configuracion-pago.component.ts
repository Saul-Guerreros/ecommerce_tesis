import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, FormGroup, ReactiveFormsModule, Validators } from '@angular/forms';
import { ButtonModule, CardModule, FormModule, SpinnerModule } from '@coreui/angular';
import { ToastrService } from 'ngx-toastr';
import { ConfiguracionPagoService } from './service/configuracion-pago.service';

@Component({
  selector: 'app-configuracion-pago',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule, ButtonModule, CardModule, FormModule, SpinnerModule],
  templateUrl: './configuracion-pago.component.html',
})
export class ConfiguracionPagoComponent implements OnInit {
  form: FormGroup;
  cargando = true;
  guardando = false;

  /**
   * Frase fija de la descripción del método. Tiene su par en el backend
   * (PagoCheque::descripcionMetodo); si se cambia allá, cambiarla acá.
   */
  private readonly descripcionCheque = 'Pagá tu compra online con cheque emitido a nombre de';

  constructor(
    private fb: FormBuilder,
    private service: ConfiguracionPagoService,
    private toastr: ToastrService,
  ) {
    this.form = this.fb.group({
      ctn_pago_banco: ['', Validators.maxLength(80)],
      ctn_pago_banco_titular: ['', Validators.maxLength(150)],
      ctn_pago_banco_cuenta: ['', Validators.maxLength(40)],
      ctn_pago_tigo_titular: ['', Validators.maxLength(150)],
      ctn_pago_tigo_numero: ['', Validators.maxLength(20)],
      ctn_pago_tigo_referencia: ['', Validators.maxLength(60)],
      ctn_pago_efectivo_mensaje: ['', Validators.maxLength(200)],

      // Cheque: textos
      ctn_pago_cheque_titular: ['', Validators.maxLength(150)],
      ctn_pago_cheque_mensaje: ['', Validators.maxLength(300)],
      ctn_pago_cheque_aviso: ['', Validators.maxLength(400)],

      // Cheque: tipos permitidos y modalidades de entrega en las que se
      // acepta. Los valores por defecto replican los de la migración.
      ctn_pago_cheque_vista: [true],
      ctn_pago_cheque_diferido: [true],
      ctn_pago_cheque_retiro: [true],
      ctn_pago_cheque_envio: [false],

      // Momento en que el cheque descuenta stock real. El backend valida
      // 'in:RECEPCION,ACREDITACION', así que el control no necesita más
      // que su valor por defecto: la pantalla solo ofrece esas dos.
      ctn_cheque_stock: ['RECEPCION'],
    });
  }

  ngOnInit(): void {
    this.cargar();
  }

  private cargar(): void {
    this.cargando = true;
    this.service.getConfig().subscribe({
      next: (data) => {
        this.form.patchValue(data);
        this.cargando = false;
      },
      error: () => {
        this.toastr.error('No se pudo cargar los datos de pago', 'Error');
        this.cargando = false;
      },
    });
  }

  campoInvalido(campo: string): boolean {
    const c = this.form.get(campo);
    return !!c && c.invalid && (c.touched || c.dirty);
  }

  errorBackend(campo: string): string | null {
    return this.form.get(campo)?.errors?.['backend'] ?? null;
  }

  // ---------- Cheque: reglas de "al menos uno" ----------

  /** Ningún tipo marcado: el cliente no tendría qué elegir al pagar con cheque. */
  get sinTipoCheque(): boolean {
    return !this.form.value.ctn_pago_cheque_vista && !this.form.value.ctn_pago_cheque_diferido;
  }

  /** Ninguna modalidad habilitada: el cheque no se podría usar en ningún pedido. */
  get sinModalidadCheque(): boolean {
    return !this.form.value.ctn_pago_cheque_retiro && !this.form.value.ctn_pago_cheque_envio;
  }

  /**
   * Política vigente en la pantalla. Solo decide qué aviso se muestra: el
   * efecto real lo aplica la API (ChequeGestionService y PedidoEstadoService).
   */
  get descuentaEnRecepcion(): boolean {
    return this.form.value.ctn_cheque_stock === 'RECEPCION';
  }

  /** Frase que verá el cliente, armada con el titular que se está escribiendo. */
  get descripcionChequeVistaPrevia(): string {
    const titular = (this.form.get('ctn_pago_cheque_titular')?.value ?? '').trim();
    return titular ? `${this.descripcionCheque} ${titular}.` : 'Pagá tu compra online con cheque.';
  }

  guardar(): void {
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }

    // Las dos reglas ya se ven en pantalla mientras se cumplen; acá se
    // impide el guardado. El backend las vuelve a exigir.
    if (this.sinTipoCheque || this.sinModalidadCheque) {
      this.toastr.error('Revise la configuración del cheque', 'Validación');
      return;
    }

    this.guardando = true;
    this.service.update(this.form.value).subscribe({
      next: (res) => {
        this.toastr.success(res?.message ?? 'Datos de pago actualizados.', 'Éxito');
        this.form.markAsPristine();
        this.guardando = false;
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
        this.toastr.error(err?.error?.message ?? 'No se pudo guardar', 'Error');
      },
    });
  }
}