import { CommonModule } from '@angular/common';
import { Component, EventEmitter, Input, Output, SimpleChanges } from '@angular/core';
import {
  AbstractControl, FormBuilder, FormGroup, ReactiveFormsModule, ValidationErrors, ValidatorFn, Validators,
} from '@angular/forms';
import { ToastrService } from 'ngx-toastr';
import { TitularFacturacion } from '../../../core/models/checkout.model';
import { DatoFiscal } from '../../../core/models/dato-fiscal.model';
import { DatoFiscalClienteService, RucPropioResponse } from '../../../core/services/dato-fiscal-cliente.service';
import { PerfilClienteService } from '../../../core/services/perfil-cliente.service';

/**
 * RUC paraguayo: hasta 15 digitos, guion, y el digito verificador (un
 * solo digito, ej. "5690649-7"). Mismo patron que exige el backend
 * (DatoFiscalClienteRequest) y que cliente.cli_ruc en el perfil.
 */
const PATRON_NUMERO = /^[0-9]{1,15}-[0-9]$/;

/** Mismo patron que dir_celular/dir_telef en direcciones: digitos,
 *  espacios, guiones y un "+" inicial opcional. Nunca letras. */
const PATRON_TELEFONO = /^\+?[0-9\s\-]{6,20}$/;

@Component({
  selector: 'app-form-dato-fiscal',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule],
  templateUrl: './form-dato-fiscal.component.html',
})
export class FormDatoFiscalComponent {
  @Input() visible = false;
  @Input() datoFiscal: DatoFiscal | null = null;

  /**
   * RUCs ya guardados del cliente. Se usan para bloquear un alias
   * repetido sin esperar el 422 del backend, igual que en
   * form-direccion.component.ts.
   */
  @Input() datosFiscales: DatoFiscal[] = [];

  /**
   * Datos del titular de la cuenta (nombre, apellido, cedula). Hacen
   * falta para poder ofrecer "guardar este RUC en tu perfil": ese
   * endpoint reutiliza POST /tienda/perfil, que exige nombre y
   * apellido aunque lo unico que cambie sea el RUC.
   */
  @Input() titular: TitularFacturacion | null = null;

  @Output() visibleChange = new EventEmitter<boolean>();
  @Output() guardado = new EventEmitter<DatoFiscal>();

  form!: FormGroup;
  guardando = false;

  /**
   * Resultado de la ultima verificacion contra /verificar-propio.
   * null = todavia no se verifico nada (o el ultimo numero no era de
   * ella misma). Se usa para mostrar el aviso de autocompletado y,
   * cuando corresponde, el boton de "guardar en mi perfil".
   */
  rucPropio: RucPropioResponse | null = null;
  verificandoRuc = false;
  guardandoRucPerfil = false;

  get esEdicion(): boolean {
    return !!this.datoFiscal;
  }

  constructor(
    private fb: FormBuilder,
    private datoFiscalService: DatoFiscalClienteService,
    private perfilService: PerfilClienteService,
    private toastr: ToastrService,
  ) {
    this.form = this.fb.group({
      dfi_alias:    ['', [Validators.required, Validators.maxLength(50), this.aliasUnico()]],
      dfi_numero:   ['', [Validators.required, Validators.maxLength(20), Validators.pattern(PATRON_NUMERO)]],
      dfi_razon:    ['', [Validators.required, Validators.maxLength(150)]],
      dfi_telefono: ['', [Validators.required, Validators.maxLength(20), Validators.pattern(PATRON_TELEFONO)]],
      dfi_correo:   ['', [Validators.required, Validators.email, Validators.maxLength(100)]],
      dfi_principal: [false],
    });
  }

