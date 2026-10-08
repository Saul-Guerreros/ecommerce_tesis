import { CommonModule, CurrencyPipe } from '@angular/common';
import {
  Component, EventEmitter, Input, OnChanges, OnInit, Output, SimpleChanges,
} from '@angular/core';
import {
  FormArray, FormBuilder, FormGroup, ReactiveFormsModule, Validators,
} from '@angular/forms';
import {
  MetodoPagoCodigo, MetodoPagoOpcion, PagoDividRequest, PagoItemCheckout,
} from '../../../core/models/checkout.model';

/**
 * Seccion "Pago dividido" del Paso 2.
 *
 * Es un componente presentacional: no llama a ningun servicio ni navega.
 * Solo administra el FormArray de pagos y le avisa al contenedor
 * (paso-facturacion) dos cosas: que metodo esta mirando el cliente en este
 * momento (para la tarjeta de datos de arriba) y cual es el estado actual
 * de los pagos.
 */
@Component({
  selector: 'app-pago-dividido',
  standalone: true,
  imports: [CommonModule, CurrencyPipe, ReactiveFormsModule],
  templateUrl: './pago-dividido.component.html',
  styleUrl: './pago-dividido.component.css',
})
export class PagoDivididoComponent implements OnInit, OnChanges {
  /** Total exigible que llega del backend (config/pago -> total_pedido). */
  @Input() total = 0;
  @Input() metodos: MetodoPagoOpcion[] = [];

  /** Pagos ya guardados en el borrador, si el cliente vuelve a este paso. */
  @Input() pagosGuardados: PagoItemCheckout[] | null = null;

  /** El contenedor lo pone en true cuando el cliente intenta continuar. */
  @Input() intentoContinuar = false;

  /** Metodo del bloque que el cliente esta editando ahora mismo. */
  @Output() metodoActivoChange = new EventEmitter<MetodoPagoCodigo | null>();

  @Output() cambio = new EventEmitter<{ pagos: PagoDividRequest[]; valido: boolean }>();

  private static readonly MAX_BYTES = 5 * 1024 * 1024;
  private static readonly EXTENSIONES = ['jpg', 'jpeg', 'png', 'pdf'];

  form!: FormGroup;

  /** Error de archivo por indice de pago (tamaño / extension). */
  erroresArchivo: Record<number, string | null> = {};

  /** Archivo elegido por bloque. No va en el form: File no es serializable. */
  archivos: Record<number, File | null> = {};

  /**
   * Ultimo valor de "monto" (en digitos, sin parsear) que fue valido para
   * cada bloque. onMonto() lo usa para revertir: cuando una tecla haria que
   * el total asignado supere el total del pedido, el campo vuelve a este
   * valor — nunca se completa con ningun otro numero.
   */
  private ultimoMontoValido: Record<number, string> = {};

  /**
   * true mientras el ultimo intento de tipear en ese campo fue rechazado
   * por superar lo disponible. Es lo que dispara el mensaje en rojo; se
   * limpia apenas el campo vuelve a aceptar un digito (por ejemplo, al
   * borrar con backspace).
   */
  montoBloqueado: Record<number, boolean> = {};

  private inicializado = false;

  constructor(private fb: FormBuilder) {}

  ngOnInit(): void {
    this.construir();
    this.inicializado = true;
  }

  ngOnChanges(cambios: SimpleChanges): void {
    // El contenedor puede entregar los datos despues del primer render.
    if (this.inicializado && cambios['pagosGuardados'] && !cambios['pagosGuardados'].firstChange) {
      this.construir();
    }
  }

  // ---------- Construccion del formulario ----------

  private construir(): void {
    const guardados = this.pagosGuardados ?? [];

    // Minimo dos bloques: es la definicion misma de "dividir el pago".
    const bloques = guardados.length >= 2
      ? guardados.map((p) => this.nuevoPago(p))
      : [this.nuevoPago(), this.nuevoPago()];

    this.form = this.fb.group({ pagos: this.fb.array(bloques) });

    this.archivos = {};
    this.erroresArchivo = {};
    this.ultimoMontoValido = {};
    this.montoBloqueado = {};

    bloques.forEach((_, i) => {
      const monto = guardados[i]?.monto;
      this.ultimoMontoValido[i] = monto != null ? String(monto) : '';
    });

    this.form.valueChanges.subscribe(() => {
      this.sincronizarBloqueos();
      this.emitir();
    });

    this.sincronizarBloqueos();
    this.emitir();
  }

  private nuevoPago(guardado?: PagoItemCheckout): FormGroup {
    return this.fb.group({
      mpa_codigo: [guardado?.mpa_codigo ?? null, Validators.required],
      // Validators.max queda como red de seguridad (ej. si el valor llega
      // ya seteado desde pagosGuardados); el clamp en onMonto() es lo que
      // evita que el usuario llegue a escribirlo en primer lugar.
      monto: [guardado?.monto ?? null, [Validators.required, Validators.min(1), Validators.max(this.total)]],
      /**
       * true = este bloque ya tenia comprobante en el borrador. Sirve para
       * mostrar "Comprobante ya cargado" y para enviar conservar = 1.
       */
      tienePrevio: [guardado?.tiene_comprobante ?? false],
    });
  }

