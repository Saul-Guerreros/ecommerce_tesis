import { CommonModule, CurrencyPipe } from '@angular/common';
import { Component, DestroyRef, OnInit, inject } from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { FormBuilder, FormGroup, ReactiveFormsModule, Validators } from '@angular/forms';
import { Router, RouterModule } from '@angular/router';
import { ToastrService } from 'ngx-toastr';
import {
  ConfigEnvio, ModalidadEntrega, SucursalRetiro, TipoReceptor, TitularCheckout,
} from '../../../core/models/checkout.model';
import { Direccion } from '../../../core/models/direccion.model';
import { CheckoutService } from '../../../core/services/checkout.service';
import { DireccionClienteService } from '../../../core/services/direccion-cliente.service';
import { FormDireccionComponent } from '../form-direccion/form-direccion.component';

@Component({
  selector: 'app-paso-entrega',
  standalone: true,
  imports: [CommonModule, CurrencyPipe, ReactiveFormsModule, RouterModule, FormDireccionComponent],
  templateUrl: './paso-entrega.component.html',
  styleUrl: './paso-entrega.component.css',
})
export class PasoEntregaComponent implements OnInit {

  private readonly destroyRef = inject(DestroyRef);

  form!: FormGroup;

  sucursales: SucursalRetiro[] = [];
  direcciones: Direccion[] = [];
  titular!: TitularCheckout;
  envio!: ConfigEnvio;

  cargando = true;
  guardando = false;
  modalDireccion = false;
  direccionAEditar: Direccion | null = null;   // <-- nuevo

  /** dir_id en curso de borrado: desactiva su ícono y evita doble clic. */
  eliminandoId: number | null = null;

  constructor(
    private fb: FormBuilder,
    private checkoutService: CheckoutService,
    private direccionService: DireccionClienteService,
    private toastr: ToastrService,
    private router: Router,
  ) {}

  ngOnInit(): void {
    this.construirFormulario();
    this.cargar();
  }

  private construirFormulario(): void {
    this.form = this.fb.group({
      modalidad:  ['retiro' as ModalidadEntrega, [Validators.required]],
      receptor:   ['titular' as TipoReceptor, [Validators.required]],
      suc_id:     [null as number | null],
      dir_id:     [null as number | null],
      rec_nrodoc: [''],
      rec_nombre: [''],
      rec_apelli: [''],
      rec_telef:  [''],
    });

    this.form.get('modalidad')!.valueChanges
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe(() => this.ajustarValidadores());

    this.form.get('receptor')!.valueChanges
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe(() => this.ajustarValidadores());
  }

  /**
   * Lee el valor de un control directamente y NO desde this.form.value.
   *
   * Angular emite el valueChanges del control hijo ANTES de que el
   * FormGroup padre recalcule su propiedad .value. Dentro de una
   * suscripcion a un hijo, this.form.value todavia tiene el valor
   * anterior; el control en cambio ya esta actualizado.
   */
  private valorDe(campo: string): any {
    return this.form.get(campo)!.value;
  }

  private ajustarValidadores(): void {
    const esRetiro  = this.valorDe('modalidad') === 'retiro';
    const esTercero = this.valorDe('receptor') === 'tercero';

    this.aplicar('suc_id', esRetiro ? [Validators.required] : []);
    this.aplicar('dir_id', !esRetiro ? [Validators.required] : []);

    const exigeCedula = esTercero || !this.titular?.rec_nrodoc;
    this.aplicar('rec_nrodoc', exigeCedula
      ? [Validators.required, Validators.pattern(/^[0-9]{5,15}$/)]
      : []);

    const deTercero = esTercero ? [Validators.required] : [];
    this.aplicar('rec_nombre', deTercero);
    this.aplicar('rec_apelli', deTercero);
    this.aplicar('rec_telef',  deTercero);

    if (!esTercero) {
      this.form.patchValue({
        rec_nombre: this.titular?.rec_nombre ?? '',
        rec_apelli: this.titular?.rec_apelli ?? '',
        rec_telef:  this.titular?.rec_telef ?? '',
        rec_nrodoc: this.titular?.rec_nrodoc ?? '',
      }, { emitEvent: false });
    }
  }