  ngOnChanges(cambios: SimpleChanges): void {
    if (!this.form) {
      return;
    }

    if (cambios['visible']?.currentValue === true) {
      this.guardando = false;
      this.rucPropio = null;
      this.guardandoRucPerfil = false;

      if (this.esEdicion) {
        this.form.reset({
          dfi_alias: this.datoFiscal!.dfi_alias,
          dfi_numero: this.datoFiscal!.dfi_numero,
          dfi_razon: this.datoFiscal!.dfi_razon,
          dfi_telefono: this.datoFiscal!.dfi_telefono,
          dfi_correo: this.datoFiscal!.dfi_correo,
          dfi_principal: this.datoFiscal!.dfi_principal,
        });
      } else {
        // Si todavia no tiene ningun RUC guardado, este va a quedar
        // predeterminado si o si (lo hace el backend solo): se muestra
        // tildado de entrada para que no sea una sorpresa.
        this.form.reset({ dfi_principal: this.datosFiscales.length === 0 });
      }
    }

    // La lista puede llegar o actualizarse despues de que el modal ya
    // esta abierto (carga asincrona del padre, u otro RUC guardado
    // mientras este modal seguia abierto). Se revalida sin marcar
    // touched para no mostrar un error que el usuario no provoco.
    if (cambios['datosFiscales'] && !cambios['datosFiscales'].firstChange) {
      this.form.get('dfi_alias')?.updateValueAndValidity();
    }
  }

  // ---------------------------------------------------------------------
  // Validación de alias único (mismo criterio que form-direccion)
  // ---------------------------------------------------------------------

  private normalizar(valor: unknown): string {
    return String(valor ?? '')
      .trim()
      .replace(/\s+/g, ' ')
      .toLowerCase()
      .normalize('NFD')
      .replace(/[\u0300-\u036f]/g, '');
  }

  private aliasUnico(): ValidatorFn {
    return (control: AbstractControl): ValidationErrors | null => {
      const objetivo = this.normalizar(control.value);

      if (!objetivo) {
        return null;
      }

      const duplicado = this.datosFiscales.some((d) =>
        d.dfi_id !== this.datoFiscal?.dfi_id && this.normalizar(d.dfi_alias) === objetivo);

      return duplicado ? { aliasDuplicado: true } : null;
    };
  }

  get mensajeAlias(): string {
    const errores = this.form.get('dfi_alias')?.errors ?? {};

    if (errores['backend'])        return errores['backend'];
    if (errores['aliasDuplicado'])  return 'Ya tenés un RUC guardado con ese nombre. Elegí otro.';
    if (errores['maxlength'])       return 'El nombre no puede superar los 50 caracteres.';

    return 'Ponele un nombre a este RUC.';
  }

  // ---------------------------------------------------------------------
  // Filtro de caracteres en Número y Teléfono: bloquea letras mientras
  // se escribe, no solo al validar. setValue en el mismo handler mueve
  // el cursor al final; para campos cortos como estos no se nota.
  // ---------------------------------------------------------------------

  soloNumero(evento: Event): void {
    // Si ya habia una verificacion confirmada y el usuario sigue
    // tocando el numero, ese resultado quedo viejo: se vuelve a
    // verificar recien en el proximo blur.
    this.rucPropio = null;

    const input = evento.target as HTMLInputElement;
    const limpio = input.value.replace(/[^0-9-]/g, '');

    if (limpio !== input.value) {
      this.form.get('dfi_numero')!.setValue(limpio);
    }
  }

  // ---------------------------------------------------------------------
  // Verificacion de RUC propio: autocompleta telefono/correo cuando el
  // numero tipeado coincide con el RUC del cliente (guardado en su
  // perfil, o calculado desde su cedula). Ver DatoFiscalClienteController
  // ::verificarPropio para la logica completa.
  // ---------------------------------------------------------------------

  /** Se dispara al salir del campo Numero (blur), no en cada tecla: no
   *  tiene sentido consultar al backend mientras el usuario sigue
   *  escribiendo un numero a medio terminar. */
  verificarRucPropio(): void {
    const control = this.form.get('dfi_numero')!;

    // Si el numero ni siquiera tiene el formato correcto, no hay nada
    // que consultar todavia.
    if (control.invalid) {
      this.rucPropio = null;
      return;
    }

    this.verificandoRuc = true;

    this.datoFiscalService.verificarPropio(control.value).subscribe({
      next: (resp) => {
        this.verificandoRuc = false;
        this.rucPropio = resp.es_propio ? resp : null;

        if (!resp.es_propio) {
          return;
        }

        // Solo completa lo que esta vacio: si el cliente ya escribio
        // (o trae, en edicion) un telefono o correo distintos, no se
        // los pisa.
        const patch: Partial<Record<'dfi_telefono' | 'dfi_correo', string>> = {};

        if (!this.form.value.dfi_telefono && resp.telefono) {
          patch['dfi_telefono'] = resp.telefono;
        }
        if (!this.form.value.dfi_correo && resp.correo) {
          patch['dfi_correo'] = resp.correo;
        }

        if (Object.keys(patch).length > 0) {
          this.form.patchValue(patch);
        }
      },
      error: () => {
        // La verificacion es una comodidad, no algo bloqueante: si
        // falla, el cliente simplemente completa a mano como siempre.
        this.verificandoRuc = false;
        this.rucPropio = null;
      },
    });
  }