  get pagos(): FormArray<FormGroup> {
    return this.form.get('pagos') as FormArray<FormGroup>;
  }

  grupo(i: number): FormGroup {
    return this.pagos.at(i);
  }

  // ---------- Totales ----------

  get totalAsignado(): number {
    return this.pagos.controls.reduce((acc, g) => acc + (Number(g.getRawValue().monto) || 0), 0);
  }

  get pendiente(): number {
    return this.total - this.totalAsignado;
  }

  get excedido(): boolean {
    return this.totalAsignado > this.total;
  }

  // ---------- Estado de cada bloque ----------

  metodoDe(i: number): MetodoPagoCodigo | null {
    return this.grupo(i).getRawValue().mpa_codigo ?? null;
  }

  requiereComprobante(i: number): boolean {
    const codigo = this.metodoDe(i);
    return this.metodos.find((m) => m.mpa_codigo === codigo)?.mpa_requcom ?? false;
  }

  tieneComprobante(i: number): boolean {
    return !!this.archivos[i] || !!this.grupo(i).getRawValue().tienePrevio;
  }

  nombreArchivo(i: number): string {
    if (this.archivos[i]) return this.archivos[i]!.name;
    if (this.grupo(i).getRawValue().tienePrevio) return 'Comprobante ya cargado';
    return 'Ningún archivo seleccionado';
  }

  /**
   * Regla 3 del documento: una vez que el pago quedo configurado con todos
   * sus datos, su metodo no se puede cambiar. Se implementa deshabilitando
   * el control; por eso en todo el componente se lee con getRawValue(), que
   * sigue devolviendo los controles deshabilitados.
   */
  completo(i: number): boolean {
    const v = this.grupo(i).getRawValue();
    const montoOk = Number(v.monto) > 0;
    const comprobanteOk = !this.requiereComprobante(i) || this.tieneComprobante(i);

    return !!v.mpa_codigo && montoOk && comprobanteOk && !this.erroresArchivo[i];
  }

  bloqueado(i: number): boolean {
    return this.grupo(i).get('mpa_codigo')!.disabled;
  }

  /** El campo Monto se habilita recien cuando el bloque tiene un metodo elegido. */
  montoDeshabilitado(i: number): boolean {
    return this.grupo(i).get('monto')!.disabled;
  }

  private sincronizarBloqueos(): void {
    this.pagos.controls.forEach((g, i) => {
      const controlMetodo = g.get('mpa_codigo')!;
      const debeBloquearMetodo = this.completo(i);

      // emitEvent: false evita que disable/enable dispare valueChanges y
      // vuelva a entrar en este mismo metodo.
      if (debeBloquearMetodo && controlMetodo.enabled) controlMetodo.disable({ emitEvent: false });
      if (!debeBloquearMetodo && controlMetodo.disabled) controlMetodo.enable({ emitEvent: false });

      // El monto no se puede tipear sin haber elegido primero el metodo:
      // un monto sin metodo es un dato huerfano que igual se sumaria al
      // total asignado sin representar ningun pago real.
      const controlMonto = g.get('monto')!;
      const hayMetodo = !!this.metodoDe(i); // getRawValue: lee aun si mpa_codigo esta bloqueado

      if (!hayMetodo && controlMonto.enabled) controlMonto.disable({ emitEvent: false });
      if (hayMetodo && controlMonto.disabled) controlMonto.enable({ emitEvent: false });
    });
  }

  // ---------- Acciones ----------

  activar(i: number): void {
    this.metodoActivoChange.emit(this.metodoDe(i));
  }

  /**
   * Cuanto puede llegar a valer el monto de este bloque sin que la suma de
   * TODOS los pagos supere el total del pedido. No es this.total: es el
   * total menos lo que ya esta asignado en los demas bloques, porque el
   * sentido mismo de dividir el pago es que cada uno cubra una parte.
   */
  private disponibleParaCampo(i: number): number {
    const otros = this.pagos.controls.reduce(
      (acc, g, j) => (j === i ? acc : acc + (Number(g.getRawValue().monto) || 0)),
      0,
    );

    return this.total - otros;
  }

  /**
   * Se ejecuta en cada tecla, no solo al perder el foco.
   *
   * No autocompleta ni corrige hacia ningun valor: si el digito que se
   * acaba de tipear hace que el total asignado supere el disponible, esa
   * tecla simplemente no se escribe y el campo vuelve a quedar como estaba
   * justo antes. El usuario nota que no puede seguir tipeando, nunca ve un
   * numero que el no escribio.
   *
   * Tambien descarta cualquier caracter que no sea digito (pegar texto,
   * notacion cientifica "e", signos) y limita a 12 digitos como tope duro,
   * para que un numero absurdamente largo ni siquiera llegue a parsearse.
   */
  onMonto(i: number, evento: Event): void {
    const input = evento.target as HTMLInputElement;
    const digitos = input.value.replace(/[^0-9]/g, '').slice(0, 12);
    const numero = digitos ? Number(digitos) : null;

    const disponible = this.disponibleParaCampo(i);

    if (numero !== null && numero > disponible) {
      // El digito de mas no se acepta: se revierte al ultimo valor valido,
      // sin completar nada por el usuario.
      const anterior = this.ultimoMontoValido[i] ?? '';
      input.value = anterior;
      this.grupo(i).get('monto')!.setValue(anterior ? Number(anterior) : null);
      this.montoBloqueado[i] = true;
      return;
    }

    this.ultimoMontoValido[i] = digitos;
    this.montoBloqueado[i] = false;
    input.value = digitos;
    this.grupo(i).get('monto')!.setValue(numero);
  }