  private aplicar(campo: string, validadores: any[]): void {
    const control = this.form.get(campo)!;
    control.setValidators(validadores);
    control.updateValueAndValidity({ emitEvent: false });
  }

  private cargar(): void {
    this.cargando = true;

    this.checkoutService.configEntrega().subscribe({
      next: (resp) => {
        if (resp.carrito_vacio) {
          // cargando queda en true: evita el parpadeo del formulario
          // antes de redirigir.
          this.toastr.info('Tu carrito está vacío.', 'Checkout');
          this.router.navigate(['/'], { replaceUrl: true });
          return;
        }

        this.sucursales  = resp.sucursales;
        this.direcciones = resp.direcciones;
        this.titular     = resp.titular;
        this.envio       = resp.envio;

        if (resp.entrega) {
          this.form.patchValue({
            modalidad:  resp.entrega.modalidad,
            receptor:   resp.entrega.receptor,
            suc_id:     resp.entrega.suc_id,
            dir_id:     resp.entrega.dir_id,
            rec_nrodoc: resp.entrega.rec_nrodoc,
            rec_nombre: resp.entrega.rec_nombre,
            rec_apelli: resp.entrega.rec_apelli,
            rec_telef:  resp.entrega.rec_telef,
          }, { emitEvent: false });
        } else {
          this.form.patchValue(
            { dir_id: this.direcciones.find((d) => d.dir_predet)?.dir_id ?? null },
            { emitEvent: false },
          );
        }

        this.ajustarValidadores();
        this.cargando = false;
      },
      error: () => {
        this.cargando = false;
        this.toastr.error('No pudimos cargar tus opciones de entrega.', 'Error');
        this.router.navigate(['/carrito-de-compra']);
      },
    });
  }

  get esRetiro(): boolean  { return this.valorDe('modalidad') === 'retiro'; }
  get esTercero(): boolean { return this.valorDe('receptor') === 'tercero'; }

  get titularCompleto(): boolean {
    return !this.esTercero && !!this.titular?.rec_nrodoc;
  }

  /**
   * El costo ya se conoce desde que se carga la página (gratis/fijo no
   * dependen de la dirección elegida). Cuando se implemente el modo
   * "sucursal" (Parte 2 del checkout), ahí sí va a depender de la ciudad
   * de destino y va a necesitar esperar a que haya un dir_id elegido.
   */
  get costoEnvio(): number {
    return this.esRetiro ? 0 : this.envio.costo;
  }

  get mostrarCostoEnvio(): boolean {
    return !this.esRetiro;
  }

  get total(): number {
    return this.envio.subtotal + this.costoEnvio;
  }

  /** La dirección actualmente elegida en el radio-group, o null si no hay ninguna. */
  get direccionSeleccionada(): Direccion | null {
    return this.direcciones.find((d) => d.dir_id === this.valorDe('dir_id')) ?? null;
  }

  campoInvalido(campo: string): boolean {
    const c = this.form.get(campo);
    return !!c && c.invalid && (c.dirty || c.touched);
  }

  errorBackend(campo: string): string | null {
    return this.form.get(campo)?.errors?.['backend'] ?? null;
  }

  elegirModalidad(modalidad: ModalidadEntrega): void {
    this.form.patchValue({ modalidad });
  }

  elegirReceptor(receptor: TipoReceptor): void {
    if (receptor === 'tercero') {
      this.form.patchValue(
        { rec_nrodoc: '', rec_nombre: '', rec_apelli: '', rec_telef: '' },
        { emitEvent: false },
      );
      ['rec_nrodoc', 'rec_nombre', 'rec_apelli', 'rec_telef'].forEach((campo) => {
        this.form.get(campo)?.markAsPristine();
        this.form.get(campo)?.markAsUntouched();
      });
    }
    this.form.patchValue({ receptor });
  }

  elegirSucursal(suc_id: number): void { this.form.patchValue({ suc_id }); }
  elegirDireccion(dir_id: number): void { this.form.patchValue({ dir_id }); }

  abrirAgregarDireccion(): void {
    this.direccionAEditar = null;
    this.modalDireccion = true;
  }