  /**
   * Guarda este numero como cliente.cli_ruc, para que la proxima vez
   * el match sea directo (fuente "perfil") sin depender del calculo
   * por cedula. Reutiliza POST /tienda/perfil en vez de un endpoint
   * nuevo: ese es el unico lugar donde cli_ruc se persiste.
   */
  guardarRucEnPerfil(): void {
    if (!this.titular || this.guardandoRucPerfil) {
      return;
    }

    this.guardandoRucPerfil = true;

    const formData = new FormData();
    formData.append('per_nombr1', this.titular.nombre);
    formData.append('per_apell1', this.titular.apellido);
    formData.append('cli_ruc', this.form.value.dfi_numero);

    this.perfilService.actualizar(formData).subscribe({
      next: () => {
        this.guardandoRucPerfil = false;
        this.toastr.success('Guardamos este RUC en tu perfil.', 'Listo');
        if (this.rucPropio) {
          this.rucPropio = { ...this.rucPropio, ofrecer_guardar_ruc: false };
        }
      },
      error: (e) => {
        this.guardandoRucPerfil = false;
        this.toastr.error(
          e.error?.message ?? 'No pudimos guardar el RUC en tu perfil.',
          'Error',
        );
      },
    });
  }

  soloTelefono(evento: Event): void {
    const input = evento.target as HTMLInputElement;
    const limpio = input.value.replace(/[^0-9\s\-+]/g, '');

    if (limpio !== input.value) {
      this.form.get('dfi_telefono')!.setValue(limpio);
    }
  }

  // ---------------------------------------------------------------------

  campoInvalido(campo: string): boolean {
    const c = this.form.get(campo);
    return !!c && c.invalid && (c.dirty || c.touched);
  }

  errorBackend(campo: string): string | null {
    return this.form.get(campo)?.errors?.['backend'] ?? null;
  }

  mensajeNumero(): string {
    const errores = this.form.get('dfi_numero')?.errors ?? {};
    if (errores['backend'])  return errores['backend'];
    if (errores['pattern'])  return 'El número solo admite dígitos y un guion (ej: 5690649-7).';
    return 'El número es obligatorio.';
  }

  mensajeTelefono(): string {
    const errores = this.form.get('dfi_telefono')?.errors ?? {};
    if (errores['backend'])  return errores['backend'];
    if (errores['pattern'])  return 'El teléfono solo admite números, espacios y guiones.';
    return 'El teléfono es obligatorio.';
  }

  guardar(): void {
    // Normaliza el alias antes de validar/enviar, igual que en
    // form-direccion: evita guardar " Mi  RUC " y que el duplicado
    // aparezca recien del lado del servidor.
    this.form.patchValue({
      dfi_alias: String(this.form.value.dfi_alias ?? '').trim().replace(/\s+/g, ' '),
    });

    this.form.markAllAsTouched();
    if (this.form.invalid || this.guardando) return;

    this.guardando = true;
    const valores = this.form.value;
    const peticion = this.esEdicion
      ? this.datoFiscalService.actualizar(this.datoFiscal!.dfi_id, valores)
      : this.datoFiscalService.crear(valores);

    peticion.subscribe({
      next: (resp) => {
        this.guardando = false;
        this.toastr.success(resp.message, 'Listo');
        this.guardado.emit(resp.data);
      },
      error: (e) => {
        this.guardando = false;
        if (e.status === 422 && e.error?.errors) {
          Object.entries<string[]>(e.error.errors).forEach(([campo, mensajes]) => {
            this.form.get(campo)?.setErrors({ backend: mensajes[0] });
            this.form.get(campo)?.markAsTouched();
          });
          return;
        }
        this.toastr.error('No pudimos guardar el dato fiscal.', 'Error');
      },
    });
  }

  cerrar(): void {
    this.visibleChange.emit(false);
  }
}