  onMetodo(i: number): void {
    // Cambiar de metodo invalida el comprobante cargado para el anterior.
    this.archivos[i] = null;
    this.erroresArchivo[i] = null;
    this.grupo(i).patchValue({ tienePrevio: false }, { emitEvent: false });

    this.activar(i);
    this.emitir();
  }

  onArchivo(i: number, evento: Event): void {
    const input = evento.target as HTMLInputElement;
    const archivo = input.files?.[0] ?? null;

    this.erroresArchivo[i] = null;

    if (archivo) {
      const extension = archivo.name.split('.').pop()?.toLowerCase() ?? '';

      if (!PagoDivididoComponent.EXTENSIONES.includes(extension)) {
        this.erroresArchivo[i] = 'El comprobante debe ser JPG, PNG o PDF.';
      } else if (archivo.size > PagoDivididoComponent.MAX_BYTES) {
        this.erroresArchivo[i] = 'El comprobante no puede superar los 5 MB.';
      }
    }

    this.archivos[i] = this.erroresArchivo[i] ? null : archivo;

    // Si eligio un archivo nuevo, deja de valer el que estaba guardado.
    if (this.archivos[i]) {
      this.grupo(i).patchValue({ tienePrevio: false }, { emitEvent: false });
    }

    input.value = '';
    this.sincronizarBloqueos();
    this.emitir();
  }

  /**
   * Regla 6: el boton solo tiene sentido cuando los bloques existentes ya
   * estan completos y todavia queda monto por cubrir.
   */
  get puedeAgregar(): boolean {
    return this.pendiente > 0 && this.pagos.controls.every((_, i) => this.completo(i));
  }

  agregarPago(): void {
    this.pagos.push(this.nuevoPago());
    this.emitir();
  }

  /**
   * No esta en el documento, pero sin esto un cliente que se equivoca en el
   * tercer pago queda trabado. Los dos primeros bloques no se pueden quitar.
   */
  quitarPago(i: number): void {
    if (i < 2) return;

    this.pagos.removeAt(i);

    // Reindexa los archivos y los montos: el bloque 3 pasa a ser el 2, etc.
    const archivos: Record<number, File | null> = {};
    const errores: Record<number, string | null> = {};
    const montos: Record<number, string> = {};
    const bloqueados: Record<number, boolean> = {};

    this.pagos.controls.forEach((_, nuevo) => {
      const viejo = nuevo >= i ? nuevo + 1 : nuevo;
      archivos[nuevo] = this.archivos[viejo] ?? null;
      errores[nuevo] = this.erroresArchivo[viejo] ?? null;
      montos[nuevo] = this.ultimoMontoValido[viejo] ?? '';
      bloqueados[nuevo] = this.montoBloqueado[viejo] ?? false;
    });

    this.archivos = archivos;
    this.erroresArchivo = errores;
    this.ultimoMontoValido = montos;
    this.montoBloqueado = bloqueados;

    this.sincronizarBloqueos();
    this.emitir();
  }

  // ---------- Validacion visible ----------

  metodoInvalido(i: number): boolean {
    return this.intentoContinuar && !this.metodoDe(i);
  }

  montoInvalido(i: number): boolean {
    return this.intentoContinuar && !(Number(this.grupo(i).getRawValue().monto) > 0);
  }

  /**
   * Se muestra apenas onMonto() rechaza un digito por superar lo
   * disponible (total menos lo asignado en los demas bloques). Tambien
   * contempla hasError('max') como red de seguridad para el caso borde en
   * que el control se setea por otra via (ej. patchValue).
   */
  montoExcedeTotal(i: number): boolean {
    return !!this.montoBloqueado[i] || this.grupo(i).get('monto')!.hasError('max');
  }

  comprobanteInvalido(i: number): boolean {
    return this.intentoContinuar && this.requiereComprobante(i) && !this.tieneComprobante(i);
  }

  // ---------- Salida hacia el contenedor ----------

  private emitir(): void {
    const pagos: PagoDividRequest[] = this.pagos.controls.map((g, i) => {
      const v = g.getRawValue();

      return {
        mpa_codigo: v.mpa_codigo,
        monto: Number(v.monto) || 0,
        comprobante: this.archivos[i] ?? null,
        conservar: !!v.tienePrevio,
      };
    });

    const valido =
      this.pendiente === 0 &&
      !this.excedido &&
      this.pagos.controls.every((_, i) => this.completo(i));

    this.cambio.emit({ pagos, valido });
  }
}