  abrirEditarDireccion(): void {
    if (!this.direccionSeleccionada) return;
    this.direccionAEditar = this.direccionSeleccionada;
    this.modalDireccion = true;
  }

  /**
   * El ícono vive dentro del <label> del radio: sin stopPropagation el
   * clic burbujea y ANTES de borrar selecciona la dirección, dejando
   * elegido algo que esta por desaparecer.
   */
  eliminarDireccion(direccion: Direccion, evento: Event): void {
    evento.stopPropagation();
    evento.preventDefault();

    if (this.eliminandoId !== null) {
      return;
    }

    if (!confirm(`¿Eliminar la dirección "${direccion.dir_alias}"?`)) {
      return;
    }

    this.eliminandoId = direccion.dir_id;

    this.direccionService.eliminar(direccion.dir_id).subscribe({
      next: (resp) => {
        this.eliminandoId = null;
        this.toastr.success(resp.message, 'Direcciones');

        this.direcciones = this.direcciones.filter((d) => d.dir_id !== direccion.dir_id);

        // Si se borro la que estaba elegida en el radio-group, hay que
        // reasignar: dejar dir_id apuntando a una fila inexistente
        // rompe el paso 2 (el backend hace findOrFail sobre ese id).
        if (this.valorDe('dir_id') === direccion.dir_id) {
          const reemplazo = this.direcciones.find((d) => d.dir_predet) ?? this.direcciones[0] ?? null;
          this.form.patchValue({ dir_id: reemplazo?.dir_id ?? null });
        }

        // El backend promueve otra a predeterminada cuando se borra la
        // que lo era. Se refleja en memoria para no pedir la lista de nuevo.
        if (direccion.dir_predet && this.direcciones.length > 0) {
          this.direcciones = this.direcciones.map((d, i) => ({ ...d, dir_predet: i === 0 }));
        }
      },
      error: (e) => {
        this.eliminandoId = null;
        this.toastr.error(
          e.error?.message ?? 'No pudimos eliminar la dirección.',
          'Error',
        );
      },
    });
  }

  direccionGuardada(direccion: Direccion): void {
    const esEdicion = this.direcciones.some((d) => d.dir_id === direccion.dir_id);

    if (direccion.dir_predet) {
      this.direcciones = this.direcciones.map((d) => ({ ...d, dir_predet: false }));
    }

    this.direcciones = esEdicion
      ? this.direcciones.map((d) => (d.dir_id === direccion.dir_id ? direccion : d))
      : [direccion, ...this.direcciones];

    this.form.patchValue({ dir_id: direccion.dir_id });
    this.modalDireccion = false;
    this.direccionAEditar = null;
  }

  continuar(): void {
    this.form.markAllAsTouched();

    if (this.form.invalid || this.guardando) {
      return;
    }

    this.guardando = true;
    const v = this.form.value;

    this.checkoutService.guardarEntrega({
      modalidad:  v.modalidad,
      receptor:   v.receptor,
      suc_id:     this.esRetiro ? v.suc_id : null,
      dir_id:     this.esRetiro ? null : v.dir_id,
      rec_nrodoc: this.titularCompleto ? null : v.rec_nrodoc,
      rec_nombre: this.esTercero ? v.rec_nombre : null,
      rec_apelli: this.esTercero ? v.rec_apelli : null,
      rec_telef:  this.esTercero ? v.rec_telef : null,
    }).subscribe({
      next: () => {
        this.guardando = false;
        this.toastr.success('Datos de entrega guardados.', 'Listo');
        this.router.navigate(['/proceso-de-pago/facturacion'], { replaceUrl: true });
      },
      error: (e) => this.manejarError(e),
    });
  }

  volver(): void {
    this.router.navigate(['/carrito-de-compra']);
  }

  private manejarError(e: any): void {
    this.guardando = false;

    if (e.status === 422 && e.error?.errors) {
      Object.entries<string[]>(e.error.errors).forEach(([campo, mensajes]) => {
        this.form.get(campo)?.setErrors({ backend: mensajes[0] });
      });
      this.toastr.error('Revisá los datos marcados.', 'Validación');
      return;
    }

    this.toastr.error(e.error?.message ?? 'No pudimos guardar los datos de entrega.', 'Error');
  }
}